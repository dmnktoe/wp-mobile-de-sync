<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Str {
	/**
	 * @param mixed $value
	 * @return int Characters, not bytes, wherever mbstring is available.
	 */
	public static function length( $value ) {
		$value = self::text( $value );

		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	/**
	 * @param mixed $value
	 * @param int   $limit
	 * @return string The first $limit characters, cut wherever they end.
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
	 * @return string One line, single spaces, no leading or trailing space.
	 */
	public static function collapse( $value ) {
		return trim( preg_replace( '/\s+/u', ' ', self::text( $value ) ) );
	}

	/**
	 * Makes a value from outside safe to keep.
	 *
	 * Control characters and angle brackets come out, whitespace collapses,
	 * and the result is cut to a length nobody can use as a payload. This is
	 * not an escaping function: what it returns still has to be escaped at
	 * the point it is printed.
	 *
	 * @param mixed $value
	 * @param int   $limit 0 for no limit.
	 * @return string Empty for anything that was not a scalar.
	 */
	public static function scrub( $value, $limit = 0 ) {
		if ( is_array( $value ) || is_object( $value ) || null === $value ) {
			return '';
		}

		$value = preg_replace( '/[\x00-\x1F\x7F<>]/u', '', (string) $value );

		return self::cut( self::collapse( $value ), $limit );
	}

	/**
	 * @param mixed  $value
	 * @param int    $limit
	 * @param string $ellipsis
	 * @return string Cut at a word where one is near enough, with the
	 *                trailing punctuation removed.
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
	 * @return string Safe to put in a mail header, which cannot carry a line
	 *                break whatever was typed into the field.
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
