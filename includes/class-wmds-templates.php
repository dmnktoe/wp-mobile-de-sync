<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Templates {
	const HANDLE = 'wmds';

	const SUBDIR = 'wp-mobile-de-sync';

	/**
	 * @var string[]
	 */
	private static $tags = array( 'vehicles', 'fahrzeuge-anzeigen' );

	/**
	 * Shortcodes that other classes register but that still need the stylesheet.
	 *
	 * @var string[]
	 */
	private static $styled_tags = array( 'vehicle-filter', 'fahrzeug-filter', 'vehicle-count', 'vehicle-enquiry', 'fahrzeug-anfrage' );

	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template_include' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		foreach ( self::$tags as $tag ) {
			add_shortcode( $tag, array( __CLASS__, 'shortcode' ) );
		}
	}

	/**
	 * @param string $file Path relative to templates/.
	 * @return string Absolute path, '' when nothing exists.
	 */
	public static function locate( $file ) {
		$file = ltrim( (string) $file, '/' );

		$found = '';
		if ( function_exists( 'locate_template' ) ) {
			$found = locate_template(
				array(
					self::SUBDIR . '/' . $file,
					$file,
				)
			);
		}

		if ( '' === $found ) {
			$candidate = WMDS_DIR . 'templates/' . $file;
			$found     = file_exists( $candidate ) ? $candidate : '';
		}

		/**
		 * @param string $found
		 * @param string $file
		 */
		return apply_filters( 'wmds_template', $found, $file );
	}

	/**
	 * @param string $file Path relative to templates/.
	 * @param array  $args Available to the template as $args.
	 */
	public static function render( $file, $args = array() ) {
		$path = self::locate( $file );
		if ( '' === $path || ! file_exists( $path ) ) {
			return;
		}

		self::include_template( $path, $args );
	}

	/**
	 * @param string $path
	 * @param array  $args
	 */
	private static function include_template( $path, $args ) {
		if ( isset( $args['atts'] ) ) {
			$atts = $args['atts']; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- read by the included template.
		}

		include $path;
	}

	/**
	 * @param string $template
	 * @return string
	 */
	public static function template_include( $template ) {
		if ( is_singular( WMDS_CPT ) ) {
			$file = 'single-fahrzeuge.php';
		} elseif ( is_post_type_archive( WMDS_CPT ) ) {
			$file = 'archive-fahrzeuge.php';
		} else {
			return $template;
		}

		$found = self::locate( $file );

		return '' !== $found ? $found : $template;
	}

	/**
	 * @param array  $atts    Shortcode attributes, consumed by the template.
	 * @param string $content Enclosed content, unused.
	 * @return string
	 */
	public static function shortcode( $atts, $content = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- see above.
		self::maybe_enqueue( true );

		ob_start();
		self::render( 'mob_vehicle-list.php', array( 'atts' => $atts ) );
		$output = ob_get_clean();

		wp_reset_postdata();

		return $output;
	}

	public static function enqueue() {
		self::register();
		self::maybe_enqueue( self::styles_needed() );
	}

	/**
	 * @return bool
	 */
	public static function styles_needed() {
		if ( is_singular( WMDS_CPT ) || is_post_type_archive( WMDS_CPT ) ) {
			return true;
		}

		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		foreach ( array_merge( self::$tags, self::$styled_tags ) as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param bool $needed
	 */
	public static function maybe_enqueue( $needed ) {
		/**
		 * @param bool $needed
		 */
		if ( ! apply_filters( 'wmds_enqueue_styles', (bool) $needed ) ) {
			return;
		}

		self::register();
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	private static function register() {
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_register_script( self::HANDLE, WMDS_URL . 'assets/wmds.js', array(), WMDS_VERSION, true );
		}

		if ( wp_style_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		wp_register_style( self::HANDLE, WMDS_URL . 'assets/wmds.css', array(), WMDS_VERSION );
	}
}
