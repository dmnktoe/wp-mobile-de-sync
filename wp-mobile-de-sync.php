<?php
/**
 * Plugin Name: mobile.de Sync
 * Plugin URI:  https://github.com/dmnktoe/wp-mobile-de-sync
 * Description: Synchronises a dealer's vehicle inventory from the mobile.de Search API into a "fahrzeuge" custom post type. Brings its own filters, templates, blocks and shortcodes, and works with existing theme templates.
 * Version:     2.5.1
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

define( 'WMDS_VERSION', '2.5.1' );
define( 'WMDS_FILE', __FILE__ );
define( 'WMDS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WMDS_URL', plugin_dir_url( __FILE__ ) );

define( 'WMDS_CPT', 'fahrzeuge' );

define( 'WMDS_CRON_HOOK', 'wmds_import_event' );
define( 'WMDS_CRON_FULL_HOOK', 'wmds_full_sync_event' );

require_once WMDS_DIR . 'includes/class-wmds-autoloader.php';
WMDS_Autoloader::register();

require_once WMDS_DIR . 'includes/class-wmds-logos.php';
require_once WMDS_DIR . 'includes/class-wmds-stickers.php';

WMDS_Plugin::boot( __FILE__ );
