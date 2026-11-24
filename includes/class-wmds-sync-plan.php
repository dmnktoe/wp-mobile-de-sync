<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Sync_Plan {
	const REMOVAL_RATIO = 0.3;

	const REMOVAL_FLOOR = 3;

	/**
	 * @param array $ads
	 * @param array $known
	 * @param bool  $force
	 * @return array{create:array,update:array,skip:array,seen:array}
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
	 * @param string[] $seen
	 * @param array    $known
	 * @param bool     $is_full
	 * @return array{remove:int[],abort:string,candidates:int}
	 */
	public static function removals( array $seen, array $known, $is_full ) {
		$result = array(
			'remove'     => array(),
			'abort'      => '',
			'candidates' => 0,
		);

		if ( ! $is_full ) {
			$result['abort'] = __( 'Partial listing - removal happens only once a full one has been read.', 'wp-mobile-de-sync' );
			return $result;
		}

		if ( ! $known ) {
			return $result;
		}

		if ( ! $seen ) {
			$result['abort'] = __( 'The run did not see a single vehicle, so nothing was removed.', 'wp-mobile-de-sync' );
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
				/* translators: 1: vehicles that would be removed, 2: vehicles on the site, 3: the limit. */
				__( 'Safety guard: %1$d of %2$d vehicles would be removed (limit %3$d). Nothing was deleted; please check the inventory on mobile.de and start the run again.', 'wp-mobile-de-sync' ),
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
	 * @param array $mapped
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
	 * @param string[] $stored
	 * @param array    $images
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
	 * @param string[] $modification_dates
	 * @return string
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
