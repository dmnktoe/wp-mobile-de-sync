<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Mail {
	const PLAIN = 'Content-Type: text/plain; charset=UTF-8';

	/**
	 * Reads a settings field into a list of addresses.
	 *
	 * An empty or unusable field falls back to the site administrator rather
	 * than to nobody: a notification sent nowhere is worse than one sent to
	 * the wrong person, because nothing says it did not arrive.
	 *
	 * @param string   $configured Comma-separated, as stored in the settings.
	 * @param string[] $extra      Further addresses, added once each.
	 * @return string[]
	 */
	public static function addresses( $configured, array $extra = array() ) {
		$to = array();

		foreach ( explode( ',', (string) $configured ) as $address ) {
			$address = trim( $address );
			if ( '' !== $address && is_email( $address ) && ! in_array( $address, $to, true ) ) {
				$to[] = $address;
			}
		}

		if ( ! $to ) {
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

	/**
	 * @param string|string[] $to
	 * @param string          $subject
	 * @param string          $body
	 * @param string[]        $headers Added to the plain-text content type.
	 * @return bool
	 */
	public static function send( $to, $subject, $body, array $headers = array() ) {
		if ( ! $to || '' === (string) $subject ) {
			return false;
		}

		return (bool) wp_mail(
			$to,
			$subject,
			$body,
			array_merge( array( self::PLAIN ), $headers )
		);
	}

	/**
	 * @param string $name
	 * @param string $email
	 * @return string A Reply-To that cannot carry a second header, whatever
	 *                was typed into the name field.
	 */
	public static function reply_to( $name, $email ) {
		$name  = WMDS_Str::one_line( $name );
		$email = WMDS_Str::one_line( $email );

		return ( '' === $name )
			? sprintf( 'Reply-To: %s', $email )
			: sprintf( 'Reply-To: %s <%s>', $name, $email );
	}
}
