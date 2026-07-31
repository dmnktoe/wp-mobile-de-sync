<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Sync_Plan {
	const REMOVAL_RATIO = 0.3;

	const REMOVAL_FLOOR = 3;

	/**
	 * @param array $ads   Ads from the search result (raw, with mobileAdId
	 *                     and modificationDate).
	 * @param array $known Known inventory, as
	 *                     [ ad_id => ['post_id'=>int, 'modified'=>string] ].
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

			$id             = (string) $ad['mobileAdId'];
			$plan['seen'][] = $id;

			if ( ! isset( $known[ $id ] ) ) {
				$plan['create'][] = $ad;
				continue;
			}

			$modified = isset( $ad['modificationDate'] ) ? (string) $ad['modificationDate'] : '';
			$stored   = isset( $known[ $id ]['modified'] ) ? (string) $known[ $id ]['modified'] : '';

			if ( ! $force && '' !== $modified && $modified === $stored ) {
				$plan['skip'][] = $id;
				continue;
			}

			$plan['update'][] = $ad;
		}

		return $plan;
	}

	/**
	 * @param string[] $seen    Ad IDs seen during the run.
	 * @param array    $known   Known inventory, as [ ad_id => ['post_id'=>int, ...] ].
	 * @param bool     $is_full Whether this was a complete pass.
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
			return $result;
		}

		if ( ! $seen ) {
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

		if ( ! $current ) {
			return false;
		}

		if ( ! is_array( $stored ) ) {
			return true;
		}

		return array_values( $stored ) !== $current;
	}

	/**
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
