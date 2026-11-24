<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Str {
	/**
	 * @param mixed $value
	 * @return int
	 */
	public static function length( $value ) {
		$value = self::text( $value );

		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	/**
	 * @param mixed $value
	 * @param int   $limit
	 * @return string
	 */
	public static function cut( $value, $limit ) {
		$value = self::text( $value );
		$limit = (int) $limit;

		if ( $limit <= 0 || self::length( $value ) <= $limit ) {
			return $value;
		}

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function collapse( $value ) {
		$text = self::text( $value );

		$one = preg_replace( '/\s+/u', ' ', $text );
		if ( null === $one ) {
			$one = (string) preg_replace( '/\s+/', ' ', $text );
		}

		return trim( $one );
	}

	/**
	 * @param mixed $value
	 * @param int   $limit
	 * @return string
	 */
	public static function scrub( $value, $limit = 0 ) {
		if ( is_array( $value ) || is_object( $value ) || null === $value ) {
			return '';
		}

		$value = preg_replace( '/[\x00-\x1F\x7F<>]/', '', (string) $value );

		return self::cut( self::collapse( $value ), $limit );
	}

	/**
	 * @param mixed  $value
	 * @param int    $limit
	 * @param string $ellipsis
	 * @return string
	 */
	public static function shorten( $value, $limit, $ellipsis = '…' ) {
		$value = trim( self::text( $value ) );
		$limit = (int) $limit;

		if ( $limit <= 0 || self::length( $value ) <= $limit ) {
			return $value;
		}

		$cut  = self::cut( $value, $limit );
		$stop = strrpos( $cut, ' ' );

		if ( false !== $stop && $stop > $limit / 2 ) {
			$cut = substr( $cut, 0, $stop );
		}

		return rtrim( $cut, " \t\n\r,;:-" ) . $ellipsis;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function one_line( $value ) {
		return self::collapse( preg_replace( '/[\r\n<>]+/', ' ', self::text( $value ) ) );
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function text( $value ) {
		if ( is_array( $value ) || is_object( $value ) || null === $value ) {
			return '';
		}

		return (string) $value;
	}
}
