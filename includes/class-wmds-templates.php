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
	private static $tags = array( 'vehicles' );

	/**
	 * Shortcodes that other classes register but that still need the stylesheet.
	 *
	 * @var string[]
	 */
	private static $styled_tags = array( 'vehicle-filter', 'vehicle-count', 'vehicle-enquiry' );

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
			$file = 'single-vehicles.php';
		} elseif ( is_post_type_archive( WMDS_CPT ) ) {
			$file = 'archive-vehicles.php';
		} else {
			return $template;
		}

		$found = self::locate( $file );

		return '' !== $found ? $found : $template;
	}

	/** @return bool Whether the active theme is built from blocks and has no header.php. */
	public static function is_block_theme() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	/**
	 * Opens the page around a vehicle template.
	 *
	 * A classic theme has a header.php and get_header() prints it. A block
	 * theme has none: get_header() falls back to a bare document from
	 * theme-compat, raises a deprecation and drops the theme's own header,
	 * its styles and its content width. For those the page is collected
	 * instead and footer() wraps it the way the theme's own templates are
	 * wrapped.
	 */
	public static function header() {
		if ( self::is_block_theme() ) {
			ob_start();

			return;
		}

		get_header();
	}

	/** Closes what header() opened. */
	public static function footer() {
		if ( ! self::is_block_theme() ) {
			get_footer();

			return;
		}

		$content = (string) ob_get_clean();

		// Rendered before wp_head() runs, as the block template canvas does:
		// the styles of every block in a template part are only known once
		// the part has been rendered.
		$header = self::template_part( 'header' );
		$footer = self::template_part( 'footer' );
		$main   = do_blocks( '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} --><main class="wp-block-group">wmds-content-goes-here</main><!-- /wp:group -->' );
		$main   = str_replace( 'wmds-content-goes-here', $content, $main );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
		<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
		<?php wp_body_open(); ?>
<div class="wp-site-blocks">
		<?php
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks and the template's own, already escaped output.
		echo $header;
		echo $main;
		echo $footer;
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
</div>
		<?php wp_footer(); ?>
</body>
</html>
		<?php
	}

	/**
	 * @param string $slug header or footer.
	 * @return string The rendered part, empty when the theme has none.
	 */
	private static function template_part( $slug ) {
		if ( ! function_exists( 'get_block_template' ) ) {
			return '';
		}

		$part = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
		if ( ! $part || empty( $part->content ) ) {
			return '';
		}

		return do_blocks( sprintf( '<!-- wp:template-part {"slug":"%1$s","area":"%1$s","tagName":"%1$s"} /-->', $slug ) );
	}

	/** @return string Classes for the outermost element of a vehicle template. */
	public static function wrapper_class() {
		return self::is_block_theme() ? ' alignwide' : '';
	}

	/**
	 * @param array  $atts    Shortcode attributes, consumed by the template.
	 * @param string $content Enclosed content, unused.
	 * @return string
	 */
	public static function shortcode( $atts, $content = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- see above.
		self::maybe_enqueue( true );

		ob_start();
		self::render( 'vehicle-list.php', array( 'atts' => $atts ) );
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

		if ( function_exists( 'has_block' ) ) {
			foreach ( WMDS_Blocks::styled_names() as $block ) {
				if ( has_block( $block, $post ) ) {
					return true;
				}
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
