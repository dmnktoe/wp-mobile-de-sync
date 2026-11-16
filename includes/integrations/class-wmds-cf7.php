<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact Form 7, on a vehicle page.
 *
 * A dealer who already has a contact form does not want a second one, and the
 * bundled enquiry form can be switched off on the Enquiries tab. What that
 * form did and Contact Form 7 cannot is know which vehicle the visitor is
 * looking at: a mail saying "I am interested, when can I come by" is worth
 * nothing without it.
 *
 * So the vehicle is handed to Contact Form 7 three ways. As mail tags, which
 * go straight into the mail template and need no field in the form. As a form
 * tag, for a value that should travel with the submission and be shown in the
 * admin. And as a record: a submission sent from a vehicle page is filed
 * under Vehicles → Enquiries like any other, so a mail that is never
 * delivered is not simply gone.
 */
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
	}

	/** @return bool */
	public static function active() {
		return defined( 'WPCF7_VERSION' );
	}

	/** @return string */
	public static function version() {
		return defined( 'WPCF7_VERSION' ) ? (string) WPCF7_VERSION : '';
	}

	/** @return bool Whether a submission from a vehicle page is filed. */
	public static function stores() {
		return 'no' !== WMDS_Settings::get( 'cf7_store', 'yes' );
	}

	/** @return bool Whether the address the feed carries is added as a recipient. */
	public static function copies_seller() {
		return 'yes' === WMDS_Settings::get( 'cf7_copy_seller', 'no' );
	}

	/**
	 * What a form can ask about the vehicle, and what to call it on screen.
	 *
	 * The key is the suffix of the mail tag and the value of the form tag's
	 * field option, so [_wmds_vehicle_price] and [wmds_vehicle car field:price]
	 * are the same property twice.
	 *
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
	 * @param string $name A mail tag name, as Contact Form 7 passes it.
	 * @return string The property it asks for, empty when it is not ours.
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
	 * @param array  $context As context() returns it.
	 * @return string
	 */
	public static function value( $property, array $context ) {
		return isset( $context[ $property ] ) ? (string) $context[ $property ] : '';
	}

	/**
	 * Everything a form may ask about one vehicle.
	 *
	 * @param int $post_id
	 * @return array<string, string> Every property, empty where nothing is known.
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
	 * The vehicle a submission is about: the page the form sits on.
	 *
	 * @return int 0 when the form was submitted from anywhere else.
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
	 * @param mixed  $mail_tag Unused; Contact Form 7 added it in 5.2.
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
	 * Renders [wmds_vehicle field-name field:price] as a hidden input.
	 *
	 * @param mixed $tag A WPCF7_FormTag.
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
	 * Adds the seller to the recipients of the mail to the dealer.
	 *
	 * Never to the second mail: that one goes to the visitor, and the seller's
	 * address has no business in it.
	 *
	 * @param array $components
	 * @param mixed $contact_form
	 * @param mixed $mail A WPCF7_Mail, from Contact Form 7 5.0 on.
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
	 * Files a submission sent from a vehicle page.
	 *
	 * @param mixed $contact_form A WPCF7_ContactForm.
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

		return WMDS_Str::scrub( $contact_form->title(), WMDS_Leads::MAX_FIELD );
	}

	/**
	 * Reads a submission into the four fields an enquiry is made of.
	 *
	 * Contact Form 7 names nothing for us: the fields are whatever the form
	 * was built with. The conventional names come first, then a substring
	 * match, and for the address a last resort that simply looks for a value
	 * that is one — a form whose field is called "kontaktadresse" still ends
	 * up with an answerable enquiry rather than an anonymous one.
	 *
	 * @param array $posted Posted data, as Contact Form 7 hands it over.
	 * @return array{name:string,email:string,phone:string,message:string,consent:string}
	 */
	public static function fields( array $posted ) {
		$flat = self::flatten( $posted );

		return array(
			'name'    => self::pick(
				$flat,
				array( 'your-name', 'name', 'fullname', 'full-name', 'ihr-name' ),
				array( 'name' ),
				WMDS_Leads::MAX_FIELD
			),
			'email'   => self::address( $flat ),
			'phone'   => self::pick(
				$flat,
				array( 'your-phone', 'phone', 'tel', 'telephone', 'telefon' ),
				array( 'phone', 'tel' ),
				WMDS_Leads::MAX_PHONE
			),
			'message' => self::pick(
				$flat,
				array( 'your-message', 'message', 'comments', 'your-subject', 'nachricht' ),
				array( 'message', 'comment', 'enquiry', 'question', 'text', 'nachricht' ),
				WMDS_Leads::MAX_MESSAGE,
				true
			),
			'consent' => '',
		);
	}

	/**
	 * @param array $posted
	 * @return array<string, string> One string per field, internals dropped.
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
	 * @param string[] $names     Field names, in the order they are preferred.
	 * @param string[] $needles   Substrings, when none of the names is there.
	 * @param int      $limit
	 * @param bool     $multiline Whether the value is allowed to keep its lines.
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

		// Everything scrub() removes except the line breaks a message is written with.
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
			WMDS_Leads::MAX_FIELD
		);

		if ( '' !== $found && is_email( $found ) ) {
			return $found;
		}

		foreach ( $flat as $value ) {
			if ( is_email( $value ) ) {
				return WMDS_Str::scrub( $value, WMDS_Leads::MAX_FIELD );
			}
		}

		return '';
	}
}
