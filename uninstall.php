<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wmds_settings' );
delete_option( 'wmds_last_run' );
delete_option( 'wmds_watermark' );
delete_option( 'wmds_log' );

wp_clear_scheduled_hook( 'wmds_import_event' );
wp_clear_scheduled_hook( 'wmds_full_sync_event' );

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
