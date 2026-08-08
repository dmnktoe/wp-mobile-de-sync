<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Requirements {
	const OK   = 'ok';
	const WARN = 'warn';
	const FAIL = 'fail';

	const MIN_PHP = '7.0';
	const MIN_WP  = '5.8';

	const SUPPORTED_PHP    = '8.1';
	const WANTED_EXECUTION = 120;
	const WANTED_MEMORY    = 134217728;

	/**
	 * @return array<int,array{key:string,group:string,label:string,value:string,status:string,hint:string}>
	 */
	public static function all() {
		return array_merge(
			self::platform(),
			self::extensions(),
			self::limits(),
			self::wordpress(),
			WMDS_Smtp::checks()
		);
	}

	/**
	 * @return string The worst status across all checks.
	 */
	public static function worst() {
		$worst = self::OK;

		foreach ( self::all() as $check ) {
			if ( self::FAIL === $check['status'] ) {
				return self::FAIL;
			}
			if ( self::WARN === $check['status'] ) {
				$worst = self::WARN;
			}
		}

		return $worst;
	}

	/**
	 * @return array{fail:int,warn:int,ok:int}
	 */
	public static function tally() {
		$tally = array(
			'fail' => 0,
			'warn' => 0,
			'ok'   => 0,
		);

		foreach ( self::all() as $check ) {
			++$tally[ $check['status'] ];
		}

		return $tally;
	}

	/** @return array */
	private static function platform() {
		$checks = array();

		$php       = PHP_VERSION;
		$too_old   = version_compare( $php, self::MIN_PHP, '<' );
		$unpatched = version_compare( $php, self::SUPPORTED_PHP, '<' );

		$checks[] = self::check(
			'php',
			__( 'Platform', 'wp-mobile-de-sync' ),
			__( 'PHP version', 'wp-mobile-de-sync' ),
			$php,
			$too_old ? self::FAIL : ( $unpatched ? self::WARN : self::OK ),
			$too_old
				? sprintf(
					/* translators: %s: minimum PHP version. */
					__( 'The plugin needs at least PHP %s.', 'wp-mobile-de-sync' ),
					self::MIN_PHP
				)
				: ( $unpatched
					? sprintf(
						/* translators: %s: oldest PHP version still receiving security fixes. */
						__( 'This release no longer gets security fixes. PHP %s or newer is worth asking your host for.', 'wp-mobile-de-sync' ),
						self::SUPPORTED_PHP
					)
					: '' )
		);

		$wp     = self::wp_version();
		$wp_old = version_compare( $wp, self::MIN_WP, '<' );

		$checks[] = self::check(
			'wp',
			__( 'Platform', 'wp-mobile-de-sync' ),
			__( 'WordPress version', 'wp-mobile-de-sync' ),
			$wp,
			$wp_old ? self::FAIL : self::OK,
			$wp_old
				? sprintf(
					/* translators: %s: minimum WordPress version. */
					__( 'The plugin needs at least WordPress %s.', 'wp-mobile-de-sync' ),
					self::MIN_WP
				)
				: ''
		);

		return $checks;
	}

	/** @return array */
	private static function extensions() {
		$required = array(
			'json'    => __( 'Reads the API responses.', 'wp-mobile-de-sync' ),
			'openssl' => __( 'Needed for the HTTPS calls to mobile.de.', 'wp-mobile-de-sync' ),
		);

		$optional = array(
			'curl'     => __( 'WordPress falls back to PHP streams without it, which is slower.', 'wp-mobile-de-sync' ),
			'mbstring' => __( 'Without it, umlauts in descriptions can be cut off mid-character.', 'wp-mobile-de-sync' ),
		);

		$checks = array();

		foreach ( $required as $name => $hint ) {
			$loaded   = extension_loaded( $name );
			$checks[] = self::check(
				'ext-' . $name,
				__( 'PHP extensions', 'wp-mobile-de-sync' ),
				$name,
				$loaded ? __( 'available', 'wp-mobile-de-sync' ) : __( 'missing', 'wp-mobile-de-sync' ),
				$loaded ? self::OK : self::FAIL,
				$loaded ? '' : $hint
			);
		}

		foreach ( $optional as $name => $hint ) {
			$loaded   = extension_loaded( $name );
			$checks[] = self::check(
				'ext-' . $name,
				__( 'PHP extensions', 'wp-mobile-de-sync' ),
				$name,
				$loaded ? __( 'available', 'wp-mobile-de-sync' ) : __( 'missing', 'wp-mobile-de-sync' ),
				$loaded ? self::OK : self::WARN,
				$loaded ? '' : $hint
			);
		}

		$editor = extension_loaded( 'imagick' ) || extension_loaded( 'gd' );

		$checks[] = self::check(
			'image-editor',
			__( 'PHP extensions', 'wp-mobile-de-sync' ),
			__( 'Image processing', 'wp-mobile-de-sync' ),
			$editor
				? ( extension_loaded( 'imagick' ) ? 'Imagick' : 'GD' )
				: __( 'missing', 'wp-mobile-de-sync' ),
			$editor ? self::OK : self::FAIL,
			$editor ? '' : __( 'Without GD or Imagick, WordPress cannot make the thumbnail sizes for vehicle images.', 'wp-mobile-de-sync' )
		);

		return $checks;
	}

	/** @return array */
	private static function limits() {
		$checks = array();

		$execution = (int) ini_get( 'max_execution_time' );
		$liftable  = self::can_lift_execution_limit();

		if ( 0 === $execution ) {
			$status = self::OK;
			$value  = __( 'no limit', 'wp-mobile-de-sync' );
			$hint   = '';
		} elseif ( $execution >= self::WANTED_EXECUTION || $liftable ) {
			$status = self::OK;
			$value  = $execution . ' s';
			$hint   = $liftable && $execution < self::WANTED_EXECUTION
				? __( 'Low, but the plugin is allowed to raise it for the duration of a run.', 'wp-mobile-de-sync' )
				: '';
		} else {
			$status = self::WARN;
			$value  = $execution . ' s';
			$hint   = sprintf(
				/* translators: %d: seconds of execution time worth having. */
				__( 'set_time_limit() is disabled here, so a run has to stop after a few vehicles and continue on the next one. %d seconds would let it work through a whole batch.', 'wp-mobile-de-sync' ),
				self::WANTED_EXECUTION
			);
		}

		$checks[] = self::check(
			'execution-time',
			__( 'Limits', 'wp-mobile-de-sync' ),
			__( 'Execution time', 'wp-mobile-de-sync' ),
			$value,
			$status,
			$hint
		);

		$memory = self::bytes( ini_get( 'memory_limit' ) );
		$enough = ( $memory <= 0 || $memory >= self::WANTED_MEMORY );

		$checks[] = self::check(
			'memory',
			__( 'Limits', 'wp-mobile-de-sync' ),
			__( 'Memory limit', 'wp-mobile-de-sync' ),
			$memory > 0 ? size_format( $memory ) : __( 'no limit', 'wp-mobile-de-sync' ),
			$enough ? self::OK : self::WARN,
			$enough ? '' : sprintf(
				/* translators: %s: recommended memory limit. */
				__( 'Resizing large vehicle photos can run out of memory below %s.', 'wp-mobile-de-sync' ),
				size_format( self::WANTED_MEMORY )
			)
		);

		return $checks;
	}

	/** @return array */
	private static function wordpress() {
		$checks = array();

		$uploads  = wp_upload_dir();
		$writable = empty( $uploads['error'] ) && wp_is_writable( $uploads['basedir'] );

		$checks[] = self::check(
			'uploads',
			__( 'WordPress', 'wp-mobile-de-sync' ),
			__( 'Uploads folder', 'wp-mobile-de-sync' ),
			$writable ? __( 'writable', 'wp-mobile-de-sync' ) : __( 'not writable', 'wp-mobile-de-sync' ),
			$writable ? self::OK : self::FAIL,
			$writable ? '' : __( 'Vehicle images are stored in the media library and cannot be written without it.', 'wp-mobile-de-sync' )
		);

		$permalinks = (string) get_option( 'permalink_structure', '' );
		$pretty     = ( '' !== $permalinks );

		$checks[] = self::check(
			'permalinks',
			__( 'WordPress', 'wp-mobile-de-sync' ),
			__( 'Permalinks', 'wp-mobile-de-sync' ),
			$pretty ? __( 'pretty', 'wp-mobile-de-sync' ) : __( 'plain', 'wp-mobile-de-sync' ),
			$pretty ? self::OK : self::WARN,
			$pretty ? '' : __( 'Vehicle pages work either way, but their addresses stay numeric.', 'wp-mobile-de-sync' )
		);

		$rewrite = self::rewrite();

		$checks[] = self::check(
			'rewrite',
			__( 'WordPress', 'wp-mobile-de-sync' ),
			__( 'URL rewriting', 'wp-mobile-de-sync' ),
			$rewrite['value'],
			$rewrite['status'],
			$rewrite['hint']
		);

		$cron_off = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

		$checks[] = self::check(
			'cron',
			__( 'WordPress', 'wp-mobile-de-sync' ),
			__( 'WP-Cron', 'wp-mobile-de-sync' ),
			$cron_off ? __( 'disabled', 'wp-mobile-de-sync' ) : __( 'enabled', 'wp-mobile-de-sync' ),
			self::OK,
			$cron_off
				? __( 'The recommended setting, as long as a system cron calls wp-cron.php regularly.', 'wp-mobile-de-sync' )
				: __( 'Runs are triggered by page views. On a quiet site they are delayed accordingly.', 'wp-mobile-de-sync' )
		);

		$checks[] = self::outbound();

		return $checks;
	}

	/**
	 * @return array{value:string,status:string,hint:string}
	 */
	private static function rewrite() {
		if ( ! empty( $GLOBALS['is_nginx'] ) ) {
			return array(
				'value'  => 'nginx',
				'status' => self::OK,
				'hint'   => __( 'Rewriting is handled by the server configuration.', 'wp-mobile-de-sync' ),
			);
		}

		if ( ! function_exists( 'apache_get_modules' ) ) {
			return array(
				'value'  => __( 'not detectable', 'wp-mobile-de-sync' ),
				'status' => self::OK,
				'hint'   => __( 'The server does not report its modules. If vehicle pages open, rewriting works.', 'wp-mobile-de-sync' ),
			);
		}

		$loaded = in_array( 'mod_rewrite', apache_get_modules(), true );

		return array(
			'value'  => $loaded ? __( 'mod_rewrite active', 'wp-mobile-de-sync' ) : __( 'mod_rewrite missing', 'wp-mobile-de-sync' ),
			'status' => $loaded ? self::OK : self::WARN,
			'hint'   => $loaded ? '' : __( 'Without it, pretty permalinks for vehicle pages cannot work.', 'wp-mobile-de-sync' ),
		);
	}

	/** @return array */
	private static function outbound() {
		$hosts   = array( 'services.mobile.de', 'api.github.com' );
		$blocked = array();

		foreach ( $hosts as $host ) {
			if ( self::host_blocked( $host ) ) {
				$blocked[] = $host;
			}
		}

		return self::check(
			'outbound',
			__( 'WordPress', 'wp-mobile-de-sync' ),
			__( 'Outbound requests', 'wp-mobile-de-sync' ),
			$blocked ? __( 'blocked', 'wp-mobile-de-sync' ) : __( 'allowed', 'wp-mobile-de-sync' ),
			$blocked ? self::FAIL : self::OK,
			$blocked
				? sprintf(
					/* translators: %s: comma-separated list of host names. */
					__( 'WP_HTTP_BLOCK_EXTERNAL is set and these hosts are not in WP_ACCESSIBLE_HOSTS: %s', 'wp-mobile-de-sync' ),
					implode( ', ', $blocked )
				)
				: ''
		);
	}

	/**
	 * @param string $host
	 * @return bool
	 */
	private static function host_blocked( $host ) {
		if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
			return false;
		}

		if ( ! defined( 'WP_ACCESSIBLE_HOSTS' ) ) {
			return true;
		}

		foreach ( array_map( 'trim', explode( ',', WP_ACCESSIBLE_HOSTS ) ) as $allowed ) {
			if ( '' === $allowed ) {
				continue;
			}
			if ( $allowed === $host ) {
				return false;
			}
			if ( 0 === strpos( $allowed, '*.' ) && self::ends_with( $host, substr( $allowed, 1 ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	private static function ends_with( $haystack, $needle ) {
		$length = strlen( $needle );

		return 0 === $length || substr( $haystack, -$length ) === $needle;
	}

	/** @return bool */
	private static function can_lift_execution_limit() {
		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );

		return function_exists( 'set_time_limit' ) && ! in_array( 'set_time_limit', $disabled, true );
	}

	/**
	 * @param string $value A php.ini shorthand such as 256M.
	 * @return int Bytes, or -1 when unlimited.
	 */
	public static function bytes( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return 0;
		}
		if ( '-1' === $value ) {
			return -1;
		}

		$unit   = strtolower( substr( $value, -1 ) );
		$number = (int) $value;

		if ( 'g' === $unit ) {
			return $number * 1024 * 1024 * 1024;
		}
		if ( 'm' === $unit ) {
			return $number * 1024 * 1024;
		}
		if ( 'k' === $unit ) {
			return $number * 1024;
		}

		return $number;
	}

	/** @return string */
	private static function wp_version() {
		return isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';
	}

	/**
	 * @param string $key
	 * @param string $group
	 * @param string $label
	 * @param string $value
	 * @param string $status
	 * @param string $hint
	 * @return array
	 */
	private static function check( $key, $group, $label, $value, $status, $hint = '' ) {
		return array(
			'key'    => $key,
			'group'  => $group,
			'label'  => $label,
			'value'  => (string) $value,
			'status' => $status,
			'hint'   => (string) $hint,
		);
	}
}
