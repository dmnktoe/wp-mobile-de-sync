<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Templates {
	const HANDLE = 'wmds';

	const SUBDIR = 'wp-mobile-de-sync';

	/** @var string[] */
	private static $tags = array( 'vehicles' );

	/** @var string[] */
	private static $styled_tags = array( 'vehicle-filter', 'vehicle-count', 'vehicle-enquiry' );

	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template_include' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		foreach ( self::$tags as $tag ) {
			add_shortcode( $tag, array( __CLASS__, 'shortcode' ) );
		}
	}

	/**
	 * @param string $file
	 * @return string
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

		return apply_filters( 'wmds_template', $found, $file );
	}

	/**
	 * @param string $file
	 * @param array  $args
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
			$file = 'single-' . WMDS_CPT . '.php';
		} elseif ( is_post_type_archive( WMDS_CPT ) ) {
			$file = 'archive-' . WMDS_CPT . '.php';
		} else {
			return $template;
		}

		$found = self::locate( $file );

		return '' !== $found ? $found : $template;
	}

	/** @return array{0:string,1:string} */
	public static function wrapper() {
		$wrapper = array( '', '' );

		if ( function_exists( 'get_template' ) && 'salient' === get_template() ) {
			$wrapper = array(
				'<div class="container-wrap"><div class="container main-content wmds-theme-frame"><div class="row">',
				'</div></div></div>',
			);
		}

		$wrapper = apply_filters( 'wmds_theme_wrapper', $wrapper );

		return ( is_array( $wrapper ) && isset( $wrapper[0], $wrapper[1] ) ) ? array( (string) $wrapper[0], (string) $wrapper[1] ) : array( '', '' );
	}

	/** @return bool */
	public static function is_block_theme() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	public static function header() {
		if ( self::is_block_theme() ) {
			ob_start();

			return;
		}

		get_header();

		echo self::wrapper()[0]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup, or what a theme passes through the filter.
	}

	public static function footer() {
		if ( ! self::is_block_theme() ) {
			echo self::wrapper()[1]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see header().

			get_footer();

			return;
		}

		$content = (string) ob_get_clean();

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
	 * @param string $slug
	 * @return string
	 */
	private static function template_part( $slug ) {
		$part = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
		if ( ! $part || empty( $part->content ) ) {
			return '';
		}

		return do_blocks(
			sprintf(
				'<!-- wp:template-part {"slug":"%1$s","theme":"%2$s","area":"%1$s","tagName":"%1$s"} /-->',
				$slug,
				esc_attr( get_stylesheet() )
			)
		);
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public static function icon( $name ) {
		$paths = array(
			'mileage'       => '<path d="M4 16a8 8 0 1 1 16 0"/><path d="M12 16l4.5-5"/><circle cx="12" cy="16" r="1.25"/>',
			'registration'  => '<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
			'power'         => '<path d="M13.5 3L5 13.5h6.5L10.5 21 19 10.5h-6.5z"/>',
			'gearbox'       => '<circle cx="6" cy="5.5" r="1.75"/><circle cx="12" cy="5.5" r="1.75"/><circle cx="18" cy="5.5" r="1.75"/><circle cx="6" cy="18.5" r="1.75"/><circle cx="12" cy="18.5" r="1.75"/><path d="M6 7.25v9.5M12 7.25v9.5M18 7.25V12H6"/>',
			'fuel'          => '<path d="M5 21V5.5A2.5 2.5 0 0 1 7.5 3h5A2.5 2.5 0 0 1 15 5.5V21M3.5 21h13M5 10h10"/><path d="M15 8.5l3 3V17a1.5 1.5 0 0 0 3 0V9.5L18.5 7"/>',
			'arrow-right'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'arrow-left'    => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
			'chevron-left'  => '<path d="M15 5l-7 7 7 7"/>',
			'chevron-right' => '<path d="M9 5l7 7-7 7"/>',
			'chevron-down'  => '<path d="M6 9l6 6 6-6"/>',
			'phone'         => '<path d="M5.5 3.5h3.5l2 5-2.5 1.75a11.5 11.5 0 0 0 5.25 5.25L15.5 13l5 2v3.5a2 2 0 0 1-2 2A16.5 16.5 0 0 1 3.5 5.5a2 2 0 0 1 2-2z"/>',
			'mail'          => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 7l8.5 6 8.5-6"/>',
			'message'       => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v9a2.5 2.5 0 0 1-2.5 2.5H10l-4.5 4v-4h-1A.5.5 0 0 1 4 16.5z"/>',
			'check'         => '<path d="M5 12.5l4.5 4.5L19 7"/>',
			'sliders'       => '<path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/>',
			'search'        => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.25-4.25"/>',
			'images'        => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><circle cx="9" cy="10" r="1.75"/><path d="M21 16l-5-5-9 8"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="wmds-icon wmds-icon--' . $name . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/** @param string $name */
	public static function the_icon( $name ) {
		echo self::icon( $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from a fixed table above, nothing in it comes from outside.
	}

	/** @return string */
	public static function wrapper_class() {
		return self::is_block_theme() ? ' alignwide' : ' wmds-page';
	}

	/**
	 * @param array  $atts
	 * @param string $content
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

	/** @return bool */
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

	/** @param bool $needed */
	public static function maybe_enqueue( $needed ) {
		if ( ! apply_filters( 'wmds_enqueue_styles', (bool) $needed ) ) {
			return;
		}

		self::register();
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	public static function register() {
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_register_script( self::HANDLE, WMDS_URL . 'assets/wmds.js', array(), WMDS_VERSION, true );
		}

		if ( wp_style_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		wp_register_style( self::HANDLE, WMDS_URL . 'assets/wmds.css', array(), WMDS_VERSION );
	}
}
