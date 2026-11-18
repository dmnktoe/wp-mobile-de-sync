<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The enquiry form on a vehicle page: what it shows, what it accepts and
 * what it turns away.
 */
class WMDS_Lead_Form {
	const NONCE  = 'wmds_enquiry';
	const ACTION = 'wmds_enquiry';

	const STATE = 'wmds_lead_form_';
	const RATE  = 'wmds_lead_rate_';

	const STATE_TTL = 900;
	const RATE_TTL  = 3600;
	const RATE_MAX  = 5;

	const MIN_SECONDS = 3;

	const MAX_MESSAGE = 5000;
	const MIN_MESSAGE = 10;
	const MAX_FIELD   = 100;
	const MAX_PHONE   = 40;

	public static function init() {
		add_shortcode( 'vehicle-enquiry', array( __CLASS__, 'shortcode' ) );

		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Everything that can be decided about a submission without a database.
	 *
	 * @param array $data Cleaned field values.
	 * @param bool  $consent_required
	 * @return array<string, string> Field name => what is wrong with it.
	 */
	public static function validate( array $data, $consent_required = false ) {
		$errors = array();

		$name = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			$errors['name'] = __( 'Please tell us your name.', 'wp-mobile-de-sync' );
		} elseif ( self::length( $name ) > self::MAX_FIELD ) {
			$errors['name'] = __( 'That name is too long.', 'wp-mobile-de-sync' );
		}

		$email = isset( $data['email'] ) ? trim( (string) $data['email'] ) : '';
		if ( '' === $email ) {
			$errors['email'] = __( 'Please enter an e-mail address so that we can reply.', 'wp-mobile-de-sync' );
		} elseif ( ! is_email( $email ) ) {
			$errors['email'] = __( 'Please enter a valid e-mail address.', 'wp-mobile-de-sync' );
		}

		$phone = isset( $data['phone'] ) ? trim( (string) $data['phone'] ) : '';
		if ( '' !== $phone && ( self::length( $phone ) > self::MAX_PHONE || ! preg_match( '/^[0-9 +()\/-]+$/', $phone ) ) ) {
			$errors['phone'] = __( 'Please enter a valid phone number.', 'wp-mobile-de-sync' );
		}

		$message = isset( $data['message'] ) ? trim( (string) $data['message'] ) : '';
		if ( '' === $message ) {
			$errors['message'] = __( 'Please enter a message.', 'wp-mobile-de-sync' );
		} elseif ( self::length( $message ) < self::MIN_MESSAGE ) {
			$errors['message'] = __( 'Please enter a more detailed message.', 'wp-mobile-de-sync' );
		} elseif ( self::length( $message ) > self::MAX_MESSAGE ) {
			$errors['message'] = __( 'That message is too long.', 'wp-mobile-de-sync' );
		}

		if ( $consent_required && empty( $data['consent'] ) ) {
			$errors['consent'] = __( 'Please agree to the privacy notice.', 'wp-mobile-de-sync' );
		}

		return $errors;
	}

	/**
	 * The checks a person never notices and a script always fails.
	 *
	 * @param array $data
	 * @param int   $now Unix time of the submission.
	 * @return bool
	 */
	public static function is_spam( array $data, $now ) {
		if ( '' !== trim( (string) ( isset( $data['website'] ) ? $data['website'] : '' ) ) ) {
			return true;
		}

		$opened = isset( $data['opened'] ) ? (int) $data['opened'] : 0;

		if ( $opened <= 0 || $opened > $now ) {
			return true;
		}

		return ( $now - $opened ) < self::MIN_SECONDS;
	}

	/**
	 * @param int $sent How many this address or IP has already sent.
	 * @return bool
	 */
	public static function over_limit( $sent ) {
		return (int) $sent >= self::RATE_MAX;
	}

	public static function handle() {
		$posted = self::read_request();

		$referer = wp_get_referer();
		$back    = $referer ? $referer : home_url( '/' );

		// Only for somebody who is logged in. A nonce for a visitor is the
		// same for every visitor and protects nothing, and it is printed into
		// a page that a page cache keeps for longer than the nonce lives:
		// the first enquiry from a cached vehicle page would always bounce.
		if ( is_user_logged_in() && ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '', self::NONCE ) ) {
			self::refuse( $back, 'expired' );
		}

