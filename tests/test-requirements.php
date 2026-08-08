<?php

require_once __DIR__ . '/bootstrap.php';

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array(
			'basedir' => sys_get_temp_dir(),
			'error'   => false,
		);
	}
}

if ( ! function_exists( 'wp_is_writable' ) ) {
	function wp_is_writable( $path ) {
		return is_writable( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- the stub is what the plugin calls instead.
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return isset( $GLOBALS['wmds_test_options'][ $key ] ) ? $GLOBALS['wmds_test_options'][ $key ] : $default;
	}
}

if ( ! function_exists( 'size_format' ) ) {
	function size_format( $bytes, $decimals = 0 ) {
		return round( $bytes / 1048576 ) . ' MB';
	}
}

$GLOBALS['wmds_test_options'] = array( 'permalink_structure' => '/%postname%/' );
$GLOBALS['wp_version']        = '6.4';

require_once dirname( __DIR__ ) . '/includes/class-wmds-str.php';
require_once dirname( __DIR__ ) . '/includes/integrations/class-wmds-smtp.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-requirements.php';

wmds_section( 'php.ini shorthand' );

wmds_assert( 'megabytes', 268435456, WMDS_Requirements::bytes( '256M' ) );
wmds_assert( 'gigabytes', 1073741824, WMDS_Requirements::bytes( '1G' ) );
wmds_assert( 'kilobytes', 524288, WMDS_Requirements::bytes( '512K' ) );
wmds_assert( 'plain bytes', 1024, WMDS_Requirements::bytes( '1024' ) );
wmds_assert( 'unlimited', -1, WMDS_Requirements::bytes( '-1' ) );
wmds_assert( 'nothing at all', 0, WMDS_Requirements::bytes( '' ) );
wmds_assert( 'lower case is the same', 268435456, WMDS_Requirements::bytes( '256m' ) );

wmds_section( 'Every check reports a shape the tab can render' );

$checks = WMDS_Requirements::all();

wmds_assert( 'there are checks', true, count( $checks ) > 5 );

$statuses = array( WMDS_Requirements::OK, WMDS_Requirements::WARN, WMDS_Requirements::FAIL );
$complete = true;
$known    = true;
$keys     = array();

foreach ( $checks as $check ) {
	foreach ( array( 'key', 'group', 'label', 'value', 'status', 'hint' ) as $field ) {
		if ( ! array_key_exists( $field, $check ) ) {
			$complete = false;
		}
	}
	if ( ! in_array( $check['status'], $statuses, true ) ) {
		$known = false;
	}
	$keys[] = $check['key'];
}

wmds_assert( 'each one is complete', true, $complete );
wmds_assert( 'each status is one of the three', true, $known );
wmds_assert( 'no key appears twice', count( $keys ), count( array_unique( $keys ) ) );

wmds_section( 'The checks that matter are covered' );

foreach ( array( 'php', 'wp', 'ext-json', 'image-editor', 'execution-time', 'memory', 'uploads', 'permalinks', 'rewrite', 'cron', 'outbound' ) as $key ) {
	wmds_assert( $key, true, in_array( $key, $keys, true ) );
}

wmds_section( 'The tally adds up' );

$tally = WMDS_Requirements::tally();

wmds_assert( 'every check is counted once', count( $checks ), $tally['ok'] + $tally['warn'] + $tally['fail'] );
wmds_assert( 'the worst status is a known one', true, in_array( WMDS_Requirements::worst(), $statuses, true ) );

wmds_section( 'This PHP passes its own requirement' );

$php = null;
foreach ( $checks as $check ) {
	if ( 'php' === $check['key'] ) {
		$php = $check;
	}
}

wmds_assert( 'the version is reported', PHP_VERSION, $php['value'] );
wmds_assert( 'and it is not a hard failure', true, WMDS_Requirements::FAIL !== $php['status'] );

wmds_result();
