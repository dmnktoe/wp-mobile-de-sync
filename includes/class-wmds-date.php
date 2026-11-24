<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Date {
	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function iso_month( $value ) {
		$value = self::text( $value );

		if ( preg_match( '/^(\d{4})-?(\d{2})/', $value, $m ) ) {
			return $m[1] . '-' . $m[2] . '-01';
		}
		if ( preg_match( '/^(\d{2})\.(\d{4})$/', $value, $m ) ) {
			return $m[2] . '-' . $m[1] . '-01';
		}

		return '';
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function month_year( $value ) {
		$value = self::text( $value );

		if ( preg_match( '/^(\d{4})-(\d{2})/', $value, $m ) ) {
			return $m[2] . '.' . $m[1];
		}

		return $value;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function day( $value ) {
		$value = self::text( $value );

		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $value, $m ) ) {
			return $m[3] . '.' . $m[2] . '.' . $m[1];
		}

		return $value;
	}

	/**
	 * @param mixed $value
	 * @return int|string
	 */
	public static function year( $value ) {
		if ( preg_match( '/^(\d{4})/', self::text( $value ), $m ) ) {
			return (int) $m[1];
		}

		return '';
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function text( $value ) {
		if ( is_array( $value ) || is_object( $value ) || null === $value ) {
			return '';
		}

		return trim( (string) $value );
	}
}
