<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Facets {
	const PREFIX = 'wmds_';

	const HANDLE = 'wmds-facets';

	const CACHE     = 'wmds_facet_';
	const CACHE_TTL = 600;

	const MAX_CONSTRAINT = 5000;

	const MAX_LENGTH = 100;

	/** @var bool */
	private static $flushed = false;

	public static function init() {
		add_shortcode( 'vehicle-filter', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'fahrzeug-filter', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'vehicle-count', array( __CLASS__, 'shortcode_count' ) );

		add_action( 'pre_get_posts', array( __CLASS__, 'apply_to_archive' ) );

		add_action( 'save_post_' . WMDS_CPT, array( __CLASS__, 'flush' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush' ) );
		add_action( 'trashed_post', array( __CLASS__, 'flush' ) );
	}

	/**
	 * The facets, in the order they are rendered.
	 *
	 * @return array<string, array>
	 */
	public static function definitions() {
		$facets = array(
			'q'         => array(
				'type'  => 'search',
				'label' => __( 'Search', 'wp-mobile-de-sync' ),
			),
			'make'      => array(
				'type'  => 'select',
				'label' => __( 'Make', 'wp-mobile-de-sync' ),
				'meta'  => 'make',
			),
			'model'     => array(
				'type'  => 'select',
				'label' => __( 'Model', 'wp-mobile-de-sync' ),
				'meta'  => 'model',
			),
			'category'  => array(
				'type'  => 'select',
				'label' => __( 'Body type', 'wp-mobile-de-sync' ),
				'meta'  => 'category',
			),
			'condition' => array(
				'type'  => 'radio',
				'label' => __( 'Condition', 'wp-mobile-de-sync' ),
				'meta'  => 'condition',
			),
			'fuel'      => array(
				'type'  => 'checkbox',
				'label' => __( 'Fuel', 'wp-mobile-de-sync' ),
				'meta'  => 'fuel',
			),
			'gearbox'   => array(
				'type'  => 'checkbox',
				'label' => __( 'Transmission', 'wp-mobile-de-sync' ),
				'meta'  => 'gearbox',
			),
			'colour'    => array(
				'type'  => 'checkbox',
				'label' => __( 'Exterior colour', 'wp-mobile-de-sync' ),
				'meta'  => 'exteriorColor',
			),
			'price'     => array(
				'type'  => 'range',
				'label' => __( 'Price', 'wp-mobile-de-sync' ),
				'meta'  => 'price_raw',
				'unit'  => '€',
				'step'  => 500,
			),
			'mileage'   => array(
				'type'  => 'range',
				'label' => __( 'Mileage', 'wp-mobile-de-sync' ),
				'meta'  => 'mileage_raw',
				'unit'  => __( 'km', 'wp-mobile-de-sync' ),
				'step'  => 5000,
			),
			'power'     => array(
				'type'  => 'range',
				'label' => __( 'Power', 'wp-mobile-de-sync' ),
				'meta'  => 'power',
				'unit'  => __( 'kW', 'wp-mobile-de-sync' ),
				'step'  => 5,
			),
			'year'      => array(
				'type'  => 'range',
				'label' => __( 'First registration', 'wp-mobile-de-sync' ),
				'meta'  => 'firstRegistration_year',
				'unit'  => '',
				'step'  => 1,
			),
		);

		/**
		 * @param array $facets Facet key => definition.
		 */
		$facets = apply_filters( 'wmds_facets', $facets );

		return is_array( $facets ) ? $facets : array();
	}

	/**
	 * @return array<string, array{label:string,meta:string,order:string,type:string}>
	 */
	public static function sorts() {
		$sorts = array(
			'newest'       => array(
				'label' => __( 'Newest first', 'wp-mobile-de-sync' ),
				'meta'  => '',
				'order' => 'DESC',
				'type'  => '',
			),
			'price-asc'    => array(
				'label' => __( 'Price, lowest first', 'wp-mobile-de-sync' ),
				'meta'  => 'price_raw',
				'order' => 'ASC',
				'type'  => 'NUMERIC',
			),
			'price-desc'   => array(
				'label' => __( 'Price, highest first', 'wp-mobile-de-sync' ),
				'meta'  => 'price_raw',
				'order' => 'DESC',
				'type'  => 'NUMERIC',
			),
			'mileage-asc'  => array(
				'label' => __( 'Mileage, lowest first', 'wp-mobile-de-sync' ),
				'meta'  => 'mileage_raw',
				'order' => 'ASC',
				'type'  => 'NUMERIC',
			),
			'mileage-desc' => array(
				'label' => __( 'Mileage, highest first', 'wp-mobile-de-sync' ),
				'meta'  => 'mileage_raw',
				'order' => 'DESC',
				'type'  => 'NUMERIC',
			),
			'year-desc'    => array(
				'label' => __( 'First registration, newest first', 'wp-mobile-de-sync' ),
				'meta'  => 'firstRegistration_year',
				'order' => 'DESC',
				'type'  => 'NUMERIC',
			),
			'year-asc'     => array(
				'label' => __( 'First registration, oldest first', 'wp-mobile-de-sync' ),
				'meta'  => 'firstRegistration_year',
				'order' => 'ASC',
				'type'  => 'NUMERIC',
			),
			'power-desc'   => array(
				'label' => __( 'Power, highest first', 'wp-mobile-de-sync' ),
				'meta'  => 'power',
				'order' => 'DESC',
				'type'  => 'NUMERIC',
			),
		);

		/**
		 * @param array $sorts Sort key => definition.
		 */
		$sorts = apply_filters( 'wmds_facet_sorts', $sorts );

		return is_array( $sorts ) ? $sorts : array();
	}

	/**
	 * Reads a request into the normalised selection every other method takes.
	 *
	 * Pure: it touches no superglobal and no WordPress function, so the
	 * decisions it makes are testable on their own.
	 *
	 * @param array $request Raw request values, already unslashed.
	 * @return array<string, mixed>
	 */
	public static function parse( array $request ) {
		$selection = array();

		foreach ( self::definitions() as $key => $facet ) {
			$type = isset( $facet['type'] ) ? $facet['type'] : 'select';

			if ( 'range' === $type ) {
				$range = self::parse_range( $request, $key );
				if ( $range ) {
					$selection[ $key ] = $range;
				}
				continue;
			}

			if ( 'search' === $type ) {
				$term = self::clean( isset( $request[ self::PREFIX . $key ] ) ? $request[ self::PREFIX . $key ] : '' );
				if ( '' !== $term ) {
					$selection[ $key ] = $term;
				}
				continue;
			}

			$values = self::parse_values( isset( $request[ self::PREFIX . $key ] ) ? $request[ self::PREFIX . $key ] : null );
			if ( ! $values ) {
				continue;
			}

			$selection[ $key ] = ( 'checkbox' === $type ) ? $values : array( $values[0] );
		}

		$sort = self::clean( isset( $request[ self::PREFIX . 'sort' ] ) ? $request[ self::PREFIX . 'sort' ] : '' );
		if ( '' !== $sort && array_key_exists( $sort, self::sorts() ) ) {
			$selection['sort'] = $sort;
		}

		return $selection;
	}

	/** @return array<string, mixed> The selection the current request describes. */
	public static function selection() {
		$request = isset( $_GET ) ? wp_unslash( $_GET ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a read-only list filter, and parse() normalises every value it keeps.

		return self::parse( is_array( $request ) ? $request : array() );
	}

	/**
	 * @param array  $request
	 * @param string $key
	 * @return array{min?:float,max?:float}
	 */
	private static function parse_range( array $request, $key ) {
		$out = array();

		foreach ( array( 'min', 'max' ) as $bound ) {
			$field = self::PREFIX . $key . '_' . $bound;
			if ( ! isset( $request[ $field ] ) ) {
				continue;
			}

			$raw = self::clean( $request[ $field ] );
			if ( '' === $raw || ! is_numeric( str_replace( array( ' ', '.', ',' ), array( '', '', '.' ), $raw ) ) ) {
				continue;
			}

			$out[ $bound ] = (float) str_replace( array( ' ', '.', ',' ), array( '', '', '.' ), $raw );
		}

		if ( isset( $out['min'], $out['max'] ) && $out['min'] > $out['max'] ) {
			$swap       = $out['min'];
			$out['min'] = $out['max'];
			$out['max'] = $swap;
		}

		return $out;
	}

	/**
	 * @param mixed $raw
	 * @return string[]
	 */
	private static function parse_values( $raw ) {
		if ( null === $raw ) {
			return array();
		}

		$values = is_array( $raw ) ? $raw : array( $raw );
		$out    = array();

		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				continue;
			}
			$value = self::clean( $value );
			if ( '' !== $value && ! in_array( $value, $out, true ) ) {
				$out[] = $value;
			}
		}

		return $out;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function clean( $value ) {
		if ( is_array( $value ) || is_object( $value ) || null === $value ) {
			return '';
		}

		$value = preg_replace( '/[\x00-\x1F\x7F<>]/u', '', (string) $value );
		$value = trim( preg_replace( '/\s+/u', ' ', (string) $value ) );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, self::MAX_LENGTH );
		}

		return substr( $value, 0, self::MAX_LENGTH );
	}

	/**
	 * @param array $selection
	 * @return bool Whether anything narrows the inventory.
	 */
	public static function is_filtered( array $selection ) {
		foreach ( $selection as $key => $value ) {
			if ( 'sort' !== $key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array $selection
	 * @param array $exclude   Facet keys to leave out.
	 * @return array The meta_query the selection amounts to.
	 */
	public static function meta_query( array $selection, array $exclude = array() ) {
		$clauses = array();

		foreach ( self::definitions() as $key => $facet ) {
			if ( in_array( $key, $exclude, true ) || ! isset( $selection[ $key ] ) ) {
				continue;
			}

			$type = isset( $facet['type'] ) ? $facet['type'] : 'select';
			$meta = isset( $facet['meta'] ) ? $facet['meta'] : '';

			if ( 'search' === $type || '' === $meta ) {
				continue;
			}

			if ( 'range' === $type ) {
				$range = $selection[ $key ];
				if ( isset( $range['min'] ) ) {
					$clauses[] = array(
						'key'     => $meta,
						'value'   => $range['min'],
						'compare' => '>=',
						'type'    => 'DECIMAL(20,4)',
					);
				}
				if ( isset( $range['max'] ) ) {
					$clauses[] = array(
						'key'     => $meta,
						'value'   => $range['max'],
						'compare' => '<=',
						'type'    => 'DECIMAL(20,4)',
					);
				}
				continue;
			}

			$values = (array) $selection[ $key ];
			if ( ! $values ) {
				continue;
			}

			$clauses[] = array(
				'key'     => $meta,
				'value'   => ( count( $values ) > 1 ) ? $values : $values[0],
				'compare' => ( count( $values ) > 1 ) ? 'IN' : '=',
			);
		}

		if ( ! $clauses ) {
			return array();
		}

		$clauses['relation'] = 'AND';

		return $clauses;
	}

	/**
	 * @param string $sort
	 * @return array Query arguments that order the result.
	 */
	public static function order_args( $sort ) {
		$sorts = self::sorts();
		$sort  = (string) $sort;

		if ( '' === $sort || ! isset( $sorts[ $sort ] ) || '' === $sorts[ $sort ]['meta'] ) {
			return array(
				'orderby' => 'date',
				'order'   => 'DESC',
			);
		}

		$definition = $sorts[ $sort ];

		return array(
			'orderby'    => array( 'wmds_sorted' => $definition['order'] ),
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- sorting on a meta field is what the control is for.
				array(
					'relation'    => 'OR',
					'wmds_sorted' => array(
						'key'     => $definition['meta'],
						'compare' => 'EXISTS',
						'type'    => $definition['type'],
					),
					'wmds_absent' => array(
						'key'     => $definition['meta'],
						'compare' => 'NOT EXISTS',
					),
				),
			),
		);
	}

	/**
	 * @param array $selection
	 * @param array $base      Arguments the caller has already decided on.
	 * @return array WP_Query arguments.
	 */
	public static function query_args( array $selection, array $base = array() ) {
		$args = array_merge(
			array(
				'post_type'   => WMDS_CPT,
				'post_status' => 'publish',
			),
			$base
		);

		$meta = self::meta_query( $selection );

		$order = self::order_args( isset( $selection['sort'] ) ? $selection['sort'] : '' );
		if ( isset( $order['meta_query'] ) ) {
			$meta = self::combine( $meta, $order['meta_query'][0] );
			unset( $order['meta_query'] );
		}

		if ( $meta ) {
			$existing = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array();
			$combined = self::combine( $existing, $meta );

			$args['meta_query'] = $combined; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- filtering by meta is what the facets are for.
		}

		if ( isset( $selection['q'] ) && '' !== $selection['q'] ) {
			$args['s'] = $selection['q'];
		}

		return array_merge( $args, $order );
	}

	/**
	 * @param array $first
	 * @param array $second
	 * @return array The two clause sets, ANDed, without nesting an empty one.
	 */
	private static function combine( array $first, array $second ) {
		if ( ! $first ) {
			return $second;
		}
		if ( ! $second ) {
			return $first;
		}

		return array(
			'relation' => 'AND',
			$first,
			$second,
		);
	}

	/**
	 * @param WP_Query $query
	 */
	public static function apply_to_archive( $query ) {
		if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}
		if ( ! $query->is_post_type_archive( WMDS_CPT ) ) {
			return;
		}

		$selection = self::selection();
		if ( ! $selection ) {
			return;
		}

		$meta = self::meta_query( $selection );
		if ( $meta ) {
			$combined = self::combine( (array) $query->get( 'meta_query' ), $meta );
			$query->set( 'meta_query', $combined ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- filtering by meta is what the facets are for.
		}

		if ( isset( $selection['q'] ) && '' !== $selection['q'] ) {
			$query->set( 's', $selection['q'] );
		}

		if ( ! isset( $selection['sort'] ) ) {
			return;
		}

		$order = self::order_args( $selection['sort'] );
		if ( isset( $order['meta_query'] ) ) {
			$combined = self::combine( (array) $query->get( 'meta_query' ), $order['meta_query'][0] );
			$query->set( 'meta_query', $combined ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- see above.
		}

		$query->set( 'orderby', $order['orderby'] );
		if ( isset( $order['order'] ) ) {
			$query->set( 'order', $order['order'] );
		}
	}

	/**
	 * Distinct values of a facet with the number of vehicles behind each.
	 *
	 * Counted against the rest of the selection, so an option that would
	 * leave nothing says so rather than promising a result it cannot keep.
	 *
	 * @param string $key
	 * @param array  $selection
	 * @return array<string, int> Value => count, ordered by value.
	 */
	public static function choices( $key, array $selection = array() ) {
		$facets = self::definitions();
		if ( ! isset( $facets[ $key ]['meta'] ) || '' === $facets[ $key ]['meta'] ) {
			return array();
		}

		$meta_key = $facets[ $key ]['meta'];
		$ids      = self::constraint( $selection, $key );

		if ( is_array( $ids ) && ! $ids ) {
			return array();
		}

		$cache  = self::CACHE . 'choices_' . md5( $meta_key . '|' . wp_json_encode( $ids ) );
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

		set_transient( $cache, $out, self::CACHE_TTL );

		return $out;
	}

	/**
	 * @param string $key
	 * @param array  $selection
	 * @return array{min:float,max:float}|array Empty when the facet holds no numbers.
	 */
	public static function bounds( $key, array $selection = array() ) {
		$facets = self::definitions();
		if ( ! isset( $facets[ $key ]['meta'] ) || '' === $facets[ $key ]['meta'] ) {
			return array();
		}

		$meta_key = $facets[ $key ]['meta'];
		$ids      = self::constraint( $selection, $key );

		if ( is_array( $ids ) && ! $ids ) {
			return array();
		}

		$cache  = self::CACHE . 'bounds_' . md5( $meta_key . '|' . wp_json_encode( $ids ) );
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
			$out = array(
				'min' => (float) $row->low,
				'max' => (float) $row->high,
			);
		}

		set_transient( $cache, $out, self::CACHE_TTL );

		return $out;
	}

	/**
	 * @param array  $selection
	 * @param string $except Facet to leave out, so its own choice does not hide the others.
	 * @return int[]|null Post IDs the rest of the selection allows, null when it allows everything.
	 */
	private static function constraint( array $selection, $except ) {
		$meta = self::meta_query( $selection, array( $except ) );
		$term = ( 'q' === $except || ! isset( $selection['q'] ) ) ? '' : $selection['q'];

		if ( ! $meta && '' === $term ) {
			return null;
		}

		$args = array(
			'post_type'              => WMDS_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => self::MAX_CONSTRAINT,
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

		return ( count( $ids ) >= self::MAX_CONSTRAINT ) ? null : $ids;
	}

	/**
	 * Drops the cached counts and bounds.
	 *
	 * A full sync saves two thousand vehicles in one request; the cache is
	 * stale after the first of them, so the run flushes once and not once
	 * per vehicle.
	 *
	 * @param int $post_id
	 */
	public static function flush( $post_id = 0 ) {
		if ( self::$flushed ) {
			return;
		}

		if ( $post_id && WMDS_CPT !== get_post_type( $post_id ) ) {
			return;
		}

		self::$flushed = true;

		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- deleting the cache is the point.
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::CACHE ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::CACHE ) . '%'
			)
		);
	}

	/**
	 * @param array $selection
	 * @return array<int, array{key:string,label:string,value:string,url:string}>
	 */
	public static function chips( array $selection ) {
		$facets = self::definitions();
		$out    = array();

		foreach ( $selection as $key => $value ) {
			if ( 'sort' === $key || ! isset( $facets[ $key ] ) ) {
				continue;
			}

			$label = isset( $facets[ $key ]['label'] ) ? $facets[ $key ]['label'] : $key;
			$type  = isset( $facets[ $key ]['type'] ) ? $facets[ $key ]['type'] : 'select';

			if ( 'range' === $type ) {
				$out[] = array(
					'key'   => $key,
					'label' => $label,
					'value' => self::range_label( $value, isset( $facets[ $key ]['unit'] ) ? $facets[ $key ]['unit'] : '' ),
					'url'   => self::url_without( $selection, $key ),
				);
				continue;
			}

			if ( 'search' === $type ) {
				$out[] = array(
					'key'   => $key,
					'label' => $label,
					'value' => (string) $value,
					'url'   => self::url_without( $selection, $key ),
				);
				continue;
			}

			foreach ( (array) $value as $single ) {
				$out[] = array(
					'key'   => $key,
					'label' => $label,
					'value' => (string) $single,
					'url'   => self::url_without( $selection, $key, (string) $single ),
				);
			}
		}

		return $out;
	}

	/**
	 * @param array  $range
	 * @param string $unit
	 * @return string
	 */
	public static function range_label( array $range, $unit = '' ) {
		$unit = ( '' === $unit ) ? '' : ' ' . $unit;

		if ( isset( $range['min'], $range['max'] ) ) {
			/* translators: 1: lower bound, 2: upper bound including the unit. */
			return sprintf( __( '%1$s to %2$s', 'wp-mobile-de-sync' ), self::number( $range['min'] ), self::number( $range['max'] ) . $unit );
		}
		if ( isset( $range['min'] ) ) {
			/* translators: %s: lower bound including the unit. */
			return sprintf( __( 'from %s', 'wp-mobile-de-sync' ), self::number( $range['min'] ) . $unit );
		}
		if ( isset( $range['max'] ) ) {
			/* translators: %s: upper bound including the unit. */
			return sprintf( __( 'up to %s', 'wp-mobile-de-sync' ), self::number( $range['max'] ) . $unit );
		}

		return '';
	}

	/**
	 * @param float $value
	 * @return string
	 */
	public static function number( $value ) {
		return number_format_i18n( (float) $value );
	}

	/**
	 * Turns a selection back into query arguments.
	 *
	 * @param array $selection
	 * @return array<string, mixed>
	 */
	public static function to_query( array $selection ) {
		$facets = self::definitions();
		$out    = array();

		foreach ( $selection as $key => $value ) {
			if ( 'sort' === $key ) {
				$out[ self::PREFIX . 'sort' ] = $value;
				continue;
			}
			if ( ! isset( $facets[ $key ] ) ) {
				continue;
			}

			$type = isset( $facets[ $key ]['type'] ) ? $facets[ $key ]['type'] : 'select';

			if ( 'range' === $type ) {
				foreach ( array( 'min', 'max' ) as $bound ) {
					if ( isset( $value[ $bound ] ) ) {
						$out[ self::PREFIX . $key . '_' . $bound ] = $value[ $bound ];
					}
				}
				continue;
			}

			$out[ self::PREFIX . $key ] = ( 'search' === $type ) ? $value : array_values( (array) $value );
		}

		return $out;
	}

	/**
	 * @param array  $selection
	 * @param string $key
	 * @param string $value Only that value, for a facet that holds several.
	 * @return string
	 */
	public static function url_without( array $selection, $key, $value = '' ) {
		if ( '' !== $value && isset( $selection[ $key ] ) && is_array( $selection[ $key ] ) ) {
			$selection[ $key ] = array_values( array_diff( (array) $selection[ $key ], array( $value ) ) );
			if ( ! $selection[ $key ] ) {
				unset( $selection[ $key ] );
			}
		} else {
			unset( $selection[ $key ] );
		}

		return self::url( $selection );
	}

	/**
	 * @param array $selection
	 * @return string
	 */
	public static function url( array $selection ) {
		$base = self::base_url();
		$args = self::to_query( $selection );

		if ( ! $args ) {
			return $base;
		}

		return $base . ( ( false === strpos( $base, '?' ) ) ? '?' : '&' ) . http_build_query( $args );
	}

	/** @return string Where the filter form submits to. */
	public static function base_url() {
		if ( is_singular() ) {
			$link = get_permalink();
			if ( $link ) {
				return $link;
			}
		}

		$archive = get_post_type_archive_link( WMDS_CPT );

		return $archive ? $archive : home_url( '/' );
	}

	/**
	 * @param array $atts
	 * @return string
	 */
	public static function shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'facets'  => '',
				'layout'  => 'bar',
				'counts'  => 'yes',
				'heading' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'vehicle-filter'
		);

		WMDS_Templates::maybe_enqueue( true );
		self::enqueue();

		ob_start();
		WMDS_Templates::render(
			'parts/filter-bar.php',
			array(
				'selection' => self::selection(),
				'only'      => array_filter( array_map( 'trim', explode( ',', (string) $atts['facets'] ) ) ),
				'layout'    => ( 'sidebar' === $atts['layout'] ) ? 'sidebar' : 'bar',
				'counts'    => ( 'no' !== $atts['counts'] ),
				'heading'   => (string) $atts['heading'],
			)
		);

		return ob_get_clean();
	}

	/**
	 * @param array $atts
	 * @return string
	 */
	public static function shortcode_count( $atts = array() ) {
		$atts = shortcode_atts(
			array( 'filtered' => 'yes' ),
			is_array( $atts ) ? $atts : array(),
			'vehicle-count'
		);

		if ( 'no' === $atts['filtered'] ) {
			return (string) WMDS_Status::count();
		}

		$query = new WP_Query(
			self::query_args(
				self::selection(),
				array(
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			)
		);

		return (string) (int) $query->found_posts;
	}

	public static function enqueue() {
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_register_script( self::HANDLE, WMDS_URL . 'assets/facets.js', array(), WMDS_VERSION, true );
		}

		wp_enqueue_script( self::HANDLE );
	}
}
