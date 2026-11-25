<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Json_Mapper {
	/** @var array<string, array> */
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
		'PARKING_SENSORS'           => array(
			'type'   => 'list',
			'field'  => 'parkingAssistants',
			'values' => array( 'FRONT_SENSORS', 'REAR_SENSORS' ),
		),
	);

	/** @var string[] */
	private static $feature_skip = array(
		'accidentDamaged',
		'commercial',
		'damageUnrepaired',
		'metallic',
		'newHuAu',
		'roadworthy',
	);

	/** @var array<string, string> */
	private static $feature_aliases = array(
		'NON_SMOKER_VEHICLE'          => 'NONSMOKER_VEHICLE',
		'VEGETABLE_OIL_FUEL_SUITABLE' => 'VEGETABLEOILFUEL_SUITABLE',
		'PLUGIN_HYBRID'               => 'HYBRID_PLUGIN',
	);

	/** @var WMDS_Refdata|null */
	private $refdata;

	/** @param WMDS_Refdata|null $refdata */
	public function __construct( $refdata = null ) {
		$this->refdata = $refdata;
	}

	/**
	 * @param array $ad
	 * @return array{ad_id:string,title:string,description:string,modified:string,images:array,meta:array}
	 */
	public function map( array $ad ) {
		$ad_id = isset( $ad['mobileAdId'] ) ? (string) $ad['mobileAdId'] : '';
		$class = isset( $ad['vehicleClass'] ) ? (string) $ad['vehicleClass'] : 'Car';

		$meta = array_merge(
			$this->identity_meta( $ad, $class ),
			$this->price_meta( $ad ),
			$this->technical_meta( $ad, $class ),
			$this->history_meta( $ad ),
			$this->appearance_meta( $ad, $class ),
			$this->emission_meta( $ad, $class ),
			$this->equipment_meta( $ad, $class ),
			$this->consumption_meta( $ad ),
			$this->availability_meta( $ad ),
			array(
				'vehicleListingID' => $ad_id,
				'schwacke-code'    => $this->str( $ad, 'schwackeCode' ),
				'detail_page_url'  => $this->str( $ad, 'detailPageUrl' ),
			),
			$this->seller_meta( $ad )
		);

		$model_desc = WMDS_Creole::decode_field( $this->str( $ad, 'modelDescription' ) );
		$title      = trim( $meta['make'] . ' ' . ( '' !== $model_desc ? $model_desc : $meta['model'] ) );
		if ( '' === $title ) {
			$title = 'Vehicle ' . $ad_id;
		}

		$features = self::collect_features( $ad );

		foreach ( $features as $key => $label ) {
			$meta[ $key ] = $label;
		}

		$meta['feature_keys'] = implode( ',', array_keys( $features ) );

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

	/**
	 * @param array  $ad
	 * @param string $class
	 * @return array<string, string>
	 */
	private function identity_meta( array $ad, $class ) {
		$make_key  = isset( $ad['make'] ) ? (string) $ad['make'] : '';
		$condition = $this->str( $ad, 'condition' );

		return array(
			'make'          => $this->label( 'make', $make_key, $class ),
			'make_key'      => $make_key,
			'model'         => isset( $ad['model'] ) ? (string) $ad['model'] : '',
			'class'         => self::vehicle_class_label( $class ),
			'class_key'     => $class,
			'category'      => $this->label( 'category', $this->str( $ad, 'category' ), $class ),
			'condition'     => $this->label( 'condition', $condition, $class ),
			'condition_key' => $condition,
		);
	}

	/**
	 * @param array $ad
	 * @return array<string, mixed>
	 */
	private function price_meta( array $ad ) {
		$price = isset( $ad['price'] ) && is_array( $ad['price'] ) ? $ad['price'] : array();
		$gross = isset( $price['consumerPriceGross'] ) ? (string) $price['consumerPriceGross'] : '';
		$raw   = ( '' !== $gross ) ? (int) round( (float) $gross ) : '';

		return array(
			'price'           => ( '' !== $gross ) ? self::number( (float) $gross ) : '',
			'currency'        => isset( $price['currency'] ) ? (string) $price['currency'] : 'EUR',
			'vatable'         => isset( $price['vatRate'] ) && '' !== $price['vatRate'] ? 'true' : 'false',
			'vat_rate'        => isset( $price['vatRate'] ) ? (string) $price['vatRate'] : '',
			'price_raw'       => $raw,
			'price_raw_short' => self::price_bucket( $raw ),
		);
	}

	/**
	 * @param array  $ad
	 * @param string $class
	 * @return array<string, mixed>
	 */
	private function technical_meta( array $ad, $class ) {
		$mileage  = isset( $ad['mileage'] ) ? (int) $ad['mileage'] : null;
		$capacity = $this->str( $ad, 'cubicCapacity' );

		return array(
			'mileage'        => ( null !== $mileage ) ? self::number( $mileage ) : '',
			'mileage_raw'    => ( null !== $mileage ) ? $mileage : '',
			'power'          => isset( $ad['power'] ) ? (int) $ad['power'] : '',
			'cubic_capacity' => $capacity,
			'cubicCapacity'  => $capacity,
			'num_seats'      => $this->str( $ad, 'seats' ),
			'axles'          => $this->str( $ad, 'axles' ),
			'height'         => $this->str( $ad, 'height' ),
			'length'         => $this->str( $ad, 'length' ),
			'countryVersion' => $this->label( 'countryVersion', $this->str( $ad, 'countryVersion' ), $class ),
			'fuel'           => $this->label( 'fuel', $this->str( $ad, 'fuel' ), $class ),
			'gearbox'        => $this->label( 'gearbox', $this->str( $ad, 'gearbox' ), $class ),
			'door_count'     => $this->label( 'doors', $this->str( $ad, 'doors' ), $class ),
		);
	}

	/**
	 * @param array $ad
	 * @return array<string, mixed>
	 */
	private function history_meta( array $ad ) {
		$first_reg  = $this->str( $ad, 'firstRegistration' );
		$roadworthy = '';

		if ( array_key_exists( 'roadworthy', $ad ) ) {
			$roadworthy = $ad['roadworthy'] ? 'true' : 'false';
		}

		return array(
			'firstRegistration'      => self::format_month( $first_reg ),
			'firstRegistration_year' => self::year( $first_reg ),
			'nextInspection'         => self::format_month( $this->str( $ad, 'generalInspection' ) ),
			'newHuAu'                => ! empty( $ad['newHuAu'] ) ? __( 'New inspection on purchase', 'wp-mobile-de-sync' ) : '',
			'construction-year'      => $this->str( $ad, 'constructionYear' ),
			'owners'                 => $this->str( $ad, 'numberOfPreviousOwners' ),
			'damageUnrepaired'       => ! empty( $ad['damageUnrepaired'] ) ? 'true' : 'false',
			'roadWorthy'             => $roadworthy,
		);
	}

	/**
	 * @param array  $ad
	 * @param string $class
	 * @return array<string, string>
	 */
	private function appearance_meta( array $ad, $class ) {
		return array(
			'exteriorColor'           => $this->label( 'exteriorColor', $this->str( $ad, 'exteriorColor' ), $class ),
			'manufacturer_color_name' => WMDS_Creole::decode_field( $this->str( $ad, 'manufacturerColorName' ) ),
			'interior_type'           => $this->label( 'interiorType', $this->str( $ad, 'interiorType' ), $class ),
			'interior_color'          => $this->label( 'interiorColor', $this->str( $ad, 'interiorColor' ), $class ),
		);
	}

	/**
	 * @param array  $ad
	 * @param string $class
	 * @return array<string, string>
	 */
	private function emission_meta( array $ad, $class ) {
		$sticker = $this->str( $ad, 'emissionSticker' );

		return array(
			'emissionClass'       => $this->label( 'emissionClass', $this->str( $ad, 'emissionClass' ), $class ),
			'emissionSticker'     => $this->label( 'emissionSticker', $sticker, $class ),
			'emissionSticker_key' => $sticker,
		);
	}

	/**
	 * @param array  $ad
	 * @param string $class
	 * @return array<string, string>
	 */
	private function equipment_meta( array $ad, $class ) {
		return array(
			'climatisation'      => $this->label( 'climatisation', $this->str( $ad, 'climatisation' ), $class ),
			'airbag'             => $this->label( 'airbag', $this->str( $ad, 'airbag' ), $class ),
			'parking-assistants' => self::join_list( $ad, 'parkingAssistants' ),
		);
	}

	/**
	 * @param array $ad
	 * @return array<string, string>
	 */
	private function consumption_meta( array $ad ) {
		$fuel = self::dig( $ad, array( 'consumptions', 'fuel' ) );

		return array(
			'emissionFuelConsumption_Combined' => $this->str( $fuel, 'combined' ),
			'emissionFuelConsumption_Inner'    => $this->str( $fuel, 'city' ),
			'emissionFuelConsumption_Outer'    => $this->str( $fuel, 'highway' ),
			'combinedPowerConsumption'         => $this->str( self::dig( $ad, array( 'consumptions', 'power' ) ), 'combined' ),
			'emissionFuelConsumption_CO2'      => $this->str( self::dig( $ad, array( 'emissions', 'combined' ) ), 'co2' ),
		);
	}

	/**
	 * @param array $ad
	 * @return array<string, string>
	 */
	private function availability_meta( array $ad ) {
		$from = '';
		$now  = '';

		if ( isset( $ad['deliveryPeriod'] ) && (int) $ad['deliveryPeriod'] <= 1 ) {
			$from = __( 'Immediately', 'wp-mobile-de-sync' );
			$now  = 'true';
		} elseif ( '' !== $this->str( $ad, 'deliveryDate' ) ) {
			$from = self::format_day( $this->str( $ad, 'deliveryDate' ) );
		}

		return array(
			'available_from' => $from,
			'available_now'  => $now,
		);
	}

	/**
	 * @param array $ad
	 * @return array<string, string>
	 */
	private function seller_meta( array $ad ) {
		$seller  = isset( $ad['seller'] ) && is_array( $ad['seller'] ) ? $ad['seller'] : array();
		$address = isset( $seller['address'] ) && is_array( $seller['address'] ) ? $seller['address'] : array();
		$company = $this->str( $seller, 'companyName' );

		$meta = array(
			'seller'              => $company,
			'seller_company_name' => $company,
			'seller_email'        => $this->str( $seller, 'email' ),
			'seller_id'           => $this->str( $seller, 'mobileSellerId' ),
			'seller_since'        => self::format_day( $this->str( $seller, 'mobileSellerSince' ) ),
			'seller_homepage'     => $this->str( $seller, 'homepage' ),
			'seller_street'       => $this->str( $address, 'street' ),
			'seller_zipcode'      => $this->str( $address, 'zipcode' ),
			'seller_city'         => $this->str( $address, 'city' ),
			'seller_country'      => $this->str( $address, 'country' ),
		);

		$phone = ! empty( $seller['phones'] ) && is_array( $seller['phones'] ) ? reset( $seller['phones'] ) : null;

		if ( is_array( $phone ) ) {
			$meta['seller_phone_country_calling_code'] = $this->str( $phone, 'internationalPrefix' );
			$meta['seller_phone_area_code']            = $this->str( $phone, 'nationalPrefix' );
			$meta['seller_phone_number']               = $this->str( $phone, 'number' );
		}

		return $meta;
	}

	/**
	 * @param float|int $value
	 * @return string
	 */
	private static function number( $value ) {
		return function_exists( 'number_format_i18n' )
			? number_format_i18n( $value, 0 )
			: number_format( $value, 0, '.', ',' );
	}

	/** @return array<string, string> */
	private static function feature_labels() {
		return array(
			'ABS'                         => __( 'ABS', 'wp-mobile-de-sync' ),
			'AUTOMATIC_RAIN_SENSOR'       => __( 'Rain sensor', 'wp-mobile-de-sync' ),
			'AUXILIARY_HEATING'           => __( 'Auxiliary heating', 'wp-mobile-de-sync' ),
			'CENTRAL_LOCKING'             => __( 'Central locking', 'wp-mobile-de-sync' ),
			'ELECTRIC_ADJUSTABLE_SEATS'   => __( 'Electrically adjustable seats', 'wp-mobile-de-sync' ),
			'ELECTRIC_EXTERIOR_MIRRORS'   => __( 'Electrically adjustable mirrors', 'wp-mobile-de-sync' ),
			'ELECTRIC_HEATED_SEATS'       => __( 'Heated seats', 'wp-mobile-de-sync' ),
			'ELECTRIC_WINDOWS'            => __( 'Electric windows', 'wp-mobile-de-sync' ),
			'ESP'                         => __( 'ESP', 'wp-mobile-de-sync' ),
			'FRONT_FOG_LIGHTS'            => __( 'Front fog lights', 'wp-mobile-de-sync' ),
			'FULL_SERVICE_HISTORY'        => __( 'Full service history', 'wp-mobile-de-sync' ),
			'HEAD_UP_DISPLAY'             => __( 'Head-up display', 'wp-mobile-de-sync' ),
			'IMMOBILIZER'                 => __( 'Immobiliser', 'wp-mobile-de-sync' ),
			'ISOFIX'                      => __( 'Isofix', 'wp-mobile-de-sync' ),
			'LIGHT_SENSOR'                => __( 'Light sensor', 'wp-mobile-de-sync' ),
			'METALLIC'                    => __( 'Metallic paint', 'wp-mobile-de-sync' ),
			'MULTIFUNCTIONAL_WHEEL'       => __( 'Multifunction steering wheel', 'wp-mobile-de-sync' ),
			'NAVIGATION_SYSTEM'           => __( 'Navigation system', 'wp-mobile-de-sync' ),
			'PANORAMIC_GLASS_ROOF'        => __( 'Panoramic glass roof', 'wp-mobile-de-sync' ),
			'POWER_ASSISTED_STEERING'     => __( 'Power steering', 'wp-mobile-de-sync' ),
			'START_STOP_SYSTEM'           => __( 'Start/stop system', 'wp-mobile-de-sync' ),
			'SUNROOF'                     => __( 'Sunroof', 'wp-mobile-de-sync' ),
			'TRACTION_CONTROL_SYSTEM'     => __( 'Traction control', 'wp-mobile-de-sync' ),
			'XENON_HEADLIGHTS'            => __( 'Xenon headlights', 'wp-mobile-de-sync' ),
			'BENDING_LIGHTS'              => __( 'Cornering lights', 'wp-mobile-de-sync' ),
			'DAYTIME_RUNNING_LIGHTS'      => __( 'Daytime running lights', 'wp-mobile-de-sync' ),
			'CRUISE_CONTROL'              => __( 'Cruise control', 'wp-mobile-de-sync' ),
			'PARKING_SENSORS'             => __( 'Parking sensors', 'wp-mobile-de-sync' ),

			'AIR_SUSPENSION'              => __( 'Air suspension', 'wp-mobile-de-sync' ),
			'ALLOY_WHEELS'                => __( 'Alloy wheels', 'wp-mobile-de-sync' ),
			'BIODIESEL_SUITABLE'          => __( 'Suitable for biodiesel', 'wp-mobile-de-sync' ),
			'BLUETOOTH'                   => __( 'Bluetooth', 'wp-mobile-de-sync' ),
			'CD_MULTICHANGER'             => __( 'CD multichanger', 'wp-mobile-de-sync' ),
			'CD_PLAYER'                   => __( 'CD player', 'wp-mobile-de-sync' ),
			'DISABLED_ACCESSIBLE'         => __( 'Disabled accessible', 'wp-mobile-de-sync' ),
			'E10_ENABLED'                 => __( 'E10 compatible', 'wp-mobile-de-sync' ),
			'EXPORT'                      => __( 'Export vehicle', 'wp-mobile-de-sync' ),
			'HANDS_FREE_PHONE_SYSTEM'     => __( 'Hands-free system', 'wp-mobile-de-sync' ),
			'HU_AU_NEU'                   => __( 'New inspection on purchase', 'wp-mobile-de-sync' ),
			'HYBRID_PLUGIN'               => __( 'Plug-in hybrid', 'wp-mobile-de-sync' ),
			'MP3_INTERFACE'               => __( 'MP3 interface', 'wp-mobile-de-sync' ),
			'NONSMOKER_VEHICLE'           => __( 'Non-smoker vehicle', 'wp-mobile-de-sync' ),
			'ON_BOARD_COMPUTER'           => __( 'On-board computer', 'wp-mobile-de-sync' ),
			'PARTICULATE_FILTER_DIESEL'   => __( 'Diesel particulate filter', 'wp-mobile-de-sync' ),
			'PERFORMANCE_HANDLING_SYSTEM' => __( 'Sport suspension', 'wp-mobile-de-sync' ),
			'ROOF_RAILS'                  => __( 'Roof rails', 'wp-mobile-de-sync' ),
			'SKI_BAG'                     => __( 'Ski bag', 'wp-mobile-de-sync' ),
			'SPORT_PACKAGE'               => __( 'Sport package', 'wp-mobile-de-sync' ),
			'SPORT_SEATS'                 => __( 'Sport seats', 'wp-mobile-de-sync' ),
			'TAXI'                        => __( 'Taxi or rental car', 'wp-mobile-de-sync' ),
			'TRAILER_COUPLING'            => __( 'Trailer coupling', 'wp-mobile-de-sync' ),
			'TUNER'                       => __( 'Radio', 'wp-mobile-de-sync' ),
			'VEGETABLEOILFUEL_SUITABLE'   => __( 'Suitable for vegetable oil', 'wp-mobile-de-sync' ),
			'WARRANTY'                    => __( 'Warranty', 'wp-mobile-de-sync' ),
		);
	}

	/**
	 * @param array $ad
	 * @return array<string, string>
	 */
	private static function collect_features( array $ad ) {
		$labels = self::feature_labels();
		$out    = array();

		foreach ( self::$features as $key => $rule ) {
			if ( self::has_feature( $ad, $rule ) ) {
				$out[ $key ] = isset( $labels[ $key ] ) ? $labels[ $key ] : self::humanise( $key );
			}
		}

		foreach ( $ad as $field => $value ) {
			if ( true !== $value || in_array( $field, self::$feature_skip, true ) ) {
				continue;
			}

			$key  = self::screaming_snake( $field );
			$name = isset( self::$feature_aliases[ $key ] ) ? self::$feature_aliases[ $key ] : $key;

			if ( ! isset( $out[ $name ] ) ) {
				$out[ $name ] = isset( $labels[ $name ] ) ? $labels[ $name ] : self::humanise( $name );
			}
		}

		if ( ! empty( $ad['newHuAu'] ) ) {
			$out['HU_AU_NEU'] = __( 'New inspection on purchase', 'wp-mobile-de-sync' );
		}

		ksort( $out );

		return $out;
	}

	/**
	 * @param string $field
	 * @return string
	 */
	private static function screaming_snake( $field ) {
		$field = preg_replace( '/([a-z0-9])([A-Z])/', '$1_$2', (string) $field );

		return strtoupper( (string) $field );
	}

	/**
	 * @param string $key
	 * @return string
	 */
	private static function humanise( $key ) {
		$words = strtolower( str_replace( '_', ' ', (string) $key ) );

		return ucfirst( $words );
	}

	/**
	 * @param string $class
	 * @return string
	 */
	private static function vehicle_class_label( $class ) {
		$labels = array(
			'Car'                 => __( 'Car', 'wp-mobile-de-sync' ),
			'Motorbike'           => __( 'Motorbike', 'wp-mobile-de-sync' ),
			'Motorhome'           => __( 'Motorhome', 'wp-mobile-de-sync' ),
			'VanUpTo7500'         => __( 'Van up to 7.5 t', 'wp-mobile-de-sync' ),
			'TruckOver7500'       => __( 'Truck over 7.5 t', 'wp-mobile-de-sync' ),
			'Trailer'             => __( 'Trailer', 'wp-mobile-de-sync' ),
			'SemiTrailer'         => __( 'Semi-trailer', 'wp-mobile-de-sync' ),
			'SemiTrailerTruck'    => __( 'Semi-trailer truck', 'wp-mobile-de-sync' ),
			'Bus'                 => __( 'Bus', 'wp-mobile-de-sync' ),
			'AgriculturalVehicle' => __( 'Agricultural vehicle', 'wp-mobile-de-sync' ),
			'ConstructionMachine' => __( 'Construction machine', 'wp-mobile-de-sync' ),
			'ForkLiftTruck'       => __( 'Forklift truck', 'wp-mobile-de-sync' ),
		);

		$class = (string) $class;

		return isset( $labels[ $class ] ) ? $labels[ $class ] : $class;
	}

	/**
	 * @param array  $ad
	 * @param string $field
	 * @return string
	 */
	private static function join_list( array $ad, $field ) {
		if ( empty( $ad[ $field ] ) || ! is_array( $ad[ $field ] ) ) {
			return '';
		}

		$out = array();
		foreach ( $ad[ $field ] as $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				$out[] = self::humanise( $value );
			}
		}

		return implode( ', ', $out );
	}

	/**
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
	 * @param array $ad
	 * @return array<int, array{url:string,hash:string}>
	 */
	private static function collect_images( array $ad ) {
		if ( empty( $ad['images'] ) || ! is_array( $ad['images'] ) ) {
			return array();
		}

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
	 * @param string $value
	 * @return string
	 */
	public static function format_month( $value ) {
		$iso = WMDS_Date::iso_month( $value );

		return ( '' !== $iso ) ? $iso : trim( (string) $value );
	}

	/**
	 * @param string $value
	 * @return string
	 */
	public static function format_day( $value ) {
		return WMDS_Date::day( $value );
	}

	/**
	 * @param string $value
	 * @return int|string
	 */
	public static function year( $value ) {
		return WMDS_Date::year( $value );
	}

	/**
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

	/** @return string[] */
	public static function feature_keys() {
		return array_keys( self::$features );
	}
}
