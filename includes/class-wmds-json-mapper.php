<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translates an ad in the current mobile.de format (New JSON) into the meta
 * structure that theme templates and FacetWP facets expect.
 *
 * Expects the most complete array available. The importer therefore layers
 * the single-ad response over the search result (array_merge), because on a
 * live feed:
 *   - description (formatted) is absent from the search result entirely, and
 *     plainTextDescription is an empty string there
 *   - companyName and the seller's phone numbers exist only in the single-ad
 *     call; seller.phones is an empty array in a search result
 *
 * All key values (DIESEL, MANUAL_GEAR, BLACK ...) are resolved to readable
 * labels through WMDS_Refdata. Without a refdata instance the keys survive
 * unchanged - the import still runs.
 */
class WMDS_Json_Mapper {

	/**
	 * The 28 feature keys the templates query.
	 *
	 * The current data format has no common features list; the individual
	 * features live in separate fields of three different shapes. The labels
	 * therefore come from us, which also makes them identical across every
	 * site rather than dependent on what the API happens to return.
	 *
	 * Labels are resolved through feature_labels() at map time, so they
	 * are stored in the site's language.
	 *
	 * type: bool  - field is true
	 *       enum  - field has a value; 'values' narrows it further
	 *       list  - field is a list containing one of 'values'
	 *
	 * @var array<string, array>
	 */
	private static $features = array(
		'ABS'                       => array(
			'type'  => 'bool',
			'field' => 'abs',
		),
		'AUTOMATIC_RAIN_SENSOR'     => array(
			'type'  => 'bool',
			'field' => 'automaticRainSensor',
		),
		'AUXILIARY_HEATING'         => array(
			'type'  => 'bool',
			'field' => 'auxiliaryHeating',
		),
		'CENTRAL_LOCKING'           => array(
			'type'  => 'bool',
			'field' => 'centralLocking',
		),
		'ELECTRIC_ADJUSTABLE_SEATS' => array(
			'type'  => 'bool',
			'field' => 'electricAdjustableSeats',
		),
		'ELECTRIC_EXTERIOR_MIRRORS' => array(
			'type'  => 'bool',
			'field' => 'electricExteriorMirrors',
		),
		'ELECTRIC_HEATED_SEATS'     => array(
			'type'  => 'bool',
			'field' => 'electricHeatedSeats',
		),
		'ELECTRIC_WINDOWS'          => array(
			'type'  => 'bool',
			'field' => 'electricWindows',
		),
		'ESP'                       => array(
			'type'  => 'bool',
			'field' => 'esp',
		),
		'FRONT_FOG_LIGHTS'          => array(
			'type'  => 'bool',
			'field' => 'frontFogLights',
		),
		'FULL_SERVICE_HISTORY'      => array(
			'type'  => 'bool',
			'field' => 'fullServiceHistory',
		),
		'HEAD_UP_DISPLAY'           => array(
			'type'  => 'bool',
			'field' => 'headUpDisplay',
		),
		'IMMOBILIZER'               => array(
			'type'  => 'bool',
			'field' => 'immobilizer',
		),
		'ISOFIX'                    => array(
			'type'  => 'bool',
			'field' => 'isofix',
		),
		'LIGHT_SENSOR'              => array(
			'type'  => 'bool',
			'field' => 'lightSensor',
		),
		'METALLIC'                  => array(
			'type'  => 'bool',
			'field' => 'metallic',
		),
		'MULTIFUNCTIONAL_WHEEL'     => array(
			'type'  => 'bool',
			'field' => 'multifunctionalWheel',
		),
		'NAVIGATION_SYSTEM'         => array(
			'type'  => 'bool',
			'field' => 'navigationSystem',
		),
		'PANORAMIC_GLASS_ROOF'      => array(
			'type'  => 'bool',
			'field' => 'panoramicGlassRoof',
		),
		'POWER_ASSISTED_STEERING'   => array(
			'type'  => 'bool',
			'field' => 'powerAssistedSteering',
		),
		'START_STOP_SYSTEM'         => array(
			'type'  => 'bool',
			'field' => 'startStopSystem',
		),
		'SUNROOF'                   => array(
			'type'  => 'bool',
			'field' => 'sunroof',
		),
		'TRACTION_CONTROL_SYSTEM'   => array(
			'type'  => 'bool',
			'field' => 'tractionControlSystem',
		),

		'XENON_HEADLIGHTS'          => array(
			'type'   => 'enum',
			'field'  => 'headlightType',
			'values' => array( 'XENON_HEADLIGHTS' ),
		),
		'BENDING_LIGHTS'            => array(
			'type'  => 'enum',
			'field' => 'bendingLightsType',
		),
		'DAYTIME_RUNNING_LIGHTS'    => array(
			'type'  => 'enum',
			'field' => 'daytimeRunningLamps',
		),
		'CRUISE_CONTROL'            => array(
			'type'   => 'enum',
			'field'  => 'speedControl',
			'values' => array( 'CRUISE_CONTROL', 'ADAPTIVE_CRUISE_CONTROL' ),
		),
		// A reversing camera alone is not parking sensors - the feed lists
		// REAR_VIEW_CAM in the same array.
		'PARKING_SENSORS'           => array(
			'type'   => 'list',
			'field'  => 'parkingAssistants',
			'values' => array( 'FRONT_SENSORS', 'REAR_SENSORS' ),
		),
	);

