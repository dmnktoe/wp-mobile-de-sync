<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FacetWP, where a site already runs it.
 *
 * The plugin has filtered on its own since 2.2, and nothing here is needed for
 * that. This is for the sites that were built on FacetWP before and are not
 * going to be rebuilt: their facets keep working, and three things that used
 * to be manual stop being manual.
 *
 * The meta keys turn up in the facet source dropdown under their own heading
 * with readable labels, instead of having to be typed as `cf/price_raw` from
 * memory. The sort orders the filter bar offers are offered to a FacetWP
 * template too. And a vehicle is re-indexed after the import has written its
 * meta rather than while it is writing it — FacetWP indexes on save_post, the
 * importer saves the post first and fills the meta afterwards, so what
 * FacetWP indexed was the state before the update. That is the stale count
 * nobody could explain.
 */
class WMDS_Facetwp {
	public static function init() {
		if ( ! self::active() ) {
			return;
		}

		add_filter( 'facetwp_facet_sources', array( __CLASS__, 'sources' ) );
		add_filter( 'facetwp_sort_options', array( __CLASS__, 'sort_options' ), 10, 2 );
		add_action( 'wmds_vehicle_stored', array( __CLASS__, 'index_post' ) );
	}

	/** @return bool */
	public static function active() {
		return defined( 'FACETWP_VERSION' ) || function_exists( 'FWP' );
	}

	/** @return string */
	public static function version() {
		return defined( 'FACETWP_VERSION' ) ? (string) FACETWP_VERSION : '';
	}

	/** @return bool Whether a vehicle is re-indexed after a sync wrote it. */
	public static function reindexes() {
		return 'no' !== WMDS_Settings::get( 'facetwp_reindex', 'yes' );
	}

	/**
	 * The meta keys, as facet sources.
	 *
	 * @param array $sources
	 * @return array
	 */
	public static function sources( $sources ) {
		$sources = is_array( $sources ) ? $sources : array();

		$sources['wmds'] = array(
			'label'   => 'mobile.de',
			'weight'  => 5,
			'choices' => self::choices( WMDS_Facets::definitions(), self::extra() ),
		);

		return $sources;
	}

	/**
	 * @param array $definitions As WMDS_Facets::definitions() returns them.
	 * @param array $extra       Further meta key => label.
	 * @return array<string, string> FacetWP source => label.
	 */
	public static function choices( array $definitions, array $extra = array() ) {
		$choices = array();

		foreach ( $definitions as $facet ) {
			$meta = isset( $facet['meta'] ) ? (string) $facet['meta'] : '';
			if ( '' === $meta ) {
				continue;
			}

			$choices[ 'cf/' . $meta ] = isset( $facet['label'] ) ? (string) $facet['label'] : $meta;
		}

		foreach ( $extra as $meta => $label ) {
			$key = 'cf/' . $meta;
			if ( ! isset( $choices[ $key ] ) ) {
				$choices[ $key ] = (string) $label;
			}
		}

		return $choices;
	}

	/**
	 * Fields worth faceting on that the filter bar has no component for.
	 *
	 * @return array<string, string>
	 */
	public static function extra() {
		return array(
			'make_key'         => __( 'Make (key)', 'wp-mobile-de-sync' ),
			'vehicleListingID' => __( 'Listing number', 'wp-mobile-de-sync' ),
			'emissionClass'    => __( 'Emission class', 'wp-mobile-de-sync' ),
			'emissionSticker'  => __( 'Emissions sticker', 'wp-mobile-de-sync' ),
			'door_count'       => __( 'Doors', 'wp-mobile-de-sync' ),
			'num_seats'        => __( 'Seats', 'wp-mobile-de-sync' ),
			'owners'           => __( 'Previous owners', 'wp-mobile-de-sync' ),
			'interior_type'    => __( 'Interior', 'wp-mobile-de-sync' ),
			'seller'           => __( 'Seller', 'wp-mobile-de-sync' ),
		);
	}

	/**
	 * @param array $options
	 * @param array $params
	 * @return array
	 */
	public static function sort_options( $options, $params = array() ) {
		unset( $params );

		return array_merge(
			is_array( $options ) ? $options : array(),
			self::sorts( WMDS_Facets::sorts() )
		);
	}

	/**
	 * @param array $sorts As WMDS_Facets::sorts() returns them.
	 * @return array<string, array{label:string,query_args:array}>
	 */
	public static function sorts( array $sorts ) {
		$options = array();

		foreach ( $sorts as $key => $sort ) {
			$meta  = isset( $sort['meta'] ) ? (string) $sort['meta'] : '';
			$order = isset( $sort['order'] ) ? (string) $sort['order'] : 'DESC';

			$args = ( '' === $meta )
				? array(
					'orderby' => 'date',
					'order'   => $order,
				)
				: array(
					'orderby'  => ( 'NUMERIC' === ( isset( $sort['type'] ) ? $sort['type'] : '' ) ) ? 'meta_value_num' : 'meta_value',
					'meta_key' => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- sorting by meta is what the option is for.
					'order'    => $order,
				);

			$options[ 'wmds_' . str_replace( '-', '_', (string) $key ) ] = array(
				'label'      => isset( $sort['label'] ) ? (string) $sort['label'] : (string) $key,
				'query_args' => $args,
			);
		}

		return $options;
	}

	/**
	 * Re-indexes one vehicle, after the import has finished writing it.
	 *
	 * @param int $post_id
	 */
	public static function index_post( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! $post_id || ! self::reindexes() || ! function_exists( 'FWP' ) ) {
			return;
		}

		$fwp = FWP();

		if ( ! is_object( $fwp ) || ! isset( $fwp->indexer ) || ! is_object( $fwp->indexer ) ) {
			return;
		}

		$fwp->indexer->index( $post_id );
	}
}
