<?php
/**
 * Checks a class can reach what it refers to.
 *
 * PHP resolves a static property at runtime, so a class that says
 * self::$flushed without declaring it parses cleanly, passes php -l, passes
 * phpcs, and dies the first time that line runs. Splitting a class into
 * several is exactly how a property gets left behind on the wrong one.
 *
 * That happened: the facet cache flush moved to WMDS_Facet_Store while
 * $flushed stayed on WMDS_Facets, and nothing found it until the sync ran
 * inside real WordPress. This is the cheap check that would have.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname( __DIR__ );

$files = array_merge(
	glob( $root . '/includes/*.php' ),
	glob( $root . '/includes/tabs/*.php' )
);

wmds_section( 'Every class file is readable' );

wmds_assert( 'there are class files to check', true, count( $files ) > 20 );

wmds_section( 'A static property is declared where it is used' );

$orphans = array();

foreach ( $files as $file ) {
	$source = (string) file_get_contents( $file );

	if ( ! preg_match( '/^class (\w+) \{(.*)\n\}/sm', $source, $m ) ) {
		continue;
	}

	$class = $m[1];
	$body  = $m[2];

	preg_match_all( '/(?:private|protected|public) static \$(\w+)/', $body, $declared );
	preg_match_all( '/self::\$(\w+)/', $body, $used );

	foreach ( array_unique( $used[1] ) as $property ) {
		if ( ! in_array( $property, $declared[1], true ) ) {
			$orphans[] = $class . '::$' . $property;
		}
	}
}

wmds_assert( 'no class reaches for a property it does not have', array(), $orphans );

wmds_section( 'A class that is referred to exists' );

$known   = array();
$missing = array();

foreach ( $files as $file ) {
	if ( preg_match( '/^class (\w+)/m', (string) file_get_contents( $file ), $m ) ) {
		$known[] = $m[1];
	}
}

/*
 * Only our own classes: WP_Query, WP_Post, WP_Error and WP_CLI come from
 * WordPress, and WMDS_Test_* live in the test harness.
 */
foreach ( $files as $file ) {
	preg_match_all( '/\b(WMDS_\w+)::/', (string) file_get_contents( $file ), $m );

	foreach ( array_unique( $m[1] ) as $class ) {
		if ( ! in_array( $class, $known, true ) && ! in_array( $class, $missing, true ) ) {
			$missing[] = $class;
		}
	}
}

wmds_assert( 'no class calls one that does not exist', array(), $missing );

wmds_result();
