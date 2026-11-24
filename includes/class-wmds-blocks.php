<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Blocks {
	const HANDLE = 'wmds-blocks';

	const CATEGORY = 'wmds';

	/**
	 * @var string[]
	 */
	private static $styled = array(
		'wmds/vehicles',
		'wmds/vehicle-filter',
		'wmds/vehicle-count',
		'wmds/vehicle-enquiry',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'category' ), 10, 2 );
	}

	/**
	 * @param array[]                 $categories
	 * @param WP_Block_Editor_Context $context
	 * @return array[]
	 */
	public static function category( $categories, $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- WordPress passes both.
		foreach ( $categories as $category ) {
			if ( isset( $category['slug'] ) && self::CATEGORY === $category['slug'] ) {
				return $categories;
			}
		}

		$categories[] = array(
			'slug'  => self::CATEGORY,
			'title' => 'mobile.de Sync',
			'icon'  => 'car',
		);

		return $categories;
	}

	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		WMDS_Templates::register();

		wp_register_script(
			self::HANDLE,
			WMDS_URL . 'assets/blocks.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-components',
				'wp-block-editor',
				'wp-server-side-render',
				'wp-i18n',
			),
			WMDS_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( self::HANDLE, 'wp-mobile-de-sync', WMDS_DIR . 'languages' );
		}

		foreach ( self::definitions() as $name => $definition ) {
			register_block_type(
				$name,
				array(
					'api_version'     => 2,
					'title'           => $definition['title'],
					'description'     => $definition['description'],
					'category'        => self::CATEGORY,
					'icon'            => $definition['icon'],
					'keywords'        => $definition['keywords'],
					'attributes'      => $definition['attributes'],
					'supports'        => array(
						'html' => false,
					),
					'editor_script'   => self::HANDLE,
					'editor_style'    => WMDS_Templates::HANDLE,
					'render_callback' => $definition['render'],
				)
			);
		}
	}

	/**
	 * @return array<string, array>
	 */
	public static function definitions() {
		return array(
			'wmds/vehicles'        => array(
				'title'       => __( 'Vehicles', 'wp-mobile-de-sync' ),
				'description' => __( 'A list of vehicles from the synced inventory.', 'wp-mobile-de-sync' ),
				'icon'        => 'car',
				'keywords'    => array( 'vehicle', 'inventory', 'mobile.de', 'cars' ),
				'attributes'  => array(
					'postsPerPage' => array(
						'type'    => 'number',
						'default' => 6,
					),
					'columns'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'layout'       => array(
						'type'    => 'string',
						'default' => 'grid',
					),
					'filters'      => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'pagination'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'heading'      => array(
						'type'    => 'string',
						'default' => 'h3',
					),
					'orderby'      => array(
						'type'    => 'string',
						'default' => 'date',
					),
					'order'        => array(
						'type'    => 'string',
						'default' => 'DESC',
					),
				),
				'render'      => array( __CLASS__, 'render_vehicles' ),
			),
			'wmds/vehicle-filter'  => array(
				'title'       => __( 'Vehicle filter', 'wp-mobile-de-sync' ),
				'description' => __( 'Filter controls for the vehicle inventory.', 'wp-mobile-de-sync' ),
				'icon'        => 'filter',
				'keywords'    => array( 'filter', 'facets', 'search' ),
				'attributes'  => array(
					'facets'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'layout'  => array(
						'type'    => 'string',
						'default' => 'bar',
					),
					'counts'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'heading' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render'      => array( __CLASS__, 'render_filter' ),
			),
			'wmds/vehicle-count'   => array(
				'title'       => __( 'Vehicle count', 'wp-mobile-de-sync' ),
				'description' => __( 'Number of vehicles remaining with the current filters.', 'wp-mobile-de-sync' ),
				'icon'        => 'editor-ol',
				'keywords'    => array( 'count', 'number' ),
				'attributes'  => array(
					'filtered' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'render'      => array( __CLASS__, 'render_count' ),
			),
			'wmds/vehicle-enquiry' => array(
				'title'       => __( 'Vehicle enquiry', 'wp-mobile-de-sync' ),
				'description' => __( 'An enquiry form for a vehicle.', 'wp-mobile-de-sync' ),
				'icon'        => 'email',
				'keywords'    => array( 'enquiry', 'form', 'contact' ),
				'attributes'  => array(
					'vehicle' => array(
						'type'    => 'number',
						'default' => 0,
					),
					'heading' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render'      => array( __CLASS__, 'render_enquiry' ),
			),
		);
	}

	/**
	 * @return string[]
	 */
	public static function styled_names() {
		return self::$styled;
	}

	/**
	 * @param array $attributes
	 * @return string
	 */
	public static function render_vehicles( $attributes ) {
		$atts = array(
			'posts_per_page' => isset( $attributes['postsPerPage'] ) ? (string) (int) $attributes['postsPerPage'] : '6',
			'columns'        => isset( $attributes['columns'] ) ? (string) $attributes['columns'] : '',
			'layout'         => isset( $attributes['layout'] ) ? (string) $attributes['layout'] : 'grid',
			'filters'        => ! empty( $attributes['filters'] ) ? 'yes' : 'no',
			'pagination'     => ! empty( $attributes['pagination'] ) ? 'yes' : 'no',
			'heading'        => isset( $attributes['heading'] ) ? (string) $attributes['heading'] : 'h3',
			'orderby'        => isset( $attributes['orderby'] ) ? (string) $attributes['orderby'] : 'date',
			'order'          => isset( $attributes['order'] ) ? (string) $attributes['order'] : 'DESC',
		);

		return WMDS_Templates::shortcode( $atts );
	}

	/**
	 * @param array $attributes
	 * @return string
	 */
	public static function render_filter( $attributes ) {
		return WMDS_Facets::shortcode(
			array(
				'facets'  => isset( $attributes['facets'] ) ? (string) $attributes['facets'] : '',
				'layout'  => isset( $attributes['layout'] ) ? (string) $attributes['layout'] : 'bar',
				'counts'  => empty( $attributes['counts'] ) ? 'no' : 'yes',
				'heading' => isset( $attributes['heading'] ) ? (string) $attributes['heading'] : '',
			)
		);
	}

	/**
	 * @param array $attributes
	 * @return string
	 */
	public static function render_count( $attributes ) {
		return WMDS_Facets::shortcode_count(
			array(
				'filtered' => empty( $attributes['filtered'] ) ? 'no' : 'yes',
			)
		);
	}

	/**
	 * @param array $attributes
	 * @return string
	 */
	public static function render_enquiry( $attributes ) {
		return WMDS_Lead_Form::shortcode(
			array(
				'vehicle' => isset( $attributes['vehicle'] ) ? (int) $attributes['vehicle'] : 0,
				'heading' => isset( $attributes['heading'] ) ? (string) $attributes['heading'] : '',
			)
		);
	}
}
