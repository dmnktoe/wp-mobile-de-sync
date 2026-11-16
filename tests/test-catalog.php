<?php

$GLOBALS['wmds_test_options']   = array();
$GLOBALS['wmds_test_redirects'] = array();
$GLOBALS['wmds_test_updates']   = array();

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['wmds_test_options'] )
		? $GLOBALS['wmds_test_options'][ $key ]
		: $default;
}

function update_option( $key, $value, $autoload = true ) {
	$GLOBALS['wmds_test_options'][ $key ] = $value;

	return true;
}

function flush_rewrite_rules( $hard = true ) {
	$GLOBALS['wmds_test_options']['flushed'] = true;

	return true;
}

function is_admin() {
	return ! empty( $GLOBALS['wmds_test_is_admin'] );
}

function wp_doing_ajax() {
	return false;
}

function home_url( $path = '/' ) {
	return 'https://example.invalid' . $path;
}

function get_post_type_archive_link( $post_type ) {
	return 'https://example.invalid/' . $post_type . '/';
}

function apply_filters( $hook, $value ) {
	if ( 'wmds_migrate_exit' === $hook ) {
		return false;
	}

	return $value;
}

function wp_safe_redirect( $url, $status = 302 ) {
	$GLOBALS['wmds_test_redirects'][] = array(
		'url'    => $url,
		'status' => $status,
	);
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}

function user_trailingslashit( $value ) {
	return trailingslashit( $value );
}

function wp_unslash( $value ) {
	return $value;
}

class WMDS_Test_Wpdb {
	public $posts = 'wp_posts';

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		$GLOBALS['wmds_test_updates'][] = array(
			'table' => $table,
			'data'  => $data,
			'where' => $where,
		);

		return 1;
	}
}

$wpdb = new WMDS_Test_Wpdb(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the migrate class reads $wpdb.

require_once __DIR__ . '/bootstrap.php';

if ( ! defined( 'WMDS_VERSION' ) ) {
	define( 'WMDS_VERSION', '2.3.0' );
}
if ( ! defined( 'WMDS_CPT' ) ) {
	define( 'WMDS_CPT', 'vehicles' );
}
if ( ! defined( 'WMDS_URL' ) ) {
	define( 'WMDS_URL', 'https://example.invalid/wp-content/plugins/wp-mobile-de-sync/' );
}
if ( ! defined( 'WMDS_DIR' ) ) {
	define( 'WMDS_DIR', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-wmds-migrate.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-dashboard.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-blocks.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-catalog.php';

wmds_section( 'The catalogue lists every public surface' );

$tags = array();
foreach ( WMDS_Catalog::shortcodes() as $shortcode ) {
	$tags[] = $shortcode['tag'];
}
wmds_assert(
	'every English shortcode is documented',
	array( 'vehicles', 'vehicle-filter', 'vehicle-count', 'vehicle-enquiry', 'vehicle-logo', 'emission-sticker' ),
	$tags
);

$blocks = array();
foreach ( WMDS_Catalog::blocks() as $block ) {
	$blocks[] = $block['name'];
}
wmds_assert(
	'every block is documented',
	array( 'wmds/vehicles', 'wmds/vehicle-filter', 'wmds/vehicle-count', 'wmds/vehicle-enquiry' ),
	$blocks
);

$widgets = WMDS_Catalog::widgets();
wmds_assert( 'the dashboard widget is documented', 1, count( $widgets ) );
wmds_assert( 'under its real id', WMDS_Dashboard::WIDGET, $widgets[0]['id'] );

wmds_section( 'Migrating the post type off fahrzeuge' );

$GLOBALS['wmds_test_options'] = array();
$GLOBALS['wmds_test_updates'] = array();

WMDS_Migrate::activate();

wmds_assert( 'rewrites are flushed on activate', true, ! empty( $GLOBALS['wmds_test_options']['flushed'] ) );
wmds_assert( 'the schema version is stored', WMDS_Migrate::SCHEMA, $GLOBALS['wmds_test_options'][ WMDS_Migrate::OPTION ] );
wmds_assert( 'posts of the old type are rewritten', 1, count( $GLOBALS['wmds_test_updates'] ) );
wmds_assert(
	'from fahrzeuge to vehicles',
	array(
		'table' => 'wp_posts',
		'data'  => array( 'post_type' => 'vehicles' ),
		'where' => array( 'post_type' => 'fahrzeuge' ),
	),
	$GLOBALS['wmds_test_updates'][0]
);

$GLOBALS['wmds_test_updates'] = array();
WMDS_Migrate::maybe_run();
wmds_assert( 'a second run does nothing', 0, count( $GLOBALS['wmds_test_updates'] ) );

wmds_section( 'Legacy /fahrzeuge/ URLs redirect once' );

$GLOBALS['wmds_test_redirects'] = array();
$_SERVER['REQUEST_URI']         = '/fahrzeuge/audi-a4/';
WMDS_Migrate::redirect_legacy();
wmds_assert( 'a vehicle URL redirects', 1, count( $GLOBALS['wmds_test_redirects'] ) );
wmds_assert( 'to the English slug', 'https://example.invalid/vehicles/audi-a4/', $GLOBALS['wmds_test_redirects'][0]['url'] );
wmds_assert( 'permanently', 301, $GLOBALS['wmds_test_redirects'][0]['status'] );

$GLOBALS['wmds_test_redirects'] = array();
$_SERVER['REQUEST_URI']         = '/fahrzeuge/';
WMDS_Migrate::redirect_legacy();
wmds_assert( 'the archive redirects', 'https://example.invalid/vehicles/', $GLOBALS['wmds_test_redirects'][0]['url'] );

$GLOBALS['wmds_test_redirects'] = array();
$_SERVER['REQUEST_URI']         = '/vehicles/audi-a4/';
WMDS_Migrate::redirect_legacy();
wmds_assert( 'the new slug is left alone', 0, count( $GLOBALS['wmds_test_redirects'] ) );

wmds_result();
