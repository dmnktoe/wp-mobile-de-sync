<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The sync log: the last runs and whatever went wrong in them.
 *
 * One place that writes and reads it. An entry carries its level, so the
 * screen can show problems on their own without guessing from the wording -
 * the wording is translated, and a German line has no "failed" in it.
 */
class WMDS_Log {
	const OPTION = 'wmds_log';

	const MAX = 50;

	const INFO    = 'info';
	const PROBLEM = 'problem';

	/**
	 * @param string $message Already in the site's language.
	 * @param string $level   INFO or PROBLEM.
	 */
	public static function write( $message, $level = self::INFO ) {
		$log = self::entries();

		array_unshift(
			$log,
			array(
				'time'    => current_time( 'mysql' ),
				'message' => (string) $message,
				'level'   => self::PROBLEM === $level ? self::PROBLEM : self::INFO,
			)
		);

		update_option( self::OPTION, array_slice( $log, 0, self::MAX ), false );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[mobile.de Sync] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only with WP_DEBUG.
		}
	}

	/**
	 * @param string $message
	 */
	public static function problem( $message ) {
		self::write( $message, self::PROBLEM );
	}

	/**
	 * @param int $limit 0 for all.
	 * @return array<int, array{time:string,message:string,level:string}> Newest first.
	 */
	public static function entries( $limit = 0 ) {
		$log = get_option( self::OPTION, array() );
		$log = is_array( $log ) ? array_values( array_filter( $log, 'is_array' ) ) : array();

		return $limit > 0 ? array_slice( $log, 0, $limit ) : $log;
	}

	/**
	 * @param array $entry
	 * @return bool
	 */
	public static function is_problem( array $entry ) {
		return isset( $entry['level'] ) && self::PROBLEM === $entry['level'];
	}

	/**
	 * @param array $entry
	 * @return string "time message", for mails and downloads.
	 */
	public static function line( array $entry ) {
		$time    = isset( $entry['time'] ) ? (string) $entry['time'] : '';
		$message = isset( $entry['message'] ) ? (string) $entry['message'] : '';

		return trim( $time . ' ' . $message );
	}

	public static function clear() {
		delete_option( self::OPTION );
	}
}
