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

		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'privacy_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'privacy_erasers' ) );

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
			// The notice is stored as it was shown, link and all; here it is
			// read, not clicked, so the markup would only be in the way.
			__( 'Consent', 'wp-mobile-de-sync' )          => wp_strip_all_tags( (string) get_post_meta( $post->ID, '_wmds_consent', true ) ),
			__( 'Received through', 'wp-mobile-de-sync' ) => self::source_label(
				(string) get_post_meta( $post->ID, '_wmds_source', true ),
				(string) get_post_meta( $post->ID, '_wmds_form', true )
			),
			__( 'From', 'wp-mobile-de-sync' )             => (string) get_post_meta( $post->ID, '_wmds_ip', true ),
			__( 'Delivery', 'wp-mobile-de-sync' )         => (string) get_post_meta( $post->ID, '_wmds_delivery', true ),
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

		printf( '<p><strong>%s</strong></p>', esc_html_x( 'Message', 'what the enquirer wrote', 'wp-mobile-de-sync' ) );
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

	/**
	 * What became of the mail, for the record of the enquiry.
	 *
	 * "Handed over" and no more: wp_mail() knows that the mailer took the
	 * message, not that it arrived. That is still the first question when an
	 * enquiry is on the screen but not in the inbox: was it sent at all, and
	 * to whom.
	 *
	 * @param bool     $mailed  What wp_mail() returned.
	 * @param string[] $to      The recipients it was sent to.
	 * @param array    $failure As WMDS_Smtp::failure() returns it.
	 * @param int      $tried   When the attempt started; an older failure is someone else's.
	 * @return string
	 */
	public static function delivery_note( $mailed, array $to, array $failure, $tried ) {
		if ( $mailed ) {
			/* translators: %s: the addresses the enquiry was sent to. */
			return sprintf( __( 'Handed over to the mailer for %s', 'wp-mobile-de-sync' ), implode( ', ', $to ) );
		}

		if ( ! empty( $failure['message'] ) && isset( $failure['time'] ) && (int) $failure['time'] >= (int) $tried ) {
			/* translators: %s: the error WordPress reported. */
			return sprintf( __( 'Not sent: %s', 'wp-mobile-de-sync' ), $failure['message'] );
		}

		return __( 'Not sent', 'wp-mobile-de-sync' );
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
		$subject = sprintf( __( '[%s] Confirmation of your enquiry', 'wp-mobile-de-sync' ), $site );

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

		// The message is not sent back. The address it would go to is
		// whatever was typed into the form, and a confirmation that quotes
		// the text is a way to have this site mail anything to anybody.
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

		// Only for somebody who is logged in. A nonce for a visitor is the
		// same for every visitor and protects nothing, and it is printed into
		// a page that a page cache keeps for longer than the nonce lives:
		// the first enquiry from a cached vehicle page would always bounce.
		if ( is_user_logged_in() && ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '', self::NONCE ) ) {
			self::refuse( $back, 'expired' );
		}

		if ( ! self::enabled() ) {
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

		$consent_required = ( '' !== self::consent_text() );

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

		$context = self::context( $post_id, $vehicle );
		$site    = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );

		$stored = self::store( $posted, $post_id, $context );
		$tried  = time();
		$mailed = self::send( $posted, $context, $site, $vehicle );

		if ( $stored ) {
			update_post_meta( $stored, '_wmds_delivery', wp_slash( self::delivery_note( $mailed, self::recipients( $vehicle ), WMDS_Smtp::failure(), $tried ) ) );
		}

		if ( ! $stored && ! $mailed ) {
			self::bounce( $back, array( 'form' => __( 'Unfortunately, the enquiry could not be sent. Please contact us by phone.', 'wp-mobile-de-sync' ) ), $posted );
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

		// Slashed: WordPress unslashes what it is handed, and a message that
		// mentions C:\\ or a name with a backslash would lose it.
		$lead = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => self::CPT,
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => isset( $data['message'] ) ? $data['message'] : '',
				)
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
			update_post_meta( $lead, $key, wp_slash( $value ) );
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

	/**
	 * @param array $exporters
	 * @return array
	 */
	public static function privacy_exporters( $exporters ) {
		$exporters = is_array( $exporters ) ? $exporters : array();

		$exporters['wp-mobile-de-sync'] = array(
			'exporter_friendly_name' => __( 'Vehicle enquiries', 'wp-mobile-de-sync' ),
			'callback'               => array( __CLASS__, 'privacy_export' ),
		);

		return $exporters;
	}

	/**
	 * @param array $erasers
	 * @return array
	 */
	public static function privacy_erasers( $erasers ) {
		$erasers = is_array( $erasers ) ? $erasers : array();

		$erasers['wp-mobile-de-sync'] = array(
			'eraser_friendly_name' => __( 'Vehicle enquiries', 'wp-mobile-de-sync' ),
			'callback'             => array( __CLASS__, 'privacy_erase' ),
		);

		return $erasers;
	}

	/**
	 * @param string $email
	 * @param int    $page
	 * @return int[] Enquiries sent from that address, fifty at a time.
	 */
	private static function leads_of( $email, $page ) {
		$email = trim( (string) $email );
		if ( '' === $email ) {
			return array();
		}

		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => self::CPT,
					'post_status'    => 'any',
					'posts_per_page' => 50,
					'paged'          => max( 1, (int) $page ),
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
					'meta_key'       => '_wmds_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a privacy request, run by hand and rarely.
					'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
				)
			)
		);
	}

	/**
	 * @param string $email
	 * @param int    $page
	 * @return array{data:array,done:bool}
	 */
	public static function privacy_export( $email, $page = 1 ) {
		$ids  = self::leads_of( $email, $page );
		$data = array();

		$fields = array(
			'_wmds_name'    => __( 'Name', 'wp-mobile-de-sync' ),
			'_wmds_email'   => __( 'E-mail', 'wp-mobile-de-sync' ),
			'_wmds_phone'   => __( 'Phone', 'wp-mobile-de-sync' ),
			'_wmds_url'     => __( 'Vehicle', 'wp-mobile-de-sync' ),
			'_wmds_consent' => __( 'Consent', 'wp-mobile-de-sync' ),
			'_wmds_ip'      => __( 'From', 'wp-mobile-de-sync' ),
		);

		foreach ( $ids as $id ) {
			$rows = array(
				array(
					'name'  => __( 'Received', 'wp-mobile-de-sync' ),
					'value' => (string) get_post_field( 'post_date', $id ),
				),
				array(
					'name'  => __( 'Your message', 'wp-mobile-de-sync' ),
					'value' => (string) get_post_field( 'post_content', $id ),
				),
			);

			foreach ( $fields as $key => $label ) {
				$value = (string) get_post_meta( $id, $key, true );
				if ( '' !== $value ) {
					$rows[] = array(
						'name'  => $label,
						'value' => $value,
					);
				}
			}

			$data[] = array(
				'group_id'    => 'wmds-enquiries',
				'group_label' => __( 'Vehicle enquiries', 'wp-mobile-de-sync' ),
				'item_id'     => 'wmds-enquiry-' . $id,
				'data'        => $rows,
			);
		}

		return array(
			'data' => $data,
			'done' => count( $ids ) < 50,
		);
	}

	/**
	 * @param string $email
	 * @param int    $page Unused: what was found is deleted, so the next call
	 *                     starts at the beginning again.
	 * @return array{items_removed:int,items_retained:bool,messages:array,done:bool}
	 */
	public static function privacy_erase( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the signature is WordPress's.
		$ids     = self::leads_of( $email, 1 );
		$removed = 0;

		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				++$removed;
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => $removed < count( $ids ),
			'messages'       => array(),
			'done'           => count( $ids ) < 50 || $removed < count( $ids ),
		);
	}
}
