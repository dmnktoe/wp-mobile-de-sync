<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Admin_Ajax {
	private static function verify_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'wp-mobile-de-sync' ) ), 403 );
		}
		check_ajax_referer( WMDS_Admin::NONCE, 'nonce' );
	}

	public static function ajax_test() {
		self::verify_ajax();

		$result = WMDS_Admin_Actions::test_result();

		wp_send_json_success( $result );
	}

	public static function ajax_sync() {
		self::verify_ajax();

		$step  = isset( $_POST['step'] ) ? max( 1, (int) $_POST['step'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_ajax() checks the nonce before this runs.
		$stats = WMDS_Admin_Actions::run_pass( 1 === $step );

		if ( is_wp_error( $stats ) ) {
			wp_send_json_error( array( 'message' => $stats->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'pending' => (int) $stats['pending'],
				'created' => (int) $stats['created'],
				'updated' => (int) $stats['updated'],
				'seen'    => (int) $stats['seen'],
				'summary' => WMDS_Status::summarise( $stats ),
				'done'    => 0 === (int) $stats['pending'],
			)
		);
	}

	public static function ajax_dismiss() {
		self::verify_ajax();

		$level = isset( $_POST['level'] ) ? sanitize_key( wp_unslash( $_POST['level'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_ajax() checks the nonce before this runs.
		update_user_meta( get_current_user_id(), WMDS_Admin_Notices::DISMISSED, $level );

		wp_send_json_success();
	}
}