	/** @var WMDS_Refdata|null */
	private $refdata;

	/**
	 * @param WMDS_Refdata|null $refdata Label resolver; without it the raw
	 *                                   keys survive unchanged.
	 */
	public function __construct( $refdata = null ) {
		$this->refdata = $refdata;
	}

	/**
	 * @param array $ad Ad array, ideally including the single-ad fields.
	 * @return array{ad_id:string,title:string,description:string,modified:string,images:array,meta:array}
	 */
	public function map( array $ad ) {
		$ad_id = isset( $ad['mobileAdId'] ) ? (string) $ad['mobileAdId'] : '';
		$class = isset( $ad['vehicleClass'] ) ? (string) $ad['vehicleClass'] : 'Car';

		$meta = array();

		/* --- Make, model, category --------------------------------------- */

		$make_key = isset( $ad['make'] ) ? (string) $ad['make'] : '';
		$make     = $this->label( 'make', $make_key, $class );

		$meta['make']      = $make;
		$meta['make_key']  = $make_key; // Language independent, drives the logo lookup.
		$meta['model']     = isset( $ad['model'] ) ? (string) $ad['model'] : '';
		$meta['category']  = $this->label( 'category', $this->str( $ad, 'category' ), $class );
		$meta['condition'] = $this->label( 'condition', $this->str( $ad, 'condition' ), $class );

		/*
		--- Title ---------------------------------------------------------
		 * Per the documentation modelDescription is "also used as ad title
		 * together with the make". The model name is already in there -
		 * prefixing model as well would produce "Land Rover Defender Defender
		 * 90 ...". The feed's detailPageUrl slug confirms make +
		 * modelDescription.
		 */
		$model_desc = WMDS_Creole::decode_field( $this->str( $ad, 'modelDescription' ) );
		$title      = trim( $make . ' ' . ( '' !== $model_desc ? $model_desc : $meta['model'] ) );
		if ( '' === $title ) {
			$title = 'Vehicle ' . $ad_id;
		}

		/* --- Price --------------------------------------------------------- */

		$price = isset( $ad['price'] ) && is_array( $ad['price'] ) ? $ad['price'] : array();
		$gross = isset( $price['consumerPriceGross'] ) ? (string) $price['consumerPriceGross'] : '';

		$meta['price']    = ( '' !== $gross ) ? self::number( (float) $gross ) : '';
		$meta['currency'] = isset( $price['currency'] ) ? (string) $price['currency'] : 'EUR';

		// There is no dedicated vatable field. vatRate is present only when
		// VAT is actually reclaimable, so its presence is the signal. The
		// templates compare literally against 'false', hence these two values.
		$meta['vatable']  = isset( $price['vatRate'] ) && '' !== $price['vatRate'] ? 'true' : 'false';
		$meta['vat_rate'] = isset( $price['vatRate'] ) ? (string) $price['vatRate'] : '';

		$meta['price_raw']       = ( '' !== $gross ) ? (int) round( (float) $gross ) : '';
		$meta['price_raw_short'] = self::price_bucket( $meta['price_raw'] );

		/* --- Key figures ---------------------------------------------------- */

		$mileage             = isset( $ad['mileage'] ) ? (int) $ad['mileage'] : null;
		$meta['mileage']     = ( null !== $mileage ) ? self::number( $mileage ) : '';
		$meta['mileage_raw'] = ( null !== $mileage ) ? $mileage : '';

		$meta['power']          = isset( $ad['power'] ) ? (int) $ad['power'] : '';
		$meta['cubic_capacity'] = $this->str( $ad, 'cubicCapacity' );
		$meta['num_seats']      = $this->str( $ad, 'seats' );

		$meta['fuel']       = $this->label( 'fuel', $this->str( $ad, 'fuel' ), $class );
		$meta['gearbox']    = $this->label( 'gearbox', $this->str( $ad, 'gearbox' ), $class );
		$meta['door_count'] = $this->label( 'doors', $this->str( $ad, 'doors' ), $class );

		/* --- Registration, inspection, history ------------------------------ */

		$first_reg                      = $this->str( $ad, 'firstRegistration' );
		$meta['firstRegistration']      = self::format_month( $first_reg );
		$meta['firstRegistration_year'] = self::year( $first_reg );

		// generalInspection is optional and dealers who sell with a fresh
		// inspection do not fill it in - they set newHuAu instead. Both are
		// mapped; the template decides.
		$meta['nextInspection'] = self::format_month( $this->str( $ad, 'generalInspection' ) );
		$meta['newHuAu']        = ! empty( $ad['newHuAu'] ) ? __( 'New inspection on purchase', 'wp-mobile-de-sync' ) : '';

		$meta['construction-year'] = $this->str( $ad, 'constructionYear' );
		$meta['owners']            = $this->str( $ad, 'numberOfPreviousOwners' );

		// The templates compare literally against 'false'.
		$meta['damageRepaired'] = ! empty( $ad['damageUnrepaired'] ) ? 'true' : 'false';
		$meta['roadWorthy']     = ! empty( $ad['roadworthy'] ) ? 'true' : 'false';

		/* --- Colours, interior ---------------------------------------------- */

		$meta['exteriorColor']           = $this->label( 'exteriorColor', $this->str( $ad, 'exteriorColor' ), $class );
		$meta['manufacturer_color_name'] = WMDS_Creole::decode_field( $this->str( $ad, 'manufacturerColorName' ) );
		$meta['interior_type']           = $this->label( 'interiorType', $this->str( $ad, 'interiorType' ), $class );
		$meta['interior_color']          = $this->label( 'interiorColor', $this->str( $ad, 'interiorColor' ), $class );

		/* --- Emissions and consumption --------------------------------------- */

		$meta['emissionClass']   = $this->label( 'emissionClass', $this->str( $ad, 'emissionClass' ), $class );
		$meta['emissionSticker'] = $this->label( 'emissionSticker', $this->str( $ad, 'emissionSticker' ), $class );

		$fuel_c                                   = self::dig( $ad, array( 'consumptions', 'fuel' ) );
		$meta['emissionFuelConsumption_Combined'] = $this->str( $fuel_c, 'combined' );
		$meta['emissionFuelConsumption_Inner']    = $this->str( $fuel_c, 'city' );
		$meta['emissionFuelConsumption_Outer']    = $this->str( $fuel_c, 'highway' );

		$power_c                          = self::dig( $ad, array( 'consumptions', 'power' ) );
		$meta['combinedPowerConsumption'] = $this->str( $power_c, 'combined' );

		$meta['emissionFuelConsumption_CO2'] = $this->str(
			self::dig( $ad, array( 'emissions', 'combined' ) ),
			'co2'
		);

		/*
		--- Availability ---------------------------------------------------
		 * Per the documentation deliveryPeriod applies to new vehicles only.
		 * A value of at most one day means immediately available.
		 */
		$meta['available_from'] = '';
		// A separate, language-independent flag: comparing the display string
		// against a translated literal breaks the moment it is translated.
		$meta['available_now'] = '';
		if ( isset( $ad['deliveryPeriod'] ) && (int) $ad['deliveryPeriod'] <= 1 ) {
			$meta['available_from'] = __( 'Immediately', 'wp-mobile-de-sync' );
			$meta['available_now']  = 'true';
		} elseif ( '' !== $this->str( $ad, 'deliveryDate' ) ) {
			$meta['available_from'] = self::format_day( $this->str( $ad, 'deliveryDate' ) );
		}

		/* --- Other ----------------------------------------------------------- */

		$meta['vehicleListingID'] = $ad_id;
		$meta['schwacke-code']    = $this->str( $ad, 'schwackeCode' );
		$meta['detail_page_url']  = $this->str( $ad, 'detailPageUrl' );

		/* --- Seller ------------------------------------------------------------ */

		$seller = isset( $ad['seller'] ) && is_array( $ad['seller'] ) ? $ad['seller'] : array();

		$meta['seller']       = $this->str( $seller, 'companyName' );
		$meta['seller_email'] = $this->str( $seller, 'email' );

		if ( ! empty( $seller['phones'] ) && is_array( $seller['phones'] ) ) {
			$phone = reset( $seller['phones'] );
			if ( is_array( $phone ) ) {
				$meta['seller_phone_country_calling_code'] = $this->str( $phone, 'internationalPrefix' );
				$meta['seller_phone_area_code']            = $this->str( $phone, 'nationalPrefix' );
				$meta['seller_phone_number']               = $this->str( $phone, 'number' );
			}
		}

		/* --- Features ---------------------------------------------------------- */

		$labels = self::feature_labels();

		foreach ( self::$features as $key => $rule ) {
			if ( self::has_feature( $ad, $rule ) ) {
				// Translated at map time, because the label is stored in
				// post meta - exactly like the refdata labels above.
				$meta[ $key ] = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
			}
		}

		/* --- Description ------------------------------------------------------- */

		$description = $this->str( $ad, 'description' );
		$plain       = $this->str( $ad, 'plainTextDescription' );

		$meta['enriched_description'] = WMDS_Creole::to_html( '' !== $description ? $description : $plain );

		return array(
			'ad_id'       => $ad_id,
			'title'       => $title,
			'description' => $plain,
			'modified'    => $this->str( $ad, 'modificationDate' ),
			'images'      => self::collect_images( $ad ),
			'meta'        => $meta,
		);
	}

