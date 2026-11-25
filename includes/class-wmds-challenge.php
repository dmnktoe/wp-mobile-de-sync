<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Challenge {
	const ACTION = 'wmds_challenge';

	const OPT_KEY = 'wmds_challenge_key';
	const USED    = 'wmds_pow_used_';

	const ALGORITHM = 'SHA-256';

	const MAX_NUMBER = 100000;

	const LIFETIME = 1800;

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'serve' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'serve' ) );
	}

	/** @return bool */
	public static function enabled() {
		return WMDS_Settings::flag( 'enquiry_challenge' );
	}

	/** @return string */
	public static function url() {
		return add_query_arg( 'action', self::ACTION, admin_url( 'admin-post.php' ) );
	}

	public static function serve() {
		nocache_headers();

		wp_send_json( self::create( self::key(), time() ) );
	}

	/**
	 * @param string   $key
	 * @param int      $now
	 * @param int|null $number
	 * @return array{algorithm:string,challenge:string,maxnumber:int,salt:string,signature:string,lifetime:int}
	 */
	public static function create( $key, $now, $number = null ) {
		$number = null === $number ? random_int( 0, self::MAX_NUMBER ) : (int) $number;

		$salt      = bin2hex( random_bytes( 12 ) ) . '?expires=' . ( (int) $now + self::LIFETIME );
		$challenge = hash( 'sha256', $salt . $number );

		return array(
			'algorithm' => self::ALGORITHM,
			'challenge' => $challenge,
			'maxnumber' => self::MAX_NUMBER,
			'salt'      => $salt,
			'signature' => hash_hmac( 'sha256', $challenge, (string) $key ),
			'lifetime'  => self::LIFETIME,
		);
	}

	/**
	 * @param string $field
	 * @return array{algorithm:string,challenge:string,number:int,salt:string,signature:string}|null
	 */
	public static function decode( $field ) {
		$json = base64_decode( (string) $field, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- the answer is base64 by the ALTCHA format, and only ever decoded into JSON.
		if ( false === $json ) {
			return null;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		foreach ( array( 'algorithm', 'challenge', 'salt', 'signature' ) as $name ) {
			if ( ! isset( $data[ $name ] ) || ! is_string( $data[ $name ] ) ) {
				return null;
			}
		}

		if ( ! isset( $data['number'] ) || ! is_int( $data['number'] ) ) {
			return null;
		}

		return array(
			'algorithm' => $data['algorithm'],
			'challenge' => $data['challenge'],
			'number'    => $data['number'],
			'salt'      => $data['salt'],
			'signature' => $data['signature'],
		);
	}

	/**
	 * @param string $field
	 * @param string $key
	 * @param int    $now
	 * @return bool
	 */
	public static function verify( $field, $key, $now ) {
		$data = self::decode( $field );

		if ( ! $data || self::ALGORITHM !== $data['algorithm'] ) {
			return false;
		}

		if ( $data['number'] < 0 || $data['number'] > self::MAX_NUMBER ) {
			return false;
		}

		$expires = self::expires( $data['salt'] );
		if ( ! $expires || $expires < (int) $now ) {
			return false;
		}

		if ( ! hash_equals( hash( 'sha256', $data['salt'] . $data['number'] ), $data['challenge'] ) ) {
			return false;
		}

		return hash_equals( hash_hmac( 'sha256', $data['challenge'], (string) $key ), $data['signature'] );
	}

	/**
	 * @param string $salt
	 * @return int
	 */
	public static function expires( $salt ) {
		return preg_match( '/[?&]expires=(\d{1,12})(?:&|$)/', (string) $salt, $m ) ? (int) $m[1] : 0;
	}

	/**
	 * @param string $field
	 * @return bool
	 */
	public static function accept( $field ) {
		$now = time();

		if ( ! self::verify( $field, self::key(), $now ) ) {
			return false;
		}

		$data = self::decode( $field );
		$used = self::USED . md5( $data['challenge'] );

		if ( false !== get_transient( $used ) ) {
			return false;
		}

		set_transient( $used, 1, max( 60, self::expires( $data['salt'] ) - $now ) );

		return true;
	}

	/** @return string */
	public static function key() {
		$key = (string) get_option( self::OPT_KEY, '' );

		if ( strlen( $key ) < 32 ) {
			$key = bin2hex( random_bytes( 32 ) );
			update_option( self::OPT_KEY, $key, true );
		}

		return $key;
	}
}
