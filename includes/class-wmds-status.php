<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one health verdict the whole admin UI hangs off: admin bar, dashboard
 * widget, settings header and the global notice all read from here, so they
 * cannot contradict each other.
 */
class WMDS_Status {
	const PAGE  = 'wmds-settings';
	const NONCE = 'wmds_admin';

	const STALE_AFTER = 21600;

	const UNCONFIGURED = 'unconfigured';
	const RUNNING      = 'running';
	const NEVER        = 'never';
	const STALE        = 'stale';
	const FAILED       = 'failed';
	const OK           = 'ok';

	/**
	 * Kept free of WordPress so the precedence is testable on its own. The
	 * order is deliberate: a running import outranks a stale timestamp,
	 * because "it is working on it right now" is the more useful answer.
	 *
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
	 * A sentence that says what to do about it, not just what happened.
	 *
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
	 * Levels worth interrupting somebody over on an unrelated admin screen.
	 *
	 * @param string $level
	 * @return bool
	 */
	public static function is_actionable( $level ) {
		return in_array( $level, array( self::UNCONFIGURED, self::STALE, self::FAILED ), true );
	}

	/**
	 * @return array Everything the UI needs, read once.
	 */
	public static function get() {
		$last = get_option( WMDS_Importer::OPT_LAST_RUN, array() );
		$last = is_array( $last ) ? $last : array();

		// The run stamps its time in site-local MySQL format; comparing it
		// against time() only works once it is back in UTC.
		$last_ts = ( ! empty( $last['time'] ) ) ? (int) get_gmt_from_date( $last['time'], 'U' ) : 0;

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
			'last_ts'     => $last_ts,
			'next'        => wp_next_scheduled( WMDS_CRON_HOOK ),
			'pending'     => isset( $last['pending'] ) ? (int) $last['pending'] : 0,
			'incremental' => '' !== (string) get_option( WMDS_Importer::OPT_WATERMARK, '' ),
		);
	}

	/**
	 * @return int Published vehicles.
	 */
	public static function count() {
		$counts = wp_count_posts( WMDS_CPT );

		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * @param int $timestamp
	 * @return string Local date and time, or an em dash.
	 */
	public static function local_time( $timestamp ) {
		if ( ! $timestamp ) {
			return '—';
		}

		return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), 'Y-m-d H:i' );
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
	 * A nonce-protected link to one of the admin actions. Lives here rather
	 * than on WMDS_Admin because the admin bar builds these on the front end,
	 * where the admin classes are not loaded.
	 *
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
