<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Admin_Actions {
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'wp-mobile-de-sync' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( WMDS_Admin::NONCE );

		$action = isset( $_REQUEST['wmds_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['wmds_action'] ) ) : '';

		if ( 'export-log' === $action ) {
			self::export_log();
		}

		$handlers = array(
			'save'         => array( __CLASS__, 'do_save' ),
			'test'         => array( __CLASS__, 'do_test' ),
			'sync'         => array( __CLASS__, 'do_sync' ),
			'full'         => array( __CLASS__, 'do_full' ),
			'flush'        => array( __CLASS__, 'do_flush' ),
			'clear-log'    => array( __CLASS__, 'do_clear_log' ),
			'forget'       => array( __CLASS__, 'do_forget' ),
			'refresh'      => array( __CLASS__, 'do_refresh' ),
			'unlock'       => array( __CLASS__, 'do_unlock' ),
			'check-update' => array( __CLASS__, 'do_check_update' ),
			'test-alert'   => array( __CLASS__, 'do_test_alert' ),
		);

		if ( isset( $handlers[ $action ] ) ) {
			call_user_func( $handlers[ $action ] );
		}

		WMDS_Admin_Notices::stash();
		wp_safe_redirect( self::redirect_target( $action ) );
		exit;
	}

	/**
	 * @param string $action
	 * @return string
	 */
	private static function redirect_target( $action ) {
		if ( 'check-update' === $action ) {
			return self_admin_url( 'update-core.php?force-check=1' );
		}

		if ( 'refresh' === $action ) {
			$referer = wp_get_referer();
			if ( $referer ) {
				return $referer;
			}

			return WMDS_Status::list_url();
		}

		$tabs = array(
			'save'       => 'connection',
			'test'       => 'connection',
			'sync'       => 'status',
			'full'       => 'status',
			'clear-log'  => 'status',
			'test-alert' => 'status',
			'flush'      => 'tools',
			'unlock'     => 'tools',
			'forget'     => 'connection',
		);

		$tab = isset( $tabs[ $action ] ) ? $tabs[ $action ] : '';

		if ( 'save' === $action ) {
			$submitted = isset( $_REQUEST['wmds_tab'] ) ? sanitize_key( wp_unslash( $_REQUEST['wmds_tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified in handle() before this runs.
			if ( array_key_exists( $submitted, WMDS_Admin::tabs() ) ) {
				$tab = $submitted;
			}
		}

		return WMDS_Status::settings_url( $tab );
	}

	/**
	 * Saves the tab that was submitted, and only that one.
	 *
	 * Each tab is a form of its own and posts its own fields. Reading every
	 * setting out of one submission emptied the ones the other tabs own.
	 */
	private static function do_save() {
		$tab = isset( $_REQUEST['wmds_tab'] ) ? sanitize_key( wp_unslash( $_REQUEST['wmds_tab'] ) ) : 'connection'; // phpcs:ignore WordPress.Security.NonceVerification -- verified in handle() before this runs.

		if ( 'schedule' === $tab ) {
			self::save_schedule();
		} elseif ( 'enquiries' === $tab ) {
			self::save_enquiries();
		} elseif ( 'status' === $tab ) {
			self::save_alerts();
		} else {
			self::save_connection();
		}

		WMDS_Admin_Notices::notice( 'success', __( 'Settings saved.', 'wp-mobile-de-sync' ) );
	}

	private static function save_connection() {
		// phpcs:disable WordPress.Security.NonceVerification -- verified in handle() before this runs.
		$mode = isset( $_POST['wmds_seller_mode'] ) ? sanitize_key( wp_unslash( $_POST['wmds_seller_mode'] ) ) : 'id';

		$values = array(
			'username' => sanitize_text_field( wp_unslash( $_POST['wmds_username'] ?? '' ) ),
		);

		if ( 'dealer' === $mode ) {
			$values['seller_id'] = '';
			$values['dealer']    = sanitize_text_field( wp_unslash( $_POST['wmds_dealer'] ?? '' ) );
		} else {
			$values['seller_id'] = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['wmds_seller_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- preg_replace strips it to digits, which is stricter than any sanitiser.
			$values['dealer']    = '';
		}

		$password = (string) wp_unslash( $_POST['wmds_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a password is stored verbatim on purpose.
		if ( '' !== $password ) {
			$values['password'] = $password;
		}
		// phpcs:enable WordPress.Security.NonceVerification

		WMDS_Settings::update( $values );
	}

	private static function save_schedule() {
		// phpcs:disable WordPress.Security.NonceVerification -- verified in handle() before this runs.
		WMDS_Settings::update(
			array(
				'language' => sanitize_key( wp_unslash( $_POST['wmds_language'] ?? '' ) ),
				'interval' => sanitize_key( wp_unslash( $_POST['wmds_interval'] ?? 'wmds_15min' ) ),
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification

		self::resync_schedule();
	}

	private static function save_alerts() {
		// phpcs:disable WordPress.Security.NonceVerification -- verified in handle() before this runs.
		$was_enabled = WMDS_Alerts::enabled();

		WMDS_Settings::update(
			array(
				'alerts_enabled'   => isset( $_POST['wmds_alerts_enabled'] ) ? 'yes' : 'no',
				'alerts_weekly'    => isset( $_POST['wmds_alerts_weekly'] ) ? 'yes' : 'no',
				'alerts_recipient' => sanitize_text_field( wp_unslash( $_POST['wmds_alerts_recipient'] ?? '' ) ),
				'alerts_cooldown'  => absint( wp_unslash( $_POST['wmds_alerts_cooldown'] ?? 21600 ) ),
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! $was_enabled && WMDS_Alerts::enabled() ) {
			WMDS_Alerts::forget();
		}

		WMDS_Alerts::schedule();
	}

	private static function save_enquiries() {
		// phpcs:disable WordPress.Security.NonceVerification -- verified in handle() before this runs.
		WMDS_Settings::update(
			array(
				'enquiry_enabled'     => isset( $_POST['wmds_enquiry_enabled'] ) ? 'yes' : 'no',
				'enquiry_copy_seller' => isset( $_POST['wmds_enquiry_copy_seller'] ) ? 'yes' : 'no',
				'enquiry_autoreply'   => isset( $_POST['wmds_enquiry_autoreply'] ) ? 'yes' : 'no',
				'enquiry_store'       => isset( $_POST['wmds_enquiry_store'] ) ? 'yes' : 'no',
				'enquiry_recipient'   => sanitize_text_field( wp_unslash( $_POST['wmds_enquiry_recipient'] ?? '' ) ),
				'enquiry_consent'     => wp_kses_post( wp_unslash( $_POST['wmds_enquiry_consent'] ?? '' ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_kses_post is the sanitiser; a link is allowed here on purpose.
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification
	}

	private static function do_forget() {
		WMDS_Settings::update(
			array(
				'username'  => '',
				'password'  => '',
				'seller_id' => '',
				'dealer'    => '',
			)
		);

		WMDS_Admin_Notices::notice( 'success', __( 'Credentials deleted. Nothing is imported until new ones are stored.', 'wp-mobile-de-sync' ) );
	}

	private static function resync_schedule() {
		$current = wp_get_schedule( WMDS_CRON_HOOK );
		$wanted  = WMDS_Settings::interval();

		if ( $current === $wanted ) {
			return;
		}

		wp_clear_scheduled_hook( WMDS_CRON_HOOK );
		wp_schedule_event( time() + 60, $wanted, WMDS_CRON_HOOK );
	}

	private static function do_unlock() {
		WMDS_Importer::unlock();

		WMDS_Admin_Notices::notice( 'success', __( 'The import lock has been released. You can start a new run.', 'wp-mobile-de-sync' ) );
	}

	private static function do_test() {
		$result = self::test_result();

		WMDS_Admin_Notices::notice( $result['ok'] ? 'success' : 'error', $result['message'] );
	}

	/** @return array{ok:bool,message:string} */
	public static function test_result() {
		$result = WMDS_Settings::client()->search( 1, 1 );

		WMDS_Status::record_test( ! is_wp_error( $result ) );

		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: error message returned by the API. */
					__( 'Connection test failed: %s', 'wp-mobile-de-sync' ),
					$result->get_error_message()
				),
			);
		}

		$message = sprintf(
			/* translators: %d: number of vehicles in the inventory. */
			__( 'Connection OK – %d vehicles in the inventory.', 'wp-mobile-de-sync' ),
			$result['total']
		);

		if ( ! empty( $result['capped'] ) ) {
			$message .= ' ' . sprintf(
				/* translators: %d: maximum number of ads reachable through pagination. */
				__( 'Warning: across paginated pages the API hands out at most %d vehicles, so a reconciliation would be incomplete.', 'wp-mobile-de-sync' ),
				WMDS_Client::PAGINATION_CAP
			);
		}

		return array(
			'ok'      => true,
			'message' => $message,
		);
	}

	private static function do_sync() {
		$stats = self::run_pass( true );

		if ( is_wp_error( $stats ) ) {
			WMDS_Admin_Notices::notice(
				'error',
				sprintf(
					/* translators: %s: error message from the import run. */
					__( 'Run failed: %s', 'wp-mobile-de-sync' ),
					$stats->get_error_message()
				)
			);
			return;
		}

		$message = WMDS_Status::summarise( $stats );

		if ( $stats['pending'] > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of vehicles still pending. */
				__( '%d vehicles still pending – the rest continues automatically.', 'wp-mobile-de-sync' ),
				$stats['pending']
			);
		}

		WMDS_Admin_Notices::notice( 'success', $message );
	}

	/**
	 * @param bool $full
	 * @return array|WP_Error
	 */
	public static function run_pass( $full ) {
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled on many hosts, the failure is harmless.

		$importer = new WMDS_Importer();
		$stats    = $importer->run( array( 'full' => (bool) $full ) );

		WMDS_Posts::flush_makes();

		return $stats;
	}

	private static function do_full() {
		delete_option( WMDS_Importer::OPT_WATERMARK );

		WMDS_Admin_Notices::notice(
			'success',
			__( 'Watermark discarded. The next run reads the whole inventory again and is the first one allowed to remove sold vehicles.', 'wp-mobile-de-sync' )
		);
	}

	private static function do_flush() {
		WMDS_Refdata::flush();
		WMDS_Logos::flush();
		WMDS_Posts::flush_makes();

		WMDS_Admin_Notices::notice( 'success', __( 'Reference data discarded. It will be fetched again on the next run.', 'wp-mobile-de-sync' ) );
	}

	private static function do_check_update() {
		WMDS_Updater::flush();
		delete_site_transient( 'update_plugins' );
	}

	private static function do_test_alert() {
		if ( WMDS_Alerts::send_test() ) {
			WMDS_Admin_Notices::notice( 'success', __( 'A test alert has been sent. If it does not arrive, the problem is the site\'s mail, not the sync.', 'wp-mobile-de-sync' ) );

			return;
		}

		WMDS_Admin_Notices::notice( 'error', __( 'WordPress could not send the mail. Alerts will not arrive either until that is fixed — an SMTP plugin is the usual answer.', 'wp-mobile-de-sync' ) );
	}

	private static function do_clear_log() {
		delete_option( WMDS_Importer::OPT_LOG );

		WMDS_Admin_Notices::notice( 'success', __( 'Log cleared.', 'wp-mobile-de-sync' ) );
	}

	private static function do_refresh() {
		$post_id = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification -- verified in handle() before this runs.

		if ( ! $post_id || WMDS_CPT !== get_post_type( $post_id ) ) {
			WMDS_Admin_Notices::notice( 'error', __( 'That is not a vehicle.', 'wp-mobile-de-sync' ) );
			return;
		}

		$ad_id = (string) get_post_meta( $post_id, WMDS_Importer::META_AD_ID, true );
		if ( '' === $ad_id ) {
			WMDS_Admin_Notices::notice( 'error', __( 'This vehicle has no mobile.de ad ID and cannot be reloaded.', 'wp-mobile-de-sync' ) );
			return;
		}

		delete_post_meta( $post_id, WMDS_Importer::META_HASH );
		delete_post_meta( $post_id, WMDS_Importer::META_IMAGES );

		$importer = new WMDS_Importer();
		$result   = $importer->refresh( $ad_id );

		if ( is_wp_error( $result ) ) {
			WMDS_Admin_Notices::notice(
				'error',
				sprintf(
					/* translators: %s: error message from the API. */
					__( 'Reload failed: %s', 'wp-mobile-de-sync' ),
					$result->get_error_message()
				)
			);
			return;
		}

		WMDS_Posts::flush_makes();

		WMDS_Admin_Notices::notice(
			'success',
			sprintf(
				/* translators: %s: the vehicle title. */
				__( '“%s” was read again from mobile.de.', 'wp-mobile-de-sync' ),
				get_the_title( $post_id )
			)
		);
	}

	private static function export_log() {
		$log = get_option( WMDS_Importer::OPT_LOG, array() );
		$log = is_array( $log ) ? $log : array();

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mobile-de-sync-log.txt"' );

		foreach ( $log as $entry ) {
			echo esc_html( sprintf( "[%s] %s\n", $entry['time'], $entry['message'] ) );
		}

		exit;
	}
}
