<?php

require_once __DIR__ . '/bootstrap.php';

define( 'WMDS_CPT', 'fahrzeuge' );

class WMDS_Status {
	const FAILED = 'failed';
	const STALE  = 'stale';
	const OK     = 'ok';
	const NEVER  = 'never';

	/**
	 * @param array $stats
	 * @return string
	 */
	public static function summarise( array $stats ) {
		return sprintf(
			'%d seen, %d created, %d failed',
			isset( $stats['seen'] ) ? (int) $stats['seen'] : 0,
			isset( $stats['created'] ) ? (int) $stats['created'] : 0,
			isset( $stats['failed'] ) ? (int) $stats['failed'] : 0
		);
	}

	/**
	 * @param int $timestamp
	 * @return string
	 */
	public static function local_time( $timestamp ) {
		return gmdate( 'Y-m-d H:i', (int) $timestamp );
	}
}

class WMDS_Importer {
	const OPT_LOG = 'wmds_log';
}

require_once __DIR__ . '/../includes/class-wmds-alerts.php';

$hour = 3600;
$now  = 1786000000;

wmds_section( 'When an e-mail is due' );

wmds_assert(
	'a failed run with nothing reported yet',
	WMDS_Alerts::PROBLEM,
	WMDS_Alerts::decide( WMDS_Status::FAILED, array(), $now, 6 * $hour )
);

wmds_assert(
	'the same failure an hour later is not reported again',
	'',
	WMDS_Alerts::decide(
		WMDS_Status::FAILED,
		array(
			'level' => WMDS_Status::FAILED,
			'time'  => $now - $hour,
		),
		$now,
		6 * $hour
	)
);

wmds_assert(
	'once the cooldown has passed, it is',
	WMDS_Alerts::PROBLEM,
	WMDS_Alerts::decide(
		WMDS_Status::FAILED,
		array(
			'level' => WMDS_Status::FAILED,
			'time'  => $now - 6 * $hour,
		),
		$now,
		6 * $hour
	)
);

wmds_assert(
	'a different problem does not wait for the cooldown',
	WMDS_Alerts::PROBLEM,
	WMDS_Alerts::decide(
		WMDS_Status::STALE,
		array(
			'level' => WMDS_Status::FAILED,
			'time'  => $now - 60,
		),
		$now,
		6 * $hour
	)
);

wmds_section( 'When it is over' );

wmds_assert(
	'a recovery is reported straight away',
	WMDS_Alerts::RECOVERY,
	WMDS_Alerts::decide(
		WMDS_Status::OK,
		array(
			'level' => WMDS_Status::FAILED,
			'time'  => $now - 60,
		),
		$now,
		6 * $hour
	)
);

wmds_assert(
	'but only once',
	'',
	WMDS_Alerts::decide(
		WMDS_Status::OK,
		array(
			'level' => WMDS_Status::OK,
			'time'  => $now - 60,
		),
		$now,
		6 * $hour
	)
);

wmds_section( 'What is not worth an e-mail' );

wmds_assert(
	'a healthy sync nobody was warned about',
	'',
	WMDS_Alerts::decide( WMDS_Status::OK, array(), $now, 6 * $hour )
);

wmds_assert(
	'a plugin nobody has set up yet',
	'',
	WMDS_Alerts::decide( 'unconfigured', array(), $now, 6 * $hour )
);

wmds_assert(
	'and a first run that has not happened yet',
	'',
	WMDS_Alerts::decide( WMDS_Status::NEVER, array(), $now, 6 * $hour )
);

wmds_section( 'The e-mail itself' );

$status = array(
	'level'       => WMDS_Status::FAILED,
	'label'       => 'Last run had failures',
	'explanation' => 'Some vehicles could not be processed in the last run.',
	'count'       => 42,
	'last'        => array(
		'seen'    => 44,
		'created' => 0,
		'failed'  => 2,
	),
	'last_ts'     => $now - 900,
	'next'        => $now + 900,
);

$log = array(
	array(
		'time'    => '2026-08-08 09:00:00',
		'message' => 'Image could not be downloaded.',
	),
	array(
		'time'    => '2026-08-08 08:45:00',
		'message' => 'Incremental sync: 44 seen.',
	),
);

$mail = WMDS_Alerts::compose(
	WMDS_Alerts::PROBLEM,
	$status,
	$log,
	'Autohaus Example',
	'https://example.invalid/wp-admin/'
);

wmds_assert( 'the subject says which site and what', '[Autohaus Example] Vehicle sync: Last run had failures', $mail['subject'] );
wmds_assert_contains( 'the body explains it', 'Some vehicles could not be processed', $mail['body'] );
wmds_assert_contains( 'and says how large the inventory is', '42', $mail['body'] );
wmds_assert_contains( 'and summarises the run', '44 seen', $mail['body'] );
wmds_assert_contains( 'and carries the log', 'Image could not be downloaded.', $mail['body'] );
wmds_assert_contains( 'and links to the screen that shows it', 'https://example.invalid/wp-admin/', $mail['body'] );

$recovery = WMDS_Alerts::compose(
	WMDS_Alerts::RECOVERY,
	array(
		'level' => WMDS_Status::OK,
		'label' => 'Up to date',
		'count' => 42,
	),
	array(),
	'Autohaus Example',
	'https://example.invalid/wp-admin/'
);

wmds_assert( 'a recovery reads as good news', '[Autohaus Example] The vehicle sync is working again', $recovery['subject'] );
wmds_assert( 'and carries no log section it does not have', false, strpos( $recovery['body'], 'From the log' ) );

wmds_section( 'The weekly summary' );

$weekly = WMDS_Alerts::compose_summary( $status, 'Autohaus Example', 'https://example.invalid/wp-admin/' );

wmds_assert( 'it says what it is', '[Autohaus Example] Vehicle sync, the week in one mail', $weekly['subject'] );
wmds_assert_contains( 'it states the inventory', '42', $weekly['body'] );
wmds_assert_contains( 'and when the next run is due', 'Next run', $weekly['body'] );

wmds_result();
