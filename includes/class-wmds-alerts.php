<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Alerts {
	const CHECK_HOOK  = 'wmds_alert_event';
	const WEEKLY_HOOK = 'wmds_alert_weekly_event';

	const OPT_LAST = 'wmds_last_alert';

	const PROBLEM  = 'problem';
	const RECOVERY = 'recovery';

	const LOG_LINES = 10;

	/**
	 * The states worth waking somebody up for.
	 *
	 * Not configured is left out on purpose: a plugin nobody has set up yet
	 * is not a fault, and mailing about it every hour would be.
	 *
	 * @var string[]
	 */
	private static $watched = array( WMDS_Status::FAILED, WMDS_Status::STALE );

	public static function init() {
		add_action( self::CHECK_HOOK, array( __CLASS__, 'check' ) );
		add_action( self::WEEKLY_HOOK, array( __CLASS__, 'weekly' ) );
		add_action( 'wmds_run_finished', array( __CLASS__, 'check' ) );
		add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
	}

	/** @return bool */
	public static function enabled() {
		return 'yes' === WMDS_Settings::get( 'alerts_enabled', 'no' );
	}

	/** @return int Seconds before the same problem is reported again. */
	public static function cooldown() {
		$allowed = array( 3600, 21600, 43200, 86400 );
		$stored  = (int) WMDS_Settings::get( 'alerts_cooldown', 21600 );

		return in_array( $stored, $allowed, true ) ? $stored : 21600;
	}

	/** @return string[] */
	public static function recipients() {
		$to = array();

		foreach ( explode( ',', (string) WMDS_Settings::get( 'alerts_recipient', '' ) ) as $address ) {
			$address = trim( $address );
			if ( '' !== $address && is_email( $address ) ) {
				$to[] = $address;
			}
		}

		if ( ! $to ) {
			$to[] = (string) get_option( 'admin_email' );
		}

		/**
		 * @param string[] $to
		 */
		return (array) apply_filters( 'wmds_alert_recipients', $to );
	}

	/**
	 * Whether this state is worth an e-mail, and which kind.
	 *
	 * A problem is reported once and then not again until the cooldown has
	 * passed — a sync that fails every fifteen minutes should produce one
	 * mail, not ninety-six a day. A different problem is reported straight
	 * away, and so is the recovery, because "it is fixed" is the one message
	 * nobody wants to wait six hours for.
	 *
	 * @param string $level    The current health level.
	 * @param array  $memory   What was reported last: level and time.
	 * @param int    $now      Unix time.
	 * @param int    $cooldown Seconds.
	 * @return string One of the kind constants, empty when nothing is due.
	 */
	public static function decide( $level, array $memory, $now, $cooldown ) {
		$last_level = isset( $memory['level'] ) ? (string) $memory['level'] : '';
		$last_time  = isset( $memory['time'] ) ? (int) $memory['time'] : 0;

		$problem = in_array( $level, self::$watched, true );

		if ( ! $problem ) {
			$was_problem = in_array( $last_level, self::$watched, true );

			return $was_problem ? self::RECOVERY : '';
		}

		if ( $level !== $last_level ) {
			return self::PROBLEM;
		}

		return ( $now - $last_time >= $cooldown ) ? self::PROBLEM : '';
	}

	public static function check() {
		if ( ! self::enabled() || ! WMDS_Settings::is_configured() ) {
			return;
		}

		$status = WMDS_Status::get();

		if ( WMDS_Status::RUNNING === $status['level'] ) {
			return;
		}

		$memory = get_option( self::OPT_LAST, array() );
		$memory = is_array( $memory ) ? $memory : array();

		$kind = self::decide( $status['level'], $memory, time(), self::cooldown() );
		if ( '' === $kind ) {
			return;
		}

		$mail = self::compose( $kind, $status, self::log(), self::site(), WMDS_Status::settings_url( 'status' ) );

		self::send( $mail );

		update_option(
			self::OPT_LAST,
			array(
				'level' => $status['level'],
				'time'  => time(),
			),
			false
		);
	}

	public static function weekly() {
		if ( ! self::enabled() || 'yes' !== WMDS_Settings::get( 'alerts_weekly', 'no' ) ) {
			return;
		}
		if ( ! WMDS_Settings::is_configured() ) {
			return;
		}

		self::send( self::compose_summary( WMDS_Status::get(), self::site(), WMDS_Status::settings_url( 'status' ) ) );
	}

	/**
	 * @param string $kind
	 * @param array  $status
	 * @param array  $log
	 * @param string $site
	 * @param string $url
	 * @return array{subject:string,body:string}
	 */
	public static function compose( $kind, array $status, array $log, $site, $url ) {
		$label = isset( $status['label'] ) ? (string) $status['label'] : '';

		if ( self::RECOVERY === $kind ) {
			/* translators: %s: site name. */
			$subject = sprintf( __( '[%s] The vehicle sync is working again', 'wp-mobile-de-sync' ), $site );
		} else {
			$subject = sprintf(
				/* translators: 1: site name, 2: the health state, e.g. "Sync overdue". */
				__( '[%1$s] Vehicle sync: %2$s', 'wp-mobile-de-sync' ),
				$site,
				$label
			);
		}

		$lines = array( $label );

		if ( ! empty( $status['explanation'] ) ) {
			$lines[] = (string) $status['explanation'];
		}

		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %d: number of published vehicles. */
			__( 'Vehicles published: %d', 'wp-mobile-de-sync' ),
			isset( $status['count'] ) ? (int) $status['count'] : 0
		);

		if ( ! empty( $status['last'] ) && is_array( $status['last'] ) ) {
			$lines[] = __( 'Last run', 'wp-mobile-de-sync' ) . ': ' . WMDS_Status::summarise( $status['last'] );
		}

		if ( ! empty( $status['last_ts'] ) ) {
			$lines[] = __( 'Last run at', 'wp-mobile-de-sync' ) . ': ' . WMDS_Status::local_time( (int) $status['last_ts'] );
		}

		if ( $log ) {
			$lines[] = '';
			$lines[] = __( 'From the log:', 'wp-mobile-de-sync' );
			foreach ( array_slice( $log, 0, self::LOG_LINES ) as $entry ) {
				$lines[] = '  ' . self::line( $entry );
			}
		}

		$lines[] = '';
		$lines[] = $url;

		return array(
			'subject' => $subject,
			'body'    => implode( "\n", $lines ),
		);
	}

	/**
	 * @param array  $status
	 * @param string $site
	 * @param string $url
	 * @return array{subject:string,body:string}
	 */
	public static function compose_summary( array $status, $site, $url ) {
		/* translators: %s: site name. */
		$subject = sprintf( __( '[%s] Vehicle sync, the week in one mail', 'wp-mobile-de-sync' ), $site );

		$lines = array(
			isset( $status['label'] ) ? (string) $status['label'] : '',
			'',
			sprintf(
				/* translators: %d: number of published vehicles. */
				__( 'Vehicles published: %d', 'wp-mobile-de-sync' ),
				isset( $status['count'] ) ? (int) $status['count'] : 0
			),
		);

		if ( ! empty( $status['last'] ) && is_array( $status['last'] ) ) {
			$lines[] = __( 'Last run', 'wp-mobile-de-sync' ) . ': ' . WMDS_Status::summarise( $status['last'] );
		}
		if ( ! empty( $status['last_ts'] ) ) {
			$lines[] = __( 'Last run at', 'wp-mobile-de-sync' ) . ': ' . WMDS_Status::local_time( (int) $status['last_ts'] );
		}
		if ( ! empty( $status['next'] ) ) {
			$lines[] = __( 'Next run', 'wp-mobile-de-sync' ) . ': ' . WMDS_Status::local_time( (int) $status['next'] );
		}

		$lines[] = '';
		$lines[] = $url;

		return array(
			'subject' => $subject,
			'body'    => implode( "\n", $lines ),
		);
	}

	/**
	 * @param array $mail
	 * @return bool
	 */
	public static function send( array $mail ) {
		if ( empty( $mail['subject'] ) ) {
			return false;
		}

		return (bool) wp_mail(
			self::recipients(),
			$mail['subject'],
			$mail['body'],
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/** @return bool Whether a test alert went out. */
	public static function send_test() {
		$status = WMDS_Status::get();

		$mail = self::compose(
			self::PROBLEM,
			$status,
			self::log(),
			self::site(),
			WMDS_Status::settings_url( 'status' )
		);

		$mail['subject'] = sprintf(
			/* translators: %s: the subject an alert would have carried. */
			__( '[Test] %s', 'wp-mobile-de-sync' ),
			$mail['subject']
		);

		return self::send( $mail );
	}

	/** Clears the memory, so the next problem is reported rather than suppressed. */
	public static function forget() {
		delete_option( self::OPT_LAST );
	}

	/**
	 * Keeps the schedule in step with the setting.
	 *
	 * Called on activation and on every admin request, so an installation
	 * that was updated rather than activated gets its events too.
	 */
	public static function schedule() {
		if ( ! self::enabled() ) {
			self::unschedule();

			return;
		}

		if ( ! wp_next_scheduled( self::CHECK_HOOK ) ) {
			wp_schedule_event( time() + 900, 'hourly', self::CHECK_HOOK );
		}
		if ( ! wp_next_scheduled( self::WEEKLY_HOOK ) ) {
			wp_schedule_event( time() + 3600, 'weekly', self::WEEKLY_HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::CHECK_HOOK );
		wp_clear_scheduled_hook( self::WEEKLY_HOOK );
	}

	/** @return array The last log entries, newest first. */
	private static function log() {
		$log = get_option( WMDS_Importer::OPT_LOG, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * @param mixed $entry
	 * @return string
	 */
	private static function line( $entry ) {
		if ( is_array( $entry ) ) {
			$time    = isset( $entry['time'] ) ? (string) $entry['time'] : '';
			$message = isset( $entry['message'] ) ? (string) $entry['message'] : '';

			return trim( $time . ' ' . $message );
		}

		return (string) $entry;
	}

	/** @return string */
	private static function site() {
		return wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
	}
}
