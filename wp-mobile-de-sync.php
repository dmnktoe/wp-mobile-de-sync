<?php
/**
 * Plugin Name: mobile.de Sync
 * Plugin URI:  https://github.com/dmnktoe/wp-mobile-de-sync
 * Description: Synchronises a dealer's vehicle inventory from the mobile.de Search API into a "fahrzeuge" custom post type. Works with FacetWP and with existing theme templates.
 * Version:     1.0.0
 * Author:      Domenik Töfflinger
 * Author URI:  https://github.com/dmnktoe
 * License:     GPL-2.0-or-later
 * Text Domain: wp-mobile-de-sync
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.0
 * Update URI: https://github.com/dmnktoe/wp-mobile-de-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WMDS_VERSION', '1.0.0' );
define( 'WMDS_FILE', __FILE__ );
define( 'WMDS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WMDS_URL', plugin_dir_url( __FILE__ ) );

define( 'WMDS_CPT', 'fahrzeuge' );

define( 'WMDS_CRON_HOOK', 'wmds_import_event' );
define( 'WMDS_CRON_FULL_HOOK', 'wmds_full_sync_event' );

if ( ! defined( 'WMDS_ICONS' ) ) {
	define( 'WMDS_ICONS', content_url( '/icons/' ) );
}

require_once WMDS_DIR . 'includes/class-wmds-settings.php';
require_once WMDS_DIR . 'includes/class-wmds-compat.php';
require_once WMDS_DIR . 'includes/class-wmds-creole.php';
require_once WMDS_DIR . 'includes/class-wmds-refdata.php';
require_once WMDS_DIR . 'includes/class-wmds-client.php';
require_once WMDS_DIR . 'includes/class-wmds-json-mapper.php';
require_once WMDS_DIR . 'includes/class-wmds-sync-plan.php';
require_once WMDS_DIR . 'includes/class-wmds-importer.php';
require_once WMDS_DIR . 'includes/class-wmds-logos.php';
require_once WMDS_DIR . 'includes/class-wmds-vehicle.php';

require_once WMDS_DIR . 'includes/class-wmds-updater.php';
add_action( 'init', array( 'WMDS_Updater', 'init' ) );

if ( is_admin() ) {
	require_once WMDS_DIR . 'includes/class-wmds-admin.php';
	add_action( 'plugins_loaded', array( 'WMDS_Admin', 'init' ) );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WMDS_DIR . 'includes/class-wmds-cli.php';
}

add_action( 'init', 'wmds_load_textdomain' );
function wmds_load_textdomain() {
	load_plugin_textdomain( 'wp-mobile-de-sync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

function wmds_register_cpt() {
	register_post_type(
		WMDS_CPT,
		array(
			'labels'             => array(
				'name'          => __( 'Vehicles', 'wp-mobile-de-sync' ),
				'singular_name' => __( 'Vehicle', 'wp-mobile-de-sync' ),
				'menu_name'     => __( 'Vehicles', 'wp-mobile-de-sync' ),
				'all_items'     => __( 'All Vehicles', 'wp-mobile-de-sync' ),
				'add_new'       => __( 'Add New', 'wp-mobile-de-sync' ),
				'add_new_item'  => __( 'Add New Vehicle', 'wp-mobile-de-sync' ),
				'edit_item'     => __( 'Edit Vehicle', 'wp-mobile-de-sync' ),
				'view_item'     => __( 'View Vehicle', 'wp-mobile-de-sync' ),
				'search_items'  => __( 'Search Vehicles', 'wp-mobile-de-sync' ),
				'not_found'     => __( 'No vehicles found', 'wp-mobile-de-sync' ),
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'fahrzeuge',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-car',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'show_in_rest'       => true,
		)
	);
}
add_action( 'init', 'wmds_register_cpt' );

add_action( 'before_delete_post', array( 'WMDS_Importer', 'delete_attachments' ) );

/**
 * @param string $template
 * @return string
 */
