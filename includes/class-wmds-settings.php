<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Settings {
	const OPTION = 'wmds_settings';

	const DEFAULT_SLUG = 'fahrzeuge';

	/**
	 * @return array<string, array{type:string,default:mixed,choices?:array}>
	 */
	public static function fields() {
		return array(
			'username'            => array(
				'type'    => 'text',
				'default' => '',
			),
			'password'            => array(
				'type'    => 'password',
				'default' => '',
			),
			'seller_id'           => array(
				'type'    => 'digits',
				'default' => '',
			),
			'dealer'              => array(
				'type'    => 'text',
				'default' => '',
			),
			'interval'            => array(
				'type'    => 'choice',
				'default' => 'wmds_15min',
				'choices' => array( 'wmds_5min', 'wmds_15min', 'wmds_30min', 'wmds_60min', 'daily' ),
			),
			'language'            => array(
				'type'    => 'choice',
				'default' => '',
				'choices' => array( '', 'de', 'en', 'fr', 'it', 'es', 'nl', 'pl' ),
			),
			'slug'                => array(
				'type'    => 'slug',
				'default' => '',
			),
			'alerts_enabled'      => array(
				'type'    => 'bool',
				'default' => 'no',
			),
			'alerts_recipient'    => array(
				'type'    => 'emails',
				'default' => '',
			),
			'alerts_cooldown'     => array(
				'type'    => 'choice',
				'default' => 21600,
				'choices' => array( 3600, 21600, 43200, 86400 ),
			),
			'alerts_weekly'       => array(
				'type'    => 'bool',
				'default' => 'no',
			),
			'enquiry_enabled'     => array(
				'type'    => 'bool',
				'default' => 'yes',
			),
			'enquiry_recipient'   => array(
				'type'    => 'emails',
				'default' => '',
			),
			'enquiry_copy_seller' => array(
				'type'    => 'bool',
				'default' => 'no',
			),
			'enquiry_autoreply'   => array(
				'type'    => 'bool',
				'default' => 'no',
			),
			'enquiry_store'       => array(
				'type'    => 'bool',
				'default' => 'yes',
			),
			'enquiry_consent'     => array(
				'type'    => 'html',
				'default' => '',
			),
			'similar_enabled'     => array(
				'type'    => 'bool',
				'default' => 'yes',
			),
			'cf7_store'           => array(
				'type'    => 'bool',
				'default' => 'yes',
			),
			'cf7_copy_seller'     => array(
				'type'    => 'bool',
				'default' => 'no',
			),
			'facetwp_reindex'     => array(
				'type'    => 'bool',
				'default' => 'yes',
			),
		);
	}

	/**
	 * @param string     $key
	 * @param mixed|null $fallback
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$opts = get_option( self::OPTION, array() );
		if ( is_array( $opts ) && isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) {
			return $opts[ $key ];
		}

		if ( null !== $fallback ) {
			return $fallback;
		}

		$fields = self::fields();

		return isset( $fields[ $key ] ) ? $fields[ $key ]['default'] : '';
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public static function flag( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * @param string[] $keys
	 * @param array    $request
	 * @return array<string, mixed>
	 */
	public static function from_request( array $keys, array $request ) {
		$fields = self::fields();
		$values = array();

		foreach ( $keys as $key ) {
			if ( ! isset( $fields[ $key ] ) ) {
				continue;
			}

			$field = $fields[ $key ];
			$raw   = isset( $request[ 'wmds_' . $key ] ) ? $request[ 'wmds_' . $key ] : null;
			$raw   = is_scalar( $raw ) ? (string) $raw : null;

			switch ( $field['type'] ) {
				case 'bool':
					$values[ $key ] = ( null !== $raw && '' !== $raw ) ? 'yes' : 'no';
					break;

				case 'password':
					if ( null !== $raw && '' !== $raw ) {
						$values[ $key ] = $raw;
					}
					break;

				case 'digits':
					$values[ $key ] = preg_replace( '/\D/', '', (string) $raw );
					break;

				case 'emails':
					$values[ $key ] = implode( ', ', WMDS_Mail::addresses( (string) $raw, array(), false ) );
					break;

				case 'html':
					$values[ $key ] = wp_kses_post( (string) $raw );
					break;

				case 'slug':
					$values[ $key ] = sanitize_title( (string) $raw );
					break;

				case 'choice':
					$picked = null;
					foreach ( $field['choices'] as $choice ) {
						if ( (string) $choice === (string) $raw ) {
							$picked = $choice;
						}
					}
					$values[ $key ] = null === $picked ? $field['default'] : $picked;
					break;

				default:
					$values[ $key ] = sanitize_text_field( (string) $raw );
			}
		}

		return $values;
	}

	/** @return string */
	public static function username() {
		return trim( (string) self::get( 'username' ) );
	}

	/** @return string */
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

	/**
	 * @return string
	 */
	public static function slug() {
		$slug = sanitize_title( (string) self::get( 'slug' ) );

		/**
		 * @param string $slug
		 */
		$slug = sanitize_title( (string) apply_filters( 'wmds_slug', '' !== $slug ? $slug : self::DEFAULT_SLUG ) );

		return '' !== $slug ? $slug : self::DEFAULT_SLUG;
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
		$fields = self::fields();
		$value  = (string) self::get( 'interval' );

		return in_array( $value, $fields['interval']['choices'], true ) ? $value : $fields['interval']['default'];
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
	 * @param array $values
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
