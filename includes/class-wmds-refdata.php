<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves mobile.de reference data into readable labels.
 *
 * Background: the current data format (New JSON) returns only a field's key,
 * e.g. gearbox = "MANUAL_GEAR". The Accept-Language header has no effect
 * there - per the documentation it applies to Legacy XML only, and a "Legacy
 * JSON" does not exist (the API answers application/json with HTTP 406).
 * Translated labels therefore have to come from the refdata endpoints.
 *
 * Those endpoints are unauthenticated, localisable via Accept-Language, and
 * practically static - so fetch once and cache for a long time:
 *
 *   GET /refdata/gearboxes   (Accept-Language: en)
 *   {"values":[{"name":"MANUAL_GEAR","description":"Manual gearbox", ...}]}
 *
 * If the fetch fails, label() returns the key itself. A vehicle then shows
 * "MANUAL_GEAR" instead of "Manual gearbox" - ugly, but the import keeps
 * running.
 */
class WMDS_Refdata {

	const BASE = 'https://services.mobile.de/refdata';

	/** How long a table is cached. Reference data is practically static. */
	const TTL = 2592000; // 30 days

	/** Transient prefix, so they can be deleted selectively. */
	const PREFIX = 'wmds_refdata_';

	/**
	 * Runtime cache, so one import run does not read the same table from the
	 * database repeatedly.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static $memo = array();

	/**
	 * Fixed mapping of field name -> refdata path.
	 *
	 * Only fields that surface in templates or FacetWP facets. '%class%' is
	 * replaced with the vehicle's vehicleClass (e.g. "Car").
	 *
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
	 * Translates a field's key into its localised label.
	 *
	 * @param string $field         Field name from the ad, e.g. 'gearbox'.
	 * @param string $key           The key, e.g. 'MANUAL_GEAR'.
	 * @param string $vehicle_class The vehicle's vehicleClass, needed for make/category.
	 * @return string Localised label, otherwise the key itself.
	 */
	public function label( $field, $key, $vehicle_class = 'Car' ) {
		$key = trim( (string) $key );
		if ( '' === $key || ! isset( self::$tables[ $field ] ) ) {
			return $key;
		}

		$path  = str_replace( '%class%', rawurlencode( $vehicle_class ), self::$tables[ $field ] );
		$table = $this->table( $path );

		return isset( $table[ $key ] ) ? $table[ $key ] : $key;
	}

	/**
	 * Loads a refdata table as key => description and caches it.
	 *
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

		// Cache an empty result too, but only briefly - otherwise every
		// import runs into the same timeout again during an outage.
		set_transient( $cache_key, $table, $table ? self::TTL : 300 );
		self::$memo[ $cache_key ] = $table;

		return $table;
	}

	/**
	 * Fetches a table from the API. No authentication needed.
	 *
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
	 * Converts a refdata body into key => description.
	 *
	 * Deliberately public and static so the conversion is testable without
	 * HTTP.
	 *
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
			// Without a description the entry is useless - the fallback applies.
			if ( '' !== $desc ) {
				$out[ $name ] = $desc;
			}
		}

		return $out;
	}

	/**
	 * Discards every cached table. Backs the "flush cache" button on the
	 * settings screen.
	 */
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
	 * Which fields get resolved at all. For tests and diagnostics.
	 *
	 * @return string[]
	 */
	public static function resolvable_fields() {
		return array_keys( self::$tables );
	}
}