	// --------------------------------------------------------------------
	// Helpers
	// --------------------------------------------------------------------

	/**
	 * Formats a whole number for display, following the site's locale.
	 *
	 * An English site gets 78,000, a German one 78.000. The value is stored
	 * in post meta, so the formatting is fixed at import time - same as the
	 * refdata labels.
	 *
	 * @param float|int $value
	 * @return string
	 */
	private static function number( $value ) {
		return function_exists( 'number_format_i18n' )
			? number_format_i18n( $value, 0 )
			: number_format( $value, 0, '.', ',' );
	}

	/**
	 * Labels for the feature keys.
	 *
	 * A method rather than a property, because __() cannot run in a static
	 * property initialiser - and passing a variable to __() would leave the
	 * strings invisible to the extractor. Here they are plain literals.
	 *
	 * @return array<string, string>
	 */
	private static function feature_labels() {
		return array(
			'ABS'                       => __( 'ABS', 'wp-mobile-de-sync' ),
			'AUTOMATIC_RAIN_SENSOR'     => __( 'Rain sensor', 'wp-mobile-de-sync' ),
			'AUXILIARY_HEATING'         => __( 'Auxiliary heating', 'wp-mobile-de-sync' ),
			'CENTRAL_LOCKING'           => __( 'Central locking', 'wp-mobile-de-sync' ),
			'ELECTRIC_ADJUSTABLE_SEATS' => __( 'Electrically adjustable seats', 'wp-mobile-de-sync' ),
			'ELECTRIC_EXTERIOR_MIRRORS' => __( 'Electrically adjustable mirrors', 'wp-mobile-de-sync' ),
			'ELECTRIC_HEATED_SEATS'     => __( 'Heated seats', 'wp-mobile-de-sync' ),
			'ELECTRIC_WINDOWS'          => __( 'Electric windows', 'wp-mobile-de-sync' ),
			'ESP'                       => __( 'ESP', 'wp-mobile-de-sync' ),
			'FRONT_FOG_LIGHTS'          => __( 'Front fog lights', 'wp-mobile-de-sync' ),
			'FULL_SERVICE_HISTORY'      => __( 'Full service history', 'wp-mobile-de-sync' ),
			'HEAD_UP_DISPLAY'           => __( 'Head-up display', 'wp-mobile-de-sync' ),
			'IMMOBILIZER'               => __( 'Immobiliser', 'wp-mobile-de-sync' ),
			'ISOFIX'                    => __( 'Isofix', 'wp-mobile-de-sync' ),
			'LIGHT_SENSOR'              => __( 'Light sensor', 'wp-mobile-de-sync' ),
			'METALLIC'                  => __( 'Metallic paint', 'wp-mobile-de-sync' ),
			'MULTIFUNCTIONAL_WHEEL'     => __( 'Multifunction steering wheel', 'wp-mobile-de-sync' ),
			'NAVIGATION_SYSTEM'         => __( 'Navigation system', 'wp-mobile-de-sync' ),
			'PANORAMIC_GLASS_ROOF'      => __( 'Panoramic glass roof', 'wp-mobile-de-sync' ),
			'POWER_ASSISTED_STEERING'   => __( 'Power steering', 'wp-mobile-de-sync' ),
			'START_STOP_SYSTEM'         => __( 'Start/stop system', 'wp-mobile-de-sync' ),
			'SUNROOF'                   => __( 'Sunroof', 'wp-mobile-de-sync' ),
			'TRACTION_CONTROL_SYSTEM'   => __( 'Traction control', 'wp-mobile-de-sync' ),
			'XENON_HEADLIGHTS'          => __( 'Xenon headlights', 'wp-mobile-de-sync' ),
			'BENDING_LIGHTS'            => __( 'Cornering lights', 'wp-mobile-de-sync' ),
			'DAYTIME_RUNNING_LIGHTS'    => __( 'Daytime running lights', 'wp-mobile-de-sync' ),
			'CRUISE_CONTROL'            => __( 'Cruise control', 'wp-mobile-de-sync' ),
			'PARKING_SENSORS'           => __( 'Parking sensors', 'wp-mobile-de-sync' ),
		);
	}

