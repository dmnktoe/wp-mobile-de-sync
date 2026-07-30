<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central configuration.
 *
 * Credentials and settings live exclusively in this plugin's own
 * 'wmds_settings' option and are edited on the plugin's settings screen.
 * Nothing reads options belonging to other plugins.
 */
class WMDS_Settings {

	const OPTION = 'wmds_settings';

	public static function get( $key, $default = '' ) {
		$opts = get_option( self::OPTION, array() );
		if ( is_array( $opts ) && isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) {
			return $opts[ $key ];
		}
		return $default;
	}

	public static function username() {
		return trim( (string) self::get( 'username' ) );
	}

	public static function password() {
		return (string) self::get( 'password' );
	}

	/**
	 * Language for reference-data labels, as an ISO 639-1 code.
	 *
	 * Defaults to the site's own language, so a fresh install shows labels
	 * the site's editors can read.
	 *
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

	/**
	 * Numeric mobile.de seller ID.
	 *
	 * The preferred way to address an inventory: customerId is the only
	 * documented parameter for it, verified against a live feed.
	 */
	public static function seller_id() {
		return preg_replace( '/\D/', '', (string) self::get( 'seller_id' ) );
	}

	/**
	 * Public dealer vanity name, used for the dealer= search.
	 *
	 * Fallback only: dealer= appears in no official parameter list and may
	 * disappear at any time. Useful when only the vanity name is known.
	 */
	public static function dealer() {
		return trim( (string) self::get( 'dealer' ) );
	}

	/**
	 * Cron interval, as a key registered by wmds_cron_schedules().
	 *
	 * @return string
	 */
	public static function interval() {
		$allowed = array( 'wmds_5min', 'wmds_15min', 'wmds_30min', 'wmds_60min' );
		$value   = (string) self::get( 'interval', 'wmds_15min' );

		return in_array( $value, $allowed, true ) ? $value : 'wmds_15min';
	}

	/**
	 * Fully configured API client.
	 *
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

	/**
	 * Are all required values present? Username, password, and at least one
	 * of the two ways to name the dealer.
	 */
	public static function is_configured() {
		return '' !== self::username()
			&& '' !== self::password()
			&& ( '' !== self::seller_id() || '' !== self::dealer() );
	}

	public static function update( array $values ) {
		$opts = get_option( self::OPTION, array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		$opts = array_merge( $opts, $values );
		update_option( self::OPTION, $opts );
	}
}
