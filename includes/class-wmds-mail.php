<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Mail {
	const PLAIN = 'Content-Type: text/plain; charset=UTF-8';

	/**
	 * @param string   $configured
	 * @param string[] $extra
	 * @param bool     $fallback
	 * @return string[]
	 */
	public static function addresses( $configured, array $extra = array(), $fallback = true ) {
		$to = array();

		foreach ( explode( ',', (string) $configured ) as $address ) {
			$address = trim( $address );
			if ( '' !== $address && is_email( $address ) && ! in_array( $address, $to, true ) ) {
				$to[] = $address;
			}
		}

		if ( ! $to && $fallback ) {
			$to[] = (string) get_option( 'admin_email' );
		}

		foreach ( $extra as $address ) {
			$address = trim( (string) $address );
			if ( '' !== $address && is_email( $address ) && ! in_array( $address, $to, true ) ) {
				$to[] = $address;
			}
		}

		return $to;
	}

	/** @return string[] */
	public static function test_recipients() {
		$to = WMDS_Lead_Mail::recipients();

		if ( WMDS_Alerts::enabled() ) {
			$to = array_merge( $to, WMDS_Alerts::recipients() );
		}

		return array_values( array_unique( $to ) );
	}

	/**
	 * @param string|string[] $to
	 * @param string          $subject
	 * @param string          $body
	 * @param string[]        $headers
	 * @return bool
	 */
	public static function send( $to, $subject, $body, array $headers = array() ) {
		if ( ! $to || '' === (string) $subject ) {
			return false;
		}

		$sent = (bool) wp_mail(
			$to,
			$subject,
			$body,
			array_merge( array( self::PLAIN ), $headers )
		);

		if ( $sent ) {
			do_action( 'wmds_mail_sent', $to, $subject );
		}

		return $sent;
	}

	/**
	 * @param string $name
	 * @param string $email
	 * @return string
	 */
	public static function reply_to( $name, $email ) {
		$name  = WMDS_Str::one_line( $name );
		$email = WMDS_Str::one_line( $email );

		return ( '' === $name )
			? sprintf( 'Reply-To: %s', $email )
			: sprintf( 'Reply-To: "%s" <%s>', str_replace( array( '"', '\\' ), '', $name ), $email );
	}
}
