<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Refdata {
	const BASE = 'https://services.mobile.de/refdata';

	const TTL = 2592000;

	const PREFIX = 'wmds_refdata_';

	/**
	 * @var array<string, array<string, string>>
	 */
	private static $memo = array();

	/**
	 * @var array<string, string>
	 */
	private static $tables = array(
		'gearbox'         => 'gearboxes',
		'fuel'            => 'fuels',
		'exteriorColor'   => 'colors',
		'interiorColor'   => 'interiorcolors',
		'interiorType'    => 'interiortypes',
		'condition'       => 'conditions',
		'doors'           => 'doorcounts',
		'emissionClass'   => 'emissionclasses',
		'emissionSticker' => 'emissionstickers',
		'usageType'       => 'usagetypes',
		'climatisation'   => 'climatisations',
		'airbag'          => 'airbags',
		'countryVersion'  => 'countries',
		'category'        => 'classes/%class%/categories',
		'make'            => 'classes/%class%/makes',
	);

	/** @var string */
	private $lang;

	/**
	 * @param string $lang Language code for Accept-Language (cz, de, en, es, fr, it, pl, ro, ru).
	 */
	public function __construct( $lang = 'en' ) {
		$this->lang = $lang ? $lang : 'en';
	}

	/**
	 * @param string $field         Field name from the ad, e.g. 'gearbox'.
	 * @param string $key           The key, e.g. 'MANUAL_GEAR'.
	 * @param string $vehicle_class The vehicle's vehicleClass, needed for make/category.
	 * @return string Localised label, otherwise the key in readable form.
	 */
	public function label( $field, $key, $vehicle_class = 'Car' ) {
		$key = trim( (string) $key );
		if ( '' === $key || ! isset( self::$tables[ $field ] ) ) {
			return $key;
		}

		$path  = str_replace( '%class%', rawurlencode( $vehicle_class ), self::$tables[ $field ] );
		$table = $this->table( $path );

		return isset( $table[ $key ] ) ? $table[ $key ] : self::readable( $key );
	}

	/**
	 * What to show for a key the reference data has no label for.
	 *
	 * The API gains values before they are documented, and the reference
	 * endpoint has bad days. MERCEDES-BENZ and AUTOMATIC_GEAR on a public
	 * page look like a broken site; Mercedes-Benz and Automatic Gear do not.
	 *
	 * @param string $key
	 * @return string
	 */
	public static function readable( $key ) {
		$key = trim( (string) $key );

		if ( '' === $key || ! preg_match( '/^[A-Z0-9_ -]+$/', $key ) ) {
			return $key;
		}

		// Short all-capitals keys are names in their own right: BMW, MG, DS.
		if ( strlen( $key ) <= 3 ) {
			return $key;
		}

		return ucwords( strtolower( str_replace( '_', ' ', $key ) ), ' -' );
	}

	/**
	 * @param string $path Path below /refdata, e.g. 'gearboxes'.
	 * @return array<string, string>
	 */
	private function table( $path ) {
		$cache_key = self::PREFIX . $this->lang . '_' . md5( $path );

		if ( isset( self::$memo[ $cache_key ] ) ) {
			return self::$memo[ $cache_key ];
		}

		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			self::$memo[ $cache_key ] = $cached;
			return $cached;
		}

		$table = $this->fetch( $path );

		set_transient( $cache_key, $table, $table ? self::TTL : 300 );
		self::$memo[ $cache_key ] = $table;

		return $table;
	}

	/**
	 * @param string $path
	 * @return array<string, string> Empty array on any error.
	 */
	protected function fetch( $path ) {
		$response = wp_remote_get(
			self::BASE . '/' . $path,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'          => 'application/vnd.de.mobile.api+json',
					'Accept-Language' => $this->lang,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		return self::parse( (string) wp_remote_retrieve_body( $response ) );
	}

	/**
	 * @param string $body
	 * @return array<string, string>
	 */
	public static function parse( $body ) {
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['values'] ) || ! is_array( $data['values'] ) ) {
			return array();
		}

		$out = array();
		foreach ( $data['values'] as $item ) {
			if ( ! is_array( $item ) || empty( $item['name'] ) ) {
				continue;
			}
			$name = (string) $item['name'];
			$desc = isset( $item['description'] ) ? trim( (string) $item['description'] ) : '';
			if ( '' !== $desc ) {
				$out[ $name ] = $desc;
			}
		}

		return $out;
	}

	public static function flush() {
		global $wpdb;

		self::$memo = array();

		if ( isset( $wpdb ) ) {
			$like = $wpdb->esc_like( '_transient_' . self::PREFIX ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
			$like = $wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		}
	}

	/**
	 * @return string[]
	 */
	public static function resolvable_fields() {
		return array_keys( self::$tables );
	}
}
