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
wmds_assert( 'Citroen mit Trema', 'citroen', WMDS_Logos::normalize( 'Citroën' ) );
wmds_assert( 'CITROEN without', 'citroen', WMDS_Logos::normalize( 'CITROEN' ) );
wmds_assert( 'BMW bleibt BMW', 'bmw', WMDS_Logos::normalize( 'BMW' ) );
wmds_assert( 'ALPINA', 'alpina', WMDS_Logos::normalize( 'ALPINA' ) );
wmds_assert( 'Alfa Romeo', 'alfaromeo', WMDS_Logos::normalize( 'ALFA ROMEO' ) );
wmds_assert( 'Harley-Davidson', 'harleydavidson', WMDS_Logos::normalize( 'HARLEY-DAVIDSON' ) );
wmds_assert( 'leer bleibt leer', '', WMDS_Logos::normalize( '   ' ) );

wmds_section( 'Matching against assets/logos/' );

$dateien = glob( WMDS_DIR . 'assets/logos/*.png' );
wmds_assert( '30 Logos vorhanden', 30, count( (array) $dateien ) );

$schluessel = array(
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
);

foreach ( $schluessel as $key => $erwartet_im_dateinamen ) {
	$url = WMDS_Logos::url( $key );
	wmds_assert_contains( $key . ' findet ein Logo', $erwartet_im_dateinamen, rawurldecode( $url ) );
}

wmds_section( 'An unknown make yields nothing rather than a broken image' );

wmds_assert( 'make with no file', '', WMDS_Logos::url( 'TESLA' ) );
wmds_assert( 'leerer Schluessel', '', WMDS_Logos::url( '' ) );
wmds_assert( 'Unsinn', '', WMDS_Logos::url( '###' ) );

wmds_section( 'URL zeigt ins Plugin-Verzeichnis' );

wmds_assert_contains( 'Basis-URL', WMDS_URL . 'assets/logos/', WMDS_Logos::url( 'VOLVO' ) );
wmds_assert_contains( 'Leerzeichen kodiert', 'Land%20Rover', WMDS_Logos::url( 'LAND ROVER' ) );

wmds_result();
