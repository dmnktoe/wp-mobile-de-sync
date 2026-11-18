<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Admin_Actions {
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-mobile-de-sync' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( WMDS_Admin::NONCE );

		$action = isset( $_REQUEST['wmds_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['wmds_action'] ) ) : '';

		// A link carries the same nonce as every form. Only what is offered
		// as a link may arrive as one: a crafted address must not be able to
		// save an empty form or delete the credentials.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'POST' !== $method && ! in_array( $action, array( 'sync', 'refresh', 'check-update', 'export-log' ), true ) ) {
			wp_die( esc_html__( 'This action must be submitted from its form.', 'wp-mobile-de-sync' ), '', array( 'response' => 405 ) );
		}

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
			'test-mail'    => array( __CLASS__, 'do_test_mail' ),
			'clean-images' => array( __CLASS__, 'do_clean_images' ),
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
			'save'         => 'settings',
			'forget'       => 'settings',
			'test'         => 'tools',
			'sync'         => 'overview',
			'full'         => 'tools',
			'clear-log'    => 'tools',
			'flush'        => 'tools',
			'unlock'       => 'tools',
			'clean-images' => 'tools',
			'test-mail'    => 'enquiries',
		);

		$tab = isset( $tabs[ $action ] ) ? $tabs[ $action ] : '';

		// A form that names the tab it was submitted from is answered there.
		$submitted = isset( $_REQUEST['wmds_tab'] ) ? sanitize_key( wp_unslash( $_REQUEST['wmds_tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified in handle() before this runs.
		if ( array_key_exists( $submitted, WMDS_Admin::tabs() ) ) {
			$tab = $submitted;
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
		// phpcs:disable WordPress.Security.NonceVerification -- verified in handle() before this runs.
		$tab     = isset( $_POST['wmds_tab'] ) ? sanitize_key( wp_unslash( $_POST['wmds_tab'] ) ) : '';
		$forms   = WMDS_Admin::forms();
		$request = wp_unslash( $_POST );
		// phpcs:enable WordPress.Security.NonceVerification

		// A form that does not say which tab it is saves nothing. Guessing
		// read the fields of one tab out of a submission that had none and
		// emptied them.
		if ( ! isset( $forms[ $tab ] ) ) {
			WMDS_Admin_Notices::notice( 'error', __( 'Nothing was saved: the form did not specify which settings it contains.', 'wp-mobile-de-sync' ) );

			return;
		}

		$before = array();
		foreach ( $forms[ $tab ] as $key ) {
			$before[ $key ] = WMDS_Settings::get( $key );
		}

		$values = WMDS_Settings::from_request( $forms[ $tab ], is_array( $request ) ? $request : array() );

		// Either the seller ID or the dealer name, never both: the radio
		// button says which one the form means.
		if ( isset( $values['seller_id'] ) ) {
			$mode = isset( $request['wmds_seller_mode'] ) ? sanitize_key( (string) $request['wmds_seller_mode'] ) : 'id';

			if ( 'dealer' === $mode ) {
				$values['seller_id'] = '';
			} else {
				$values['dealer'] = '';
			}
		}

		WMDS_Settings::update( $values );

		self::after_save( $before );

		WMDS_Admin_Notices::notice( 'success', __( 'Settings saved.', 'wp-mobile-de-sync' ) );
	}

	/**
	 * What a changed setting sets in motion.
	 *
	 * @param array $before The values of the submitted settings before saving.
	 */
	private static function after_save( array $before ) {
		$changed = array();
		foreach ( $before as $key => $value ) {
			if ( (string) WMDS_Settings::get( $key ) !== (string) $value ) {
				$changed[] = $key;
			}
		}

		// A test passed for the credentials that were there before.
		if ( array_intersect( array( 'username', 'password', 'seller_id', 'dealer' ), $changed ) ) {
			delete_option( WMDS_Status::OPT_LAST_TEST );
		}

		if ( in_array( 'interval', $changed, true ) ) {
			WMDS_Plugin::resync_schedule();
		}

		// The post type is registered with the old slug for the rest of this
		// request. Dropping the stored rules makes the next request build
		// them from the new one.
		if ( in_array( 'slug', $changed, true ) ) {
			delete_option( 'rewrite_rules' );
		}

		if ( array_key_exists( 'alerts_enabled', $before ) ) {
			// Switched on again: the last problem reported is not news to
			// anybody any more, and the next one should not wait for a
			// cooldown that started back then.
			if ( in_array( 'alerts_enabled', $changed, true ) && WMDS_Alerts::enabled() ) {
				WMDS_Alerts::forget();
			}

			WMDS_Alerts::schedule();
		}
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

		delete_option( WMDS_Status::OPT_LAST_TEST );

		WMDS_Admin_Notices::notice( 'success', __( 'Credentials deleted. No vehicles will be imported until new credentials are saved.', 'wp-mobile-de-sync' ) );
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
				__( 'Note: the API returns at most %d vehicles across paginated requests, so a reconciliation would be incomplete.', 'wp-mobile-de-sync' ),
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
				__( '%d vehicles pending. Processing will continue automatically.', 'wp-mobile-de-sync' ),
				$stats['pending']
			);
		}

		WMDS_Admin_Notices::notice( 'success', $message );
	}

	/**
	 * @param bool $full       Start over with the whole inventory.
	 * @param bool $reschedule False when the caller asks for the next pass
	 *                         itself, as the progress bar does.
	 * @return array|WP_Error
	 */
	public static function run_pass( $full, $reschedule = true ) {
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled on many hosts, the failure is harmless.

		$importer = new WMDS_Importer();
		$stats    = $importer->run(
			array(
				'full'       => (bool) $full,
				'reschedule' => (bool) $reschedule,
			)
		);

		WMDS_Posts::flush_makes();

		return $stats;
	}

	private static function do_full() {
		delete_option( WMDS_Importer::OPT_WATERMARK );

		WMDS_Admin_Notices::notice(
			'success',
			__( 'Change marker reset. The next run reads the entire inventory and removes sold vehicles.', 'wp-mobile-de-sync' )
		);
	}

	private static function do_flush() {
		WMDS_Refdata::flush();
		WMDS_Logos::flush();
		WMDS_Posts::flush_makes();

		WMDS_Admin_Notices::notice( 'success', __( 'Reference data reset. It will be retrieved again on the next run.', 'wp-mobile-de-sync' ) );
	}

	private static function do_check_update() {
		WMDS_Updater::flush();
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * One test mail for every address the plugin writes to.
	 *
	 * Enquiries and alerts keep addresses of their own, and one button covers
	 * both: whether mail leaves this site is a property of the site, not of
	 * the kind of message.
	 */
	private static function do_test_mail() {
		$to   = WMDS_Mail::test_recipients();
		$site = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
		$list = implode( ', ', $to );

		$sent = WMDS_Mail::send(
			$to,
			/* translators: %s: site name. */
			sprintf( __( '[%s] Test mail', 'wp-mobile-de-sync' ), $site ),
			__( 'This is a test mail from the mobile.de Sync plugin. It was sent the same way as enquiries and sync alerts.', 'wp-mobile-de-sync' )
		);

		if ( $sent ) {
			/* translators: %s: the addresses the test mail was sent to. */
			WMDS_Admin_Notices::notice( 'success', sprintf( __( 'The test mail to %s has been handed over for delivery. If it does not arrive within a few minutes, please check the spam folder and the mail settings of the site.', 'wp-mobile-de-sync' ), $list ) );

			return;
		}

		/* translators: %s: the addresses the test mail was meant for. */
		WMDS_Admin_Notices::notice( 'error', sprintf( __( 'WordPress could not send the test mail to %s.', 'wp-mobile-de-sync' ), $list ) );
	}

	private static function do_clean_images() {
		$deleted = WMDS_Images::delete_orphans();

		WMDS_Admin_Notices::notice(
			'success',
			sprintf(
				/* translators: %d: number of pictures deleted. */
				_n( '%d unused vehicle picture deleted.', '%d unused vehicle pictures deleted.', $deleted, 'wp-mobile-de-sync' ),
				$deleted
			)
		);
	}

	private static function do_clear_log() {
		WMDS_Log::clear();

		WMDS_Admin_Notices::notice( 'success', __( 'Log cleared.', 'wp-mobile-de-sync' ) );
	}

	private static function do_refresh() {
		$post_id = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification -- verified in handle() before this runs.

		if ( ! $post_id || WMDS_CPT !== get_post_type( $post_id ) ) {
			WMDS_Admin_Notices::notice( 'error', __( 'This entry is not a vehicle.', 'wp-mobile-de-sync' ) );
			return;
		}

		$ad_id = (string) get_post_meta( $post_id, WMDS_Importer::META_AD_ID, true );
		if ( '' === $ad_id ) {
			WMDS_Admin_Notices::notice( 'error', __( 'This vehicle has no mobile.de ad ID and cannot be reloaded.', 'wp-mobile-de-sync' ) );
			return;
		}

		delete_post_meta( $post_id, WMDS_Importer::META_HASH );
		delete_post_meta( $post_id, WMDS_Images::META_LIST );

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
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mobile-de-sync-log.txt"' );

		foreach ( WMDS_Log::entries() as $entry ) {
			// Plain text in a download: escaping it for HTML would put &amp;
			// and &#039; into the file.
			echo WMDS_Log::line( $entry ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain, sent as an attachment.
		}

		exit;
	}
}
