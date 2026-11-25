<?php

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

function add_filter( $hook, $callback = null, $priority = 10, $args = 1 ) {
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
wmds_assert( 'a lost schedule is looked for on init', true, is_callable( array( 'WMDS_Plugin', 'maybe_ensure_schedule' ) ) );

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
	'WMDS_Blocks',
	'WMDS_Catalog',
	'WMDS_Leads',
	'WMDS_Seo',
	'WMDS_Alerts',
	'WMDS_Str',
	'WMDS_Num',
	'WMDS_Date',
	'WMDS_Mail',
	'WMDS_Log',
	'WMDS_Images',
	'WMDS_Lead_Form',
	'WMDS_Lead_Mail',
	'WMDS_Removed',
	'WMDS_Similar',
	'WMDS_Plugin',
) as $wmds_class ) {
	wmds_assert( sprintf( '%s resolved', $wmds_class ), true, class_exists( $wmds_class ) );
}

wmds_section( 'A tab is found in its own directory' );

foreach ( array( 'Overview', 'Settings', 'Enquiries', 'Embed', 'Tools' ) as $wmds_tab ) {
	wmds_assert( sprintf( 'WMDS_Tab_%s resolved', $wmds_tab ), true, class_exists( 'WMDS_Tab_' . $wmds_tab ) );
}

wmds_section( 'The shortcodes a lazy load would have swallowed' );

foreach ( array( 'vehicle-logo', 'emission-sticker' ) as $wmds_tag ) {
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

wmds_section( 'A lost schedule comes back' );

$GLOBALS['wmds_admin_schedule'] = array();

WMDS_Plugin::maybe_ensure_schedule();
wmds_assert( 'the sync is scheduled again', true, isset( $GLOBALS['wmds_admin_schedule'][ WMDS_CRON_HOOK ] ) );
wmds_assert( 'and so is the daily full sync', true, isset( $GLOBALS['wmds_admin_schedule'][ WMDS_CRON_FULL_HOOK ] ) );

$GLOBALS['wmds_admin_schedule'][ WMDS_CRON_HOOK ] = 12345;
WMDS_Plugin::ensure_schedule();
wmds_assert( 'an event that is there is left alone', 12345, $GLOBALS['wmds_admin_schedule'][ WMDS_CRON_HOOK ] );

wmds_result();
