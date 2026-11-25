<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Spam {
	const MAX_LINKS = 3;

	const MAX_BLOCKED = 500;

	/** @return string[] */
	public static function builtin_words() {
		return array(
			'unsubscribe',
			'opt out',
			'opt-out',
			'backlink',
			'backlinks',
			'seo',
			'guest post',
			'casino',
			'viagra',
		);
	}

	/** @return string[] */
	public static function shared_domains() {
		return array(
			'gmail.com',
			'googlemail.com',
			'gmx.de',
			'gmx.net',
			'gmx.at',
			'gmx.ch',
			'web.de',
			't-online.de',
			'freenet.de',
			'arcor.de',
			'online.de',
			'email.de',
			'mail.de',
			'posteo.de',
			'mailbox.org',
			'vodafone.de',
			'yahoo.com',
			'yahoo.de',
			'ymail.com',
			'outlook.com',
			'outlook.de',
			'hotmail.com',
			'hotmail.de',
			'live.com',
			'live.de',
			'msn.com',
			'icloud.com',
			'me.com',
			'mac.com',
			'aol.com',
			'aol.de',
			'proton.me',
			'protonmail.com',
			'mail.ru',
			'yandex.ru',
		);
	}

	/**
	 * @param string $text
	 * @return string[]
	 */
	public static function lines( $text ) {
		$entries = preg_split( '/[\r\n,]+/', strtolower( (string) $text ) );
		$entries = array_map( 'trim', $entries ? $entries : array() );

		return array_values( array_unique( array_filter( $entries, 'strlen' ) ) );
	}

	/**
	 * @param string $email
	 * @return string
	 */
	public static function domain( $email ) {
		$email = strtolower( trim( (string) $email ) );
		$at    = strrpos( $email, '@' );

		return false === $at ? '' : substr( $email, $at + 1 );
	}

	/**
	 * @param string   $email
	 * @param string[] $blocked
	 * @return string
	 */
	public static function blocked( $email, array $blocked ) {
		$email  = strtolower( trim( (string) $email ) );
		$domain = self::domain( $email );

		if ( '' === $email ) {
			return '';
		}

		foreach ( $blocked as $entry ) {
			$entry = strtolower( trim( (string) $entry ) );

			if ( '' === $entry ) {
				continue;
			}

			if ( '@' !== $entry[0] && false !== strpos( $entry, '@' ) ) {
				if ( $entry === $email ) {
					return $entry;
				}

				continue;
			}

			$host = ltrim( $entry, '@' );

			if ( '' !== $host && '' !== $domain && ( $host === $domain || substr( $domain, -strlen( $host ) - 1 ) === '.' . $host ) ) {
				return $entry;
			}
		}

		return '';
	}

	/**
	 * @param string $text
	 * @return int
	 */
	public static function links( $text ) {
		return (int) preg_match_all( '~(?:https?://|www\.)[^\s<>"]+~i', (string) $text );
	}

	/**
	 * @param string   $text
	 * @param string[] $words
	 * @return string
	 */
	public static function word( $text, array $words ) {
		$text = (string) $text;

		foreach ( $words as $word ) {
			$word = trim( (string) $word );

			if ( '' === $word ) {
				continue;
			}

			if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $word, '/' ) . '(?![\p{L}\p{N}])/iu', $text ) ) {
				return $word;
			}
		}

		return '';
	}

	/**
	 * @param array    $data
	 * @param string[] $blocked
	 * @param string[] $words
	 * @return string
	 */
	public static function reason( array $data, array $blocked, array $words ) {
		$email   = isset( $data['email'] ) ? (string) $data['email'] : '';
		$message = isset( $data['message'] ) ? (string) $data['message'] : '';
		$name    = isset( $data['name'] ) ? (string) $data['name'] : '';

		$entry = self::blocked( $email, $blocked );
		if ( '' !== $entry ) {
			/* translators: %s: the blocklist entry that matched. */
			return sprintf( __( 'Sender is on the blocklist (%s)', 'wp-mobile-de-sync' ), $entry );
		}

		$word = self::word( $name . "\n" . $message, $words );
		if ( '' !== $word ) {
			/* translators: %s: the word that was found. */
			return sprintf( __( 'Contains "%s"', 'wp-mobile-de-sync' ), $word );
		}

		$links = self::links( $message );
		if ( $links >= self::MAX_LINKS ) {
			/* translators: %d: number of links. */
			return sprintf( _n( 'Contains %d link', 'Contains %d links', $links, 'wp-mobile-de-sync' ), $links );
		}

		return '';
	}

	/**
	 * @param string $email
	 * @return string
	 */
	public static function entry_for( $email ) {
		$email  = strtolower( trim( (string) $email ) );
		$domain = self::domain( $email );

		if ( '' === $domain ) {
			return '';
		}

		return in_array( $domain, self::shared_domains(), true ) ? $email : '@' . $domain;
	}

	/**
	 * @param string[] $blocked
	 * @param string   $email
	 * @return string[]
	 */
	public static function learn( array $blocked, $email ) {
		$entry = self::entry_for( $email );

		if ( '' === $entry || '' !== self::blocked( $email, $blocked ) ) {
			return array_values( $blocked );
		}

		$blocked[] = $entry;

		return array_slice( array_values( $blocked ), -self::MAX_BLOCKED );
	}

	/**
	 * @param string[] $blocked
	 * @param string   $email
	 * @return string[]
	 */
	public static function forget( array $blocked, $email ) {
		$left = array();

		foreach ( $blocked as $entry ) {
			if ( '' === self::blocked( $email, array( $entry ) ) ) {
				$left[] = $entry;
			}
		}

		return $left;
	}

	/** @return string[] */
	public static function blocklist() {
		return self::lines( (string) WMDS_Settings::get( 'enquiry_blocklist' ) );
	}

	/** @param string[] $blocked */
	public static function save_blocklist( array $blocked ) {
		WMDS_Settings::update( array( 'enquiry_blocklist' => implode( "\n", $blocked ) ) );
	}

	/** @return string[] */
	public static function words() {
		$words = array_merge( self::builtin_words(), self::lines( (string) WMDS_Settings::get( 'enquiry_spam_words' ) ) );

		/** @param string[] $words */
		return array_values( array_unique( (array) apply_filters( 'wmds_spam_words', $words ) ) );
	}

	/**
	 * @param array $data
	 * @return string
	 */
	public static function check( array $data ) {
		$reason = self::reason( $data, self::blocklist(), self::words() );

		/**
		 * @param string $reason
		 * @param array  $data
		 */
		return (string) apply_filters( 'wmds_enquiry_spam', $reason, $data );
	}
}
