<?php
/**
 * Template resolution and the stylesheet decision.
 *
 * The fakes here are richer than the ones in bootstrap.php: this test needs
 * filters that actually run and a style registry it can read back, so both
 * are defined before bootstrap.php gets its turn.
 */

$GLOBALS['wmds_test_filters']     = array();
$GLOBALS['wmds_test_styles']      = array();
$GLOBALS['wmds_test_theme_files'] = array();
$GLOBALS['wmds_test_query']       = array();

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wmds_test_filters'][ $hook ][] = $callback;

	return true;
}

function apply_filters( $hook, $value ) {
	$args = array_slice( func_get_args(), 1 );

	if ( empty( $GLOBALS['wmds_test_filters'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['wmds_test_filters'][ $hook ] as $callback ) {
		$value   = call_user_func_array( $callback, $args );
		$args[0] = $value;
	}

	return $value;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function locate_template( $templates ) {
	foreach ( (array) $templates as $template ) {
		if ( isset( $GLOBALS['wmds_test_theme_files'][ $template ] ) ) {
			return $GLOBALS['wmds_test_theme_files'][ $template ];
		}
	}

	return '';
}

function is_singular( $post_types = '' ) {
	$current = isset( $GLOBALS['wmds_test_query']['singular'] ) ? $GLOBALS['wmds_test_query']['singular'] : '';

	if ( '' === $current ) {
		return false;
	}

	return '' === $post_types ? true : $current === $post_types;
}

function is_post_type_archive( $post_types = '' ) {
	$current = isset( $GLOBALS['wmds_test_query']['archive'] ) ? $GLOBALS['wmds_test_query']['archive'] : '';

	return '' !== $current && $current === $post_types;
}

function get_post( $post = null ) {
	return isset( $GLOBALS['wmds_test_query']['post'] ) ? $GLOBALS['wmds_test_query']['post'] : null;
}

function has_shortcode( $content, $tag ) {
	return false !== strpos( (string) $content, '[' . $tag );
}

function wp_style_is( $handle, $state = 'enqueued' ) {
	if ( 'registered' === $state ) {
		return isset( $GLOBALS['wmds_test_styles'][ $handle ] );
	}

	return ! empty( $GLOBALS['wmds_test_styles'][ $handle ]['enqueued'] );
}

function wp_register_style( $handle, $src, $deps = array(), $version = false ) {
	$GLOBALS['wmds_test_styles'][ $handle ] = array(
		'src'      => $src,
		'version'  => $version,
		'enqueued' => false,
	);

	return true;
}

function wp_enqueue_style( $handle ) {
	if ( ! isset( $GLOBALS['wmds_test_styles'][ $handle ] ) ) {
		return;
	}

	$GLOBALS['wmds_test_styles'][ $handle ]['enqueued'] = true;
}

class WP_Post {
	public $post_content = '';

	public function __construct( $content = '' ) {
		$this->post_content = $content;
	}
}

require_once __DIR__ . '/bootstrap.php';

define( 'WMDS_DIR', dirname( __DIR__ ) . '/' );
define( 'WMDS_URL', 'https://example.invalid/wp-content/plugins/wp-mobile-de-sync/' );
define( 'WMDS_VERSION', '0.0.0-test' );
define( 'WMDS_CPT', 'fahrzeuge' );

require_once __DIR__ . '/../includes/class-wmds-templates.php';

function wmds_test_reset() {
	$GLOBALS['wmds_test_styles']      = array();
	$GLOBALS['wmds_test_theme_files'] = array();
	$GLOBALS['wmds_test_query']       = array();
	$GLOBALS['wmds_test_filters']     = array();
}

wmds_section( 'Template resolution: the theme wins, the plugin fills in' );

wmds_test_reset();
wmds_assert(
	'no override: the bundled template',
	WMDS_DIR . 'templates/mob_vehicle-list.php',
	WMDS_Templates::locate( 'mob_vehicle-list.php' )
);

$GLOBALS['wmds_test_theme_files']['mob_vehicle-list.php'] = '/theme/mob_vehicle-list.php';
wmds_assert(
	'theme root beats the plugin',
	'/theme/mob_vehicle-list.php',
	WMDS_Templates::locate( 'mob_vehicle-list.php' )
);

$GLOBALS['wmds_test_theme_files']['wp-mobile-de-sync/mob_vehicle-list.php'] = '/theme/wp-mobile-de-sync/mob_vehicle-list.php';
wmds_assert(
	'the subdirectory beats the theme root',
	'/theme/wp-mobile-de-sync/mob_vehicle-list.php',
	WMDS_Templates::locate( 'mob_vehicle-list.php' )
);

wmds_test_reset();
wmds_assert(
	'the card is a template of its own',
	WMDS_DIR . 'templates/parts/vehicle-card.php',
	WMDS_Templates::locate( 'parts/vehicle-card.php' )
);

wmds_assert( 'nothing anywhere stays empty', '', WMDS_Templates::locate( 'does-not-exist.php' ) );

add_filter(
	'wmds_template',
	function ( $found, $file ) {
		return 'parts/vehicle-card.php' === $file ? '/elsewhere/card.php' : $found;
	}
);
wmds_assert( 'the filter has the last word', '/elsewhere/card.php', WMDS_Templates::locate( 'parts/vehicle-card.php' ) );

wmds_section( 'The stylesheet follows the templates, not the post type' );

wmds_test_reset();
$GLOBALS['wmds_test_query']['singular'] = WMDS_CPT;
wmds_assert( 'a vehicle page needs it', true, WMDS_Templates::styles_needed() );

wmds_test_reset();
$GLOBALS['wmds_test_query']['archive'] = WMDS_CPT;
wmds_assert( 'the vehicle archive needs it', true, WMDS_Templates::styles_needed() );

wmds_test_reset();
$GLOBALS['wmds_test_query']['singular'] = 'page';
$GLOBALS['wmds_test_query']['post']     = new WP_Post( 'Our offers: [fahrzeuge-anzeigen posts_per_page="6"]' );
wmds_assert( 'a page with the shortcode needs it', true, WMDS_Templates::styles_needed() );

$GLOBALS['wmds_test_query']['post'] = new WP_Post( 'A page about opening hours.' );
wmds_assert( 'a page without it does not', false, WMDS_Templates::styles_needed() );

wmds_test_reset();
wmds_assert( 'the front page does not', false, WMDS_Templates::styles_needed() );

wmds_section( 'Enqueueing' );

wmds_test_reset();
WMDS_Templates::maybe_enqueue( true );
wmds_assert( 'registers on demand', true, wp_style_is( 'wmds', 'registered' ) );
wmds_assert( 'and enqueues', true, wp_style_is( 'wmds', 'enqueued' ) );
wmds_assert_contains( 'points at the bundled file', 'assets/wmds.css', $GLOBALS['wmds_test_styles']['wmds']['src'] );
wmds_assert( 'carries the plugin version', WMDS_VERSION, $GLOBALS['wmds_test_styles']['wmds']['version'] );

wmds_test_reset();
WMDS_Templates::maybe_enqueue( false );
wmds_assert( 'not wanted, not enqueued', false, wp_style_is( 'wmds', 'enqueued' ) );

wmds_test_reset();
add_filter( 'wmds_enqueue_styles', 'wmds_test_return_false' );
WMDS_Templates::maybe_enqueue( true );
wmds_assert( 'the filter switches it off', false, wp_style_is( 'wmds', 'enqueued' ) );

function wmds_test_return_false( $value ) {
	return false;
}

wmds_result();
