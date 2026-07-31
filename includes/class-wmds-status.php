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
	const FAILED       = 'failed';
	const OK           = 'ok';

	/**
	 * @param bool  $configured Whether credentials and a seller are stored.
	 * @param bool  $running    Whether the import lock is held.
	 * @param array $last       Statistics of the last run, empty when none.
	 * @param int   $last_ts    Unix time of the last run, 0 when none.
	 * @param int   $now        Unix time.
	 * @return string One of the level constants.
	 */
	public static function level( $configured, $running, array $last, $last_ts, $now ) {
		if ( ! $configured ) {
			return self::UNCONFIGURED;
		}
		if ( $running ) {
			return self::RUNNING;
		}
		if ( ! $last || $last_ts <= 0 ) {
			return self::NEVER;
		}
		if ( $last_ts < $now - self::STALE_AFTER ) {
			return self::STALE;
		}
		if ( ! empty( $last['failed'] ) ) {
			return self::FAILED;
		}

		return self::OK;
	}

	/**
	 * @param string $level
	 * @return string ok|info|warning|error
	 */
	public static function severity( $level ) {
		if ( self::UNCONFIGURED === $level ) {
			return 'error';
		}
		if ( self::RUNNING === $level ) {
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
			self::FAILED       => __( 'Last run had failures', 'wp-mobile-de-sync' ),
			self::OK           => __( 'Up to date', 'wp-mobile-de-sync' ),
		);

		return isset( $labels[ $level ] ) ? $labels[ $level ] : $level;
	}

	/**
	 * @param string $level
	 * @return string
	 */
	public static function explanation( $level ) {
		$texts = array(
			self::UNCONFIGURED => __( 'Username, password and the seller ID (or a dealer name instead) are missing. Nothing is imported until they are stored.', 'wp-mobile-de-sync' ),
			self::RUNNING      => __( 'An import is in progress. Large inventories are processed in batches across several runs.', 'wp-mobile-de-sync' ),
			self::NEVER        => __( 'The credentials are stored, but no run has completed yet. Start the first import from the tools below.', 'wp-mobile-de-sync' ),
			self::STALE        => __( 'The last run is more than six hours old. The schedule is most likely not being triggered — check the cron setup.', 'wp-mobile-de-sync' ),
			self::FAILED       => __( 'Some vehicles could not be processed in the last run. The log below names them.', 'wp-mobile-de-sync' ),
			self::OK           => __( 'The inventory is in sync.', 'wp-mobile-de-sync' ),
		);

		return isset( $texts[ $level ] ) ? $texts[ $level ] : '';
	}

	/**
	 * @param string $level
	 * @return bool
	 */
	public static function is_actionable( $level ) {
		return in_array( $level, array( self::UNCONFIGURED, self::STALE, self::FAILED ), true );
	}

	/** @return array Everything the UI needs, read once. */
	public static function get() {
		$last = get_option( WMDS_Importer::OPT_LAST_RUN, array() );
		$last = is_array( $last ) ? $last : array();

		$last_ts = self::timestamp( isset( $last['time'] ) ? $last['time'] : '' );

		$configured = WMDS_Settings::is_configured();
		$running    = (bool) get_transient( WMDS_Importer::LOCK );
		$level      = self::level( $configured, $running, $last, $last_ts, time() );

		return array(
			'level'       => $level,
			'severity'    => self::severity( $level ),
			'label'       => self::label( $level ),
			'explanation' => self::explanation( $level ),
			'configured'  => $configured,
			'running'     => $running,
			'count'       => self::count(),
			'last'        => $last,
			'tested'      => self::tested(),
			'last_ts'     => $last_ts,
			'next'        => wp_next_scheduled( WMDS_CRON_HOOK ),
			'pending'     => isset( $last['pending'] ) ? (int) $last['pending'] : 0,
			'incremental' => '' !== (string) get_option( WMDS_Importer::OPT_WATERMARK, '' ),
		);
	}

	/**
	 * @param bool $ok
	 */
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

	/** @return bool Whether a connection test or a sync run has succeeded. */
	public static function tested() {
		$test = get_option( self::OPT_LAST_TEST, array() );
		if ( is_array( $test ) && ! empty( $test['ok'] ) ) {
			return true;
		}

		$last = get_option( WMDS_Importer::OPT_LAST_RUN, array() );

		return is_array( $last ) && ! empty( $last );
	}

	/** @return int Published vehicles. */
	public static function count() {
		$counts = wp_count_posts( WMDS_CPT );

		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * @param string $mysql Local time in MySQL format, as the importer stores it.
	 * @return int Timestamp, 0 when there is nothing usable.
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
	 * @return string Date and time in the site's own formats, or an em dash.
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
	 * @return string e.g. "5 mins ago", empty without a timestamp.
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
	 * @return string ISO 8601 for the datetime attribute, empty without one.
	 */
	public static function iso_time( $timestamp ) {
		$timestamp = (int) $timestamp;

		return $timestamp ? wp_date( 'c', $timestamp ) : '';
	}

	/**
	 * @param array $stats
	 * @return string e.g. "42 seen · 3 created · 2 updated"
	 */
	public static function summarise( array $stats ) {
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
	 * @param string $tab Optional tab to open.
	 * @return string URL of the settings screen.
	 */
	public static function settings_url( $tab = '' ) {
		$url = admin_url( 'edit.php?post_type=' . WMDS_CPT . '&page=' . self::PAGE );

		return $tab ? $url . '&tab=' . rawurlencode( $tab ) : $url;
	}

	/** @return string URL of the vehicle list. */
	public static function list_url() {
		return admin_url( 'edit.php?post_type=' . WMDS_CPT );
	}

	/**
	 * @param string $action One of the wmds_action names.
	 * @param array  $args   Extra query arguments.
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
