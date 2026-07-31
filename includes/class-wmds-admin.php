<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Admin {
	const PAGE  = WMDS_Status::PAGE;
	const NONCE = WMDS_Status::NONCE;

	const NOTICES   = 'wmds_notices_';
	const DISMISSED = 'wmds_dismissed';

	/** @var array<int, array{type:string,message:string}> */
	private static $notices = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_wmds', array( __CLASS__, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'global_notice' ) );

		add_action( 'wp_ajax_wmds_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_wmds_sync', array( __CLASS__, 'ajax_sync' ) );
		add_action( 'wp_ajax_wmds_dismiss', array( __CLASS__, 'ajax_dismiss' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( WMDS_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . WMDS_CPT,
			'mobile.de Sync',
			__( 'Sync Settings', 'wp-mobile-de-sync' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * @param array $links
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( WMDS_Status::settings_url() ),
				esc_html__( 'Settings', 'wp-mobile-de-sync' )
			)
		);

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( WMDS_Status::action_url( 'check-update' ) ),
			esc_html__( 'Check for updates', 'wp-mobile-de-sync' )
		);

		return $links;
	}

	/** @return bool Whether the current screen is our settings page. */
	private static function on_page() {
		return isset( $_GET['page'] ) && self::PAGE === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a screen check, not a state change.
	}

	public static function assets() {
		wp_enqueue_style( 'wmds-admin', WMDS_URL . 'assets/admin.css', array(), WMDS_VERSION );
		wp_enqueue_script( 'wmds-admin', WMDS_URL . 'assets/admin.js', array(), WMDS_VERSION, true );
		wp_localize_script(
			'wmds-admin',
			'wmdsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'i18n'    => array(
					'testing'  => __( 'Testing the connection…', 'wp-mobile-de-sync' ),
					'starting' => __( 'Starting…', 'wp-mobile-de-sync' ),
					/* translators: 1: vehicles processed so far, 2: total to process. */
					'progress' => __( '%1$d of %2$d vehicles processed', 'wp-mobile-de-sync' ),
					'done'     => __( 'Sync finished.', 'wp-mobile-de-sync' ),
					'failed'   => __( 'The request failed. The browser could not reach WordPress.', 'wp-mobile-de-sync' ),
				),
			)
		);
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'wp-mobile-de-sync' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE );

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
		);

		if ( isset( $handlers[ $action ] ) ) {
			call_user_func( $handlers[ $action ] );
		}

		self::stash();
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
			'save'      => 'connection',
			'test'      => 'connection',
			'sync'      => 'status',
			'full'      => 'status',
			'clear-log' => 'status',
			'flush'     => 'tools',
			'unlock'    => 'tools',
			'forget'    => 'connection',
		);

		$tab = isset( $tabs[ $action ] ) ? $tabs[ $action ] : '';

		if ( 'save' === $action ) {
			$submitted = isset( $_REQUEST['wmds_tab'] ) ? sanitize_key( wp_unslash( $_REQUEST['wmds_tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified in handle() before this runs.
			if ( array_key_exists( $submitted, self::tabs() ) ) {
				$tab = $submitted;
			}
		}

		return WMDS_Status::settings_url( $tab );
	}

	private static function do_save() {
		// phpcs:disable WordPress.Security.NonceVerification -- verified in handle() before this runs.
		$mode = isset( $_POST['wmds_seller_mode'] ) ? sanitize_key( wp_unslash( $_POST['wmds_seller_mode'] ) ) : 'id';

		$values = array(
			'username' => sanitize_text_field( wp_unslash( $_POST['wmds_username'] ?? '' ) ),
			'language' => sanitize_key( wp_unslash( $_POST['wmds_language'] ?? '' ) ),
			'interval' => sanitize_key( wp_unslash( $_POST['wmds_interval'] ?? 'wmds_15min' ) ),
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
		self::resync_schedule();

		self::notice( 'success', __( 'Settings saved.', 'wp-mobile-de-sync' ) );
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

		self::notice( 'success', __( 'Credentials deleted. Nothing is imported until new ones are stored.', 'wp-mobile-de-sync' ) );
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

		self::notice( 'success', __( 'The import lock has been released. You can start a new run.', 'wp-mobile-de-sync' ) );
	}

	private static function do_test() {
		$result = self::test_result();

		self::notice( $result['ok'] ? 'success' : 'error', $result['message'] );
	}

	/** @return array{ok:bool,message:string} */
	private static function test_result() {
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
			self::notice(
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

		self::notice( 'success', $message );
	}

	/**
	 * @param bool $full
	 * @return array|WP_Error
	 */
	private static function run_pass( $full ) {
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled on many hosts, the failure is harmless.

		$importer = new WMDS_Importer();
		$stats    = $importer->run( array( 'full' => (bool) $full ) );

		WMDS_Posts::flush_makes();

		return $stats;
	}

	private static function do_full() {
		delete_option( WMDS_Importer::OPT_WATERMARK );

		self::notice(
			'success',
			__( 'Watermark discarded. The next run reads the whole inventory again and is the first one allowed to remove sold vehicles.', 'wp-mobile-de-sync' )
		);
	}

	private static function do_flush() {
		WMDS_Refdata::flush();
		WMDS_Logos::flush();
		WMDS_Posts::flush_makes();

		self::notice( 'success', __( 'Reference data discarded. It will be fetched again on the next run.', 'wp-mobile-de-sync' ) );
	}

	private static function do_check_update() {
		WMDS_Updater::flush();
		delete_site_transient( 'update_plugins' );
	}

	private static function do_clear_log() {
		delete_option( WMDS_Importer::OPT_LOG );

		self::notice( 'success', __( 'Log cleared.', 'wp-mobile-de-sync' ) );
	}

	private static function do_refresh() {
		$post_id = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification -- verified in handle() before this runs.

		if ( ! $post_id || WMDS_CPT !== get_post_type( $post_id ) ) {
			self::notice( 'error', __( 'That is not a vehicle.', 'wp-mobile-de-sync' ) );
			return;
		}

		$ad_id = (string) get_post_meta( $post_id, WMDS_Importer::META_AD_ID, true );
		if ( '' === $ad_id ) {
			self::notice( 'error', __( 'This vehicle has no mobile.de ad ID and cannot be reloaded.', 'wp-mobile-de-sync' ) );
			return;
		}

		delete_post_meta( $post_id, WMDS_Importer::META_HASH );
		delete_post_meta( $post_id, WMDS_Importer::META_IMAGES );

		$importer = new WMDS_Importer();
		$result   = $importer->refresh( $ad_id );

		if ( is_wp_error( $result ) ) {
			self::notice(
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

		self::notice(
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

	private static function verify_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'wp-mobile-de-sync' ) ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
	}

	public static function ajax_test() {
		self::verify_ajax();

		$result = self::test_result();

		wp_send_json_success( $result );
	}

	public static function ajax_sync() {
		self::verify_ajax();

		$step  = isset( $_POST['step'] ) ? max( 1, (int) $_POST['step'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_ajax() checks the nonce before this runs.
		$stats = self::run_pass( 1 === $step );

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
		update_user_meta( get_current_user_id(), self::DISMISSED, $level );

		wp_send_json_success();
	}

	/**
	 * @param string $type    One of success, error, warning or info.
	 * @param string $message Text shown to the user.
	 */
	private static function notice( $type, $message ) {
		self::$notices[] = array(
			'type'    => $type,
			'message' => $message,
		);
	}

	private static function stash() {
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

	private static function render_notices() {
		foreach ( self::take() as $notice ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $notice['type'] ),
				wp_kses_post( $notice['message'] )
			);
		}
	}

	public static function global_notice() {
		if ( self::on_page() || ! current_user_can( 'manage_options' ) ) {
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

	/** @return array<string,string> */
	private static function tabs() {
		return array(
			'connection' => __( 'Connection', 'wp-mobile-de-sync' ),
			'schedule'   => __( 'Schedule', 'wp-mobile-de-sync' ),
			'status'     => __( 'Status & log', 'wp-mobile-de-sync' ),
			'tools'      => __( 'Tools', 'wp-mobile-de-sync' ),
		);
	}

	/** @return string */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.

		return array_key_exists( $tab, self::tabs() ) ? $tab : 'connection';
	}

	public static function render() {
		$status = WMDS_Status::get();
		$tab    = self::current_tab();
		?>
		<div class="wrap wmds-wrap">
			<h1><?php echo esc_html__( 'mobile.de Sync', 'wp-mobile-de-sync' ); ?></h1>

			<?php self::render_notices(); ?>
			<?php self::render_status_card( $status ); ?>
			<?php self::render_checklist( $status ); ?>

			<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Settings sections', 'wp-mobile-de-sync' ); ?>">
				<?php foreach ( self::tabs() as $key => $label ) : ?>
					<a class="nav-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( WMDS_Status::settings_url( $key ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="wmds-tab-body">
				<?php
				if ( 'schedule' === $tab ) {
					self::tab_schedule( $status );
				} elseif ( 'status' === $tab ) {
					self::tab_status( $status );
				} elseif ( 'tools' === $tab ) {
					self::tab_tools( $status );
				} else {
					self::tab_connection();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param int    $timestamp
	 * @param string $mode  'absolute' for the date, 'relative' for "5 mins ago".
	 * @param string $title Tooltip, when the other reading is not the useful one.
	 */
	private static function print_time( $timestamp, $mode = 'absolute', $title = '' ) {
		$timestamp = (int) $timestamp;
		if ( ! $timestamp ) {
			echo '—';
			return;
		}

		$absolute = WMDS_Status::local_time( $timestamp );
		$relative = WMDS_Status::relative_time( $timestamp );

		if ( '' === $title ) {
			$title = ( 'relative' === $mode ) ? $absolute : $relative;
		}

		printf(
			'<time datetime="%s" title="%s">%s</time>',
			esc_attr( WMDS_Status::iso_time( $timestamp ) ),
			esc_attr( $title ),
			esc_html( 'relative' === $mode ? $relative : $absolute )
		);
	}

	/**
	 * @param array $status
	 */
	private static function render_status_card( array $status ) {
		?>
		<div class="wmds-card wmds-status-card wmds-sev-bg-<?php echo esc_attr( $status['severity'] ); ?>">
			<div class="wmds-status-head">
				<span class="wmds-bar-dot wmds-sev-<?php echo esc_attr( $status['severity'] ); ?>" aria-hidden="true"></span>
				<h2><?php echo esc_html( $status['label'] ); ?></h2>
			</div>
			<p class="wmds-status-explanation"><?php echo esc_html( $status['explanation'] ); ?></p>

			<ul class="wmds-metrics">
				<li>
					<span class="wmds-metric-value"><?php echo esc_html( number_format_i18n( $status['count'] ) ); ?></span>
					<span class="wmds-metric-label"><?php esc_html_e( 'Vehicles published', 'wp-mobile-de-sync' ); ?></span>
				</li>
				<li>
					<span class="wmds-metric-value">
						<?php self::print_time( $status['last_ts'], 'relative' ); ?>
					</span>
					<span class="wmds-metric-label"><?php esc_html_e( 'Last run', 'wp-mobile-de-sync' ); ?></span>
					<?php if ( $status['last_ts'] ) : ?>
						<span class="wmds-metric-note"><?php echo esc_html( WMDS_Status::local_time( $status['last_ts'] ) ); ?></span>
					<?php endif; ?>
				</li>
				<li>
					<span class="wmds-metric-value">
						<?php
						echo esc_html(
							$status['next']
								? WMDS_Status::relative_time( $status['next'] )
								: __( 'not scheduled', 'wp-mobile-de-sync' )
						);
						?>
					</span>
					<span class="wmds-metric-label"><?php esc_html_e( 'Next run', 'wp-mobile-de-sync' ); ?></span>
					<?php if ( $status['next'] ) : ?>
						<span class="wmds-metric-note"><?php echo esc_html( WMDS_Status::local_time( $status['next'] ) ); ?></span>
					<?php endif; ?>
				</li>
				<li>
					<span class="wmds-metric-value">
						<?php
						echo esc_html(
							$status['incremental']
								? __( 'incremental', 'wp-mobile-de-sync' )
								: __( 'full', 'wp-mobile-de-sync' )
						);
						?>
					</span>
					<span class="wmds-metric-label"><?php esc_html_e( 'Next run type', 'wp-mobile-de-sync' ); ?></span>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * @param array $status
	 */
	private static function render_checklist( array $status ) {
		if ( WMDS_Status::OK === $status['level'] && $status['count'] > 0 ) {
			return;
		}

		$steps = array(
			array(
				'done'  => $status['configured'],
				'label' => __( 'Store the mobile.de credentials', 'wp-mobile-de-sync' ),
				'url'   => WMDS_Status::settings_url( 'connection' ),
				'cta'   => __( 'Enter now', 'wp-mobile-de-sync' ),
			),
			array(
				'done'  => ! empty( $status['tested'] ),
				'label' => __( 'Test the connection', 'wp-mobile-de-sync' ),
				'url'   => WMDS_Status::settings_url( 'tools' ),
				'cta'   => __( 'Run the test', 'wp-mobile-de-sync' ),
			),
			array(
				'done'  => $status['count'] > 0,
				'label' => __( 'Import the inventory once', 'wp-mobile-de-sync' ),
				'url'   => WMDS_Status::settings_url( 'tools' ),
				'cta'   => __( 'Start the import', 'wp-mobile-de-sync' ),
			),
			array(
				'done'  => $status['count'] > 0 && $status['next'],
				'label' => __( 'Confirm the schedule is running', 'wp-mobile-de-sync' ),
				'url'   => WMDS_Status::settings_url( 'schedule' ),
				'cta'   => __( 'Check', 'wp-mobile-de-sync' ),
			),
		);
		?>
		<div class="wmds-card wmds-checklist-card">
			<h2><?php esc_html_e( 'Getting started', 'wp-mobile-de-sync' ); ?></h2>
			<ol class="wmds-checklist">
				<?php foreach ( $steps as $step ) : ?>
					<li class="<?php echo $step['done'] ? 'wmds-step-done' : 'wmds-step-open'; ?>">
						<span class="dashicons <?php echo $step['done'] ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span>
						<span class="wmds-step-label"><?php echo esc_html( $step['label'] ); ?></span>
						<?php if ( ! $step['done'] ) : ?>
							<a href="<?php echo esc_url( $step['url'] ); ?>"><?php echo esc_html( $step['cta'] ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}

	/**
	 * @param string $tab Tab to return to after saving.
	 */
	private static function form_open( $tab ) {
		printf( '<form method="post" action="%s" class="wmds-form">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="action" value="wmds">';
		echo '<input type="hidden" name="wmds_action" value="save">';
		printf( '<input type="hidden" name="wmds_tab" value="%s">', esc_attr( $tab ) );
	}

	private static function tab_connection() {
		$mode = ( '' === WMDS_Settings::seller_id() && '' !== WMDS_Settings::dealer() ) ? 'dealer' : 'id';

		self::form_open( 'connection' );
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'API credentials', 'wp-mobile-de-sync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wmds_username"><?php esc_html_e( 'Username', 'wp-mobile-de-sync' ); ?> <span class="wmds-required">*</span></label>
					</th>
					<td>
						<input name="wmds_username" id="wmds_username" type="text" class="regular-text" required
							value="<?php echo esc_attr( WMDS_Settings::username() ); ?>">
						<p class="description"><?php esc_html_e( 'Your mobile.de API username.', 'wp-mobile-de-sync' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wmds_password"><?php esc_html_e( 'Password', 'wp-mobile-de-sync' ); ?> <span class="wmds-required">*</span></label>
					</th>
					<td>
						<span class="wmds-password">
							<input name="wmds_password" id="wmds_password" type="password" class="regular-text"
								autocomplete="new-password"
								placeholder="<?php echo WMDS_Settings::password() ? esc_attr__( '········ (stored)', 'wp-mobile-de-sync' ) : ''; ?>">
							<button type="button" class="button button-secondary wmds-toggle-password"
								data-target="wmds_password"
								aria-label="<?php esc_attr_e( 'Show password', 'wp-mobile-de-sync' ); ?>">
								<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
							</button>
						</span>
						<p class="description">
							<?php esc_html_e( 'Stored unencrypted in the WordPress database, as is usual for options. Leave empty to keep the stored password.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'Which inventory', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Pick one route. Saving clears the other one, so what this form shows is what the plugin actually sends.', 'wp-mobile-de-sync' ); ?>
			</p>

			<fieldset class="wmds-seller-modes">
				<label class="wmds-mode">
					<input type="radio" name="wmds_seller_mode" value="id" <?php checked( $mode, 'id' ); ?>>
					<span><strong><?php esc_html_e( 'Seller ID', 'wp-mobile-de-sync' ); ?></strong>
						<em><?php esc_html_e( 'recommended', 'wp-mobile-de-sync' ); ?></em></span>
				</label>
				<div class="wmds-mode-body" data-wmds-mode="id">
					<input name="wmds_seller_id" id="wmds_seller_id" type="text" class="regular-text"
						inputmode="numeric" placeholder="<?php esc_attr_e( 'e.g. 12345678', 'wp-mobile-de-sync' ); ?>"
						value="<?php echo esc_attr( WMDS_Settings::seller_id() ); ?>">
					<p class="description">
						<?php esc_html_e( 'The dealer\'s numeric identifier and the only route mobile.de documents for addressing an inventory.', 'wp-mobile-de-sync' ); ?>
					</p>
				</div>

				<label class="wmds-mode">
					<input type="radio" name="wmds_seller_mode" value="dealer" <?php checked( $mode, 'dealer' ); ?>>
					<span><strong><?php esc_html_e( 'Dealer name (vanity)', 'wp-mobile-de-sync' ); ?></strong>
						<em><?php esc_html_e( 'undocumented', 'wp-mobile-de-sync' ); ?></em></span>
				</label>
				<div class="wmds-mode-body" data-wmds-mode="dealer">
					<input name="wmds_dealer" id="wmds_dealer" type="text" class="regular-text"
						placeholder="<?php esc_attr_e( 'e.g. EXAMPLEDEALER', 'wp-mobile-de-sync' ); ?>"
						value="<?php echo esc_attr( WMDS_Settings::dealer() ); ?>">
					<p class="description">
						<?php
						printf(
							/* translators: %s: the URL pattern home.mobile.de/NAME, already marked up. */
							esc_html__( 'The name from %s. This parameter is undocumented and may disappear at any time.', 'wp-mobile-de-sync' ),
							'<code>home.mobile.de/<strong>NAME</strong></code>'
						);
						?>
					</p>
				</div>
			</fieldset>
		</div>

		<?php submit_button( __( 'Save', 'wp-mobile-de-sync' ) ); ?>
		</form>

		<?php if ( WMDS_Settings::is_configured() ) : ?>
			<div class="wmds-card wmds-danger-card">
				<h2><?php esc_html_e( 'Delete credentials', 'wp-mobile-de-sync' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Removes username, password and the addressing from the database. Already imported vehicles stay untouched.', 'wp-mobile-de-sync' ); ?>
				</p>
				<?php
				self::action_button(
					'forget',
					__( 'Delete credentials', 'wp-mobile-de-sync' ),
					'delete',
					__( 'Delete the stored credentials?', 'wp-mobile-de-sync' )
				);
				?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * @param array $status
	 */
	private static function tab_schedule( array $status ) {
		self::form_open( 'schedule' );
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Interval', 'wp-mobile-de-sync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wmds_interval"><?php esc_html_e( 'Run every', 'wp-mobile-de-sync' ); ?></label></th>
					<td>
						<select name="wmds_interval" id="wmds_interval">
							<?php
							$intervals = array(
								'wmds_5min'  => __( 'Every 5 minutes', 'wp-mobile-de-sync' ),
								'wmds_15min' => __( 'Every 15 minutes (recommended)', 'wp-mobile-de-sync' ),
								'wmds_30min' => __( 'Every 30 minutes', 'wp-mobile-de-sync' ),
								'wmds_60min' => __( 'Hourly', 'wp-mobile-de-sync' ),
								'daily'      => __( 'Once a day', 'wp-mobile-de-sync' ),
							);
							foreach ( $intervals as $key => $label ) {
								printf(
									'<option value="%s"%s>%s</option>',
									esc_attr( $key ),
									selected( WMDS_Settings::interval(), $key, false ),
									esc_html( $label )
								);
							}
							?>
						</select>
						<p class="description">
							<?php esc_html_e( 'A run fetches only what changed and is therefore cheap. Once a day a full reconciliation runs in addition – only after one of those are sold vehicles moved to the trash.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wmds_language"><?php esc_html_e( 'Label language', 'wp-mobile-de-sync' ); ?></label></th>
					<td>
						<select name="wmds_language" id="wmds_language">
							<option value="" <?php selected( '', (string) WMDS_Settings::get( 'language' ) ); ?>>
								<?php esc_html_e( 'Site language', 'wp-mobile-de-sync' ); ?>
							</option>
							<?php foreach ( self::languages() as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( (string) WMDS_Settings::get( 'language' ), $code ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Language of the reference-data labels (transmission, fuel, colours …). These are resolved at import time, so a change takes effect after the next full sync.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save', 'wp-mobile-de-sync' ) ); ?>
		</div>
		</form>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'How WordPress triggers this', 'wp-mobile-de-sync' ); ?></h2>
			<?php self::render_cron_explainer( $status ); ?>
		</div>
		<?php
	}

	/** @return array<string,string> */
	private static function languages() {
		return array(
			'de' => __( 'German', 'wp-mobile-de-sync' ),
			'en' => __( 'English', 'wp-mobile-de-sync' ),
			'fr' => __( 'French', 'wp-mobile-de-sync' ),
			'it' => __( 'Italian', 'wp-mobile-de-sync' ),
			'es' => __( 'Spanish', 'wp-mobile-de-sync' ),
			'nl' => __( 'Dutch', 'wp-mobile-de-sync' ),
			'pl' => __( 'Polish', 'wp-mobile-de-sync' ),
		);
	}

	/**
	 * @param array $status
	 */
	private static function render_cron_explainer( array $status ) {
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

		echo '<p>';
		if ( $disabled ) {
			echo wp_kses_post(
				sprintf(
					/* translators: 1: DISABLE_WP_CRON, 2: wp cron event run --due-now, 3: wp-cron.php - all marked up as code. */
					__( '<strong>WP-Cron is disabled</strong> (%1$s). That is the recommended setting – provided a system cron calls %2$s or %3$s regularly. Without that, no sync happens at all.', 'wp-mobile-de-sync' ),
					'<code>DISABLE_WP_CRON</code>',
					'<code>wp cron event run --due-now</code>',
					'<code>wp-cron.php</code>'
				)
			);
		} else {
			echo wp_kses_post(
				sprintf(
					/* translators: %s: the DISABLE_WP_CRON define, already marked up as code. */
					__( '<strong>WP-Cron is triggered by page views, not by a clock.</strong> With little traffic on the site, runs are delayed accordingly. More reliable is %s plus a cron job at your host.', 'wp-mobile-de-sync' ),
					'<code>define( \'DISABLE_WP_CRON\', true );</code>'
				)
			);
		}
		echo '</p>';

		printf(
			'<p>%s <strong>%s</strong></p>',
			esc_html__( 'Next scheduled run:', 'wp-mobile-de-sync' ),
			esc_html( $status['next'] ? WMDS_Status::local_time( $status['next'] ) : __( 'not scheduled', 'wp-mobile-de-sync' ) )
		);

		echo '<pre class="wmds-code">*/15 * * * * cd /path/to/site &amp;&amp; wp cron event run --due-now</pre>';
	}

	/**
	 * @param array $status
	 */
	private static function tab_status( array $status ) {
		$log = get_option( WMDS_Importer::OPT_LOG, array() );
		$log = is_array( $log ) ? $log : array();
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Last run', 'wp-mobile-de-sync' ); ?></h2>
			<?php if ( ! $status['last'] ) : ?>
				<p><em><?php esc_html_e( 'No run has completed yet.', 'wp-mobile-de-sync' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped wmds-table">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Time', 'wp-mobile-de-sync' ); ?></td>
							<td><?php self::print_time( $status['last_ts'] ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Type', 'wp-mobile-de-sync' ); ?></td>
							<td>
								<?php
								echo esc_html(
									! empty( $status['last']['full'] )
										? __( 'full reconciliation', 'wp-mobile-de-sync' )
										: __( 'incremental', 'wp-mobile-de-sync' )
								);
								?>
							</td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Result', 'wp-mobile-de-sync' ); ?></td>
							<td><?php echo esc_html( WMDS_Status::summarise( $status['last'] ) ); ?></td>
						</tr>
						<?php if ( ! empty( $status['last']['failed'] ) ) : ?>
							<tr class="wmds-row-warning">
								<td><?php esc_html_e( 'Failed', 'wp-mobile-de-sync' ); ?></td>
								<td>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: number of vehicles that could not be processed. */
											__( '%d vehicles could not be processed', 'wp-mobile-de-sync' ),
											(int) $status['last']['failed']
										)
									);
									?>
								</td>
							</tr>
						<?php endif; ?>
						<?php if ( $status['pending'] > 0 ) : ?>
							<tr>
								<td><?php esc_html_e( 'Still queued', 'wp-mobile-de-sync' ); ?></td>
								<td>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: number of vehicles still pending. */
											__( '%d vehicles', 'wp-mobile-de-sync' ),
											$status['pending']
										)
									);
									?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="wmds-card">
			<div class="wmds-card-head">
				<h2><?php esc_html_e( 'Log', 'wp-mobile-de-sync' ); ?></h2>
				<?php if ( $log ) : ?>
					<div class="wmds-card-tools">
						<label class="wmds-log-filter">
							<input type="checkbox" id="wmds-log-errors-only">
							<?php esc_html_e( 'Problems only', 'wp-mobile-de-sync' ); ?>
						</label>
						<a class="button button-secondary" href="<?php echo esc_url( WMDS_Status::action_url( 'export-log' ) ); ?>">
							<?php esc_html_e( 'Download', 'wp-mobile-de-sync' ); ?>
						</a>
						<?php
						self::action_button(
							'clear-log',
							__( 'Clear', 'wp-mobile-de-sync' ),
							'secondary',
							__( 'Clear the log?', 'wp-mobile-de-sync' )
						);
						?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! $log ) : ?>
				<p><em><?php esc_html_e( 'The log is empty.', 'wp-mobile-de-sync' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped wmds-log">
					<thead>
						<tr>
							<th class="wmds-log-time"><?php esc_html_e( 'Time', 'wp-mobile-de-sync' ); ?></th>
							<th><?php esc_html_e( 'Message', 'wp-mobile-de-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( array_slice( $log, 0, 50 ) as $entry ) : ?>
						<tr class="<?php echo self::is_problem( $entry['message'] ) ? 'wmds-log-problem' : ''; ?>">
							<td class="wmds-log-time">
								<?php self::print_time( WMDS_Status::timestamp( $entry['time'] ), 'absolute', $entry['time'] ); ?>
							</td>
							<td><?php echo esc_html( $entry['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param string $message
	 * @return bool
	 */
	private static function is_problem( $message ) {
		foreach ( array( 'Aborted', 'Warning', 'failed', 'could not' ) as $needle ) {
			if ( false !== stripos( $message, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array $status
	 */
	private static function tab_tools( array $status ) {
		if ( ! $status['configured'] ) {
			printf(
				'<div class="wmds-card"><p><em>%s</em> <a href="%s">%s</a></p></div>',
				esc_html__( 'Store the credentials first.', 'wp-mobile-de-sync' ),
				esc_url( WMDS_Status::settings_url( 'connection' ) ),
				esc_html__( 'Go to the connection settings', 'wp-mobile-de-sync' )
			);
			return;
		}
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Connection', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Asks mobile.de for a single vehicle. Writes nothing.', 'wp-mobile-de-sync' ); ?></p>
			<p>
				<button type="button" class="button button-secondary" id="wmds-test">
					<?php esc_html_e( 'Test connection', 'wp-mobile-de-sync' ); ?>
				</button>
			</p>
			<div class="wmds-result" id="wmds-test-result" role="status" aria-live="polite"></div>
			<noscript>
				<?php self::action_button( 'test', __( 'Test connection', 'wp-mobile-de-sync' ), 'secondary' ); ?>
			</noscript>
		</div>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'Import', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Runs the sync in batches of 20 vehicles and keeps going until nothing is left. Leaving this page stops it — the schedule picks the rest up on its own.', 'wp-mobile-de-sync' ); ?>
			</p>
			<p>
				<button type="button" class="button button-primary" id="wmds-sync">
					<?php esc_html_e( 'Sync now', 'wp-mobile-de-sync' ); ?>
				</button>
			</p>
			<div class="wmds-progress" id="wmds-progress" hidden>
				<div class="wmds-progress-bar"><span id="wmds-progress-fill"></span></div>
				<p class="wmds-progress-text" id="wmds-progress-text" role="status" aria-live="polite"></p>
			</div>
			<div class="wmds-result" id="wmds-sync-result" role="status" aria-live="polite"></div>
			<noscript>
				<?php self::action_button( 'sync', __( 'Sync now', 'wp-mobile-de-sync' ), 'primary' ); ?>
			</noscript>
			<?php
			$locked_since = WMDS_Importer::locked_since();
			if ( $locked_since ) :
				?>
				<div class="wmds-result wmds-result-error">
					<p>
						<?php
						printf(
							/* translators: %s: how long ago the running import started, e.g. "20 mins". */
							esc_html__( 'An import has held the lock since %s. A run that was cut short by a PHP time limit leaves it behind; releasing it lets the next run start.', 'wp-mobile-de-sync' ),
							esc_html( WMDS_Status::relative_time( $locked_since ) )
						);
						?>
					</p>
					<?php
					self::action_button(
						'unlock',
						__( 'Release the lock', 'wp-mobile-de-sync' ),
						'secondary',
						__( 'Release the lock only once you are sure no import is still running. Two runs writing the same vehicles at once will conflict. Continue?', 'wp-mobile-de-sync' )
					);
					?>
				</div>
			<?php endif; ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: the WP-CLI command, already marked up. */
					esc_html__( 'For the very first import of a large inventory the command line has no time limit and is the better route: %s', 'wp-mobile-de-sync' ),
					'<code>wp wmds sync --full --all</code>'
				);
				?>
			</p>
		</div>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'Maintenance', 'wp-mobile-de-sync' ); ?></h2>
			<table class="wmds-tools">
				<tbody>
					<tr>
						<td>
							<strong><?php esc_html_e( 'Force a full sync', 'wp-mobile-de-sync' ); ?></strong>
							<p class="description">
								<?php esc_html_e( 'Discards the change marker. The next run reads every ad again instead of only the changed ones, and it is the first one allowed to remove sold vehicles.', 'wp-mobile-de-sync' ); ?>
							</p>
						</td>
						<td>
							<?php
							self::action_button(
								'full',
								__( 'Force', 'wp-mobile-de-sync' ),
								'secondary',
								__( 'This discards the change marker, so the next run re-reads the whole inventory. Continue?', 'wp-mobile-de-sync' )
							);
							?>
						</td>
					</tr>
					<tr>
						<td>
							<strong><?php esc_html_e( 'Re-fetch reference data', 'wp-mobile-de-sync' ); ?></strong>
							<p class="description">
								<?php esc_html_e( 'Throws away the cached labels for transmission, fuel and colours, plus the logo index.', 'wp-mobile-de-sync' ); ?>
							</p>
						</td>
						<td>
							<?php self::action_button( 'flush', __( 'Discard', 'wp-mobile-de-sync' ), 'secondary' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * @param string $action
	 * @param string $label
	 * @param string $type
	 * @param string $confirm Optional confirmation prompt.
	 */
	private static function action_button( $action, $label, $type, $confirm = '' ) {
		printf( '<form method="post" action="%s" class="wmds-inline-form">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="action" value="wmds">';
		printf( '<input type="hidden" name="wmds_action" value="%s">', esc_attr( $action ) );

		printf(
			'<button type="submit" class="button button-%s"%s>%s</button>',
			esc_attr( $type ),
			$confirm ? ' data-wmds-confirm="' . esc_attr( $confirm ) . '"' : '',
			esc_html( $label )
		);

		echo '</form>';
	}
}
