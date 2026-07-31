<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Vehicle {
	/** @var int */
	private $post_id;

	/** @var array<string, array> */
	private $meta;

	/**
	 * @param int|null $post_id Defaults to the post in the current loop.
	 */
	public function __construct( $post_id = null ) {
		$this->post_id = $post_id ? (int) $post_id : (int) get_the_ID();
		$this->meta    = $this->post_id ? (array) get_post_meta( $this->post_id ) : array();
	}

	/**
	 * @param string $key
	 * @param string $default
	 * @return string
	 */
	public function get( $key, $default = '' ) {
		if ( ! isset( $this->meta[ $key ][0] ) ) {
			return $default;
		}
		$value = trim( (string) $this->meta[ $key ][0] );

		return ( '' === $value ) ? $default : $value;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function has( $key ) {
		return '' !== $this->get( $key );
	}

	/** @return int The post ID this model reads from. */
	public function id() {
		return $this->post_id;
	}

	/** @return string e.g. "42,999 €" */
	public function price() {
		$price = $this->get( 'price' );
		if ( '' === $price ) {
			return '';
		}
		$currency = $this->get( 'currency', 'EUR' );

		return $price . ' ' . ( 'EUR' === $currency ? '€' : $currency );
	}

	/**
	 * @return string
	 */
	public function vat_note() {
		if ( 'false' === $this->get( 'vatable' ) ) {
			return __( 'VAT not reclaimable', 'wp-mobile-de-sync' );
		}
		$rate = $this->get( 'vat_rate' );

		if ( '' === $rate ) {
			return __( 'incl. VAT', 'wp-mobile-de-sync' );
		}

		/* translators: %s: VAT rate as a number, e.g. 19. */
		return sprintf( __( 'incl. %s%% VAT', 'wp-mobile-de-sync' ), rtrim( rtrim( $rate, '0' ), '.,' ) );
	}

	/** @return string e.g. "129,000 km" */
	public function mileage() {
		if ( ! $this->has( 'mileage' ) ) {
			return '';
		}

		/* translators: %s: mileage, already formatted with separators. */
		return sprintf( __( '%s km', 'wp-mobile-de-sync' ), $this->get( 'mileage' ) );
	}

	/** @return string e.g. "108 kW (147 hp)" */
	public function power() {
		$kw = (int) $this->get( 'power' );
		if ( ! $kw ) {
			return '';
		}

		/* translators: 1: power in kilowatts, 2: the same power in metric horsepower. */
		return sprintf( __( '%1$d kW (%2$d hp)', 'wp-mobile-de-sync' ), $kw, (int) round( $kw * 1.35962 ) );
	}

	/**
	 * @return string
	 */
	public function first_registration() {
		return self::month( $this->get( 'firstRegistration' ) );
	}

	/**
	 * @return string Empty when the vehicle carries no sticker.
	 */
	public function emission_sticker_url() {
		return WMDS_Stickers::url( $this->sticker_key() );
	}

	/**
	 * @return string
	 */
	public function emission_sticker_label() {
		return WMDS_Stickers::label( $this->sticker_key() );
	}

	/**
	 * @return string
	 */
	private function sticker_key() {
		$key = $this->get( 'emissionSticker_key' );

		return '' !== $key ? $key : $this->get( 'emissionSticker' );
	}

	/**
	 * @return string
	 */
	public function inspection() {
		if ( $this->has( 'nextInspection' ) ) {
			/* translators: %s: expiry month and year, e.g. 11.2027. */
			return sprintf( __( 'Inspection valid until %s', 'wp-mobile-de-sync' ), self::month( $this->get( 'nextInspection' ) ) );
		}

		return $this->get( 'newHuAu' );
	}

	/** @return string */
	public function availability() {
		return $this->get( 'available_from' );
	}

	/**
	 * @return string
	 */
	public function availability_note() {
		if ( 'true' === $this->get( 'available_now' ) ) {
			return __( 'Available immediately', 'wp-mobile-de-sync' );
		}

		$from = $this->availability();
		if ( '' === $from ) {
			return '';
		}

		/* translators: %s: a date, e.g. 01.09.2026. */
		return sprintf( __( 'Available from %s', 'wp-mobile-de-sync' ), $from );
	}

	/**
	 * @return array<string, string>
	 */
	public function highlights() {
		return array_filter(
			array(
				__( 'Mileage', 'wp-mobile-de-sync' )      => $this->mileage(),
				__( 'First registration', 'wp-mobile-de-sync' ) => $this->first_registration(),
				__( 'Power', 'wp-mobile-de-sync' )        => $this->power(),
				__( 'Transmission', 'wp-mobile-de-sync' ) => $this->get( 'gearbox' ),
				__( 'Fuel', 'wp-mobile-de-sync' )         => $this->get( 'fuel' ),
			)
		);
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	public function specifications() {
		$groups = array(
			__( 'Vehicle', 'wp-mobile-de-sync' ) => array(
				__( 'Body type', 'wp-mobile-de-sync' )  => $this->get( 'category' ),
				__( 'Condition', 'wp-mobile-de-sync' )  => $this->get( 'condition' ),
				__( 'First registration', 'wp-mobile-de-sync' ) => $this->first_registration(),
				__( 'Model year', 'wp-mobile-de-sync' ) => $this->get( 'construction-year' ),
				__( 'Mileage', 'wp-mobile-de-sync' )    => $this->mileage(),
				__( 'Previous owners', 'wp-mobile-de-sync' ) => $this->get( 'owners' ),
				__( 'Inspection', 'wp-mobile-de-sync' ) => $this->inspection(),
			),
			__( 'Engine & transmission', 'wp-mobile-de-sync' ) => array(
				__( 'Power', 'wp-mobile-de-sync' )        => $this->power(),
				__( 'Transmission', 'wp-mobile-de-sync' ) => $this->get( 'gearbox' ),
				__( 'Fuel', 'wp-mobile-de-sync' )         => $this->get( 'fuel' ),
				__( 'Displacement', 'wp-mobile-de-sync' ) => $this->unit( 'cubic_capacity', __( 'cm³', 'wp-mobile-de-sync' ) ),
			),
			__( 'Exterior & interior', 'wp-mobile-de-sync' ) => array(
				__( 'Exterior colour', 'wp-mobile-de-sync' ) => $this->get( 'exteriorColor' ),
				__( 'Paint name', 'wp-mobile-de-sync' ) => $this->get( 'manufacturer_color_name' ),
				__( 'Interior', 'wp-mobile-de-sync' )   => trim( $this->get( 'interior_type' ) . ' ' . $this->get( 'interior_color' ) ),
				__( 'Doors', 'wp-mobile-de-sync' )      => $this->get( 'door_count' ),
				__( 'Seats', 'wp-mobile-de-sync' )      => $this->get( 'num_seats' ),
			),
			__( 'Energy & environment', 'wp-mobile-de-sync' ) => array(
				__( 'Emission class', 'wp-mobile-de-sync' )        => $this->get( 'emissionClass' ),
				__( 'Emissions sticker', 'wp-mobile-de-sync' )     => $this->get( 'emissionSticker' ),
				__( 'Consumption, combined', 'wp-mobile-de-sync' ) => $this->unit( 'emissionFuelConsumption_Combined', __( 'l/100 km', 'wp-mobile-de-sync' ) ),
				__( 'Consumption, urban', 'wp-mobile-de-sync' )    => $this->unit( 'emissionFuelConsumption_Inner', __( 'l/100 km', 'wp-mobile-de-sync' ) ),
				__( 'Consumption, extra-urban', 'wp-mobile-de-sync' ) => $this->unit( 'emissionFuelConsumption_Outer', __( 'l/100 km', 'wp-mobile-de-sync' ) ),
				__( 'CO₂ emissions, combined', 'wp-mobile-de-sync' ) => $this->unit( 'emissionFuelConsumption_CO2', __( 'g/km', 'wp-mobile-de-sync' ) ),
				__( 'Power consumption, combined', 'wp-mobile-de-sync' ) => $this->unit( 'combinedPowerConsumption', __( 'kWh/100 km', 'wp-mobile-de-sync' ) ),
			),
			__( 'Other', 'wp-mobile-de-sync' )   => array(
				__( 'Listing number', 'wp-mobile-de-sync' ) => $this->get( 'vehicleListingID' ),
				__( 'Schwacke code', 'wp-mobile-de-sync' ) => $this->get( 'schwacke-code' ),
			),
		);

		foreach ( $groups as $name => $rows ) {
			$rows = array_filter( $rows );
			if ( $rows ) {
				$groups[ $name ] = $rows;
			} else {
				unset( $groups[ $name ] );
			}
		}

		return $groups;
	}

	/**
	 * @return string[]
	 */
	public function features() {
		$out = array();

		$stored = array_filter( explode( ',', $this->get( 'feature_keys' ) ) );
		$keys   = $stored ? $stored : WMDS_Json_Mapper::feature_keys();

		foreach ( $keys as $key ) {
			$label = $this->get( $key );
			if ( '' !== $label ) {
				$out[] = $label;
			}
		}

		sort( $out );

		return $out;
	}

	/**
	 * @return string[]
	 */
	public function warnings() {
		$out = array();

		if ( 'true' === $this->get( 'damageRepaired' ) ) {
			$out[] = __( 'Damaged, unrepaired', 'wp-mobile-de-sync' );
		}
		if ( 'false' === $this->get( 'roadWorthy' ) ) {
			$out[] = __( 'Not roadworthy', 'wp-mobile-de-sync' );
		}

		return $out;
	}

	/** @return string */
	public function seller() {
		return $this->get( 'seller' );
	}

	/** @return string */
	public function seller_email() {
		return $this->get( 'seller_email' );
	}

	/** @return string Empty unless a complete number is on file. */
	public function seller_phone() {
		$number = $this->get( 'seller_phone_number' );
		if ( '' === $number ) {
			return '';
		}

		$country = $this->get( 'seller_phone_country_calling_code' );
		$area    = $this->get( 'seller_phone_area_code' );

		if ( '' === $country ) {
			return trim( $area . ' ' . $number );
		}

		return trim( '+' . $country . ' ' . ltrim( $area, '0' ) . ' ' . $number );
	}

	/** @return string */
	public function logo_url() {
		if ( ! class_exists( 'WMDS_Logos' ) ) {
			return '';
		}
		$key = $this->get( 'make_key', $this->get( 'make' ) );

		return $key ? WMDS_Logos::url( $key ) : '';
	}

	/**
	 * @param string $size
	 * @return array<int, array{url:string,thumb:string,alt:string}>
	 */
	public function images( $size = 'large' ) {
		if ( ! $this->post_id ) {
			return array();
		}

		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_parent'    => $this->post_id,
				'posts_per_page' => -1,
				'orderby'        => 'menu_order ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$out = array();
		foreach ( $attachments as $id ) {
			$full = wp_get_attachment_image_url( $id, $size );
			if ( ! $full ) {
				continue;
			}
			$thumb = wp_get_attachment_image_url( $id, 'medium' );

			$out[] = array(
				'url'   => $full,
				'thumb' => $thumb ? $thumb : $full,
				'alt'   => get_the_title( $this->post_id ),
			);
		}

		return $out;
	}

	/**
	 * @param string $key
	 * @param string $unit
	 * @return string
	 */
	private function unit( $key, $unit ) {
		$value = $this->get( $key );

		return ( '' === $value ) ? '' : $value . ' ' . $unit;
	}

	/**
	 * @param string $value
	 * @return string
	 */
	private static function month( $value ) {
		if ( preg_match( '/^(\d{4})-(\d{2})/', (string) $value, $m ) ) {
			return $m[2] . '.' . $m[1];
		}

		return (string) $value;
	}
}
