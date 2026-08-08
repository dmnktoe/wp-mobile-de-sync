<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Admin {
	const PAGE  = WMDS_Status::PAGE;
	const NONCE = WMDS_Status::NONCE;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_wmds', array( 'WMDS_Admin_Actions', 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( 'WMDS_Admin_Notices', 'global_notice' ) );

		add_action( 'wp_ajax_wmds_test', array( 'WMDS_Admin_Ajax', 'ajax_test' ) );
		add_action( 'wp_ajax_wmds_sync', array( 'WMDS_Admin_Ajax', 'ajax_sync' ) );
		add_action( 'wp_ajax_wmds_dismiss', array( 'WMDS_Admin_Ajax', 'ajax_dismiss' ) );

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
	public static function on_page() {
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

	/** @return array<string,string> */
	public static function tabs() {
		return array(
			'connection' => __( 'Connection', 'wp-mobile-de-sync' ),
			'schedule'   => __( 'Schedule', 'wp-mobile-de-sync' ),
			'enquiries'  => __( 'Enquiries', 'wp-mobile-de-sync' ),
			'status'     => __( 'Status & log', 'wp-mobile-de-sync' ),
			'tools'      => __( 'Tools', 'wp-mobile-de-sync' ),
			'system'     => __( 'System', 'wp-mobile-de-sync' ),
			'about'      => __( 'About', 'wp-mobile-de-sync' ),
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

			<?php WMDS_Admin_Notices::render_notices(); ?>
			<?php WMDS_Admin_Ui::render_status_card( $status ); ?>
			<?php WMDS_Admin_Ui::render_checklist( $status ); ?>

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
					WMDS_Tab_Schedule::render( $status );
				} elseif ( 'enquiries' === $tab ) {
					WMDS_Tab_Enquiries::render();
				} elseif ( 'status' === $tab ) {
					WMDS_Tab_Status::render( $status );
				} elseif ( 'tools' === $tab ) {
					WMDS_Tab_Tools::render( $status );
				} elseif ( 'system' === $tab ) {
					WMDS_Tab_System::render();
				} elseif ( 'about' === $tab ) {
					WMDS_Tab_About::render();
				} else {
					WMDS_Tab_Connection::render();
				}
				?>
			</div>
		</div>
		<?php
	}
}
