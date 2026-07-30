<?php
/**
 * Runs when the plugin is deleted from the WordPress admin.
 *
 * Removes only this plugin's own settings, schedule and caches.
 *
 * The vehicles themselves are left in place. They are site content, not
 * plugin data: posts with established URLs, images in the media library and
 * possibly inbound links. Switching plugins must not destroy that in
 * passing. Anyone who really wants the inventory gone deletes the post type's
 * content deliberately from the Vehicles screen first.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Settings and run state.
delete_option( 'wmds_settings' );
delete_option( 'wmds_last_run' );
delete_option( 'wmds_watermark' );
delete_option( 'wmds_log' );

// Schedule.
wp_clear_scheduled_hook( 'wmds_import_event' );
wp_clear_scheduled_hook( 'wmds_full_sync_event' );

// Run lock and cached reference data.
delete_transient( 'wmds_import_lock' );

global $wpdb;
foreach ( array( '_transient_wmds_refdata_', '_transient_timeout_wmds_refdata_' ) as $prefix ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $prefix ) . '%'
		)
	);
}
