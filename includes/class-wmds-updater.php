<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Updater {
	const REPO  = 'dmnktoe/wp-mobile-de-sync';
	const HOST  = 'github.com';
	const API   = 'https://api.github.com/repos/';
	const CACHE = 'wmds_release';

	const TTL_OK    = 3600;
	const TTL_ERROR = 900;

	/** @var string plugin_basename of the main file */
	private static $basename = '';

	/** @var string Directory name, which is also the slug */
	private static $slug = '';

	public static function init() {
		self::$basename = plugin_basename( WMDS_FILE );
		self::$slug     = dirname( self::$basename );

		add_filter( 'update_plugins_' . self::HOST, array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'after_update' ), 10, 2 );
		add_action( 'after_plugin_row_' . self::$basename, array( __CLASS__, 'row_notice' ) );
	}

	/**
	 * @param array|false $update      Result of earlier filters.
	 * @param array       $plugin_data The plugin's header values.
	 * @param string      $plugin_file Path relative to the plugins directory.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( $plugin_file !== self::$basename ) {
			return $update;
		}

		$release = self::release();
		if ( ! $release ) {
			return $update;
		}

		return array(
			'id'           => self::HOST . '/' . self::REPO,
			'slug'         => self::$slug,
			'plugin'       => self::$basename,
			'version'      => $release['version'],
			'new_version'  => $release['version'],
			'url'          => 'https://' . self::HOST . '/' . self::REPO,
			'package'      => $release['package'],
			'tested'       => $release['tested'],
			'requires'     => $release['requires'],
			'requires_php' => $release['requires_php'],
			'icons'        => self::icons(),
		);
	}

	/**
	 * @return array<string,string> Icon URLs, keyed as WordPress asks for them.
	 */
	private static function icons() {
		$url = WMDS_URL . 'assets/icon.svg';

		return array(
			'svg'     => $url,
			'default' => $url,
		);
	}

	/**
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object|array
	 */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== self::$slug ) {
			return $result;
		}

		$release = self::release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'mobile.de Sync',
			'slug'          => self::$slug,
			'version'       => $release['version'],
			'author'        => '<a href="https://github.com/dmnktoe">Domenik Töfflinger</a>',
			'homepage'      => 'https://' . self::HOST . '/' . self::REPO,
			'requires'      => $release['requires'],
			'requires_php'  => $release['requires_php'],
			'tested'        => $release['tested'],
			'last_updated'  => $release['date'],
			'download_link' => $release['package'],
			'icons'         => self::icons(),
			'sections'      => array(
				'description' => wpautop( esc_html__( 'Synchronises a dealer\'s vehicle inventory from the mobile.de Search API into a "fahrzeuge" custom post type. Works with FacetWP and with existing theme templates.', 'wp-mobile-de-sync' ) ),
				'changelog'   => $release['changelog'],
			),
		);
	}

	/**
	 * @param WP_Upgrader $upgrader
	 * @param array       $extra
	 */
	public static function after_update( $upgrader, $extra ) {
		if ( empty( $extra['action'] ) || 'update' !== $extra['action'] ) {
			return;
		}
		if ( empty( $extra['type'] ) || 'plugin' !== $extra['type'] ) {
			return;
		}
		if ( empty( $extra['plugins'] ) || ! in_array( self::$basename, (array) $extra['plugins'], true ) ) {
			return;
		}

		self::flush();
		if ( class_exists( 'WMDS_Refdata' ) ) {
			WMDS_Refdata::flush();
		}
	}

	public static function flush() {
		delete_transient( self::CACHE );
	}

	/**
	 * @return array{version:string,error:string,checked:int} What the last lookup found.
	 */
	public static function state() {
		$cached = get_transient( self::CACHE );

		if ( ! is_array( $cached ) || ! array_key_exists( 'release', $cached ) ) {
			return array(
				'version' => '',
				'error'   => '',
				'checked' => 0,
			);
		}

		return array(
			'version' => isset( $cached['release']['version'] ) ? (string) $cached['release']['version'] : '',
			'error'   => isset( $cached['error'] ) ? (string) $cached['error'] : '',
			'checked' => isset( $cached['checked'] ) ? (int) $cached['checked'] : 0,
		);
	}

	/**
	 * @return bool Whether the cached lookup has to be bypassed.
	 */
	public static function forced() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reads a flag out of a WordPress-built URL; nothing here changes state.
		$forced = is_admin() && isset( $_GET['force-check'] );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$forced = true;
		}

		/**
		 * @param bool $forced
		 */
		return (bool) apply_filters( 'wmds_force_update_check', $forced );
	}

	/**
	 * @param mixed $cached
	 * @return bool Whether the cached lookup may be used as it stands.
	 */
	private static function usable( $cached ) {
		if ( ! is_array( $cached ) || ! array_key_exists( 'release', $cached ) ) {
			return false;
		}

		if ( ! isset( $cached['installed'] ) || WMDS_VERSION !== $cached['installed'] ) {
			return false;
		}

		return ! self::forced();
	}

	/**
	 * @return array|false
	 */
	private static function release() {
		$cached = get_transient( self::CACHE );

		if ( self::usable( $cached ) ) {
			return is_array( $cached['release'] ) ? $cached['release'] : false;
		}

		$response = wp_remote_get(
			self::API . self::REPO . '/releases/latest',
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'wp-mobile-de-sync/' . WMDS_VERSION,
				),
			)
		);

		$failed = is_wp_error( $response );
		$code   = $failed ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$body   = $failed ? '' : (string) wp_remote_retrieve_body( $response );

		$parsed = self::parse( $code, $body );

		set_transient(
			self::CACHE,
			array(
				'installed' => WMDS_VERSION,
				'checked'   => time(),
				'release'   => $parsed,
				'error'     => $parsed ? '' : self::reason( $code, $body, $failed ? $response->get_error_message() : '' ),
			),
			$parsed ? self::TTL_OK : self::TTL_ERROR
		);

		return $parsed;
	}

	/**
	 * @param int    $code      HTTP status, 0 when the request never got through.
	 * @param string $body      Response body.
	 * @param string $transport Error message of a failed request.
	 * @return string Why the lookup came back empty.
	 */
	public static function reason( $code, $body, $transport = '' ) {
		if ( '' !== $transport ) {
			/* translators: %s: error message of the failed HTTP request. */
			return sprintf( __( 'GitHub could not be reached: %s', 'wp-mobile-de-sync' ), $transport );
		}

		if ( 403 === (int) $code || 429 === (int) $code ) {
			return __( 'GitHub turned the request down. The API rate limit for this server is most likely used up; it resets within the hour.', 'wp-mobile-de-sync' );
		}

		if ( 200 !== (int) $code ) {
			/* translators: %d: HTTP status code GitHub answered with. */
			return sprintf( __( 'GitHub answered with HTTP %d.', 'wp-mobile-de-sync' ), (int) $code );
		}

		$data = json_decode( (string) $body, true );

		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return __( 'GitHub did not answer with a release.', 'wp-mobile-de-sync' );
		}

		if ( ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return __( 'The newest release on GitHub is a draft or a pre-release, and those are skipped.', 'wp-mobile-de-sync' );
		}

		return __( 'The newest release on GitHub carries no installable ZIP.', 'wp-mobile-de-sync' );
	}

	/**
	 * @param string $plugin_file
	 */
	public static function row_notice( $plugin_file ) {
		if ( $plugin_file !== self::$basename ) {
			return;
		}

		$state = self::state();
		if ( '' === $state['error'] ) {
			return;
		}

		$columns = 4;
		if ( isset( $GLOBALS['wp_list_table'] ) && method_exists( $GLOBALS['wp_list_table'], 'get_column_count' ) ) {
			$columns = (int) $GLOBALS['wp_list_table']->get_column_count();
		}

		printf(
			'<tr class="plugin-update-tr"><td colspan="%d" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p>%s</p></div></td></tr>',
			$columns,
			sprintf(
				/* translators: %s: reason the update check failed. */
				esc_html__( 'The update check against GitHub failed, so no new version can be offered here: %s', 'wp-mobile-de-sync' ),
				esc_html( $state['error'] )
			)
		);
	}

	/**
	 * @param int    $code
	 * @param string $body
	 * @return array|false
	 */
	public static function parse( $code, $body ) {
		if ( 200 !== (int) $code ) {
			return false;
		}

		$data = json_decode( (string) $body, true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return false;
		}

		if ( ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return false;
		}

		$version = ltrim( (string) $data['tag_name'], 'vV' );
		if ( ! preg_match( '/^\d+(\.\d+)*/', $version ) ) {
			return false;
		}

		$package = self::asset( $data );
		if ( '' === $package ) {
			return false;
		}

		$notes = isset( $data['body'] ) ? (string) $data['body'] : '';

		return array(
			'version'      => $version,
			'package'      => $package,
			'date'         => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
			'changelog'    => self::changelog( $notes ),
			'requires'     => self::header( $notes, 'Requires at least', '5.8' ),
			'requires_php' => self::header( $notes, 'Requires PHP', '7.0' ),
			'tested'       => self::header( $notes, 'Tested up to', '' ),
		);
	}

	/**
	 * @param array $data
	 * @return string
	 */
	private static function asset( array $data ) {
		if ( empty( $data['assets'] ) || ! is_array( $data['assets'] ) ) {
			return '';
		}

		foreach ( $data['assets'] as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
				continue;
			}
			if ( preg_match( '/^wp-mobile-de-sync.*\.zip$/i', (string) $asset['name'] ) ) {
				return (string) $asset['browser_download_url'];
			}
		}

		return '';
	}

	/**
	 * @param string $notes
	 * @param string $label
	 * @param string $default
	 * @return string
	 */
	private static function header( $notes, $label, $default ) {
		if ( preg_match( '/^\s*' . preg_quote( $label, '/' ) . '\s*:\s*(\S+)\s*$/mi', $notes, $m ) ) {
			return $m[1];
		}
		return $default;
	}

	/**
	 * @param string $notes
	 * @return string
	 */
	public static function changelog( $notes ) {
		$notes = trim( (string) $notes );
		if ( '' === $notes ) {
			return '<p>' . esc_html__( 'No notes for this version.', 'wp-mobile-de-sync' ) . '</p>';
		}

		$notes = wp_kses_post( $notes );
		$html  = '';
		$list  = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $notes ) as $line ) {
			$line = trim( $line );

			if ( preg_match( '/^(#{1,6})\s+(.*)$/', $line, $m ) ) {
				$html .= self::flush_list( $list );
				$level = min( 4, max( 3, strlen( $m[1] ) ) );
				$html .= sprintf( '<h%d>%s</h%d>', $level, $m[2], $level );
				continue;
			}

			if ( preg_match( '/^[-*]\s+(.*)$/', $line, $m ) ) {
				$list[] = $m[1];
				continue;
			}

			$html .= self::flush_list( $list );
			if ( '' !== $line ) {
				$html .= '<p>' . $line . '</p>';
			}
		}

		$html = $html . self::flush_list( $list );

		return preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	}

	/**
	 * @param string[] $list Emptied in place.
	 * @return string
	 */
	private static function flush_list( array &$list ) {
		if ( ! $list ) {
			return '';
		}
		$html = '<ul><li>' . implode( '</li><li>', $list ) . '</li></ul>';
		$list = array();
		return $html;
	}
}
