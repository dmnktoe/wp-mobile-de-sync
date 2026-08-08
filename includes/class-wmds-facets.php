<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The facets, as everything else addresses them.
 *
 * The definitions and the two filters live here; the work is done by
 * WMDS_Facet_Request, WMDS_Facet_Query and WMDS_Facet_Store. Templates and
 * themes only ever need this class.
 */
class WMDS_Facets {
	const PREFIX = 'wmds_';

	const HANDLE = 'wmds-facets';

	const CACHE     = 'wmds_facet_';
	const CACHE_TTL = 600;

	const MAX_CONSTRAINT = 5000;

	const MAX_LENGTH = 100;

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
			WMDS_Facet_Query::query_args(
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

	// The rest of the class is where it was; the work moved, the names did not.

	/**
	 * @param array $request Raw request values, already unslashed.
	 * @return array<string, mixed>
	 */
	public static function parse( array $request ) {
		return WMDS_Facet_Request::parse( $request );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function selection() {
		return WMDS_Facet_Request::selection();
	}

	/**
	 * @param array $selection
	 * @return bool
	 */
	public static function is_filtered( array $selection ) {
		return WMDS_Facet_Request::is_filtered( $selection );
	}

	/**
	 * @param array $selection
	 * @return array<int, array{key:string,label:string,value:string,url:string}>
	 */
	public static function chips( array $selection ) {
		return WMDS_Facet_Request::chips( $selection );
	}

	/**
	 * @param array  $range
	 * @param string $unit
	 * @return string
	 */
	public static function range_label( array $range, $unit = '' ) {
		return WMDS_Facet_Request::range_label( $range, $unit );
	}

	/**
	 * @param float $value
	 * @return string
	 */
	public static function number( $value ) {
		return WMDS_Facet_Request::number( $value );
	}

	/**
	 * @param array $selection
	 * @return array<string, mixed>
	 */
	public static function to_query( array $selection ) {
		return WMDS_Facet_Request::to_query( $selection );
	}

	/**
	 * @param array $selection
	 * @return string
	 */
	public static function url( array $selection ) {
		return WMDS_Facet_Request::url( $selection );
	}

	/**
	 * @param array  $selection
	 * @param string $key
	 * @param string $value Only that value, for a facet that holds several.
	 * @return string
	 */
	public static function url_without( array $selection, $key, $value = '' ) {
		return WMDS_Facet_Request::url_without( $selection, $key, $value );
	}

	/**
	 * @return string
	 */
	public static function base_url() {
		return WMDS_Facet_Request::base_url();
	}

	/**
	 * @param array $selection
	 * @param array $exclude Facet keys to leave out.
	 * @return array
	 */
	public static function meta_query( array $selection, array $exclude = array() ) {
		return WMDS_Facet_Query::meta_query( $selection, $exclude );
	}

	/**
	 * @param string $sort
	 * @return array
	 */
	public static function order_args( $sort ) {
		return WMDS_Facet_Query::order_args( $sort );
	}

	/**
	 * @param array $selection
	 * @param array $base Arguments the caller has already decided on.
	 * @return array
	 */
	public static function query_args( array $selection, array $base = array() ) {
		return WMDS_Facet_Query::query_args( $selection, $base );
	}

	/**
	 * @param WP_Query $query
	 */
	public static function apply_to_archive( $query ) {
		WMDS_Facet_Query::apply_to_archive( $query );
	}

	/**
	 * @param string $key
	 * @param array  $selection
	 * @return array<string, int>
	 */
	public static function choices( $key, array $selection = array() ) {
		return WMDS_Facet_Store::choices( $key, $selection );
	}

	/**
	 * @param string $key
	 * @param array  $selection
	 * @return array
	 */
	public static function bounds( $key, array $selection = array() ) {
		return WMDS_Facet_Store::bounds( $key, $selection );
	}

	/**
	 * @param int $post_id
	 */
	public static function flush( $post_id = 0 ) {
		WMDS_Facet_Store::flush( $post_id );
	}
}
