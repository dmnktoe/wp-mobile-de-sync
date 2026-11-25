<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Plugin {
	/** @var string */
	private static $file = '';

	/** @param string $file */
	public static function boot( $file ) {
		self::$file = $file;

		register_activation_hook( $file, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( $file, array( __CLASS__, 'deactivate' ) );

		WMDS_Updater::init();

		add_action( 'init', array( 'WMDS_Adminbar', 'init' ) );
		WMDS_Alerts::init();

		if ( is_admin() ) {
			add_action( 'plugins_loaded', array( 'WMDS_Admin', 'init' ) );
			add_action( 'plugins_loaded', array( 'WMDS_Dashboard', 'init' ) );
			add_action( 'plugins_loaded', array( 'WMDS_Posts', 'init' ) );
		}

		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'maybe_ensure_schedule' ), 20 );

		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'classic_editor' ), 10, 2 );

		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( WMDS_CRON_HOOK, array( __CLASS__, 'run_import' ) );
		add_action( WMDS_Importer::FOLLOWUP_HOOK, array( __CLASS__, 'run_import' ) );
		add_action( WMDS_CRON_FULL_HOOK, array( __CLASS__, 'force_full_sync' ) );

		WMDS_Images::init();
		WMDS_Templates::init();
		WMDS_Removed::init();
		WMDS_Facets::init();
		WMDS_Blocks::init();
		WMDS_Leads::init();
		WMDS_Lead_Form::init();
		WMDS_Challenge::init();
		WMDS_Seo::init();
		WMDS_Smtp::init();

		add_action( 'plugins_loaded', array( 'WMDS_Cf7', 'init' ) );
		add_action( 'plugins_loaded', array( 'WMDS_Facetwp', 'init' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once WMDS_DIR . 'includes/class-wmds-cli.php';
		}
	}

	public static function load_textdomain() {
		load_plugin_textdomain( 'wp-mobile-de-sync', false, dirname( plugin_basename( self::$file ) ) . '/languages' );
	}

	public static function register_post_type() {
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
					'slug'       => WMDS_Settings::slug(),
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

	/**
	 * @param bool   $use
	 * @param string $post_type
	 * @return bool
	 */
	public static function classic_editor( $use, $post_type ) {
		return WMDS_CPT === $post_type ? false : $use;
	}

	/**
	 * @param array $schedules
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
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

	public static function run_import() {
		if ( ! WMDS_Settings::is_configured() ) {
			return;
		}

		$importer = new WMDS_Importer();
		$importer->run();
	}

	public static function force_full_sync() {
		delete_option( WMDS_Importer::OPT_WATERMARK );
	}

	public static function activate() {
		self::register_post_type();
		flush_rewrite_rules();

		self::ensure_schedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( WMDS_CRON_HOOK );
		wp_clear_scheduled_hook( WMDS_Importer::FOLLOWUP_HOOK );
		wp_clear_scheduled_hook( WMDS_CRON_FULL_HOOK );
		WMDS_Alerts::unschedule();
		delete_transient( WMDS_Importer::LOCK );
		flush_rewrite_rules();
	}

	public static function ensure_schedule() {
		if ( ! wp_next_scheduled( WMDS_CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, WMDS_Settings::interval(), WMDS_CRON_HOOK );
		}
		if ( ! wp_next_scheduled( WMDS_CRON_FULL_HOOK ) ) {
			wp_schedule_event( time() + 300, 'daily', WMDS_CRON_FULL_HOOK );
		}

		WMDS_Alerts::schedule();
	}

	public static function maybe_ensure_schedule() {
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			self::ensure_schedule();
		}
	}

	public static function resync_schedule() {
		$wanted = WMDS_Settings::interval();

		if ( wp_get_schedule( WMDS_CRON_HOOK ) === $wanted ) {
			return;
		}

		wp_clear_scheduled_hook( WMDS_CRON_HOOK );
		wp_schedule_event( time() + 60, $wanted, WMDS_CRON_HOOK );
	}
}
