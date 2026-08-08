<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shapes a date arrives in, and the two it leaves in.
 *
 * The feed states a month as 201906 or 2019-06, a day as 2019-06-14. Post
 * meta keeps months as 2019-06-01 so they sort and compare as dates. What a
 * reader sees is 06.2019 and 14.06.2019.
 */
class WMDS_Date {
	/**
	 * @param mixed $value
	 * @return string YYYY-MM-01, empty when there is no month in there.
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
	 * @return string MM.YYYY, or the value unchanged when it is not a month.
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
	 * @return string DD.MM.YYYY, or the value unchanged when it is not a day.
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
	 * @return int|string The year, empty string when there is none.
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