function wmds_template_loader( $template ) {
	if ( is_singular( WMDS_CPT ) ) {
		$file = 'single-fahrzeuge.php';
	} elseif ( is_post_type_archive( WMDS_CPT ) ) {
		$file = 'archive-fahrzeuge.php';
	} else {
		return $template;
	}

	if ( file_exists( get_stylesheet_directory() . '/' . $file ) ) {
		return $template;
	}

	$candidate = WMDS_DIR . 'templates/' . $file;

	return file_exists( $candidate ) ? $candidate : $template;
}
add_filter( 'template_include', 'wmds_template_loader' );

/**
 * @param array  $atts    Shortcode attributes, consumed by the template.
 * @param string $content Enclosed content, unused.
 * @return string
 */
function wmds_shortcode_getcars( $atts, $content = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- see above.
	ob_start();
	$theme_tpl = get_stylesheet_directory() . '/mob_vehicle-list.php';
	if ( file_exists( $theme_tpl ) ) {
		include $theme_tpl;
	} else {
		include WMDS_DIR . 'templates/mob_vehicle-list.php';
	}
	$output = ob_get_clean();
	wp_reset_postdata();
	return $output;
}
add_shortcode( 'fahrzeuge-anzeigen', 'wmds_shortcode_getcars' );

add_action( 'wp_enqueue_scripts', 'wmds_enqueue_styles' );
function wmds_enqueue_styles() {
	$needed = is_singular( WMDS_CPT ) || is_post_type_archive( WMDS_CPT );

	/**
	 * @param bool $needed
	 */
	if ( ! apply_filters( 'wmds_enqueue_styles', $needed ) ) {
		return;
	}

	wp_enqueue_style( 'wmds', WMDS_URL . 'assets/wmds.css', array(), WMDS_VERSION );
}

add_filter( 'cron_schedules', 'wmds_cron_schedules' );
/**
 * @param array $schedules
 * @return array
 */
function wmds_cron_schedules( $schedules ) {
	foreach ( array( 5, 15, 30, 60 ) as $minutes ) {
		$key = 'wmds_' . $minutes . 'min';
		if ( ! isset( $schedules[ $key ] ) ) {
			$schedules[ $key ] = array(
				'interval' => $minutes * 60,
				/* translators: %d: number of minutes between sync runs. */
				'display'  => sprintf( __( 'Every %d minutes (mobile.de Sync)', 'wp-mobile-de-sync' ), $minutes ),
			);
		}
	}
	return $schedules;
}

add_action( WMDS_CRON_HOOK, 'wmds_run_import' );
function wmds_run_import() {
	if ( ! WMDS_Settings::is_configured() ) {
		return;
	}
	$importer = new WMDS_Importer();
	$importer->run();
}

add_action( WMDS_CRON_FULL_HOOK, 'wmds_force_full_sync' );
function wmds_force_full_sync() {
	delete_option( WMDS_Importer::OPT_WATERMARK );
}

register_activation_hook( __FILE__, 'wmds_activate' );
function wmds_activate() {
	wmds_register_cpt();
	flush_rewrite_rules();

	if ( ! wp_next_scheduled( WMDS_CRON_HOOK ) ) {
		wp_schedule_event( time() + 60, WMDS_Settings::interval(), WMDS_CRON_HOOK );
	}
	if ( ! wp_next_scheduled( WMDS_CRON_FULL_HOOK ) ) {
		wp_schedule_event( time() + 300, 'daily', WMDS_CRON_FULL_HOOK );
	}
}

register_deactivation_hook( __FILE__, 'wmds_deactivate' );
function wmds_deactivate() {
	wp_clear_scheduled_hook( WMDS_CRON_HOOK );
	wp_clear_scheduled_hook( WMDS_CRON_FULL_HOOK );
	delete_transient( WMDS_Importer::LOCK );
	flush_rewrite_rules();
}
