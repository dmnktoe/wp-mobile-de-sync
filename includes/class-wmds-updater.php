<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Updater {
	const REPO      = 'dmnktoe/wp-mobile-de-sync';
	const HOST      = 'github.com';
	const API       = 'https://api.github.com/repos/';
	const CACHE     = 'wmds_release';
	const TTL_OK    = 21600;
	const TTL_ERROR = 1800;

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
			'url'          => 'https://' . self::HOST . '/' . self::REPO,
			'package'      => $release['package'],
			'tested'       => $release['tested'],
			'requires_php' => $release['requires_php'],
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

		delete_transient( self::CACHE );
		if ( class_exists( 'WMDS_Refdata' ) ) {
			WMDS_Refdata::flush();
		}
	}

	/**
	 * @return array|false
	 */
	private static function release() {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( 'error' === $cached ) {
			return false;
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

		$parsed = self::parse(
			is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response ),
			is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response )
		);

		if ( ! $parsed ) {
			set_transient( self::CACHE, 'error', self::TTL_ERROR );
			return false;
		}

		set_transient( self::CACHE, $parsed, self::TTL_OK );

		return $parsed;
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
				$html .= self::flush( $list );
				$level = min( 4, max( 3, strlen( $m[1] ) ) );
				$html .= sprintf( '<h%d>%s</h%d>', $level, $m[2], $level );
				continue;
			}

			if ( preg_match( '/^[-*]\s+(.*)$/', $line, $m ) ) {
				$list[] = $m[1];
				continue;
			}

			$html .= self::flush( $list );
			if ( '' !== $line ) {
				$html .= '<p>' . $line . '</p>';
			}
		}

		$html = $html . self::flush( $list );

		return preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	}

	/**
	 * @param string[] $list Emptied in place.
	 * @return string
	 */
	private static function flush( array &$list ) {
		if ( ! $list ) {
			return '';
		}
		$html = '<ul><li>' . implode( '</li><li>', $list ) . '</li></ul>';
		$list = array();
		return $html;
	}
}
