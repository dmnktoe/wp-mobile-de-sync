<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/class-wmds-status.php';

$now   = 1800000000;
$ok    = array(
	'time'    => '2026-07-31 10:00:00',
	'seen'    => 12,
	'failed'  => 0,
	'pending' => 0,
);
$fresh = $now - 60;
$old   = $now - WMDS_Status::STALE_AFTER - 60;

/**
 * @param bool  $configured
 * @param bool  $running
 * @param array $last
 * @param int   $ts
 * @return string
 */
function level( $configured, $running, $last, $ts ) {
	return WMDS_Status::level( $configured, $running, $last, $ts, 1800000000 );
}

wmds_section( 'Level: precedence' );

wmds_assert(
	'missing credentials outrank everything else',
	WMDS_Status::UNCONFIGURED,
	level( false, true, $ok, $fresh )
);
wmds_assert(
	'a running import outranks a stale timestamp',
	WMDS_Status::RUNNING,
	level( true, true, $ok, $old )
);
wmds_assert(
	'configured but never run',
	WMDS_Status::NEVER,
	level( true, false, array(), 0 )
);
wmds_assert(
	'a run without a usable timestamp counts as never',
	WMDS_Status::NEVER,
	level( true, false, $ok, 0 )
);
wmds_assert(
	'a run older than the window is stale',
	WMDS_Status::STALE,
	level( true, false, $ok, $old )
);
wmds_assert(
	'a stale run outranks its own failures',
	WMDS_Status::STALE,
	level( true, false, array( 'failed' => 3 ), $old )
);
wmds_assert(
	'a fresh run with failures',
	WMDS_Status::FAILED,
	level( true, false, array( 'failed' => 3 ), $fresh )
);
wmds_assert(
	'a fresh, clean run',
	WMDS_Status::OK,
	level( true, false, $ok, $fresh )
);

wmds_section( 'Level: the edge of the stale window' );

wmds_assert(
	'exactly at the boundary is not yet stale',
	WMDS_Status::OK,
	level( true, false, $ok, $now - WMDS_Status::STALE_AFTER )
);
wmds_assert(
	'one second past it is',
	WMDS_Status::STALE,
	level( true, false, $ok, $now - WMDS_Status::STALE_AFTER - 1 )
);

wmds_section( 'Severity' );

wmds_assert( 'missing credentials are an error', 'error', WMDS_Status::severity( WMDS_Status::UNCONFIGURED ) );
wmds_assert( 'a running import is informational', 'info', WMDS_Status::severity( WMDS_Status::RUNNING ) );
wmds_assert( 'a healthy sync', 'ok', WMDS_Status::severity( WMDS_Status::OK ) );
wmds_assert( 'stale is a warning', 'warning', WMDS_Status::severity( WMDS_Status::STALE ) );
wmds_assert( 'failures are a warning', 'warning', WMDS_Status::severity( WMDS_Status::FAILED ) );
wmds_assert( 'never run is a warning', 'warning', WMDS_Status::severity( WMDS_Status::NEVER ) );

wmds_section( 'Worth interrupting somebody over' );

wmds_assert( 'missing credentials', true, WMDS_Status::is_actionable( WMDS_Status::UNCONFIGURED ) );
wmds_assert( 'stale', true, WMDS_Status::is_actionable( WMDS_Status::STALE ) );
wmds_assert( 'failures', true, WMDS_Status::is_actionable( WMDS_Status::FAILED ) );
wmds_assert( 'a healthy sync is not', false, WMDS_Status::is_actionable( WMDS_Status::OK ) );
wmds_assert( 'a running import is not', false, WMDS_Status::is_actionable( WMDS_Status::RUNNING ) );
wmds_assert(
	'waiting for the first run is not - the checklist covers it',
	false,
	WMDS_Status::is_actionable( WMDS_Status::NEVER )
);

wmds_section( 'Every level has words to go with it' );

foreach ( array(
	WMDS_Status::UNCONFIGURED,
	WMDS_Status::RUNNING,
	WMDS_Status::NEVER,
	WMDS_Status::STALE,
	WMDS_Status::FAILED,
	WMDS_Status::OK,
) as $level ) {
	$label       = WMDS_Status::label( $level );
	$explanation = WMDS_Status::explanation( $level );

	wmds_assert( 'label for ' . $level, true, '' !== $label && $label !== $level );
	wmds_assert( 'explanation for ' . $level, true, '' !== $explanation );
}

wmds_result();
