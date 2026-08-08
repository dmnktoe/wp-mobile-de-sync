<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Admin_Notices {
	const NOTICES   = 'wmds_notices_';
	const DISMISSED = 'wmds_dismissed';

	/**
	 * @var array<int, array{type:string,message:string}>
	 */
	private static $notices = array();

	/**
	 * @param string $type    One of success, error, warning or info.
	 * @param string $message Text shown to the user.
	 */
	public static function notice( $type, $message ) {
		self::$notices[] = array(
			'type'    => $type,
			'message' => $message,
		);
	}

	public static function stash() {
		if ( ! self::$notices ) {
			return;
		}

		set_transient( self::NOTICES . get_current_user_id(), self::$notices, 60 );
	}

	/** @return array<int, array{type:string,message:string}> */
	private static function take() {
		$key     = self::NOTICES . get_current_user_id();
		$stashed = get_transient( $key );
		$stashed = is_array( $stashed ) ? $stashed : array();

		if ( $stashed ) {
			delete_transient( $key );
		}

		return $stashed;
	}

	public static function render_notices() {
		foreach ( self::take() as $notice ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $notice['type'] ),
				wp_kses_post( $notice['message'] )
			);
		}
	}

	public static function global_notice() {
		if ( WMDS_Admin::on_page() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = WMDS_Status::get();
		if ( ! WMDS_Status::is_actionable( $status['level'] ) ) {
			return;
		}

		if ( (string) get_user_meta( get_current_user_id(), self::DISMISSED, true ) === $status['level'] ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible wmds-global-notice" data-wmds-level="%s"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_attr( 'error' === $status['severity'] ? 'error' : 'warning' ),
			esc_attr( $status['level'] ),
			esc_html__( 'mobile.de Sync:', 'wp-mobile-de-sync' ),
			esc_html( $status['explanation'] ),
			esc_url( WMDS_Status::settings_url( WMDS_Status::UNCONFIGURED === $status['level'] ? 'connection' : 'status' ) ),
			esc_html__( 'Open settings', 'wp-mobile-de-sync' )
		);
	}
}
