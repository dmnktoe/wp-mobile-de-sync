<?php
/**
 * The plugin file, loaded the way WordPress loads it.
 *
 * Every other test requires the classes it exercises by hand, which means
 * none of them would notice if the plugin no longer wired itself up. This
 * one requires nothing but the plugin file and then asks whether the pieces
 * arrived - which is the only thing that says the autoloader is reached at
 * all, and the only thing that would catch a shortcode left unregistered
 * because its file is never mentioned by name.
 */

$GLOBALS['wmds_boot_shortcodes'] = array();
$GLOBALS['wmds_boot_actions']    = array();

function add_shortcode( $tag, $callback ) {
	$GLOBALS['wmds_boot_shortcodes'][] = $tag;

	return true;
}

function add_action( $hook, $callback = null, $priority = 10, $args = 1 ) {
	$GLOBALS['wmds_boot_actions'][] = $hook;

	return true;
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wp-admin-fake.php';

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
	return 'https://example.invalid/wp-content/plugins/wp-mobile-de-sync/';
}

function content_url( $path = '' ) {
	return 'https://example.invalid/wp-content' . $path;
}

function register_activation_hook( $file, $callback ) {
	return true;
}

function register_deactivation_hook( $file, $callback ) {
	return true;
}

function load_plugin_textdomain( $domain, $deprecated = false, $path = false ) {
	return true;
}

function spl_autoload_registered() {
	foreach ( (array) spl_autoload_functions() as $entry ) {
		if ( is_array( $entry ) && 'WMDS_Autoloader' === $entry[0] ) {
			return true;
		}
	}

	return false;
}

require_once dirname( __DIR__ ) . '/wp-mobile-de-sync.php';

wmds_section( 'The plugin file wires itself up' );

wmds_assert( 'the autoloader is registered', true, spl_autoload_registered() );
wmds_assert( 'the post type is registered on init', true, in_array( 'init', $GLOBALS['wmds_boot_actions'], true ) );
wmds_assert( 'the import event has a listener', true, in_array( WMDS_CRON_HOOK, $GLOBALS['wmds_boot_actions'], true ) );

wmds_section( 'Classes nobody required by hand are there anyway' );

foreach ( array(
	'WMDS_Settings',
	'WMDS_Status',
	'WMDS_Importer',
	'WMDS_Vehicle',
	'WMDS_Templates',
	'WMDS_Facets',
	'WMDS_Facet_Request',
	'WMDS_Facet_Query',
	'WMDS_Facet_Store',
	'WMDS_Leads',
	'WMDS_Seo',
	'WMDS_Alerts',
	'WMDS_Str',
	'WMDS_Num',
	'WMDS_Date',
	'WMDS_Mail',
) as $wmds_class ) {
	wmds_assert( sprintf( '%s resolved', $wmds_class ), true, class_exists( $wmds_class ) );
}

wmds_section( 'A tab is found in its own directory' );

wmds_assert( 'WMDS_Tab_Connection resolved', true, class_exists( 'WMDS_Tab_Connection' ) );
wmds_assert( 'WMDS_Tab_Status resolved', true, class_exists( 'WMDS_Tab_Status' ) );

wmds_section( 'The shortcodes a lazy load would have swallowed' );

/*
 * These two are registered at file level rather than from an init() method.
 * Nothing on a page that only writes [vehicle-logo] would ever mention
 * WMDS_Logos, so an autoloaded file would never run and the shortcode would
 * simply not exist. That is why the plugin file requires them outright, and
 * this is what notices if it stops.
 */
foreach ( array( 'vehicle-logo', 'fahrzeug-logo', 'emission-sticker' ) as $wmds_tag ) {
	wmds_assert(
		sprintf( '[%s] is registered', $wmds_tag ),
		true,
		in_array( $wmds_tag, $GLOBALS['wmds_boot_shortcodes'], true )
	);
}

wmds_section( 'And the ones an init() method registers' );

foreach ( array( 'vehicles', 'vehicle-filter', 'vehicle-count', 'vehicle-enquiry' ) as $wmds_tag ) {
	wmds_assert(
		sprintf( '[%s] is registered', $wmds_tag ),
		true,
		in_array( $wmds_tag, $GLOBALS['wmds_boot_shortcodes'], true )
	);
}

wmds_result();
