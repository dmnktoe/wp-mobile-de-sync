<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Num {
	/**
	 * @param mixed $value
	 * @return float|null
	 */
	public static function from_input( $value ) {
		$value = self::scalar( $value );
		if ( '' === $value ) {
			return null;
		}

		$value = str_replace( array( ' ', '.', ',' ), array( '', '', '.' ), $value );

		return is_numeric( $value ) ? (float) $value : null;
	}

	/**
	 * @param mixed $value
	 * @return int|float|null
	 */
	public static function from_feed( $value ) {
		$value = self::scalar( $value );
		if ( '' === $value ) {
			return null;
		}

		$value = str_replace( array( ' ', ',' ), array( '', '.' ), $value );
		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$float = (float) $value;
		$whole = (float) (int) $value;

		return ( $whole === $float ) ? (int) $value : $float;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function scalar( $value ) {
		if ( is_array( $value ) || is_object( $value ) || null === $value ) {
			return '';
		}

		return trim( (string) $value );
	}
}
