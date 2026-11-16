<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Leads {
	const CPT = 'wmds_lead';

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

	const SOURCE = 'enquiry-form';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );

		add_shortcode( 'vehicle-enquiry', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'fahrzeug-anfrage', array( __CLASS__, 'shortcode' ) );

		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );

		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::CPT, array( __CLASS__, 'meta_box' ) );
	}

	/**
	 * @param array $columns
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'           => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'title'        => __( 'Enquiry', 'wp-mobile-de-sync' ),
			'wmds_contact' => __( 'Contact', 'wp-mobile-de-sync' ),
			'wmds_vehicle' => __( 'Vehicle', 'wp-mobile-de-sync' ),
			'date'         => isset( $columns['date'] ) ? $columns['date'] : __( 'Received', 'wp-mobile-de-sync' ),
		);
	}

	/**
	 * @param string $column
	 * @param int    $post_id
	 */
	public static function column( $column, $post_id ) {
		if ( 'wmds_contact' === $column ) {
			$email = (string) get_post_meta( $post_id, '_wmds_email', true );
			$phone = (string) get_post_meta( $post_id, '_wmds_phone', true );

			if ( '' !== $email ) {
				printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
			}
			if ( '' !== $phone ) {
				printf( '<br><span>%s</span>', esc_html( $phone ) );
			}

			return;
		}

		if ( 'wmds_vehicle' !== $column ) {
			return;
		}

		$vehicle = (int) get_post_meta( $post_id, '_wmds_vehicle', true );
		if ( ! $vehicle ) {
			echo '—';

			return;
		}

		printf(
			'<a href="%s">%s</a>',
			esc_url( (string) get_edit_post_link( $vehicle ) ),
			esc_html( (string) get_the_title( $vehicle ) )
		);
	}

	public static function meta_box() {
		add_meta_box(
			'wmds-lead',
			__( 'The enquiry', 'wp-mobile-de-sync' ),
			array( __CLASS__, 'render_meta_box' ),
			self::CPT,
			'normal',
			'high'
		);
	}

	/**
	 * Where an enquiry came in through, for the record.
	 *
	 * @return array<string, string>
	 */
	public static function sources() {
		return array(
			self::SOURCE     => __( 'Enquiry form', 'wp-mobile-de-sync' ),
			WMDS_Cf7::SOURCE => 'Contact Form 7',
		);
	}

	/**
	 * @param string $key
	 * @param string $form Name of the form, where one is on file.
	 * @return string
	 */
	public static function source_label( $key, $form = '' ) {
		$sources = self::sources();
		$key     = (string) $key;

		if ( '' === $key ) {
			return '';
		}

		$label = isset( $sources[ $key ] ) ? $sources[ $key ] : $key;
		$form  = trim( (string) $form );

		return ( '' === $form ) ? $label : $label . ' – ' . $form;
	}

	/**
	 * @param WP_Post $post
	 */
	public static function render_meta_box( $post ) {
		$rows = array(
			__( 'Name', 'wp-mobile-de-sync' )             => (string) get_post_meta( $post->ID, '_wmds_name', true ),
			__( 'E-mail', 'wp-mobile-de-sync' )           => (string) get_post_meta( $post->ID, '_wmds_email', true ),
			__( 'Phone', 'wp-mobile-de-sync' )            => (string) get_post_meta( $post->ID, '_wmds_phone', true ),
			__( 'Listing number', 'wp-mobile-de-sync' )   => (string) get_post_meta( $post->ID, '_wmds_ad_id', true ),
			__( 'Vehicle', 'wp-mobile-de-sync' )          => (string) get_post_meta( $post->ID, '_wmds_url', true ),
			__( 'Consent', 'wp-mobile-de-sync' )          => (string) get_post_meta( $post->ID, '_wmds_consent', true ),
			__( 'Received through', 'wp-mobile-de-sync' ) => self::source_label(
				(string) get_post_meta( $post->ID, '_wmds_source', true ),
				(string) get_post_meta( $post->ID, '_wmds_form', true )
			),
			__( 'From', 'wp-mobile-de-sync' )             => (string) get_post_meta( $post->ID, '_wmds_ip', true ),
		);

		echo '<table class="form-table" role="presentation">';
		foreach ( $rows as $label => $value ) {
			if ( '' === $value ) {
				continue;
			}
			printf(
				'<tr><th scope="row">%s</th><td>%s</td></tr>',
				esc_html( $label ),
				esc_html( $value )
			);
		}
		echo '</table>';

		printf( '<p><strong>%s</strong></p>', esc_html__( 'Message', 'wp-mobile-de-sync' ) );
		printf( '<p style="white-space:pre-wrap">%s</p>', esc_html( $post->post_content ) );
	}

	public static function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'Enquiries', 'wp-mobile-de-sync' ),
					'singular_name' => __( 'Enquiry', 'wp-mobile-de-sync' ),
					'menu_name'     => __( 'Enquiries', 'wp-mobile-de-sync' ),
					'all_items'     => __( 'Enquiries', 'wp-mobile-de-sync' ),
					'edit_item'     => __( 'Enquiry', 'wp-mobile-de-sync' ),
					'search_items'  => __( 'Search enquiries', 'wp-mobile-de-sync' ),
					'not_found'     => __( 'No enquiries yet', 'wp-mobile-de-sync' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . WMDS_CPT,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'supports'        => array( 'title' ),
				'has_archive'     => false,
				'rewrite'         => false,
				'query_var'       => false,
				'show_in_rest'    => false,
			)
		);
	}

	/** @return bool Whether the form is offered at all. */
	public static function enabled() {
		return 'no' !== WMDS_Settings::get( 'enquiry_enabled', 'yes' );
	}

	/**
	 * @param WMDS_Vehicle|null $vehicle
	 * @return string[] Where a new enquiry is sent.
	 */
	public static function recipients( WMDS_Vehicle $vehicle = null ) {
		$extra = array();

		if ( $vehicle && 'yes' === WMDS_Settings::get( 'enquiry_copy_seller', 'no' ) ) {
			$extra[] = $vehicle->seller_email();
		}

		$to = WMDS_Mail::addresses( WMDS_Settings::get( 'enquiry_recipient', '' ), $extra );

		/**
		 * @param string[] $to
		 */
		return (array) apply_filters( 'wmds_enquiry_recipients', $to );
	}

	/** @return string The consent line, empty when none is required. */
	public static function consent_text() {
		return trim( (string) WMDS_Settings::get( 'enquiry_consent', '' ) );
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
			$errors['email'] = __( 'Please give us an e-mail address to answer to.', 'wp-mobile-de-sync' );
		} elseif ( ! is_email( $email ) ) {
			$errors['email'] = __( 'That does not look like an e-mail address.', 'wp-mobile-de-sync' );
		}

		$phone = isset( $data['phone'] ) ? trim( (string) $data['phone'] ) : '';
		if ( '' !== $phone && ( self::length( $phone ) > self::MAX_PHONE || ! preg_match( '/^[0-9 +()\/-]+$/', $phone ) ) ) {
			$errors['phone'] = __( 'That does not look like a phone number.', 'wp-mobile-de-sync' );
		}

		$message = isset( $data['message'] ) ? trim( (string) $data['message'] ) : '';
		if ( '' === $message ) {
			$errors['message'] = __( 'Please tell us what you would like to know.', 'wp-mobile-de-sync' );
		} elseif ( self::length( $message ) < self::MIN_MESSAGE ) {
			$errors['message'] = __( 'A few more words, please.', 'wp-mobile-de-sync' );
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

	/**
	 * @param array  $data    Cleaned field values.
	 * @param array  $vehicle title, url, ad_id, price - whatever is known.
	 * @param string $site   Site name.
	 * @return array{subject:string,body:string}
	 */
	public static function compose( array $data, array $vehicle, $site ) {
		$title = isset( $vehicle['title'] ) ? (string) $vehicle['title'] : '';

		$subject = '' !== $title
			/* translators: 1: site name, 2: vehicle title. */
			? sprintf( __( '[%1$s] Enquiry: %2$s', 'wp-mobile-de-sync' ), $site, $title )
			/* translators: %s: site name. */
			: sprintf( __( '[%s] Enquiry', 'wp-mobile-de-sync' ), $site );

		$lines = array();

		if ( '' !== $title ) {
			$lines[] = __( 'Vehicle', 'wp-mobile-de-sync' ) . ': ' . $title;
		}
		if ( ! empty( $vehicle['ad_id'] ) ) {
			$lines[] = __( 'Listing number', 'wp-mobile-de-sync' ) . ': ' . $vehicle['ad_id'];
		}
		if ( ! empty( $vehicle['price'] ) ) {
			$lines[] = __( 'Price', 'wp-mobile-de-sync' ) . ': ' . $vehicle['price'];
		}
		if ( ! empty( $vehicle['url'] ) ) {
			$lines[] = $vehicle['url'];
		}

		if ( $lines ) {
			$lines[] = '';
		}

		$lines[] = __( 'Name', 'wp-mobile-de-sync' ) . ': ' . $data['name'];
		$lines[] = __( 'E-mail', 'wp-mobile-de-sync' ) . ': ' . $data['email'];

		if ( ! empty( $data['phone'] ) ) {
			$lines[] = __( 'Phone', 'wp-mobile-de-sync' ) . ': ' . $data['phone'];
		}

		$lines[] = '';
		$lines[] = $data['message'];

		return array(
			'subject' => $subject,
			'body'    => implode( "\n", $lines ),
		);
	}

	/**
	 * @param array  $data
	 * @param array  $vehicle
	 * @param string $site
	 * @return array{subject:string,body:string}
	 */
	public static function compose_confirmation( array $data, array $vehicle, $site ) {
		$title = isset( $vehicle['title'] ) ? (string) $vehicle['title'] : '';

		/* translators: %s: site name. */
		$subject = sprintf( __( '[%s] We have your enquiry', 'wp-mobile-de-sync' ), $site );

		$lines = array();

		/* translators: %s: the name the enquirer gave. */
		$lines[] = sprintf( __( 'Hello %s,', 'wp-mobile-de-sync' ), $data['name'] );
		$lines[] = '';

		$lines[] = '' !== $title
			/* translators: %s: vehicle title. */
			? sprintf( __( 'thank you for your enquiry about the %s. We will get back to you.', 'wp-mobile-de-sync' ), $title )
			: __( 'thank you for your enquiry. We will get back to you.', 'wp-mobile-de-sync' );

		if ( ! empty( $vehicle['url'] ) ) {
			$lines[] = '';
			$lines[] = $vehicle['url'];
		}

		$lines[] = '';
		$lines[] = __( 'This is what you sent us:', 'wp-mobile-de-sync' );
		$lines[] = '';
		$lines[] = $data['message'];
		$lines[] = '';
		$lines[] = $site;

		return array(
			'subject' => $subject,
			'body'    => implode( "\n", $lines ),
		);
	}

	public static function handle() {
		$posted = self::read_request();

		$referer = wp_get_referer();
		$back    = $referer ? $referer : home_url( '/' );

		if ( ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '', self::NONCE ) ) {
			self::bounce( $back, array( 'form' => __( 'The form has expired. Please send it again.', 'wp-mobile-de-sync' ) ), $posted );
		}

		if ( ! self::enabled() ) {
			self::bounce( $back, array( 'form' => __( 'Enquiries are switched off.', 'wp-mobile-de-sync' ) ), $posted );
		}

		if ( self::is_spam( $posted, time() ) ) {
			self::bounce( $back, array( 'form' => __( 'That was too quick to be real. Please try again.', 'wp-mobile-de-sync' ) ), $posted );
		}

		$key  = self::RATE . md5( self::client_ip() );
		$sent = (int) get_transient( $key );
		if ( self::over_limit( $sent ) ) {
			self::bounce( $back, array( 'form' => __( 'Several enquiries have already been sent from here. Please try again later.', 'wp-mobile-de-sync' ) ), $posted );
		}

		$consent_required = ( '' !== self::consent_text() );

		$errors = self::validate( $posted, $consent_required );
		if ( $errors ) {
			self::bounce( $back, $errors, $posted );
		}

		$post_id = isset( $posted['vehicle'] ) ? (int) $posted['vehicle'] : 0;
		$vehicle = ( $post_id && WMDS_CPT === get_post_type( $post_id ) ) ? new WMDS_Vehicle( $post_id ) : null;

		$context = self::context( $post_id, $vehicle );
		$site    = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );

		$stored = self::store( $posted, $post_id, $context );
		$mailed = self::send( $posted, $context, $site, $vehicle );

		if ( ! $stored && ! $mailed ) {
			self::bounce( $back, array( 'form' => __( 'The enquiry could not be sent. Please call us instead.', 'wp-mobile-de-sync' ) ), $posted );
		}

		set_transient( $key, $sent + 1, self::RATE_TTL );

		wp_safe_redirect( add_query_arg( 'wmds_enquiry', 'sent', $context['url'] ? $context['url'] : $back ) . '#wmds-enquiry' );
		exit;
	}

	/**
	 * @param int               $post_id
	 * @param WMDS_Vehicle|null $vehicle
	 * @return array{title:string,url:string,ad_id:string,price:string}
	 */
	public static function context( $post_id, $vehicle ) {
		if ( ! $post_id || ! $vehicle ) {
			return array(
				'title' => '',
				'url'   => '',
				'ad_id' => '',
				'price' => '',
			);
		}

		return array(
			'title' => (string) get_the_title( $post_id ),
			'url'   => (string) get_permalink( $post_id ),
			'ad_id' => $vehicle->get( 'vehicleListingID' ),
			'price' => $vehicle->price(),
		);
	}

	/**
	 * @param array $data
	 * @param int   $post_id
	 * @param array $context
	 * @return int The stored post ID, 0 when storing is switched off or failed.
	 */
	private static function store( array $data, $post_id, array $context ) {
		if ( 'no' === WMDS_Settings::get( 'enquiry_store', 'yes' ) ) {
			return 0;
		}

		return self::file( $data, $post_id, $context );
	}

	/**
	 * Files an enquiry, whichever form it came in through.
	 *
	 * The gate is the caller's: the bundled form asks the Enquiries tab,
	 * Contact Form 7 asks the Integrations tab. What lands in the database is
	 * the same record either way, so one screen shows both.
	 *
	 * @param array $data    name, email, phone, message, consent.
	 * @param int   $post_id The vehicle, 0 when the enquiry is about none.
	 * @param array $context As context() returns it.
	 * @param array $extra   Further meta, e.g. where the enquiry came from.
	 * @return int The stored post ID, 0 when it could not be written.
	 */
	public static function file( array $data, $post_id, array $context, array $extra = array() ) {
		$name = isset( $data['name'] ) ? (string) $data['name'] : '';

		if ( '' === $name ) {
			$name = isset( $data['email'] ) ? (string) $data['email'] : __( 'Enquiry', 'wp-mobile-de-sync' );
		}

		$title = '' !== $context['title']
			/* translators: 1: the name the enquirer gave, 2: vehicle title. */
			? sprintf( __( '%1$s about %2$s', 'wp-mobile-de-sync' ), $name, $context['title'] )
			: $name;

		$lead = wp_insert_post(
			array(
				'post_type'    => self::CPT,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => isset( $data['message'] ) ? $data['message'] : '',
			),
			true
		);

		if ( is_wp_error( $lead ) || ! $lead ) {
			return 0;
		}

		$meta = array_merge(
			array(
				'_wmds_name'    => $name,
				'_wmds_email'   => isset( $data['email'] ) ? $data['email'] : '',
				'_wmds_phone'   => isset( $data['phone'] ) ? $data['phone'] : '',
				'_wmds_vehicle' => $post_id,
				'_wmds_ad_id'   => $context['ad_id'],
				'_wmds_url'     => $context['url'],
				'_wmds_consent' => empty( $data['consent'] ) ? '' : self::consent_text(),
				'_wmds_ip'      => self::client_ip(),
				'_wmds_source'  => self::SOURCE,
			),
			$extra
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $lead, $key, $value );
		}

		return (int) $lead;
	}

	/**
	 * @param array             $data
	 * @param array             $context
	 * @param string            $site
	 * @param WMDS_Vehicle|null $vehicle
	 * @return bool
	 */
	private static function send( array $data, array $context, $site, $vehicle ) {
		$mail = self::compose( $data, $context, $site );

		$sent = WMDS_Mail::send(
			self::recipients( $vehicle ),
			$mail['subject'],
			$mail['body'],
			array( WMDS_Mail::reply_to( $data['name'], $data['email'] ) )
		);

		if ( $sent && 'yes' === WMDS_Settings::get( 'enquiry_autoreply', 'no' ) ) {
			$reply = self::compose_confirmation( $data, $context, $site );
			WMDS_Mail::send( $data['email'], $reply['subject'], $reply['body'] );
		}

		return $sent;
	}

	/** @return array<string, string> */
	private static function read_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- the nonce is checked in handle() before anything is done with this.
		return array(
			'name'    => isset( $_POST['wmds_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wmds_name'] ) ) : '',
			'email'   => isset( $_POST['wmds_email'] ) ? sanitize_email( wp_unslash( $_POST['wmds_email'] ) ) : '',
			'phone'   => isset( $_POST['wmds_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['wmds_phone'] ) ) : '',
			'message' => isset( $_POST['wmds_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wmds_message'] ) ) : '',
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

		$token = wp_generate_password( 12, false );

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

		if ( ! self::enabled() ) {
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

	/** @return string */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * @param string $value
	 * @return int
	 */
	private static function length( $value ) {
		return WMDS_Str::length( $value );
	}
}
