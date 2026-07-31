<?php
/**
 * Template resolution and the front-end stylesheet.
 *
 * Every template the plugin renders - the archive, the detail page, the
 * shortcode and the card the last two share - goes through self::locate().
 * A theme overrides any of them by dropping a file of the same name into
 * wp-mobile-de-sync/ in the stylesheet or template directory, or into the
 * theme root, which is where an earlier solution expected mob_vehicle-list.php.
 *
 * The stylesheet follows the templates instead of the post type: a page that
 * renders the shortcode gets it as well, which the post type condition alone
 * never covered.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Templates {
	const HANDLE = 'wmds';

	const SUBDIR = 'wp-mobile-de-sync';

	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template_include' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_shortcode( 'fahrzeuge-anzeigen', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * The theme wins, the plugin fills in.
	 *
	 * @param string $file Path relative to templates/, e.g. "parts/vehicle-card.php".
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
		 * @param string $found Absolute path to the template, '' when there is none.
		 * @param string $file  The requested file, relative to templates/.
		 */
		return apply_filters( 'wmds_template', $found, $file );
	}

	/**
	 * Renders a template. $args is in scope inside it.
	 *
	 * @param string $file Path relative to templates/.
	 * @param array  $args Values the template reads.
	 */
	public static function render( $file, $args = array() ) {
		$path = self::locate( $file );
		if ( '' === $path || ! file_exists( $path ) ) {
			return;
		}

		self::include_template( $path, $args );
	}

	/**
	 * Kept separate so the included file sees $path and $args only.
	 *
	 * @param string $path
	 * @param array  $args
	 */
	private static function include_template( $path, $args ) {
		// The shortcode template used to be included straight from the
		// shortcode callback, with $atts in scope. A theme copy still expects
		// that name, so it keeps it.
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
		// A page carrying the shortcode is not a vehicle page, so the enqueue
		// pass over the main query has no way of knowing the styles are needed.
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
	 * A vehicle page, or a post whose content renders the shortcode.
	 *
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

		return $post instanceof WP_Post && has_shortcode( $post->post_content, 'fahrzeuge-anzeigen' );
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

		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			self::register();
		}

		// Enqueued after wp_head, the style is printed in the footer. Late is
		// better than never: the shortcode can turn up in a widget, in a
		// page builder or in a template part, none of which the main query
		// knows about.
		wp_enqueue_style( self::HANDLE );
	}

	private static function register() {
		if ( wp_style_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		wp_register_style( self::HANDLE, WMDS_URL . 'assets/wmds.css', array(), WMDS_VERSION );
	}
}
