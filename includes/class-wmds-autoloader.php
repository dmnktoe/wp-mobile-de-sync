<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads a WMDS class from the file named after it.
 *
 * WMDS_Facet_Store lives in includes/class-wmds-facet-store.php, WMDS_Tab_Tools
 * in includes/tabs/class-wmds-tab-tools.php. Adding a class means adding a
 * file and nothing else — there is no map here to forget to update, and no
 * list in the plugin header to keep in step.
 *
 * Three files are deliberately not left to this. class-wmds-compat.php holds
 * functions rather than a class, and class-wmds-logos.php and
 * class-wmds-stickers.php each register a shortcode at file level. A file
 * that does something when it loads cannot be loaded lazily: nothing would
 * ever mention the class on a page that only uses the shortcode, so the
 * shortcode would never be registered.
 */
class WMDS_Autoloader {
	/**
	 * Where a class file may sit, relative to includes/.
	 *
	 * @var string[]
	 */
	private static $dirs = array( '', 'tabs/' );

	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * @param string $class
	 */
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