		if ( ! WMDS_Leads::enabled() ) {
			self::refuse( $back, 'off' );
		}

		if ( self::is_spam( $posted, time() ) ) {
			self::refuse( $back, 'quick' );
		}

		$key  = self::RATE . md5( self::client_ip() );
		$sent = (int) get_transient( $key );
		if ( self::over_limit( $sent ) ) {
			self::refuse( $back, 'limit' );
		}

		$consent_required = ( '' !== WMDS_Leads::consent_text() );

		$errors = self::validate( $posted, $consent_required );
		if ( $errors ) {
			self::bounce( $back, $errors, $posted );
		}

		$post_id = isset( $posted['vehicle'] ) ? (int) $posted['vehicle'] : 0;
		$vehicle = null;

		// Published only: the form posts an ID, and the title and address of
		// a draft are nobody's business.
		if ( $post_id && WMDS_CPT === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id ) ) {
			$vehicle = new WMDS_Vehicle( $post_id );
		} else {
			$post_id = 0;
		}

		$context = WMDS_Leads::context( $post_id, $vehicle );
		$site    = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );

		$stored = WMDS_Leads::store( $posted, $post_id, $context );
		$tried  = time();
		$mailed = WMDS_Lead_Mail::send( $posted, $context, $site, $vehicle );

		if ( $stored ) {
			update_post_meta( $stored, '_wmds_delivery', wp_slash( WMDS_Lead_Mail::delivery_note( $mailed, WMDS_Lead_Mail::recipients( $vehicle ), WMDS_Smtp::failure(), $tried ) ) );
		}

		if ( ! $stored && ! $mailed ) {
			self::bounce( $back, array( 'form' => __( 'Unfortunately, the enquiry could not be sent. Please contact us by phone.', 'wp-mobile-de-sync' ) ), $posted );
		}

		set_transient( $key, $sent + 1, self::RATE_TTL );

		wp_safe_redirect( add_query_arg( 'wmds_enquiry', 'sent', $context['url'] ? $context['url'] : $back ) . '#wmds-enquiry' );
		exit;
	}

	/** @return array<string, string> */
	private static function read_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- the nonce is checked in handle() before anything is done with this.
		// Cut here, before anything is kept: what comes back to the form after
		// a validation error is stored for a quarter of an hour, and nobody
		// is owed room for a megabyte.
		return array(
			'name'    => isset( $_POST['wmds_name'] ) ? WMDS_Str::cut( sanitize_text_field( wp_unslash( $_POST['wmds_name'] ) ), self::MAX_FIELD ) : '',
			'email'   => isset( $_POST['wmds_email'] ) ? WMDS_Str::cut( sanitize_email( wp_unslash( $_POST['wmds_email'] ) ), self::MAX_FIELD ) : '',
			'phone'   => isset( $_POST['wmds_phone'] ) ? WMDS_Str::cut( sanitize_text_field( wp_unslash( $_POST['wmds_phone'] ) ), self::MAX_PHONE ) : '',
			'message' => isset( $_POST['wmds_message'] ) ? WMDS_Str::cut( sanitize_textarea_field( wp_unslash( $_POST['wmds_message'] ) ), self::MAX_MESSAGE + 1 ) : '',
			'consent' => isset( $_POST['wmds_consent'] ) ? '1' : '',
			'website' => isset( $_POST['wmds_website'] ) ? sanitize_text_field( wp_unslash( $_POST['wmds_website'] ) ) : '',
			'opened'  => isset( $_POST['wmds_opened'] ) ? absint( wp_unslash( $_POST['wmds_opened'] ) ) : 0,
			'vehicle' => isset( $_POST['wmds_vehicle'] ) ? absint( wp_unslash( $_POST['wmds_vehicle'] ) ) : 0,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * @param string $url
	 * @param array  $errors
	 * @param array  $values
	 */
	private static function bounce( $url, array $errors, array $values ) {
		unset( $values['website'], $values['opened'] );

		// Lower case: the token comes back through sanitize_key(), and an
		// object cache, unlike the options table, tells aB from ab.
		$token = strtolower( wp_generate_password( 16, false ) );

		set_transient(
			self::STATE . $token,
			array(
				'errors' => $errors,
				'values' => $values,
			),
			self::STATE_TTL
		);

		wp_safe_redirect( add_query_arg( 'wmds_enquiry', $token, $url ) . '#wmds-enquiry' );
		exit;
	}

	/**
	 * Sends a submission back without keeping anything of it.
	 *
	 * For everything that is refused before the fields are even looked at.
	 * The reason travels as a word in the address; nothing is written, so a
	 * script that posts rubbish all day costs a redirect each time and not a
	 * row in the database.
	 *
	 * @param string $url
	 * @param string $reason One of the keys of refusals().
	 */
	private static function refuse( $url, $reason ) {
		wp_safe_redirect( add_query_arg( 'wmds_enquiry', $reason, $url ) . '#wmds-enquiry' );
		exit;
	}

	/** @return array<string, string> */
	private static function refusals() {
		return array(
			'expired' => __( 'The form has expired. Please send it again.', 'wp-mobile-de-sync' ),
			'off'     => __( 'Enquiries are switched off.', 'wp-mobile-de-sync' ),
			'quick'   => __( 'The form was submitted too quickly. Please try again.', 'wp-mobile-de-sync' ),
			'limit'   => __( 'Several enquiries have already been sent. Please try again later.', 'wp-mobile-de-sync' ),
		);
	}

	/** @return array{errors:array,values:array,sent:bool} What the form should show after a redirect. */
	public static function state() {
		$empty = array(
			'errors' => array(),
			'values' => array(),
			'sent'   => false,
		);

		$token = isset( $_GET['wmds_enquiry'] ) ? sanitize_key( wp_unslash( $_GET['wmds_enquiry'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reads back a redirect of our own, and the token is a lookup key.
		if ( '' === $token ) {
			return $empty;
		}

		if ( 'sent' === $token ) {
			$empty['sent'] = true;

			return $empty;
		}

		$refusals = self::refusals();
		if ( isset( $refusals[ $token ] ) ) {
			$empty['errors'] = array( 'form' => $refusals[ $token ] );

			return $empty;
		}

		$state = get_transient( self::STATE . $token );
		if ( ! is_array( $state ) ) {
			return $empty;
		}

		delete_transient( self::STATE . $token );

		return array(
			'errors' => isset( $state['errors'] ) ? (array) $state['errors'] : array(),
			'values' => isset( $state['values'] ) ? (array) $state['values'] : array(),
			'sent'   => false,
		);
	}

	/**
	 * @param array $atts
	 * @return string
	 */
	public static function shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'vehicle' => 0,
				'heading' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'vehicle-enquiry'
		);

		if ( ! WMDS_Leads::enabled() ) {
			return '';
		}

		WMDS_Templates::maybe_enqueue( true );

		ob_start();
		WMDS_Templates::render(
			'parts/enquiry-form.php',
			array(
				'vehicle_id' => (int) $atts['vehicle'] ? (int) $atts['vehicle'] : (int) get_the_ID(),
				'heading'    => (string) $atts['heading'],
			)
		);

		return ob_get_clean();
	}

	/**
	 * @param string $value
	 * @return int
	 */
	private static function length( $value ) {
		return WMDS_Str::length( $value );
	}

	/*
	 * An enquiry is a name, an address, a phone number and an IP. WordPress
	 * has a tool for "send me what you hold on me" and one for "delete it"
	 * (Tools → Export / Erase Personal Data); without these two callbacks
	 * neither of them knew the enquiries existed.
	 */

	/** @return string */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * The address a visitor is counted and recorded under. Behind a
		 * proxy that does not rewrite REMOTE_ADDR every visitor arrives from
		 * the proxy, and five enquiries an hour is then the limit for the
		 * whole site — this is where to read the forwarded header instead.
		 *
		 * @param string $ip
		 */
		$ip = (string) apply_filters( 'wmds_client_ip', $ip );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}
