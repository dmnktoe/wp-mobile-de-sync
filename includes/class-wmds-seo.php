<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Seo {
	const MAX_DESCRIPTION = 300;

	const MAX_IMAGES = 8;

	const MAX_LIST = 30;

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'render' ), 5 );
	}

	public static function render() {
		if ( is_singular( WMDS_CPT ) ) {
			self::render_vehicle();

			return;
		}

		if ( is_post_type_archive( WMDS_CPT ) ) {
			self::render_archive();
		}
	}

	/**
	 * Whether another plugin already owns the Open Graph tags.
	 *
	 * Two sets of og: tags on one page is worse than none: which one a
	 * scraper reads is not defined, and the answer differs per scraper.
	 *
	 * @return bool
	 */
	public static function seo_plugin_active() {
		$signals = array(
			defined( 'WPSEO_VERSION' ),
			defined( 'RANK_MATH_VERSION' ),
			defined( 'SEOPRESS_VERSION' ),
			defined( 'AIOSEO_VERSION' ),
			class_exists( 'The_SEO_Framework\\Load' ),
		);

		return in_array( true, $signals, true );
	}

	/**
	 * @param string $what Either jsonld or social.
	 * @return bool
	 */
	public static function wanted( $what ) {
		$default = ( 'social' === $what ) ? ! self::seo_plugin_active() : true;

		/**
		 * @param bool   $default
		 * @param string $what
		 */
		return (bool) apply_filters( 'wmds_seo_output', $default, $what );
	}

	private static function render_vehicle() {
		$post_id = (int) get_the_ID();
		$vehicle = new WMDS_Vehicle( $post_id );
		$data    = self::vehicle_data( $vehicle, $post_id );

		if ( self::wanted( 'jsonld' ) ) {
			/**
			 * @param array $schema
			 * @param array $data
			 */
			$schema = apply_filters( 'wmds_jsonld', self::vehicle_schema( $data ), $data );
			self::print_jsonld( $schema );
		}

		if ( self::wanted( 'social' ) ) {
			/**
			 * @param array $meta
			 * @param array $data
			 */
			$meta = apply_filters( 'wmds_social_meta', self::social_meta( $data ), $data );
			self::print_meta( $meta );
		}
	}

	private static function render_archive() {
		global $wp_query;

		$items = array();
		$posts = ( $wp_query instanceof WP_Query ) ? (array) $wp_query->posts : array();

		foreach ( array_slice( $posts, 0, self::MAX_LIST ) as $post ) {
			$post_id = isset( $post->ID ) ? (int) $post->ID : 0;
			if ( ! $post_id ) {
				continue;
			}

			$items[] = self::vehicle_data( new WMDS_Vehicle( $post_id ), $post_id );
		}

		if ( ! $items ) {
			return;
		}

		if ( self::wanted( 'jsonld' ) ) {
			$url = (string) get_post_type_archive_link( WMDS_CPT );

			/**
			 * @param array $schema
			 * @param array $items
			 */
			$schema = apply_filters( 'wmds_jsonld', self::item_list( $items, $url ), $items );
			self::print_jsonld( $schema );
		}
	}

	/**
	 * Everything the markup needs, read once out of the post.
	 *
	 * @param WMDS_Vehicle $vehicle
	 * @param int          $post_id
	 * @return array<string, mixed>
	 */
	public static function vehicle_data( WMDS_Vehicle $vehicle, $post_id ) {
		$images = array();
		foreach ( array_slice( $vehicle->images( 'large' ), 0, self::MAX_IMAGES ) as $image ) {
			$images[] = $image['url'];
		}

		return array(
			'title'         => (string) get_the_title( $post_id ),
			'url'           => (string) get_permalink( $post_id ),
			'description'   => self::describe( $vehicle, (string) get_the_title( $post_id ) ),
			'images'        => $images,
			'ad_id'         => $vehicle->get( 'vehicleListingID' ),
			'make'          => $vehicle->get( 'make' ),
			'model'         => $vehicle->get( 'model' ),
			'body_type'     => $vehicle->get( 'category' ),
			'colour'        => $vehicle->get( 'exteriorColor' ),
			'doors'         => $vehicle->get( 'door_count' ),
			'seats'         => $vehicle->get( 'num_seats' ),
			'fuel'          => $vehicle->get( 'fuel' ),
			'gearbox'       => $vehicle->get( 'gearbox' ),
			'power'         => $vehicle->get( 'power' ),
			'displacement'  => $vehicle->get( 'cubic_capacity' ),
			'mileage'       => $vehicle->get( 'mileage_raw' ),
			'owners'        => $vehicle->get( 'owners' ),
			'co2'           => $vehicle->get( 'emissionFuelConsumption_CO2' ),
			'registered'    => $vehicle->get( 'firstRegistration' ),
			'year'          => $vehicle->get( 'firstRegistration_year' ),
			'condition'     => $vehicle->get( 'condition' ),
			'condition_key' => $vehicle->get( 'condition_key' ),
			'price'         => $vehicle->get( 'price_raw' ),
			'currency'      => $vehicle->get( 'currency', 'EUR' ),
			'seller'        => $vehicle->seller(),
			'site'          => wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ),
		);
	}

	/**
	 * @param array $data
	 * @return array The Car node, ready to be encoded.
	 */
	public static function vehicle_schema( array $data ) {
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Car',
			'name'     => self::text( $data, 'title' ),
			'url'      => self::text( $data, 'url' ),
		);

		$schema = self::maybe( $schema, 'description', self::text( $data, 'description' ) );
		$schema = self::maybe( $schema, 'sku', self::text( $data, 'ad_id' ) );

		if ( ! empty( $data['images'] ) ) {
			$schema['image'] = array_values( (array) $data['images'] );
		}

		if ( '' !== self::text( $data, 'make' ) ) {
			$schema['brand'] = array(
				'@type' => 'Brand',
				'name'  => self::text( $data, 'make' ),
			);
		}

		$schema = self::maybe( $schema, 'model', self::text( $data, 'model' ) );
		$schema = self::maybe( $schema, 'bodyType', self::text( $data, 'body_type' ) );
		$schema = self::maybe( $schema, 'color', self::text( $data, 'colour' ) );
		$schema = self::maybe( $schema, 'fuelType', self::text( $data, 'fuel' ) );
		$schema = self::maybe( $schema, 'vehicleTransmission', self::text( $data, 'gearbox' ) );

		$doors = self::number( $data, 'doors' );
		if ( null !== $doors ) {
			$schema['numberOfDoors'] = $doors;
		}

		$seats = self::number( $data, 'seats' );
		if ( null !== $seats ) {
			$schema['vehicleSeatingCapacity'] = $seats;
		}

		$owners = self::number( $data, 'owners' );
		if ( null !== $owners ) {
			$schema['numberOfPreviousOwners'] = $owners;
		}

		$mileage = self::number( $data, 'mileage' );
		if ( null !== $mileage ) {
			$schema['mileageFromOdometer'] = self::quantity( $mileage, 'KMT' );
		}

		$engine = array();
		$power  = self::number( $data, 'power' );
		if ( null !== $power && $power > 0 ) {
			$engine['enginePower'] = self::quantity( $power, 'KWT' );
		}
		$displacement = self::number( $data, 'displacement' );
		if ( null !== $displacement && $displacement > 0 ) {
			$engine['engineDisplacement'] = self::quantity( $displacement, 'CMQ' );
		}
		if ( $engine ) {
			$schema['vehicleEngine'] = array_merge( array( '@type' => 'EngineSpecification' ), $engine );
		}

		$co2 = self::number( $data, 'co2' );
		if ( null !== $co2 && $co2 > 0 ) {
			$schema['emissionsCO2'] = $co2;
		}

		$registered = self::date( self::text( $data, 'registered' ) );
		if ( '' !== $registered ) {
			$schema['dateVehicleFirstRegistered'] = $registered;
		}

		$year = self::number( $data, 'year' );
		if ( null !== $year && $year > 1900 ) {
			$schema['vehicleModelDate'] = (string) $year;
		}

		$offer = self::offer( $data );
		if ( $offer ) {
			$schema['offers'] = $offer;
		}

		return $schema;
	}

	/**
	 * @param array $data
	 * @return array Empty when there is no price to state.
	 */
	private static function offer( array $data ) {
		$price = self::number( $data, 'price' );
		if ( null === $price || $price <= 0 ) {
			return array();
		}

		$offer = array(
			'@type'         => 'Offer',
			'price'         => $price,
			'priceCurrency' => self::text( $data, 'currency' ) ? self::text( $data, 'currency' ) : 'EUR',
			'availability'  => 'https://schema.org/InStock',
			'itemCondition' => self::condition( $data ),
			'url'           => self::text( $data, 'url' ),
		);

		if ( '' !== self::text( $data, 'seller' ) ) {
			$offer['seller'] = array(
				'@type' => 'AutoDealer',
				'name'  => self::text( $data, 'seller' ),
			);
		}

		return $offer;
	}

	/**
	 * New or used, decided from the key rather than the label.
	 *
	 * The feed states the condition as a localised label, with the key behind
	 * it stored alongside. Without either, a vehicle that has been driven is
	 * used and one that has not is new.
	 *
	 * @param array $data
	 * @return string
	 */
	public static function condition( array $data ) {
		$key = strtoupper( self::text( $data, 'condition_key' ) );

		if ( false !== strpos( $key, 'NEW' ) ) {
			return 'https://schema.org/NewCondition';
		}
		if ( false !== strpos( $key, 'USED' ) || false !== strpos( $key, 'DEMONSTRATION' ) || false !== strpos( $key, 'EMPLOYEE' ) ) {
			return 'https://schema.org/UsedCondition';
		}

		$mileage = self::number( $data, 'mileage' );

		return ( null !== $mileage && $mileage > 100 ) ? 'https://schema.org/UsedCondition' : 'https://schema.org/NewCondition';
	}

	/**
	 * @param array  $items Vehicle data arrays.
	 * @param string $url
	 * @return array
	 */
	public static function item_list( array $items, $url ) {
		$elements = array();

		foreach ( array_values( $items ) as $index => $item ) {
			$elements[] = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'item'     => self::vehicle_schema( $item ),
			);
		}

		$list = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'numberOfItems'   => count( $elements ),
			'itemListElement' => $elements,
		);

		if ( '' !== (string) $url ) {
			$list['url'] = (string) $url;
		}

		return $list;
	}

	/**
	 * @param array $data
	 * @return array<string, string> Attribute name => content.
	 */
	public static function social_meta( array $data ) {
		$meta = array(
			'og:type'       => 'product',
			'og:title'      => self::text( $data, 'title' ),
			'og:url'        => self::text( $data, 'url' ),
			'og:site_name'  => self::text( $data, 'site' ),
			'twitter:card'  => 'summary_large_image',
			'twitter:title' => self::text( $data, 'title' ),
		);

		$description = self::text( $data, 'description' );
		if ( '' !== $description ) {
			$meta['og:description']      = $description;
			$meta['twitter:description'] = $description;
		}

		if ( ! empty( $data['images'] ) ) {
			$images = array_values( (array) $data['images'] );

			$meta['og:image']      = $images[0];
			$meta['twitter:image'] = $images[0];
		}

		$price = self::number( $data, 'price' );
		if ( null !== $price && $price > 0 ) {
			$meta['product:price:amount']   = (string) $price;
			$meta['product:price:currency'] = self::text( $data, 'currency' ) ? self::text( $data, 'currency' ) : 'EUR';
		}

		return array_filter(
			$meta,
			function ( $value ) {
				return '' !== $value;
			}
		);
	}

	/**
	 * @param WMDS_Vehicle $vehicle
	 * @param string       $title
	 * @return string
	 */
	public static function describe( WMDS_Vehicle $vehicle, $title ) {
		$text = wp_strip_all_tags( (string) $vehicle->get( 'enriched_description' ) );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

		if ( '' === $text ) {
			$parts = array_filter( array_values( $vehicle->highlights() ) );
			$text  = trim( $title . ( $parts ? ' — ' . implode( ', ', $parts ) : '' ) );
		}

		return self::shorten( $text, self::MAX_DESCRIPTION );
	}

	/**
	 * @param string $text
	 * @param int    $limit
	 * @return string
	 */
	public static function shorten( $text, $limit ) {
		$text  = trim( (string) $text );
		$count = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );

		if ( $count <= $limit ) {
			return $text;
		}

		$cut  = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
		$stop = strrpos( $cut, ' ' );

		if ( false !== $stop && $stop > $limit / 2 ) {
			$cut = substr( $cut, 0, $stop );
		}

		return rtrim( $cut, " \t\n\r,;:-" ) . '…';
	}

	/**
	 * @param string $value
	 * @return string An ISO date, empty when there is none to be had.
	 */
	public static function date( $value ) {
		$value = trim( (string) $value );

		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^(\d{4})-(\d{2})$/', $value, $m ) ) {
			return $m[1] . '-' . $m[2] . '-01';
		}
		if ( preg_match( '/^(\d{2})\.(\d{4})$/', $value, $m ) ) {
			return $m[2] . '-' . $m[1] . '-01';
		}

		return '';
	}

	/**
	 * @param array  $schema
	 * @param string $key
	 * @param string $value
	 * @return array
	 */
	private static function maybe( array $schema, $key, $value ) {
		if ( '' !== $value ) {
			$schema[ $key ] = $value;
		}

		return $schema;
	}

	/**
	 * @param array  $data
	 * @param string $key
	 * @return string
	 */
	private static function text( array $data, $key ) {
		if ( ! isset( $data[ $key ] ) || is_array( $data[ $key ] ) ) {
			return '';
		}

		return trim( (string) $data[ $key ] );
	}

	/**
	 * @param array  $data
	 * @param string $key
	 * @return float|int|null
	 */
	private static function number( array $data, $key ) {
		$value = self::text( $data, $key );
		$value = str_replace( array( ' ', ',' ), array( '', '.' ), $value );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return null;
		}

		$float = (float) $value;
		$whole = (float) (int) $value;

		return ( $whole === $float ) ? (int) $value : $float;
	}

	/**
	 * @param int|float $value
	 * @param string    $unit UN/CEFACT code.
	 * @return array
	 */
	private static function quantity( $value, $unit ) {
		return array(
			'@type'    => 'QuantitativeValue',
			'value'    => $value,
			'unitCode' => $unit,
		);
	}

	/**
	 * @param array $schema
	 */
	private static function print_jsonld( array $schema ) {
		if ( ! $schema ) {
			return;
		}

		// The slashes stay escaped on purpose: a description containing
		// "</script>" would otherwise end the block it is printed inside.
		printf(
			"<script type=\"application/ld+json\">%s</script>\n",
			wp_json_encode( $schema, JSON_UNESCAPED_UNICODE ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode is the escaping; see above.
		);
	}

	/**
	 * @param array $meta
	 */
	private static function print_meta( array $meta ) {
		foreach ( $meta as $name => $content ) {
			printf(
				"<meta %s=\"%s\" content=\"%s\">\n",
				( 0 === strpos( $name, 'twitter:' ) ) ? 'name' : 'property',
				esc_attr( $name ),
				esc_attr( $content )
			);
		}
	}
}
