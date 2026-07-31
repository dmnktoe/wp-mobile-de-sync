<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Settings {
	const OPTION = 'wmds_settings';

	/**
	 * @param string $key      Setting name.
	 * @param string $fallback Returned when the setting is unset or empty.
	 * @return mixed
	 */
	public static function get( $key, $fallback = '' ) {
		$opts = get_option( self::OPTION, array() );
		if ( is_array( $opts ) && isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) {
			return $opts[ $key ];
		}
		return $fallback;
	}

	/** @return string The mobile.de API username. */
	public static function username() {
		return trim( (string) self::get( 'username' ) );
	}

	/** @return string The mobile.de API password. */
	public static function password() {
		return (string) self::get( 'password' );
	}

	/**
	 * @return string
	 */
	public static function language() {
		$stored = (string) self::get( 'language' );
		if ( '' !== $stored ) {
			return $stored;
		}

		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : '';
		$code   = strtolower( substr( $locale, 0, 2 ) );

		return preg_match( '/^[a-z]{2}$/', $code ) ? $code : 'en';
	}

	public static function seller_id() {
		return preg_replace( '/\D/', '', (string) self::get( 'seller_id' ) );
	}

	public static function dealer() {
		return trim( (string) self::get( 'dealer' ) );
	}

	/**
	 * @return string
	 */
	public static function interval() {
		$allowed = array( 'wmds_5min', 'wmds_15min', 'wmds_30min', 'wmds_60min' );
		$value   = (string) self::get( 'interval', 'wmds_15min' );

		return in_array( $value, $allowed, true ) ? $value : 'wmds_15min';
	}

	/**
	 * @return WMDS_Client
	 */
	public static function client() {
		return new WMDS_Client(
			self::username(),
			self::password(),
			self::seller_id(),
			self::dealer()
		);
	}

	public static function is_configured() {
		return '' !== self::username()
			&& '' !== self::password()
			&& ( '' !== self::seller_id() || '' !== self::dealer() );
	}

	/**
	 * @param array $values Setting name => value.
	 */
	public static function update( array $values ) {
		$opts = get_option( self::OPTION, array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		$opts = array_merge( $opts, $values );
		update_option( self::OPTION, $opts );
	}
}