	/**
	 * Tests one feature rule against an ad.
	 *
	 * @param array $ad
	 * @param array $rule
	 * @return bool
	 */
	private static function has_feature( array $ad, array $rule ) {
		$field = $rule['field'];
		if ( ! isset( $ad[ $field ] ) ) {
			return false;
		}
		$value = $ad[ $field ];

		if ( 'bool' === $rule['type'] ) {
			return true === $value;
		}

		if ( 'enum' === $rule['type'] ) {
			if ( ! is_string( $value ) || '' === $value ) {
				return false;
			}
			return empty( $rule['values'] ) || in_array( $value, $rule['values'], true );
		}

		if ( 'list' === $rule['type'] ) {
			if ( ! is_array( $value ) ) {
				return false;
			}
			if ( empty( $rule['values'] ) ) {
				return ! empty( $value );
			}
			return (bool) array_intersect( $value, $rule['values'] );
		}

		return false;
	}

	/**
	 * Collects the images with their hash. The hash is what lets the importer
	 * notice that a dealer swapped a photo.
	 *
	 * @param array $ad
	 * @return array<int, array{url:string,hash:string}>
	 */
	private static function collect_images( array $ad ) {
		if ( empty( $ad['images'] ) || ! is_array( $ad['images'] ) ) {
			return array();
		}

		// Largest first; not every representation is always present.
		$sizes = array( 'xxxl', 'xxl', 'xl', 'l', 'm', 's', 'icon' );
		$out   = array();

		foreach ( $ad['images'] as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}
			foreach ( $sizes as $size ) {
				if ( ! empty( $image[ $size ] ) ) {
					$out[] = array(
						'url'  => (string) $image[ $size ],
						'hash' => isset( $image['hash'] ) ? (string) $image['hash'] : '',
					);
					break;
				}
			}
		}

