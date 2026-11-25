<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Theme_Style {
	const DARK = 0.2;

	/** @var array|null */
	private static $tokens = null;

	public static function init() {
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function reset() {
		self::$tokens = null;
	}

	/** @return array{accent?:string,on_accent?:string,radius?:string,page?:string} */
	public static function tokens() {
		if ( null !== self::$tokens ) {
			return self::$tokens;
		}

		$tokens = array();
		if ( WMDS_Settings::flag( 'match_theme' ) ) {
			$tokens = array_merge( self::from_theme(), self::from_theme_json() );
		}

		$tokens = apply_filters( 'wmds_theme_style', $tokens );

		self::$tokens = self::clean( is_array( $tokens ) ? $tokens : array() );

		return self::$tokens;
	}

	/** @return string */
	public static function css() {
		$tokens = self::tokens();
		$vars   = array();

		if ( isset( $tokens['accent'] ) ) {
			$vars['--wmds-theme-accent'] = $tokens['accent'];
		}

		if ( isset( $tokens['on_accent'] ) ) {
			$vars['--wmds-theme-on-accent'] = $tokens['on_accent'];
		} elseif ( isset( $tokens['accent'] ) ) {
			$hex = self::hex( $tokens['accent'] );
			if ( '' !== $hex ) {
				$vars['--wmds-theme-on-accent'] = self::luminance( $hex ) > 0.4 ? '#14171c' : '#fff';
			}
		}

		if ( isset( $tokens['radius'] ) ) {
			$radius = self::pixels( $tokens['radius'] );
			if ( null !== $radius ) {
				$vars['--wmds-theme-radius']    = self::px( 0.0 === $radius ? 0.0 : min( max( $radius * 2, 6.0 ), 20.0 ) );
				$vars['--wmds-theme-radius-md'] = self::px( 0.0 === $radius ? 0.0 : min( max( $radius * 1.5, 4.0 ), 14.0 ) );
				$vars['--wmds-theme-radius-sm'] = self::px( min( $radius, 23.0 ) );
			}
		}

		if ( self::is_dark() ) {
			$page = self::hex( $tokens['page'] );

			$vars['--wmds-theme-surface'] = self::mix( $page, '#ffffff', 0.06 );
			$vars['--wmds-theme-canvas']  = self::mix( $page, '#ffffff', 0.12 );
		}

		if ( ! $vars ) {
			return '';
		}

		$css = '';
		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}

		return 'body{' . $css . '}';
	}

	/** @return bool */
	public static function is_dark() {
		$tokens = self::tokens();
		if ( ! isset( $tokens['page'] ) ) {
			return false;
		}

		$hex = self::hex( $tokens['page'] );

		return '' !== $hex && self::luminance( $hex ) < self::DARK;
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();

		if ( self::is_dark() && ! in_array( 'wmds-dark', $classes, true ) ) {
			$classes[] = 'wmds-dark';
		}

		return $classes;
	}

	/** @return array */
	private static function from_theme() {
		$tokens = array();

		if ( function_exists( 'current_theme_supports' ) && current_theme_supports( 'custom-background' ) && function_exists( 'get_background_color' ) ) {
			$background = (string) get_background_color();
			if ( '' !== $background ) {
				$tokens['page'] = '#' . ltrim( $background, '#' );
			}
		}

		$template = function_exists( 'get_template' ) ? strtolower( (string) get_template() ) : '';

		switch ( $template ) {
			case 'twentytwentyone':
				$tokens['accent']    = 'var(--button--color-background)';
				$tokens['on_accent'] = 'var(--button--color-text)';
				$tokens['radius']    = '0';
				break;
			case 'astra':
				$tokens['accent']    = 'var(--ast-global-color-0)';
				$tokens['on_accent'] = '#fff';
				break;
			case 'generatepress':
				$tokens['accent']    = 'var(--accent)';
				$tokens['on_accent'] = '#fff';
				$tokens['radius']    = '0';
				break;
			case 'kadence':
				$tokens['accent']    = 'var(--global-palette-btn-bg, var(--global-palette1))';
				$tokens['on_accent'] = 'var(--global-palette-btn, #fff)';
				break;
			case 'blocksy':
				$tokens['accent']    = 'var(--theme-button-background-initial-color, var(--theme-palette-color-1))';
				$tokens['on_accent'] = 'var(--theme-button-text-initial-color, #fff)';
				break;
			case 'neve':
				$tokens['accent']    = 'var(--nv-primary-accent)';
				$tokens['on_accent'] = 'var(--nv-text-dark-bg, #fff)';
				break;
			case 'oceanwp':
				$default          = function_exists( 'oceanwp_get_theme_mod_default' ) ? oceanwp_get_theme_mod_default( 'ocean_primary_color', '#13aff0' ) : '#13aff0';
				$tokens['accent'] = (string) get_theme_mod( 'ocean_primary_color', $default );
				$tokens['radius'] = '0';
				break;
			case 'salient':
				$options = get_option( 'salient_redux' );
				if ( is_array( $options ) && ! empty( $options['accent-color'] ) ) {
					$tokens['accent'] = (string) $options['accent-color'];
				}
				break;
			case 'divi':
				if ( function_exists( 'et_get_option' ) ) {
					$tokens['accent'] = (string) et_get_option( 'accent_color', '#2ea3f2' );
				}
				break;
		}

		return $tokens;
	}

	/** @return array */
	private static function from_theme_json() {
		if ( ! function_exists( 'wp_get_global_styles' ) || ! self::has_theme_json() ) {
			return array();
		}

		$paths = array(
			'accent'    => array( 'elements', 'button', 'color', 'background' ),
			'on_accent' => array( 'elements', 'button', 'color', 'text' ),
			'radius'    => array( 'elements', 'button', 'border', 'radius' ),
			'page'      => array( 'color', 'background' ),
		);

		$core   = self::core_styles();
		$tokens = array();

		foreach ( $paths as $key => $path ) {
			$value = wp_get_global_styles( $path );
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				continue;
			}

			if ( self::dig( $core, $path ) === $value ) {
				continue;
			}

			$tokens[ $key ] = self::preset( $value );
		}

		return $tokens;
	}

	/** @return bool */
	private static function has_theme_json() {
		if ( function_exists( 'wp_theme_has_theme_json' ) ) {
			return (bool) wp_theme_has_theme_json();
		}

		return class_exists( 'WP_Theme_JSON_Resolver' ) && method_exists( 'WP_Theme_JSON_Resolver', 'theme_has_support' ) && WP_Theme_JSON_Resolver::theme_has_support();
	}

	/** @return array */
	private static function core_styles() {
		if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) || ! method_exists( 'WP_Theme_JSON_Resolver', 'get_core_data' ) ) {
			return array();
		}

		$data = WP_Theme_JSON_Resolver::get_core_data()->get_raw_data();

		return isset( $data['styles'] ) && is_array( $data['styles'] ) ? $data['styles'] : array();
	}

	/**
	 * @param array    $data
	 * @param string[] $path
	 * @return mixed
	 */
	private static function dig( array $data, array $path ) {
		foreach ( $path as $key ) {
			if ( ! is_array( $data ) || ! isset( $data[ $key ] ) ) {
				return null;
			}
			$data = $data[ $key ];
		}

		return $data;
	}

	/**
	 * @param string $value
	 * @return string
	 */
	private static function preset( $value ) {
		if ( preg_match( '/^var:preset\|([a-z0-9-]+)\|([a-z0-9-]+)$/i', $value, $match ) ) {
			return 'var(--wp--preset--' . $match[1] . '--' . $match[2] . ')';
		}

		return $value;
	}

	/**
	 * @param array $tokens
	 * @return array
	 */
	private static function clean( array $tokens ) {
		$out = array();

		foreach ( array( 'accent', 'on_accent', 'page' ) as $key ) {
			if ( isset( $tokens[ $key ] ) && is_string( $tokens[ $key ] ) ) {
				$value = trim( self::preset( $tokens[ $key ] ) );
				if ( '' !== $value && strlen( $value ) <= 200 && preg_match( '/^[#a-zA-Z0-9\s(),.%_-]+$/', $value ) ) {
					$out[ $key ] = $value;
				}
			}
		}

		if ( isset( $tokens['radius'] ) && is_scalar( $tokens['radius'] ) && null !== self::pixels( (string) $tokens['radius'] ) ) {
			$out['radius'] = trim( (string) $tokens['radius'] );
		}

		return $out;
	}

	/**
	 * @param string $value
	 * @return float|null
	 */
	private static function pixels( $value ) {
		if ( ! preg_match( '/^\s*(\d*\.?\d+)\s*(px|rem|em)?\s*$/', (string) $value, $match ) ) {
			return null;
		}

		$number = (float) $match[1];
		$unit   = isset( $match[2] ) ? $match[2] : '';

		if ( 'rem' === $unit || 'em' === $unit ) {
			$number *= 16;
		} elseif ( '' === $unit && 0.0 !== $number ) {
			return null;
		}

		return $number;
	}

	/**
	 * @param float|int $value
	 * @return string
	 */
	private static function px( $value ) {
		$value = (float) $value;

		return 0.0 === $value ? '0' : rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' ) . 'px';
	}

	/**
	 * @param string $value
	 * @return string
	 */
	public static function hex( $value ) {
		$value = trim( (string) $value );

		if ( preg_match( '/^var\(\s*--wp--preset--color--([a-z0-9-]+)\s*\)$/i', $value, $match ) ) {
			$value = self::palette( $match[1] );
		}

		if ( preg_match( '/^#?([0-9a-f]{3})$/i', $value, $match ) ) {
			$short = $match[1];

			return strtolower( '#' . $short[0] . $short[0] . $short[1] . $short[1] . $short[2] . $short[2] );
		}

		if ( preg_match( '/^#?([0-9a-f]{6})$/i', $value, $match ) ) {
			return strtolower( '#' . $match[1] );
		}

		return '';
	}

	/**
	 * @param string $slug
	 * @return string
	 */
	private static function palette( $slug ) {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return '';
		}

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		if ( ! is_array( $palette ) ) {
			return '';
		}

		$groups = isset( $palette[0] ) ? array( $palette ) : array();
		foreach ( array( 'custom', 'theme', 'default' ) as $origin ) {
			if ( isset( $palette[ $origin ] ) && is_array( $palette[ $origin ] ) ) {
				$groups[] = $palette[ $origin ];
			}
		}

		foreach ( $groups as $colors ) {
			foreach ( $colors as $color ) {
				if ( is_array( $color ) && isset( $color['slug'], $color['color'] ) && $slug === $color['slug'] ) {
					return (string) $color['color'];
				}
			}
		}

		return '';
	}

	/**
	 * @param string $hex
	 * @return float
	 */
	public static function luminance( $hex ) {
		$channels = array();
		foreach ( array( 1, 3, 5 ) as $offset ) {
			$channel    = hexdec( substr( $hex, $offset, 2 ) ) / 255;
			$channels[] = $channel <= 0.03928 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 );
		}

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/**
	 * @param string $base
	 * @param string $with
	 * @param float  $share
	 * @return string
	 */
	private static function mix( $base, $with, $share ) {
		$out = '#';
		foreach ( array( 1, 3, 5 ) as $offset ) {
			$a    = hexdec( substr( $base, $offset, 2 ) );
			$b    = hexdec( substr( $with, $offset, 2 ) );
			$out .= sprintf( '%02x', (int) round( $a * ( 1 - $share ) + $b * $share ) );
		}

		return $out;
	}
}
