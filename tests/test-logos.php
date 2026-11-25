<?php

require_once __DIR__ . '/bootstrap.php';

define( 'WMDS_DIR', dirname( __DIR__ ) . '/' );
define( 'WMDS_URL', 'https://example.invalid/wp-content/plugins/wp-mobile-de-sync/' );

require_once __DIR__ . '/../includes/class-wmds-logos.php';

wmds_section( 'Normalisation: key and file name meet' );

wmds_assert( 'LAND ROVER', 'landrover', WMDS_Logos::normalize( 'LAND ROVER' ) );
wmds_assert( 'Land Rover.png', 'landrover', WMDS_Logos::normalize( 'Land Rover' ) );
wmds_assert( 'MERCEDES-BENZ', 'mercedesbenz', WMDS_Logos::normalize( 'MERCEDES-BENZ' ) );
wmds_assert( 'Mercedes-Benz.png', 'mercedesbenz', WMDS_Logos::normalize( 'Mercedes-Benz' ) );
wmds_assert( 'Citroen with a diaeresis', 'citroen', WMDS_Logos::normalize( 'Citroën' ) );
wmds_assert( 'CITROEN without', 'citroen', WMDS_Logos::normalize( 'CITROEN' ) );
wmds_assert( 'BMW stays BMW', 'bmw', WMDS_Logos::normalize( 'BMW' ) );
wmds_assert( 'ALPINA', 'alpina', WMDS_Logos::normalize( 'ALPINA' ) );
wmds_assert( 'Alfa Romeo', 'alfaromeo', WMDS_Logos::normalize( 'ALFA ROMEO' ) );
wmds_assert( 'Harley-Davidson', 'harleydavidson', WMDS_Logos::normalize( 'HARLEY-DAVIDSON' ) );
wmds_assert( 'empty stays empty', '', WMDS_Logos::normalize( '   ' ) );

wmds_section( 'Matching against assets/logos/' );

$files = glob( WMDS_DIR . 'assets/logos/*.png' );
wmds_assert( '32 logos bundled', 32, count( (array) $files ) );

$keys = array(
	'LAND ROVER'      => 'Land Rover',
	'VOLVO'           => 'Volvo',
	'BMW'             => 'BMW',
	'MERCEDES-BENZ'   => 'Mercedes-Benz',
	'ALFA ROMEO'      => 'Alfa Romeo',
	'CITROEN'         => 'Citro',
	'MINI'            => 'MINI',
	'ALPINA'          => 'ALPINA',
	'INEOS'           => 'INEOS',
	'HARLEY-DAVIDSON' => 'Harley-Davidson',
	'VOLKSWAGEN'      => 'Volkswagen',
	'SKODA'           => 'Skoda',
	'OPEL'            => 'Opel',
	'HYUNDAI'         => 'Hyundai',
);

foreach ( $keys as $key => $expected_in_filename ) {
	$url = WMDS_Logos::url( $key );
	wmds_assert_contains( $key . ' finds a logo', $expected_in_filename, rawurldecode( $url ) );
}

wmds_section( 'mobile.de sends VW, the file is called Volkswagen' );

wmds_assert_contains( 'VW finds a logo', 'Volkswagen', rawurldecode( WMDS_Logos::url( 'VW' ) ) );
wmds_assert_contains( 'vw in lower case', 'Volkswagen', rawurldecode( WMDS_Logos::url( 'vw' ) ) );
wmds_assert_contains( 'VOLKSWAGEN still works', 'Volkswagen', rawurldecode( WMDS_Logos::url( 'VOLKSWAGEN' ) ) );

wmds_section( 'An unknown make yields nothing rather than a broken image' );

wmds_assert( 'make with no file', '', WMDS_Logos::url( 'TESLA' ) );
wmds_assert( 'empty key', '', WMDS_Logos::url( '' ) );
wmds_assert( 'nonsense', '', WMDS_Logos::url( '###' ) );

wmds_section( 'The URL points into the plugin directory' );

wmds_assert_contains( 'base URL', WMDS_URL . 'assets/logos/', WMDS_Logos::url( 'VOLVO' ) );
wmds_assert_contains( 'spaces encoded', 'Land%20Rover', WMDS_Logos::url( 'LAND ROVER' ) );

wmds_result();
