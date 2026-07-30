<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI commands.
 *
 * For a mid-sized dealer the initial import covers roughly 150 vehicles, each
 * with a detail call and up to 15 images. A button in the admin is the wrong
 * place for that - the web server times out first. On the command line there
 * is no time limit, and you can watch it happen.
 *
 *   wp wmds sync --full          full reconciliation (also removes sold cars)
 *   wp wmds sync --force         re-read unchanged vehicles too
 *   wp wmds sync --batch=50      vehicles per pass
 *   wp wmds status               last run, schedule, log
 *   wp wmds flush-cache          re-fetch reference data
 */
class WMDS_CLI {

	/**
	 * Runs an import.
	 *
	 * ## OPTIONS
	 *
	 * [--full]
	 * : Full reconciliation instead of an incremental sync. Only afterwards
	 *   are vanished vehicles removed.
	 *
	 * [--force]
	 * : Re-read unchanged vehicles as well.
	 *
	 * [--batch=<number>]
	 * : How many vehicles one pass processes. Default 20.
	 *
	 * [--all]
	 * : Keep going until nothing is pending.
	 *
	 * @param array $args
	 * @param array $assoc
	 */
	public function sync( $args, $assoc ) {
		if ( ! WMDS_Settings::is_configured() ) {
			WP_CLI::error( 'Not configured. Enter credentials under Vehicles -> Sync Settings.' );
		}

		$options = array(
			'full'  => isset( $assoc['full'] ),
			'force' => isset( $assoc['force'] ),
		);
		if ( isset( $assoc['batch'] ) ) {
			$options['batch'] = (int) $assoc['batch'];
		}

		$runs = 0;
		do {
			$importer = new WMDS_Importer();
			$stats    = $importer->run( $options );

			if ( is_wp_error( $stats ) ) {
				WP_CLI::error( $stats->get_error_message() );
			}

			WP_CLI::log( sprintf(
				'%s: %d seen, %d created, %d updated, %d skipped, %d removed, %d images, %d pending.',
				$stats['full'] ? 'Full sync' : 'Incremental sync',
				$stats['seen'],
				$stats['created'],
				$stats['updated'],
				$stats['skipped'],
				$stats['removed'],
				$stats['images'],
				$stats['pending']
			) );

			if ( $stats['failed'] ) {
				WP_CLI::warning( sprintf( '%d vehicles could not be processed.', $stats['failed'] ) );
			}

			$runs++;
			// Follow-up passes are never "full" again: the watermark stays
			// empty while anything is pending, so they are full implicitly.
			$options['full'] = false;

		} while ( isset( $assoc['all'] ) && $stats['pending'] > 0 && $runs < 200 );

		WP_CLI::success( sprintf( 'Done after %d pass(es).', $runs ) );
	}

	/**
	 * Shows schedule, last run and log.
	 *
	 * ## OPTIONS
	 *
	 * [--log=<number>]
	 * : How many log lines to show. Default 10.
	 *
	 * @param array $args
	 * @param array $assoc
	 */
	public function status( $args, $assoc ) {
		WP_CLI::log( 'Configured:       ' . ( WMDS_Settings::is_configured() ? 'yes' : 'NO' ) );
		WP_CLI::log( 'Seller ID:        ' . ( WMDS_Settings::seller_id() ? WMDS_Settings::seller_id() : '(not set)' ) );
		WP_CLI::log( 'Dealer (vanity):  ' . ( WMDS_Settings::dealer() ? WMDS_Settings::dealer() : '(not set)' ) );
		WP_CLI::log( 'Interval:         ' . WMDS_Settings::interval() );

		$watermark = get_option( WMDS_Importer::OPT_WATERMARK, '' );
		WP_CLI::log( 'Watermark:        ' . ( $watermark ? $watermark : '(empty - next run is a full sync)' ) );

		$next = wp_next_scheduled( WMDS_CRON_HOOK );
		WP_CLI::log( 'Next run:         ' . ( $next ? gmdate( 'Y-m-d H:i:s', $next ) . ' UTC' : 'not scheduled' ) );

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			WP_CLI::log( 'WP-Cron:          disabled - a system cron must be running.' );
		} else {
			WP_CLI::log( 'WP-Cron:          active (triggered by page views).' );
		}

		if ( get_transient( WMDS_Importer::LOCK ) ) {
			WP_CLI::warning( 'An import is currently running (lock held).' );
		}

		$last = get_option( WMDS_Importer::OPT_LAST_RUN, array() );
		if ( $last ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Last run: ' . ( isset( $last['time'] ) ? $last['time'] : '?' ) );
			foreach ( $last as $key => $value ) {
				if ( 'time' === $key ) {
					continue;
				}
				WP_CLI::log( sprintf( '  %-10s %s', $key, is_bool( $value ) ? ( $value ? 'yes' : 'no' ) : $value ) );
			}
		}

		$limit = isset( $assoc['log'] ) ? (int) $assoc['log'] : 10;
		$log   = get_option( WMDS_Importer::OPT_LOG, array() );
		if ( is_array( $log ) && $log ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Log:' );
			foreach ( array_slice( $log, 0, $limit ) as $entry ) {
				WP_CLI::log( sprintf( '  [%s] %s', $entry['time'], $entry['message'] ) );
			}
		}
	}

	/**
	 * Discards the cached reference data.
	 *
	 * Useful when mobile.de has introduced new values and vehicles show raw
	 * keys instead of readable labels.
	 */
	public function flush_cache() {
		WMDS_Refdata::flush();
		WMDS_Logos::flush();
		WP_CLI::success( 'Reference data and logo index discarded.' );
	}

	/**
	 * Checks the API connection without writing anything.
	 */
	public function test() {
		$client = WMDS_Settings::client();
		$result = $client->search( 1, 1 );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Connection OK - %d vehicles in the inventory.', $result['total'] ) );

		if ( ! empty( $result['capped'] ) ) {
			WP_CLI::warning( sprintf(
				'The inventory exceeds the %d vehicles the API hands out across paginated pages. '
				. 'A reconciliation would be incomplete.',
				WMDS_Client::PAGINATION_CAP
			) );
		}
	}
}

WP_CLI::add_command( 'wmds', 'WMDS_CLI' );
