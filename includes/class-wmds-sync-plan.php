<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The decision logic of an import run, deliberately free of WordPress.
 *
 * This is the dangerous part of the import: what gets created, updated,
 * skipped - and what gets removed. That is exactly why these are pure static
 * functions with no database and no HTTP. They are fully testable, and a
 * mistake shows up in a test rather than in someone's live inventory.
 *
 * The importer itself stays a thin layer of glue on top.
 */
class WMDS_Sync_Plan {

	/**
	 * Share of the inventory above which a removal is treated as implausible.
	 * 0.3 = more than 30 percent in one go is suspicious.
	 */
	const REMOVAL_RATIO = 0.3;

	/**
	 * Absolute floor. On small inventories a percentage is useless - three of
	 * eight vehicles is 37 percent and entirely normal. Below this number the
	 * guard never trips.
	 */
	const REMOVAL_FLOOR = 3;

	/**
	 * Decides per ad what needs to happen.
	 *
	 * @param array $ads   Ads from the search result (raw, with mobileAdId
	 *                     and modificationDate).
	 * @param array $known Known inventory:
	 *                     [ ad_id => ['post_id'=>int, 'modified'=>string] ]
	 * @param bool  $force Re-read everything, including unchanged ads.
	 * @return array{create:array,update:array,skip:array,seen:array}
	 *         create/update hold the raw ads, skip only the ad IDs.
	 */
	public static function build( array $ads, array $known, $force = false ) {
		$plan = array(
			'create' => array(),
			'update' => array(),
			'skip'   => array(),
			'seen'   => array(),
		);

		foreach ( $ads as $ad ) {
			if ( ! is_array( $ad ) || empty( $ad['mobileAdId'] ) ) {
				continue;
			}

			$id            = (string) $ad['mobileAdId'];
			$plan['seen'][] = $id;

			if ( ! isset( $known[ $id ] ) ) {
				$plan['create'][] = $ad;
				continue;
			}

			$modified = isset( $ad['modificationDate'] ) ? (string) $ad['modificationDate'] : '';
			$stored   = isset( $known[ $id ]['modified'] ) ? (string) $known[ $id ]['modified'] : '';

			// Unchanged: nothing to do. Saves one detail call and roughly 95
			// meta writes per vehicle, per run.
			if ( ! $force && '' !== $modified && $modified === $stored ) {
				$plan['skip'][] = $id;
				continue;
			}

			$plan['update'][] = $ad;
		}

		return $plan;
	}

	/**
	 * Determines which posts get removed - with a safety guard.
	 *
	 * Important: on a partial pass (incremental sync via
	 * modificationTime.min) $seen contains only the changed vehicles.
	 * Deleting everything else on that basis would wipe out half the
	 * inventory. Removal therefore happens only after a full pass.
	 *
	 * @param string[] $seen    Ad IDs seen during the run.
	 * @param array    $known   [ ad_id => ['post_id'=>int, ...] ]
	 * @param bool     $is_full Was this a complete pass?
	 * @return array{remove:int[],abort:string,candidates:int}
	 *         abort is empty when deletion is allowed; otherwise the reason.
	 */
	public static function removals( array $seen, array $known, $is_full ) {
		$result = array(
			'remove'     => array(),
			'abort'      => '',
			'candidates' => 0,
		);

		if ( ! $is_full ) {
			$result['abort'] = 'Partial pass - removal happens only after a full sync.';
			return $result;
		}

		if ( ! $known ) {
			return $result; // nothing on file, nothing to remove
		}

		if ( ! $seen ) {
			// An empty result despite HTTP 200 does happen, e.g. with a
			// misspelled dealer name. That must never delete the whole
			// inventory.
			$result['abort'] = 'The run did not see a single vehicle.';
			return $result;
		}

		$lookup     = array_flip( $seen );
		$candidates = array();

		foreach ( $known as $ad_id => $entry ) {
			if ( ! isset( $lookup[ (string) $ad_id ] ) && isset( $entry['post_id'] ) ) {
				$candidates[] = (int) $entry['post_id'];
			}
		}

		$result['candidates'] = count( $candidates );
		if ( ! $candidates ) {
			return $result;
		}

		$limit = max( self::REMOVAL_FLOOR, (int) floor( count( $known ) * self::REMOVAL_RATIO ) );

		if ( count( $candidates ) > $limit ) {
			$result['abort'] = sprintf(
				'Safety guard: %d of %d vehicles would be removed (limit %d). '
				. 'Nothing deleted - check the feed and start the run again afterwards.',
				count( $candidates ),
				count( $known ),
				$limit
			);
			return $result;
		}

		$result['remove'] = $candidates;
		return $result;
	}

	/**
	 * Fingerprint of the mapped data.
	 *
	 * The importer stores it on the post and compares it on the next run. If
	 * it matches, writing the meta fields is skipped. modificationDate alone
	 * is not enough for this: it also changes when mobile.de touches
	 * something internally that does not concern us at all.
	 *
	 * @param array $mapped Return value of WMDS_Json_Mapper::map().
	 * @return string
	 */
	public static function content_hash( array $mapped ) {
		$relevant = array(
			'title'       => isset( $mapped['title'] ) ? $mapped['title'] : '',
			'description' => isset( $mapped['description'] ) ? $mapped['description'] : '',
			'meta'        => isset( $mapped['meta'] ) ? $mapped['meta'] : array(),
		);

		if ( is_array( $relevant['meta'] ) ) {
			ksort( $relevant['meta'] );
		}

		return md5( wp_json_encode( $relevant ) );
	}

	/**
	 * Compares the image hashes from the feed against the stored ones.
	 *
	 * The current data format supplies an MD5 checksum per image, which is
	 * what makes a swapped photo detectable. Fetching images once and never
	 * looking again misses every replacement.
	 *
	 * @param string[] $stored Hashes imported last time, in order.
	 * @param array    $images Return value of WMDS_Json_Mapper::map()['images'].
	 * @return bool
	 */
	public static function images_changed( $stored, array $images ) {
		$current = array();
		foreach ( $images as $image ) {
			if ( isset( $image['hash'] ) && '' !== $image['hash'] ) {
				$current[] = (string) $image['hash'];
			}
		}

		// Without hashes in the feed there is nothing to compare. Better not
		// to reload, or every run pulls every image again.
		if ( ! $current ) {
			return false;
		}

		if ( ! is_array( $stored ) ) {
			return true;
		}

		return array_values( $stored ) !== $current;
	}

	/**
	 * Timestamp for the next run's modificationTime.min.
	 *
	 * Deliberately one minute behind the newest value seen: the API works at
	 * second resolution, and an ad changed exactly during the run must not
	 * slip through. Better to re-check a few ads than to lose one.
	 *
	 * @param string[] $modification_dates Every modificationDate seen this run.
	 * @return string Empty when there was nothing usable.
	 */
	public static function next_watermark( array $modification_dates ) {
		$newest = '';
		foreach ( $modification_dates as $date ) {
			$date = trim( (string) $date );
			if ( '' !== $date && $date > $newest ) {
				$newest = $date;
			}
		}

		if ( '' === $newest ) {
			return '';
		}

		$ts = strtotime( $newest );
		if ( ! $ts ) {
			return '';
		}

		return gmdate( 'Y-m-d\TH:i:s', $ts - 60 ) . '+00:00';
	}
}
