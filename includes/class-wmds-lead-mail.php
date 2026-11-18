<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The mails an enquiry sends: to the dealer, and the copy for the enquirer.
 */
class WMDS_Lead_Mail {

	/**
	 * @param WMDS_Vehicle|null $vehicle
	 * @return string[] Where a new enquiry is sent.
	 */
	public static function recipients( WMDS_Vehicle $vehicle = null ) {
		$extra = array();

		if ( $vehicle && WMDS_Settings::flag( 'enquiry_copy_seller' ) ) {
			$extra[] = $vehicle->seller_email();
		}

		$to = WMDS_Mail::addresses( WMDS_Settings::get( 'enquiry_recipient' ), $extra );

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

	/**
	 * @param array             $data
	 * @param array             $context
	 * @param string            $site
	 * @param WMDS_Vehicle|null $vehicle
	 * @return bool
	 */
	public static function send( array $data, array $context, $site, $vehicle ) {
		$mail = self::compose( $data, $context, $site );

		$sent = WMDS_Mail::send(
			self::recipients( $vehicle ),
			$mail['subject'],
			$mail['body'],
			array( WMDS_Mail::reply_to( $data['name'], $data['email'] ) )
		);

		if ( $sent && WMDS_Settings::flag( 'enquiry_autoreply' ) ) {
			$reply = self::compose_confirmation( $data, $context, $site );
			WMDS_Mail::send( $data['email'], $reply['subject'], $reply['body'] );
		}

		return $sent;
	}
}
