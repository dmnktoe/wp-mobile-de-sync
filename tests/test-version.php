<?php

require_once __DIR__ . '/bootstrap.php';

$root = dirname( __DIR__ );

$plugin    = (string) file_get_contents( $root . '/wp-mobile-de-sync.php' );
$readme    = (string) file_get_contents( $root . '/readme.txt' );
$changelog = (string) file_get_contents( $root . '/CHANGELOG.md' );

preg_match( '/^[ \t*]*Version:[ \t]*(\S+)/m', $plugin, $header );
preg_match( "/define\(\s*'WMDS_VERSION',\s*'([^']+)'\s*\)/", $plugin, $constant );
preg_match( '/^Stable tag:[ \t]*(\S+)/m', $readme, $stable );

$version = isset( $header[1] ) ? $header[1] : '';

wmds_section( 'One version, stated in three places' );

wmds_assert( 'the plugin header states one', 1, preg_match( '/^\d+\.\d+\.\d+$/', $version ) );
wmds_assert( 'WMDS_VERSION agrees', $version, isset( $constant[1] ) ? $constant[1] : '' );
wmds_assert( 'readme.txt agrees', $version, isset( $stable[1] ) ? $stable[1] : '' );

wmds_section( 'The release it names is written up' );

wmds_assert(
	'CHANGELOG.md',
	1,
	preg_match( '/^## \[' . preg_quote( $version, '/' ) . '\]/m', $changelog )
);
wmds_assert(
	'readme.txt',
	1,
	preg_match( '/^= ' . preg_quote( $version, '/' ) . ' =$/m', $readme )
);

wmds_result();
