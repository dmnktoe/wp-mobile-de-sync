<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Smtp {
	const OPT_FAILURE = 'wmds_mail_failure';

	const MAX_MESSAGE = 500;

	public static function init() {
		add_action( 'wp_mail_failed', array( __CLASS__, 'record' ) );
		add_action( 'wmds_mail_sent', array( __CLASS__, 'clear' ) );
	}

	/** @return array<int, array{key:string,name:string,constants:string[],classes:string[]}> */
	public static function plugins() {
		return array(
			array(
				'key'       => 'wp-mail-smtp',
				'name'      => 'WP Mail SMTP',
				'constants' => array( 'WPMS_PLUGIN_VER' ),
				'classes'   => array( 'WPMailSMTP\\WP' ),
			),
			array(
				'key'       => 'fluent-smtp',
				'name'      => 'FluentSMTP',
				'constants' => array( 'FLUENTMAIL_PLUGIN_VERSION' ),
				'classes'   => array(),
			),
			array(
				'key'       => 'post-smtp',
				'name'      => 'Post SMTP',
				'constants' => array( 'POST_SMTP_VER' ),
				'classes'   => array( 'PostmanOptions' ),
			),
			array(
				'key'       => 'easy-wp-smtp',
				'name'      => 'Easy WP SMTP',
				'constants' => array( 'EasyWPSMTP\\PLUGIN_VER', 'EASY_WP_SMTP_PLUGIN_VERSION' ),
				'classes'   => array(),
			),
			array(
				'key'       => 'wp-offload-ses',
				'name'      => 'WP Offload SES',
				'constants' => array( 'WP_OFFLOAD_SES_VERSION' ),
				'classes'   => array(),
			),
			array(
				'key'       => 'mailgun',
				'name'      => 'Mailgun',
				'constants' => array( 'MAILGUN_VERSION' ),
				'classes'   => array(),
			),
			array(
				'key'       => 'brevo',
				'name'      => 'Brevo',
				'constants' => array( 'SENDINBLUE_WP_VERSION' ),
				'classes'   => array(),
			),
		);
	}

	/** @return array{key:string,name:string,version:string} */
	public static function detect() {
		foreach ( self::plugins() as $plugin ) {
			foreach ( $plugin['constants'] as $constant ) {
				if ( defined( $constant ) ) {
					return array(
						'key'     => $plugin['key'],
						'name'    => $plugin['name'],
						'version' => (string) constant( $constant ),
					);
				}
			}

			foreach ( $plugin['classes'] as $class ) {
				if ( class_exists( $class ) ) {
					return array(
						'key'     => $plugin['key'],
						'name'    => $plugin['name'],
						'version' => '',
					);
				}
			}
		}

		return array();
	}

	/**
	 * @param string $key
	 * @return string
	 */
	public static function transport( $key ) {
		if ( 'wp-mail-smtp' === $key ) {
			$options = get_option( 'wp_mail_smtp', array() );

			return ( is_array( $options ) && isset( $options['mail']['mailer'] ) )
				? (string) $options['mail']['mailer']
				: '';
		}

		if ( 'post-smtp' === $key ) {
			$options = get_option( 'postman_options', array() );

			return ( is_array( $options ) && isset( $options['transport_type'] ) )
				? (string) $options['transport_type']
				: '';
		}

		return '';
	}

	/**
	 * @param array  $found
	 * @param string $transport
	 * @return bool
	 */
	public static function is_authenticated( array $found, $transport ) {
		if ( ! $found ) {
			return false;
		}

		return ( 'mail' !== (string) $transport );
	}

	/**
	 * @param array  $found
	 * @param string $transport
	 * @return string
	 */
	public static function describe( array $found, $transport ) {
		if ( ! $found ) {
			return __( 'PHP mail()', 'wp-mobile-de-sync' );
		}

		$name = isset( $found['name'] ) ? (string) $found['name'] : '';

		if ( ! empty( $found['version'] ) ) {
			$name .= ' ' . $found['version'];
		}

		if ( 'mail' === (string) $transport ) {
			/* translators: %s: name and version of the mailer plugin. */
			return sprintf( __( '%s, still set to PHP mail()', 'wp-mobile-de-sync' ), $name );
		}

		if ( '' === (string) $transport ) {
			return $name;
		}

		/* translators: 1: name and version of the mailer plugin, 2: the transport it is configured for, e.g. smtp. */
		return sprintf( __( '%1$s, sending through %2$s', 'wp-mobile-de-sync' ), $name, $transport );
	}

	/** @return array<int, array{key:string,group:string,label:string,value:string,status:string,hint:string}> */
	public static function checks() {
		$group = __( 'E-mail', 'wp-mobile-de-sync' );

		$found     = self::detect();
		$transport = $found ? self::transport( $found['key'] ) : '';
		$sound     = self::is_authenticated( $found, $transport );

		$checks = array(
			array(
				'key'    => 'mail-delivery',
				'group'  => $group,
				'label'  => __( 'Mail delivery', 'wp-mobile-de-sync' ),
				'value'  => self::describe( $found, $transport ),
				'status' => $sound ? WMDS_Requirements::OK : WMDS_Requirements::WARN,
				'hint'   => $sound
					? ''
					: __( 'PHP mail() is unauthenticated, so many e-mail providers classify enquiries and alerts as spam or reject them without notice. An SMTP plugin that sends through the site\'s mailbox is recommended.', 'wp-mobile-de-sync' ),
			),
		);

		$failure = self::failure();

		if ( $failure ) {
			$checks[] = array(
				'key'    => 'mail-failure',
				'group'  => $group,
				'label'  => __( 'Last mail error', 'wp-mobile-de-sync' ),
				'value'  => $failure['message'],
				'status' => WMDS_Requirements::WARN,
				'hint'   => __( 'Reported by WordPress when an e-mail could not be handed over. The notice is removed automatically once the plugin has sent an e-mail successfully.', 'wp-mobile-de-sync' ),
			);
		}

		return $checks;
	}

	/** @param WP_Error|mixed $error */
	public static function record( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}

		$message = trim( (string) $error->get_error_message() );
		if ( '' === $message ) {
			return;
		}

		update_option(
			self::OPT_FAILURE,
			array(
				'time'    => time(),
				'message' => WMDS_Str::scrub( WMDS_Str::one_line( $message ), self::MAX_MESSAGE ),
			),
			false
		);
	}

	/** @return array{time:int,message:string} */
	public static function failure() {
		$stored = get_option( self::OPT_FAILURE, array() );

		if ( ! is_array( $stored ) || empty( $stored['message'] ) ) {
			return array();
		}

		return array(
			'time'    => isset( $stored['time'] ) ? (int) $stored['time'] : 0,
			'message' => (string) $stored['message'],
		);
	}

	public static function clear() {
		if ( self::failure() ) {
			delete_option( self::OPT_FAILURE );
		}
	}
}
