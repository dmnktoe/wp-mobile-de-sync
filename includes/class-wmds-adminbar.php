<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Adminbar {
	const ID = 'wmds';

	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'menu' ), 80 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
	}

	/** @return bool */
	private static function visible() {
		return is_admin_bar_showing() && current_user_can( 'manage_options' );
	}

	public static function styles() {
		if ( ! self::visible() ) {
			return;
		}

		wp_enqueue_style( 'wmds-admin', WMDS_URL . 'assets/admin.css', array(), WMDS_VERSION );
	}

	/**
	 * @param WP_Admin_Bar $bar
	 */
	public static function menu( $bar ) {
		if ( ! self::visible() ) {
			return;
		}

		$status = WMDS_Status::get();

		$bar->add_node(
			array(
				'id'    => self::ID,
				'title' => self::title( $status ),
				'href'  => WMDS_Status::settings_url(),
				'meta'  => array( 'title' => $status['label'] ),
			)
		);

		self::add_state_nodes( $bar, $status );
		self::add_action_nodes( $bar, $status );
	}

	/**
	 * @param array $status
	 * @return string
	 */
	private static function title( array $status ) {
		$text = ( WMDS_Status::UNCONFIGURED === $status['level'] )
			? __( 'mobile.de', 'wp-mobile-de-sync' )
			: sprintf(
				/* translators: %d: number of vehicles in the inventory. */
				_n( '%d vehicle', '%d vehicles', $status['count'], 'wp-mobile-de-sync' ),
				$status['count']
			);

		return sprintf(
			'<span class="ab-icon dashicons dashicons-car" aria-hidden="true"></span>'
			. '<span class="ab-label">%s</span>'
			. '<span class="wmds-bar-dot wmds-sev-%s" aria-hidden="true"></span>'
			. '<span class="screen-reader-text">%s</span>',
			esc_html( $text ),
			esc_attr( $status['severity'] ),
			esc_html( $status['label'] )
		);
	}

	/**
	 * @param WP_Admin_Bar $bar
	 * @param array        $status
	 */
	private static function add_state_nodes( $bar, array $status ) {
		$bar->add_node(
			array(
				'parent' => self::ID,
				'id'     => self::ID . '-state',
				'title'  => sprintf(
					'<span class="wmds-bar-dot wmds-sev-%s" aria-hidden="true"></span> %s',
					esc_attr( $status['severity'] ),
					esc_html( $status['label'] )
				),
				'href'   => WMDS_Status::settings_url( 'status' ),
			)
		);

		if ( ! $status['configured'] ) {
			$bar->add_node(
				array(
					'parent' => self::ID,
					'id'     => self::ID . '-setup',
					'title'  => __( 'Enter credentials', 'wp-mobile-de-sync' ),
					'href'   => WMDS_Status::settings_url( 'connection' ),
				)
			);

			return;
		}

		if ( ! empty( $status['last'] ) ) {
			$bar->add_node(
				array(
					'parent' => self::ID,
					'id'     => self::ID . '-last',
					'title'  => sprintf(
						/* translators: 1: date and time of the last run, 2: its result. */
						esc_html__( 'Last run: %1$s — %2$s', 'wp-mobile-de-sync' ),
						esc_html( isset( $status['last']['time'] ) ? $status['last']['time'] : '—' ),
						esc_html( WMDS_Status::summarise( $status['last'] ) )
					),
					'href'   => WMDS_Status::settings_url( 'status' ),
				)
			);
		}

		$bar->add_node(
			array(
				'parent' => self::ID,
				'id'     => self::ID . '-next',
				'title'  => $status['next']
					? sprintf(
						/* translators: %s: a human-readable duration, e.g. "12 mins". */
						esc_html__( 'Next run in %s', 'wp-mobile-de-sync' ),
						esc_html( human_time_diff( time(), $status['next'] ) )
					)
					: esc_html__( 'No run scheduled', 'wp-mobile-de-sync' ),
				'href'   => WMDS_Status::settings_url( 'schedule' ),
			)
		);

		if ( $status['pending'] > 0 ) {
			$bar->add_node(
				array(
					'parent' => self::ID,
					'id'     => self::ID . '-pending',
					'title'  => sprintf(
						/* translators: %d: number of vehicles still queued. */
						esc_html__( '%d vehicles still queued', 'wp-mobile-de-sync' ),
						$status['pending']
					),
					'href'   => WMDS_Status::settings_url( 'status' ),
				)
			);
		}
	}

	/**
	 * @param WP_Admin_Bar $bar
	 * @param array        $status
	 */
	private static function add_action_nodes( $bar, array $status ) {
		$bar->add_node(
			array(
				'parent' => self::ID,
				'id'     => self::ID . '-list',
				'title'  => __( 'All vehicles', 'wp-mobile-de-sync' ),
				'href'   => WMDS_Status::list_url(),
			)
		);

		if ( $status['configured'] && ! $status['running'] ) {
			$bar->add_node(
				array(
					'parent' => self::ID,
					'id'     => self::ID . '-sync',
					'title'  => __( 'Sync now', 'wp-mobile-de-sync' ),
					'href'   => WMDS_Status::action_url( 'sync' ),
				)
			);
		}

		$bar->add_node(
			array(
				'parent' => self::ID,
				'id'     => self::ID . '-settings',
				'title'  => __( 'Sync settings', 'wp-mobile-de-sync' ),
				'href'   => WMDS_Status::settings_url(),
			)
		);

		if ( is_singular( WMDS_CPT ) ) {
			self::add_vehicle_nodes( $bar, (int) get_queried_object_id() );
		}
	}

	/**
	 * @param WP_Admin_Bar $bar
	 * @param int          $post_id
	 */
	private static function add_vehicle_nodes( $bar, $post_id ) {
		if ( ! $post_id ) {
			return;
		}

		$ad_id = (string) get_post_meta( $post_id, WMDS_Importer::META_AD_ID, true );
		if ( '' === $ad_id ) {
			return;
		}

		$bar->add_node(
			array(
				'parent' => self::ID,
				'id'     => self::ID . '-refresh',
				'title'  => __( 'Reload this vehicle', 'wp-mobile-de-sync' ),
				'href'   => WMDS_Status::action_url( 'refresh', array( 'post' => $post_id ) ),
			)
		);

		$detail = (string) get_post_meta( $post_id, 'detail_page_url', true );
		if ( '' !== $detail ) {
			$bar->add_node(
				array(
					'parent' => self::ID,
					'id'     => self::ID . '-source',
					'title'  => __( 'View the ad on mobile.de', 'wp-mobile-de-sync' ),
					'href'   => $detail,
					'meta'   => array(
						'target' => '_blank',
						'rel'    => 'noopener',
					),
				)
			);
		}
	}
}
