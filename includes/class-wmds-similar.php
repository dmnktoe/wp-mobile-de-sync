<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Other vehicles worth a look at the end of a vehicle page: the same make
 * first, then whatever is closest in price.
 */
class WMDS_Similar {
	const COUNT = 3;

	/** How far the price may differ for a vehicle of another make, either way. */
	const PRICE_SPAN = 0.3;

	/** Candidates read per query; the closest in price are kept. */
	const POOL = 24;

	/**
	 * @param int $post_id The vehicle on the page.
	 * @param int $count
	 * @return int[] Post IDs, best match first.
	 */
	public static function ids( $post_id, $count = self::COUNT ) {
		$post_id = (int) $post_id;
		$make    = (string) get_post_meta( $post_id, 'make', true );
		$price   = (int) get_post_meta( $post_id, 'price_raw', true );

		$picked = array();

		if ( '' !== $make ) {
			$picked = self::closest(
				self::candidates(
					array(
						array(
							'key'   => 'make',
							'value' => $make,
						),
					),
					array( $post_id )
				),
				$price
			);
		}

		if ( count( $picked ) < $count && $price > 0 ) {
			$others = self::closest(
				self::candidates(
					array(
						array(
							'key'     => 'price_raw',
							'value'   => array( (int) floor( $price * ( 1 - self::PRICE_SPAN ) ), (int) ceil( $price * ( 1 + self::PRICE_SPAN ) ) ),
							'type'    => 'NUMERIC',
							'compare' => 'BETWEEN',
						),
					),
					array_merge( array( $post_id ), $picked )
				),
				$price
			);

			$picked = array_merge( $picked, $others );
		}

		return array_slice( $picked, 0, max( 0, (int) $count ) );
	}

	/**
	 * @param array $meta_query
	 * @param int[] $exclude
	 * @return array<int, int> Post ID => price.
	 */
	private static function candidates( array $meta_query, array $exclude ) {
		$ids = get_posts(
			array(
				'post_type'      => WMDS_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => self::POOL,
				'post__not_in'   => $exclude, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- a handful of IDs.
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one query of a few dozen rows per vehicle page.
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$prices = array();
		foreach ( $ids as $id ) {
			$prices[ (int) $id ] = (int) get_post_meta( (int) $id, 'price_raw', true );
		}

		return $prices;
	}

	/**
	 * @param array<int, int> $prices Post ID => price.
	 * @param int             $price  The price to be close to.
	 * @return int[] Post IDs, closest first.
	 */
	public static function closest( array $prices, $price ) {
		$distance = array();
		foreach ( $prices as $id => $value ) {
			$distance[ $id ] = ( $price > 0 && $value > 0 ) ? abs( $value - $price ) : PHP_INT_MAX;
		}

		asort( $distance );

		return array_map( 'intval', array_keys( $distance ) );
	}
}
