<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Status {
	const PAGE  = 'wmds-settings';
	const NONCE = 'wmds_admin';

	const OPT_LAST_TEST = 'wmds_last_test';

	const STALE_AFTER = 21600;

	const UNCONFIGURED = 'unconfigured';
	const RUNNING      = 'running';
	const NEVER        = 'never';
	const STALE        = 'stale';
	const ABORTED      = 'aborted';
	const FAILED       = 'failed';
	const CATCHING_UP  = 'catching_up';
	const OK           = 'ok';

	/**
	 * @param bool  $configured
	 * @param bool  $running
	 * @param array $last
	 * @param int   $last_ts
	 * @param int   $now
	 * @param int   $stale
	 * @return string
	 */
	public static function level( $configured, $running, array $last, $last_ts, $now, $stale = self::STALE_AFTER ) {
		if ( ! $configured ) {
			return self::UNCONFIGURED;
		}
		if ( $running ) {
			return self::RUNNING;
		}
		if ( ! $last || $last_ts <= 0 ) {
			return self::NEVER;
		}
		if ( $last_ts < $now - max( 1, (int) $stale ) ) {
			return self::STALE;
		}
		if ( ! empty( $last['error'] ) ) {
			return self::ABORTED;
		}
		if ( ! empty( $last['failed'] ) ) {
			return self::FAILED;
		}
		if ( ! empty( $last['pending'] ) ) {
			return self::CATCHING_UP;
		}

		return self::OK;
	}

	/**
	 * @param string $level
	 * @return string
	 */
	public static function severity( $level ) {
		if ( self::UNCONFIGURED === $level || self::ABORTED === $level ) {
			return 'error';
		}
		if ( self::RUNNING === $level || self::CATCHING_UP === $level ) {
			return 'info';
		}
		if ( self::OK === $level ) {
			return 'ok';
		}

		return 'warning';
	}

	/**
	 * @param string $level
	 * @return string
	 */
	public static function label( $level ) {
		$labels = array(
			self::UNCONFIGURED => __( 'Not configured', 'wp-mobile-de-sync' ),
			self::RUNNING      => __( 'Sync running', 'wp-mobile-de-sync' ),
			self::NEVER        => __( 'Waiting for the first run', 'wp-mobile-de-sync' ),
			self::STALE        => __( 'Sync overdue', 'wp-mobile-de-sync' ),
			self::ABORTED      => __( 'Last run was aborted', 'wp-mobile-de-sync' ),
			self::FAILED       => __( 'Last run had failures', 'wp-mobile-de-sync' ),
			self::CATCHING_UP  => __( 'Sync in progress', 'wp-mobile-de-sync' ),
			self::OK           => __( 'Up to date', 'wp-mobile-de-sync' ),
		);

		return isset( $labels[ $level ] ) ? $labels[ $level ] : $level;
	}

	/**
	 * @param string $level
	 * @param int    $pending
	 * @return string
	 */
	public static function explanation( $level, $pending = 0 ) {
		if ( self::CATCHING_UP === $level ) {
			return sprintf(
				/* translators: %d: number of vehicles still to be processed. */
				_n(
					'%d vehicle is still to be processed. The next pass starts automatically; vehicles sold in the meantime are removed once the entire inventory has been read.',
					'%d vehicles are still to be processed. The next pass starts automatically; vehicles sold in the meantime are removed once the entire inventory has been read.',
					max( 1, (int) $pending ),
					'wp-mobile-de-sync'
				),
				max( 1, (int) $pending )
			);
		}

		$texts = array(
			self::UNCONFIGURED => __( 'Username, password and seller ID (or dealer name) are missing. No vehicles will be imported until they are saved.', 'wp-mobile-de-sync' ),
			self::RUNNING      => __( 'An import is in progress. Large inventories are processed in batches across several runs.', 'wp-mobile-de-sync' ),
			self::NEVER        => __( 'The credentials are saved, but no run has been completed yet. Please start the first import using the tools below.', 'wp-mobile-de-sync' ),
			self::STALE        => __( 'The last run is significantly overdue. The schedule is probably not being triggered; please check the cron setup.', 'wp-mobile-de-sync' ),
			self::ABORTED      => __( 'mobile.de could not be reached or rejected the request; nothing was imported. The log below shows the cause — incorrect credentials are the most common one.', 'wp-mobile-de-sync' ),
			self::FAILED       => __( 'Some vehicles could not be processed in the last run. See the log below for details.', 'wp-mobile-de-sync' ),
			self::OK           => __( 'The inventory is in sync.', 'wp-mobile-de-sync' ),
		);

		return isset( $texts[ $level ] ) ? $texts[ $level ] : '';
	}

	/**
	 * @param string $level
	 * @return bool
	 */
	public static function is_actionable( $level ) {
		return in_array( $level, array( self::UNCONFIGURED, self::STALE, self::ABORTED, self::FAILED ), true );
	}

	/** @return int|false */
	public static function next_run() {
		$times = array_filter(
			array(
				wp_next_scheduled( WMDS_CRON_HOOK ),
				wp_next_scheduled( WMDS_Importer::FOLLOWUP_HOOK ),
			)
		);

		return $times ? (int) min( $times ) : false;
	}

	/** @return array */
	public static function get() {
		$last = get_option( WMDS_Importer::OPT_LAST_RUN, array() );
		$last = is_array( $last ) ? $last : array();

		$last_ts = self::timestamp( isset( $last['time'] ) ? $last['time'] : '' );

		$configured = WMDS_Settings::is_configured();
		$running    = (bool) get_transient( WMDS_Importer::LOCK );
		$level      = self::level( $configured, $running, $last, $last_ts, time(), self::stale_after() );

		return array(
			'level'       => $level,
			'severity'    => self::severity( $level ),
			'label'       => self::label( $level ),
			'explanation' => self::explanation( $level, isset( $last['pending'] ) ? (int) $last['pending'] : 0 ),
			'configured'  => $configured,
			'running'     => $running,
			'count'       => self::count(),
			'last'        => $last,
			'tested'      => self::tested(),
			'last_ts'     => $last_ts,
			'next'        => self::next_run(),
			'pending'     => isset( $last['pending'] ) ? (int) $last['pending'] : 0,
			'incremental' => '' !== (string) get_option( WMDS_Importer::OPT_WATERMARK, '' ),
		);
	}

	/** @return int */
	public static function stale_after() {
		$schedules = wp_get_schedules();
		$interval  = WMDS_Settings::interval();
		$seconds   = isset( $schedules[ $interval ]['interval'] ) ? (int) $schedules[ $interval ]['interval'] : 0;

		return max( self::STALE_AFTER, 2 * $seconds );
	}

	/**
	 * @param int $next
	 * @param int $now
	 * @return string
	 */
	public static function next_run_label( $next, $now ) {
		if ( ! $next ) {
			return __( 'not scheduled', 'wp-mobile-de-sync' );
		}

		if ( $next < $now - 60 ) {
			return __( 'overdue', 'wp-mobile-de-sync' );
		}

		return self::relative_time( $next );
	}

	/** @param bool $ok */
	public static function record_test( $ok ) {
		update_option(
			self::OPT_LAST_TEST,
			array(
				'time' => current_time( 'mysql' ),
				'ok'   => (bool) $ok,
			),
			false
		);
	}

	/** @return bool */
	public static function tested() {
		$test = get_option( self::OPT_LAST_TEST, array() );
		if ( is_array( $test ) && ! empty( $test['ok'] ) ) {
			return true;
		}

		$last = get_option( WMDS_Importer::OPT_LAST_RUN, array() );

		return is_array( $last ) && ! empty( $last ) && empty( $last['error'] );
	}

	/** @return int */
	public static function count() {
		$counts = wp_count_posts( WMDS_CPT );

		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * @param string $mysql
	 * @return int
	 */
	public static function timestamp( $mysql ) {
		$mysql = trim( (string) $mysql );
		if ( '' === $mysql ) {
			return 0;
		}

		return (int) get_gmt_from_date( $mysql, 'U' );
	}

	/**
	 * @param int $timestamp
	 * @return string
	 */
	public static function local_time( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( ! $timestamp ) {
			return '—';
		}

		return sprintf(
			/* translators: 1: date in the site's format, 2: time in the site's format. */
			__( '%1$s at %2$s', 'wp-mobile-de-sync' ),
			wp_date( (string) get_option( 'date_format', 'Y-m-d' ), $timestamp ),
			wp_date( (string) get_option( 'time_format', 'H:i' ), $timestamp )
		);
	}

	/**
	 * @param int $timestamp
	 * @return string
	 */
	public static function relative_time( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( ! $timestamp ) {
			return '';
		}

		$now = time();

		if ( $timestamp > $now ) {
			return sprintf(
				/* translators: %s: a human-readable duration, e.g. "12 mins". */
				__( 'in %s', 'wp-mobile-de-sync' ),
				human_time_diff( $now, $timestamp )
			);
		}

		return sprintf(
			/* translators: %s: a human-readable duration, e.g. "12 mins". */
			__( '%s ago', 'wp-mobile-de-sync' ),
			human_time_diff( $timestamp, $now )
		);
	}

	/**
	 * @param int $timestamp
	 * @return string
	 */
	public static function iso_time( $timestamp ) {
		$timestamp = (int) $timestamp;

		return $timestamp ? wp_date( 'c', $timestamp ) : '';
	}

	/**
	 * @param array $stats
	 * @return string
	 */
	public static function summarise( array $stats ) {
		if ( ! empty( $stats['error'] ) ) {
			return (string) $stats['error'];
		}

		return sprintf(
			/* translators: 1: seen, 2: created, 3: updated, 4: skipped, 5: removed, 6: images. */
			__( '%1$d seen · %2$d created · %3$d updated · %4$d skipped · %5$d removed · %6$d images', 'wp-mobile-de-sync' ),
			isset( $stats['seen'] ) ? (int) $stats['seen'] : 0,
			isset( $stats['created'] ) ? (int) $stats['created'] : 0,
			isset( $stats['updated'] ) ? (int) $stats['updated'] : 0,
			isset( $stats['skipped'] ) ? (int) $stats['skipped'] : 0,
			isset( $stats['removed'] ) ? (int) $stats['removed'] : 0,
			isset( $stats['images'] ) ? (int) $stats['images'] : 0
		);
	}

	/**
	 * @param string $tab
	 * @return string
	 */
	public static function settings_url( $tab = '' ) {
		$url = admin_url( 'edit.php?post_type=' . WMDS_CPT . '&page=' . self::PAGE );

		return $tab ? $url . '&tab=' . rawurlencode( $tab ) : $url;
	}

	/** @return string */
	public static function list_url() {
		return admin_url( 'edit.php?post_type=' . WMDS_CPT );
	}

	/**
	 * @param string $action
	 * @param array  $args
	 * @return string
	 */
	public static function action_url( $action, array $args = array() ) {
		$args = array_merge(
			array(
				'action'      => 'wmds',
				'wmds_action' => $action,
			),
			$args
		);

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::NONCE );
	}
}
