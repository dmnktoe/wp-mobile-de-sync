<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Stickers {
	const DIR = 'assets/emission-stickers/';

	/**
	 * @var array<string, string>
	 */
	private static $files = array(
		'red'    => 'red.svg',
		'yellow' => 'yellow.svg',
		'green'  => 'green.svg',
	);

	/**
	 * @var array<string, string>
	 */
	private static $groups = array(
		'2' => 'red',
		'3' => 'yellow',
		'4' => 'green',
	);

	/**
	 * @param string $key e.g. "EMISSIONSSTICKER_GREEN", "green" or the group number.
	 * @return string Empty string when the vehicle carries no sticker.
	 */
	public static function url( $key ) {
		$colour = self::normalize( $key );

		$url = isset( self::$files[ $colour ] )
			? WMDS_URL . self::DIR . self::$files[ $colour ]
			: '';

		/**
		 * @param string $url
		 * @param string $key
		 */
		return apply_filters( 'wmds_emission_sticker_url', $url, $key );
	}

	/**
	 * @param string $key
	 * @return string One of red, yellow, green, or '' for none.
	 */
	public static function normalize( $key ) {
		$key = strtolower( trim( (string) $key ) );
		if ( '' === $key ) {
			return '';
		}

		$key = preg_replace( '/^emissionssticker_/', '', $key );

		if ( preg_match( '/^([1-4])\b/', $key, $match ) ) {
			return isset( self::$groups[ $match[1] ] ) ? self::$groups[ $match[1] ] : '';
		}

		foreach ( array_keys( self::$files ) as $colour ) {
			if ( false !== strpos( $key, $colour ) ) {
				return $colour;
			}
		}

		return '';
	}

	/**
	 * @param string $key
	 * @return string
	 */
	public static function label( $key ) {
		$labels = array(
			'red'    => __( 'Emission sticker red', 'wp-mobile-de-sync' ),
			'yellow' => __( 'Emission sticker yellow', 'wp-mobile-de-sync' ),
			'green'  => __( 'Emission sticker green', 'wp-mobile-de-sync' ),
		);

		$colour = self::normalize( $key );

		return isset( $labels[ $colour ] ) ? $labels[ $colour ] : '';
	}
}

/**
 * @param array $atts
 * @return string
 */
function wmds_shortcode_emission_sticker( $atts ) {
	$atts = shortcode_atts(
		array(
			'sticker' => '',
			'width'   => '40',
			'class'   => '',
		),
		$atts,
		'emission-sticker'
	);

	$key = $atts['sticker'];
	if ( '' === $key ) {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		$key = get_post_meta( $post_id, 'emissionSticker_key', true );
		if ( '' === $key ) {
			$key = get_post_meta( $post_id, 'emissionSticker', true );
		}
	}

	$url = WMDS_Stickers::url( $key );
	if ( '' === $url ) {
		return '';
	}

	return sprintf(
		'<img src="%s" alt="%s" class="%s" style="width:%spx;height:auto">',
		esc_url( $url ),
		esc_attr( WMDS_Stickers::label( $key ) ),
		esc_attr( trim( 'wmds-emission-sticker ' . $atts['class'] ) ),
		esc_attr( (int) $atts['width'] )
	);
}
add_shortcode( 'emission-sticker', 'wmds_shortcode_emission_sticker' );
