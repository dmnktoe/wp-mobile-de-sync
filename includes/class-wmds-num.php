<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two readings of a number, kept apart on purpose.
 *
 * A person typing 12.500 into a price field means twelve and a half
 * thousand. The API sending 12.500 means twelve and a half. Reading either
 * one with the other's rule is wrong by a factor of a thousand, so there is
 * no general "parse a number" here and there should not be.
 */
class WMDS_Num {
	/**
	 * @param mixed $value As typed into a form or handed over in a URL.
	 * @return float|null Null when it is not a number at all.
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
	 * @param mixed $value As the API states it.
	 * @return int|float|null Whole numbers come back as integers, so a JSON
	 *                        encoder writes 5 rather than 5.0.
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
