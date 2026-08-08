<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A selection, as arguments WP_Query understands.
 */
class WMDS_Facet_Query {
	/**
	 * @param array $selection
	 * @param array $exclude   Facet keys to leave out.
	 * @return array The meta_query the selection amounts to.
	 */
	public static function meta_query( array $selection, array $exclude = array() ) {
		$clauses = array();

		foreach ( WMDS_Facets::definitions() as $key => $facet ) {
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
		$sorts = WMDS_Facets::sorts();
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
	public static function combine( array $first, array $second ) {
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
}
