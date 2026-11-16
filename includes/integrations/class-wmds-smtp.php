<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What happens to a mail once wp_mail() has been called.
 *
 * Two of the plugin's messages matter to somebody: an enquiry a visitor is
 * waiting for an answer to, and an alert that says the sync has stopped. Both
 * go through wp_mail(), and on a stock WordPress that means PHP's mail() —
 * unauthenticated, without SPF or DKIM, and with no record of what became of
 * the message. It is handed to the local sendmail, which reports success, and
 * the mail is dropped by the receiving side hours later.
 *
 * This class sends nothing. It knows which mailer plugin is installed,
 * whether that plugin is actually sending through it, and what the last
 * delivery failure on this site was, so the answer to "the enquiry never
 * arrived" is on a screen rather than in a support ticket.
 */
class WMDS_Smtp {
	const OPT_FAILURE = 'wmds_mail_failure';

	const MAX_MESSAGE = 500;

	public static function init() {
		add_action( 'wp_mail_failed', array( __CLASS__, 'record' ) );
		add_action( 'wmds_mail_sent', array( __CLASS__, 'clear' ) );
	}

	/**
	 * The mailer plugins worth recognising, newest-first by how often they
	 * turn up on a dealer site.
	 *
	 * A plugin is recognised by a constant it defines or a class it declares,
	 * never by a file path: a plugin that was renamed or installed from a zip
	 * still defines its constant.
	 *
	 * @return array<int, array{key:string,name:string,constants:string[],classes:string[]}>
	 */
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

	/**
	 * @return array{key:string,name:string,version:string} Empty when the site
	 *         has no mailer plugin at all.
	 */
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
	 * How the detected plugin has been told to send.
	 *
	 * Only read for the plugins that keep it somewhere stable. An empty string
	 * means "installed, and we cannot tell" — which is not a complaint.
	 *
	 * @param string $key As returned by detect().
	 * @return string e.g. smtp, gmail, sendinblue, or "mail" for PHP's own.
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
	 * Whether mail leaves this site by a route worth trusting.
	 *
	 * @param array  $found     As returned by detect().
	 * @param string $transport As returned by transport().
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
	 * @return string What to print as the value of the check.
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

	/**
	 * The rows the System tab shows under "E-mail".
	 *
	 * @return array<int, array{key:string,group:string,label:string,value:string,status:string,hint:string}>
	 */
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
					: __( 'PHP mail() is unauthenticated, so enquiries and alerts are treated as spam by many providers or dropped without a bounce. An SMTP plugin sending through the mailbox the site answers from is the usual answer.', 'wp-mobile-de-sync' ),
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
				'hint'   => __( 'Reported by WordPress when a message could not be handed over. It clears itself as soon as the plugin sends one successfully.', 'wp-mobile-de-sync' ),
			);
		}

		return $checks;
	}

	/**
	 * Keeps the last delivery failure, whoever caused it.
	 *
	 * Every mail on the site is watched, not only ours: a contact form that
	 * cannot be delivered says just as much about whether an enquiry will
	 * arrive, and it usually fails first.
	 *
	 * @param WP_Error|mixed $error
	 */
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
				// one_line() first: a PHPMailer error is several lines, and
				// scrub() would drop the breaks rather than close the words up.
				'message' => WMDS_Str::scrub( WMDS_Str::one_line( $message ), self::MAX_MESSAGE ),
			),
			false
		);
	}

	/** @return array{time:int,message:string} Empty when nothing has failed. */
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

	/** Forgets the last failure, because a mail has just gone out. */
	public static function clear() {
		if ( self::failure() ) {
			delete_option( self::OPT_FAILURE );
		}
	}
}
