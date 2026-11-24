<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Cf7 {
	const TAG = '_wmds_vehicle_';

	const SOURCE = 'contact-form-7';

	public static function init() {
		if ( ! self::active() ) {
			return;
		}

		add_action( 'wpcf7_init', array( __CLASS__, 'register_tag' ) );
		add_filter( 'wpcf7_special_mail_tags', array( __CLASS__, 'mail_tag' ), 10, 4 );
		add_filter( 'wpcf7_mail_components', array( __CLASS__, 'mail_components' ), 10, 3 );
		add_action( 'wpcf7_mail_sent', array( __CLASS__, 'record' ) );
		add_action( 'wpcf7_mail_failed', array( __CLASS__, 'record' ) );
	}

	/** @return bool */
	public static function active() {
		return defined( 'WPCF7_VERSION' );
	}

	/** @return string */
	public static function version() {
		return defined( 'WPCF7_VERSION' ) ? (string) WPCF7_VERSION : '';
	}

	/** @return bool */
	public static function stores() {
		return WMDS_Settings::flag( 'cf7_store' );
	}

	/** @return bool */
	public static function copies_seller() {
		return WMDS_Settings::flag( 'cf7_copy_seller' );
	}

	/**
	 * @return array<string, string>
	 */
	public static function properties() {
		return array(
			'title'        => __( 'Title', 'wp-mobile-de-sync' ),
			'url'          => __( 'Address of the vehicle page', 'wp-mobile-de-sync' ),
			'id'           => __( 'Post ID', 'wp-mobile-de-sync' ),
			'listing'      => __( 'Listing number', 'wp-mobile-de-sync' ),
			'price'        => __( 'Price', 'wp-mobile-de-sync' ),
			'make'         => __( 'Make', 'wp-mobile-de-sync' ),
			'model'        => __( 'Model', 'wp-mobile-de-sync' ),
			'year'         => __( 'First registration', 'wp-mobile-de-sync' ),
			'mileage'      => __( 'Mileage', 'wp-mobile-de-sync' ),
			'fuel'         => __( 'Fuel', 'wp-mobile-de-sync' ),
			'gearbox'      => __( 'Transmission', 'wp-mobile-de-sync' ),
			'seller'       => __( 'Seller', 'wp-mobile-de-sync' ),
			'seller_email' => __( 'Seller e-mail', 'wp-mobile-de-sync' ),
			'seller_phone' => __( 'Seller phone', 'wp-mobile-de-sync' ),
		);
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public static function property( $name ) {
		$name = (string) $name;

		if ( 0 !== strpos( $name, self::TAG ) ) {
			return '';
		}

		$property = substr( $name, strlen( self::TAG ) );

		return array_key_exists( $property, self::properties() ) ? $property : '';
	}

	/**
	 * @param string $property
	 * @param array  $context
	 * @return string
	 */
	public static function value( $property, array $context ) {
		return isset( $context[ $property ] ) ? (string) $context[ $property ] : '';
	}

	/**
	 * @param int $post_id
	 * @return array<string, string>
	 */
	public static function context( $post_id ) {
		$post_id = (int) $post_id;
		$empty   = array_fill_keys( array_keys( self::properties() ), '' );

		if ( ! $post_id || WMDS_CPT !== get_post_type( $post_id ) ) {
			return $empty;
		}

		$vehicle = new WMDS_Vehicle( $post_id );

		return array_merge(
			$empty,
			array(
				'title'        => (string) get_the_title( $post_id ),
				'url'          => (string) get_permalink( $post_id ),
				'id'           => (string) $post_id,
				'listing'      => $vehicle->get( 'vehicleListingID' ),
				'price'        => $vehicle->price(),
				'make'         => $vehicle->get( 'make' ),
				'model'        => $vehicle->get( 'model' ),
				'year'         => $vehicle->first_registration(),
				'mileage'      => $vehicle->mileage(),
				'fuel'         => $vehicle->get( 'fuel' ),
				'gearbox'      => $vehicle->get( 'gearbox' ),
				'seller'       => $vehicle->seller(),
				'seller_email' => $vehicle->seller_email(),
				'seller_phone' => $vehicle->seller_phone(),
			)
		);
	}

	/**
	 * @return int
	 */
	public static function vehicle_id() {
		$post_id = 0;

		if ( class_exists( 'WPCF7_Submission' ) ) {
			$submission = WPCF7_Submission::get_instance();

			if ( $submission ) {
				$post_id = (int) $submission->get_meta( 'container_post_id' );
			}
		}

		if ( ! $post_id ) {
			$post_id = (int) get_the_ID();
		}

		return ( $post_id && WMDS_CPT === get_post_type( $post_id ) ) ? $post_id : 0;
	}

	/**
	 * @param string $output
	 * @param string $name
	 * @param bool   $html
	 * @param mixed  $mail_tag
	 * @return string
	 */
	public static function mail_tag( $output, $name = '', $html = false, $mail_tag = null ) {
		unset( $mail_tag );

		$property = self::property( $name );

		if ( '' === $property ) {
			return $output;
		}

		$value = self::value( $property, self::context( self::vehicle_id() ) );

		return $html ? esc_html( $value ) : $value;
	}

	public static function register_tag() {
		if ( ! function_exists( 'wpcf7_add_form_tag' ) ) {
			return;
		}

		wpcf7_add_form_tag(
			'wmds_vehicle',
			array( __CLASS__, 'form_tag' ),
			array( 'name-attr' => true )
		);
	}

	/**
	 * @param mixed $tag
	 * @return string
	 */
	public static function form_tag( $tag ) {
		if ( ! is_object( $tag ) || empty( $tag->name ) ) {
			return '';
		}

		$property = is_callable( array( $tag, 'get_option' ) )
			? $tag->get_option( 'field', '[a-z_]+', true )
			: '';

		if ( ! $property || '' === self::property( self::TAG . $property ) ) {
			$property = 'title';
		}

		return sprintf(
			'<input type="hidden" name="%s" value="%s">',
			esc_attr( $tag->name ),
			esc_attr( self::value( $property, self::context( self::vehicle_id() ) ) )
		);
	}

	/**
	 * @param array $components
	 * @param mixed $contact_form
	 * @param mixed $mail
	 * @return array
	 */
	public static function mail_components( $components, $contact_form = null, $mail = null ) {
		unset( $contact_form );

		$components = is_array( $components ) ? $components : array();

		if ( ! self::copies_seller() ) {
			return $components;
		}

		if ( is_object( $mail ) && is_callable( array( $mail, 'name' ) ) && 'mail' !== $mail->name() ) {
			return $components;
		}

		$post_id = self::vehicle_id();
		if ( ! $post_id ) {
			return $components;
		}

		$vehicle = new WMDS_Vehicle( $post_id );
		$email   = $vehicle->seller_email();

		if ( '' === $email || ! is_email( $email ) ) {
			return $components;
		}

		$recipient = isset( $components['recipient'] ) ? (string) $components['recipient'] : '';

		if ( '' !== $recipient && false !== stripos( $recipient, $email ) ) {
			return $components;
		}

		$components['recipient'] = ( '' === $recipient ) ? $email : $recipient . ', ' . $email;

		return $components;
	}

	/**
	 * @param mixed $contact_form
	 */
	public static function record( $contact_form = null ) {
		if ( ! self::stores() || ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$post_id = self::vehicle_id();
		if ( ! $post_id ) {
			return;
		}

		$data = self::fields( (array) $submission->get_posted_data() );

		if ( '' === $data['email'] && '' === $data['message'] ) {
			return;
		}

		$vehicle = new WMDS_Vehicle( $post_id );

		WMDS_Leads::file(
			$data,
			$post_id,
			WMDS_Leads::context( $post_id, $vehicle ),
			array(
				'_wmds_source' => self::SOURCE,
				'_wmds_form'   => self::form_title( $contact_form ),
			)
		);
	}

	/**
	 * @param mixed $contact_form
	 * @return string
	 */
	public static function form_title( $contact_form ) {
		if ( ! is_object( $contact_form ) || ! is_callable( array( $contact_form, 'title' ) ) ) {
			return '';
		}

		return WMDS_Str::scrub( $contact_form->title(), WMDS_Lead_Form::MAX_FIELD );
	}

	/**
	 * @param array $posted
	 * @return array{name:string,email:string,phone:string,message:string,consent:string}
	 */
	public static function fields( array $posted ) {
		$flat = self::flatten( $posted );

		return array(
			'name'    => self::pick(
				$flat,
				array( 'your-name', 'name', 'fullname', 'full-name', 'ihr-name' ),
				array( 'name' ),
				WMDS_Lead_Form::MAX_FIELD
			),
			'email'   => self::address( $flat ),
			'phone'   => self::pick(
				$flat,
				array( 'your-phone', 'phone', 'tel', 'telephone', 'telefon' ),
				array( 'phone', 'tel' ),
				WMDS_Lead_Form::MAX_PHONE
			),
			'message' => self::pick(
				$flat,
				array( 'your-message', 'message', 'comments', 'your-subject', 'nachricht' ),
				array( 'message', 'comment', 'enquiry', 'question', 'text', 'nachricht' ),
				WMDS_Lead_Form::MAX_MESSAGE,
				true
			),
			'consent' => '',
		);
	}

	/**
	 * @param array $posted
	 * @return array<string, string>
	 */
	private static function flatten( array $posted ) {
		$flat = array();

		foreach ( $posted as $key => $value ) {
			$key = (string) $key;

			if ( '' === $key || '_' === substr( $key, 0, 1 ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$value = implode( ', ', array_filter( array_map( 'strval', $value ), 'strlen' ) );
			}

			if ( is_object( $value ) || null === $value ) {
				continue;
			}

			$value = trim( (string) $value );

			if ( '' !== $value ) {
				$flat[ strtolower( $key ) ] = $value;
			}
		}

		return $flat;
	}

	/**
	 * @param array    $flat
	 * @param string[] $names
	 * @param string[] $needles
	 * @param int      $limit
	 * @param bool     $multiline
	 * @return string
	 */
	private static function pick( array $flat, array $names, array $needles, $limit, $multiline = false ) {
		foreach ( $names as $name ) {
			if ( isset( $flat[ $name ] ) ) {
				return self::clean( $flat[ $name ], $limit, $multiline );
			}
		}

		foreach ( $needles as $needle ) {
			foreach ( $flat as $key => $value ) {
				if ( false !== strpos( $key, $needle ) ) {
					return self::clean( $value, $limit, $multiline );
				}
			}
		}

		return '';
	}

	/**
	 * @param string $value
	 * @param int    $limit
	 * @param bool   $multiline
	 * @return string
	 */
	private static function clean( $value, $limit, $multiline ) {
		if ( ! $multiline ) {
			return WMDS_Str::scrub( $value, $limit );
		}

		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]/u', '', (string) $value );

		return WMDS_Str::cut( trim( $value ), $limit );
	}

	/**
	 * @param array $flat
	 * @return string
	 */
	private static function address( array $flat ) {
		$found = self::pick(
			$flat,
			array( 'your-email', 'email', 'e-mail', 'mail', 'ihre-email' ),
			array( 'email', 'e-mail', 'mail' ),
			WMDS_Lead_Form::MAX_FIELD
		);

		if ( '' !== $found && is_email( $found ) ) {
			return $found;
		}

		foreach ( $flat as $value ) {
			if ( is_email( $value ) ) {
				return WMDS_Str::scrub( $value, WMDS_Lead_Form::MAX_FIELD );
			}
		}

		return '';
	}
}