		return $out;
	}

	/**
	 * Reads a nested value without notices when a level is missing.
	 *
	 * @param array    $data
	 * @param string[] $path
	 * @return array
	 */
	private static function dig( array $data, array $path ) {
		foreach ( $path as $step ) {
			if ( ! isset( $data[ $step ] ) || ! is_array( $data[ $step ] ) ) {
				return array();
			}
			$data = $data[ $step ];
		}
		return $data;
	}

	/**
	 * Scalar field value as a trimmed string. Arrays and null yield ''.
	 *
	 * @param array  $data
	 * @param string $key
	 * @return string
	 */
	private function str( $data, $key ) {
		if ( ! is_array( $data ) || ! isset( $data[ $key ] ) || is_array( $data[ $key ] ) ) {
			return '';
		}
		if ( is_bool( $data[ $key ] ) ) {
			return $data[ $key ] ? 'true' : 'false';
		}
		return trim( (string) $data[ $key ] );
	}

	/**
	 * @param string $field
	 * @param string $key
	 * @param string $class
	 * @return string
	 */
	private function label( $field, $key, $class ) {
		if ( '' === $key ) {
			return '';
		}
		return $this->refdata ? $this->refdata->label( $field, $key, $class ) : $key;
	}

	/**
	 * Normalises yyyyMM or yyyy-MM to yyyy-MM-01, so strtotime() works in the
	 * template.
	 *
	 * @param string $value
	 * @return string
	 */
	public static function format_month( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^(\d{4})-?(\d{2})/', $value, $m ) ) {
			return $m[1] . '-' . $m[2] . '-01';
		}
		return $value;
	}

	/**
	 * ISO date or timestamp to DD.MM.YYYY.
	 *
	 * @param string $value
	 * @return string
	 */
	public static function format_day( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $value, $m ) ) {
			return $m[3] . '.' . $m[2] . '.' . $m[1];
		}
		return $value;
	}

	/**
	 * @param string $value
	 * @return int|string
	 */
	public static function year( $value ) {
		if ( preg_match( '/^(\d{4})/', trim( (string) $value ), $m ) ) {
			return (int) $m[1];
		}
		return '';
	}

	/**
	 * Coarse price bucket for the price_raw_short FacetWP facet.
	 *
	 * @param int|string $price
	 * @return string
	 */
	public static function price_bucket( $price ) {
		if ( '' === $price || ! is_numeric( $price ) ) {
			return '';
		}
		$price = (int) $price;
		if ( $price < 5000 ) {
			return 'bis 5.000 €';
		}
		if ( $price < 10000 ) {
			return '5.000 – 10.000 €';
		}
		if ( $price < 20000 ) {
			return '10.000 – 20.000 €';
		}
		if ( $price < 30000 ) {
			return '20.000 – 30.000 €';
		}
		if ( $price < 50000 ) {
			return '30.000 – 50.000 €';
		}
		return 'ab 50.000 €';
	}

	/**
	 * The feature keys covered. For tests and diagnostics.
	 *
	 * @return string[]
	 */
	public static function feature_keys() {
		return array_keys( self::$features );
	}
}
