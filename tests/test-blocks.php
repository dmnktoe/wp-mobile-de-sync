<?php

require_once __DIR__ . '/bootstrap.php';

$GLOBALS['wmds_boot_blocks']   = array();
$GLOBALS['wmds_boot_scripts']  = array();
$GLOBALS['wmds_boot_categories'] = array(
	array(
		'slug'  => 'text',
		'title' => 'Text',
	),
);

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	if ( 'init' === $hook && is_array( $callback ) && 'register' === $callback[1] ) {
		call_user_func( $callback );
	}

	return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	if ( 'block_categories_all' === $hook && is_callable( $callback ) ) {
		$GLOBALS['wmds_boot_categories'] = call_user_func( $callback, $GLOBALS['wmds_boot_categories'], null );
	}

	return true;
}

function wp_register_script( $handle, $src, $deps = array(), $version = false, $args = array() ) {
	$GLOBALS['wmds_boot_scripts'][ $handle ] = array(
		'src'     => $src,
		'deps'    => $deps,
		'version' => $version,
	);

	return true;
}

function wp_set_script_translations( $handle, $domain, $path = null ) {
	return true;
}

function register_block_type( $name, $args = array() ) {
	$GLOBALS['wmds_boot_blocks'][ $name ] = $args;

	return true;
}

if ( ! defined( 'WMDS_VERSION' ) ) {
	define( 'WMDS_VERSION', '0.0.0' );
}
if ( ! defined( 'WMDS_URL' ) ) {
	define( 'WMDS_URL', 'https://example.invalid/wp-content/plugins/wp-mobile-de-sync/' );
}
if ( ! defined( 'WMDS_DIR' ) ) {
	define( 'WMDS_DIR', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-wmds-blocks.php';

WMDS_Blocks::init();

wmds_section( 'The blocks the editor can place' );

foreach ( array( 'wmds/vehicles', 'wmds/vehicle-filter', 'wmds/vehicle-count', 'wmds/vehicle-enquiry' ) as $name ) {
	wmds_assert( sprintf( '%s is registered', $name ), true, isset( $GLOBALS['wmds_boot_blocks'][ $name ] ) );
	wmds_assert(
		sprintf( '%s renders on the server', $name ),
		true,
		isset( $GLOBALS['wmds_boot_blocks'][ $name ]['render_callback'] )
	);
	wmds_assert(
		sprintf( '%s uses the editor script', $name ),
		'wmds-blocks',
		isset( $GLOBALS['wmds_boot_blocks'][ $name ]['editor_script'] ) ? $GLOBALS['wmds_boot_blocks'][ $name ]['editor_script'] : ''
	);
}

wmds_assert( 'the editor script is registered', true, isset( $GLOBALS['wmds_boot_scripts']['wmds-blocks'] ) );
wmds_assert_contains(
	'and points at assets/blocks.js',
	'assets/blocks.js',
	$GLOBALS['wmds_boot_scripts']['wmds-blocks']['src']
);

$slugs = array();
foreach ( $GLOBALS['wmds_boot_categories'] as $category ) {
	if ( isset( $category['slug'] ) ) {
		$slugs[] = $category['slug'];
	}
}
wmds_assert( 'the inserter has a mobile.de Sync category', true, in_array( 'wmds', $slugs, true ) );

wmds_section( 'Attributes reach the shortcodes as they expect them' );

class WMDS_Templates {
	public static $last_atts;

	public static function shortcode( $atts ) {
		self::$last_atts = $atts;

		return 'vehicles';
	}
}

class WMDS_Facets {
	public static $last_filter;
	public static $last_count;

	public static function shortcode( $atts = array() ) {
		self::$last_filter = $atts;

		return 'filter';
	}

	public static function shortcode_count( $atts = array() ) {
		self::$last_count = $atts;

		return '3';
	}
}

class WMDS_Leads {
	public static $last_atts;

	public static function shortcode( $atts = array() ) {
		self::$last_atts = $atts;

		return 'enquiry';
	}
}

wmds_assert(
	'vehicles',
	'vehicles',
	WMDS_Blocks::render_vehicles(
		array(
			'postsPerPage' => 12,
			'columns'      => '3',
			'layout'       => 'list',
			'filters'      => true,
			'pagination'   => true,
			'heading'      => 'h2',
			'orderby'      => 'date',
			'order'        => 'ASC',
		)
	)
);
wmds_assert(
	'and maps camelCase onto the shortcode names',
	array(
		'posts_per_page' => '12',
		'columns'        => '3',
		'layout'         => 'list',
		'filters'        => 'yes',
		'pagination'     => 'yes',
		'heading'        => 'h2',
		'orderby'        => 'date',
		'order'          => 'ASC',
	),
	WMDS_Templates::$last_atts
);

wmds_assert( 'filter', 'filter', WMDS_Blocks::render_filter( array( 'facets' => 'make,price', 'layout' => 'sidebar', 'counts' => false, 'heading' => 'Find' ) ) );
wmds_assert(
	'and turns booleans into yes/no',
	array(
		'facets'  => 'make,price',
		'layout'  => 'sidebar',
		'counts'  => 'no',
		'heading' => 'Find',
	),
	WMDS_Facets::$last_filter
);

wmds_assert( 'count', '3', WMDS_Blocks::render_count( array( 'filtered' => false ) ) );
wmds_assert( 'unfiltered when asked', array( 'filtered' => 'no' ), WMDS_Facets::$last_count );

wmds_assert( 'enquiry', 'enquiry', WMDS_Blocks::render_enquiry( array( 'vehicle' => 42, 'heading' => 'Ask' ) ) );
wmds_assert(
	'with the vehicle id',
	array(
		'vehicle' => 42,
		'heading' => 'Ask',
	),
	WMDS_Leads::$last_atts
);

wmds_assert(
	'the stylesheet knows which blocks to follow',
	array( 'wmds/vehicles', 'wmds/vehicle-filter', 'wmds/vehicle-count', 'wmds/vehicle-enquiry' ),
	WMDS_Blocks::styled_names()
);

wmds_result();
