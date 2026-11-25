<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Autoloader {
	/** @var string[] */
	private static $dirs = array( '', 'tabs/', 'integrations/' );

	/** @return string[] */
	public static function dirs() {
		return self::$dirs;
	}

	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/** @param string $class */
	public static function load( $class ) {
		if ( 0 !== strpos( $class, 'WMDS_' ) ) {
			return;
		}

		$file = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';

		foreach ( self::$dirs as $dir ) {
			$path = WMDS_DIR . 'includes/' . $dir . $file;

			if ( is_readable( $path ) ) {
				require_once $path;

				return;
			}
		}
	}
}
