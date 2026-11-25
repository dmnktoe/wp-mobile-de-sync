<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Facet_Store {
	const OPT_VERSION = 'wmds_facet_version';

	/** @var bool */
	private static $flushed = false;

	/**
	 * @param string $key
	 * @param array  $selection
	 * @return array<string, int>
	 */
	public static function choices( $key, array $selection = array() ) {
		$facets = WMDS_Facets::definitions();
		if ( ! isset( $facets[ $key ]['meta'] ) || '' === $facets[ $key ]['meta'] ) {
			return array();
		}

		$meta_key = $facets[ $key ]['meta'];
		$ids      = self::constraint( $selection, $key );

		if ( is_array( $ids ) && ! $ids ) {
			return array();
		}

		$cache  = WMDS_Facets::CACHE . 'choices_' . md5( self::version() . '|' . $meta_key . '|' . wp_json_encode( $ids ) );
		$cached = get_transient( $cache );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$sql = "SELECT m.meta_value AS value, COUNT(*) AS total
			FROM {$wpdb->postmeta} m
			INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE m.meta_key = %s AND m.meta_value != ''
			  AND p.post_type = %s AND p.post_status = 'publish'";

		$params = array( $meta_key, WMDS_CPT );

		if ( is_array( $ids ) ) {
			$sql   .= ' AND p.ID IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$params = array_merge( $params, $ids );
		}

		$sql .= ' GROUP BY m.meta_value ORDER BY m.meta_value ASC';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the placeholders are built above and every value goes through prepare(); cached in the transient below.

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row->value ] = (int) $row->total;
		}

		set_transient( $cache, $out, WMDS_Facets::CACHE_TTL );

		return $out;
	}

	/**
	 * @param string $key
	 * @param array  $selection
	 * @return array{min:float,max:float}|array
	 */
	public static function bounds( $key, array $selection = array() ) {
		$facets = WMDS_Facets::definitions();
		if ( ! isset( $facets[ $key ]['meta'] ) || '' === $facets[ $key ]['meta'] ) {
			return array();
		}

		$meta_key = $facets[ $key ]['meta'];
		$ids      = self::constraint( $selection, $key );

		if ( is_array( $ids ) && ! $ids ) {
			return array();
		}

		$cache  = WMDS_Facets::CACHE . 'bounds_' . md5( self::version() . '|' . $meta_key . '|' . wp_json_encode( $ids ) );
		$cached = get_transient( $cache );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$sql = "SELECT MIN(m.meta_value + 0) AS low, MAX(m.meta_value + 0) AS high
			FROM {$wpdb->postmeta} m
			INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE m.meta_key = %s AND m.meta_value != ''
			  AND p.post_type = %s AND p.post_status = 'publish'";

		$params = array( $meta_key, WMDS_CPT );

		if ( is_array( $ids ) ) {
			$sql   .= ' AND p.ID IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$params = array_merge( $params, $ids );
		}

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- see choices().

		$out = array();
		if ( $row && null !== $row->low && null !== $row->high && (float) $row->high > 0 ) {
			$step = isset( $facets[ $key ]['step'] ) ? (float) $facets[ $key ]['step'] : 1;
			$step = $step > 0 ? $step : 1;

			$out = array(
				'min' => floor( (float) $row->low / $step ) * $step,
				'max' => ceil( (float) $row->high / $step ) * $step,
			);
		}

		set_transient( $cache, $out, WMDS_Facets::CACHE_TTL );

		return $out;
	}

	/**
	 * @param array  $selection
	 * @param string $except
	 * @return int[]|null
	 */
	public static function constraint( array $selection, $except ) {
		$meta = WMDS_Facet_Query::meta_query( $selection, array( $except ) );
		$term = ( 'q' === $except || ! isset( $selection['q'] ) ) ? '' : $selection['q'];

		if ( ! $meta && '' === $term ) {
			return null;
		}

		$args = array(
			'post_type'              => WMDS_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => WMDS_Facets::MAX_CONSTRAINT,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( $meta ) {
			$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- filtering by meta is what the facets are for.
		}
		if ( '' !== $term ) {
			$args['s'] = $term;
		}

		$query = new WP_Query( $args );
		$ids   = array_map( 'intval', (array) $query->posts );

		return ( count( $ids ) >= WMDS_Facets::MAX_CONSTRAINT ) ? null : $ids;
	}

	/** @return string */
	private static function version() {
		return (string) get_option( self::OPT_VERSION, '0' );
	}

	/**
	 * @param int  $post_id
	 * @param bool $again
	 */
	public static function flush( $post_id = 0, $again = false ) {
		if ( self::$flushed && ! $again ) {
			return;
		}

		if ( $post_id && WMDS_CPT !== get_post_type( $post_id ) ) {
			return;
		}

		self::$flushed = true;

		update_option( self::OPT_VERSION, uniqid( '', true ), true );

		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- deleting the cache is the point.
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . WMDS_Facets::CACHE ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . WMDS_Facets::CACHE ) . '%'
			)
		);
	}

	public static function flush_after_run() {
		self::flush( 0, true );
	}
}
