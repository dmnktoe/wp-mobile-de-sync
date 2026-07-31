<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Logos {
	const DIR      = 'assets/logos/';
	const OVERRIDE = 'wmds-logos';

	/** @var array<string,string>|null normalised name => file name */
	private static $index = null;

	/**
	 * @param string $make_key e.g. "LAND ROVER". The display name
	 *                         ("Land Rover") works just as well.
	 * @return string Empty string when there is no logo.
	 */
	public static function url( $make_key ) {
		$needle = self::normalize( $make_key );
		if ( '' === $needle ) {
			return '';
		}

		$index = self::index();
		if ( ! isset( $index[ $needle ] ) ) {
			return apply_filters( 'wmds_logo_url', '', $make_key );
		}

		$entry = $index[ $needle ];
		$url   = ( 'override' === $entry['source'] )
			? trailingslashit( self::override_url() ) . rawurlencode( $entry['file'] )
			: WMDS_URL . self::DIR . rawurlencode( $entry['file'] );

		return apply_filters( 'wmds_logo_url', $url, $make_key );
	}

	/**
	 * @param string $value
	 * @return string
	 */
	public static function normalize( $value ) {
		$value = (string) $value;
		if ( '' === trim( $value ) ) {
			return '';
		}

		$value = strtr(
			$value,
			array(
				'ä' => 'ae',
				'ö' => 'oe',
				'ü' => 'ue',
				'ß' => 'ss',
				'Ä' => 'ae',
				'Ö' => 'oe',
				'Ü' => 'ue',
				'ë' => 'e',
				'é' => 'e',
				'è' => 'e',
				'ê' => 'e',
				'á' => 'a',
				'à' => 'a',
				'â' => 'a',
				'å' => 'a',
				'í' => 'i',
				'ì' => 'i',
				'î' => 'i',
				'ó' => 'o',
				'ò' => 'o',
				'ô' => 'o',
				'ø' => 'o',
				'ú' => 'u',
				'ù' => 'u',
				'û' => 'u',
				'ç' => 'c',
				'ñ' => 'n',
				'š' => 's',
				'ž' => 'z',
			)
		);

		$value = strtolower( $value );

		return preg_replace( '/[^a-z0-9]/', '', $value );
	}

	/**
	 * @return array<string, array{file:string,source:string}>
	 */
	private static function index() {
		if ( null !== self::$index ) {
			return self::$index;
		}

		self::$index = array();

		foreach ( array(
			'bundled'  => WMDS_DIR . self::DIR,
			'override' => self::override_dir(),
		) as $source => $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			foreach ( (array) glob( $dir . '*.{png,svg,jpg,jpeg,webp}', GLOB_BRACE ) as $path ) {
				$file = basename( $path );
				$key  = self::normalize( pathinfo( $file, PATHINFO_FILENAME ) );
				if ( '' === $key ) {
					continue;
				}
				self::$index[ $key ] = array(
					'file'   => $file,
					'source' => $source,
				);
			}
		}

		return self::$index;
	}

	/** @return string */
	private static function override_dir() {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . self::OVERRIDE . '/';
	}

	/** @return string */
	private static function override_url() {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['baseurl'] ) . self::OVERRIDE;
	}

	public static function flush() {
		self::$index = null;
	}
}

/**
 * @param array $atts
 * @return string
 */
function wmds_shortcode_logo( $atts ) {
	$atts = shortcode_atts(
		array(
			'make'  => '',
			'width' => '40',
			'class' => '',
		),
		$atts,
		'fahrzeug-logo'
	);

	$make = $atts['make'];
	if ( '' === $make ) {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		$make = get_post_meta( $post_id, 'make_key', true );
		if ( '' === $make ) {
			$make = get_post_meta( $post_id, 'make', true );
		}
	}

	$url = WMDS_Logos::url( $make );
	if ( '' === $url ) {
		return '';
	}

	return sprintf(
		'<img src="%s" alt="%s" class="%s" style="width:%spx;height:auto">',
		esc_url( $url ),
		esc_attr( $make ),
		esc_attr( trim( 'wmds-logo ' . $atts['class'] ) ),
		esc_attr( (int) $atts['width'] )
	);
}
add_shortcode( 'fahrzeug-logo', 'wmds_shortcode_logo' );
