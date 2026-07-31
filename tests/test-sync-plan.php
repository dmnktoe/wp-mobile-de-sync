<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/class-wmds-sync-plan.php';

function ad( $id, $modified = '2026-07-28T18:00:00+02:00' ) {
	return array(
		'mobileAdId'       => $id,
		'modificationDate' => $modified,
	);
}

function known( $post_id, $modified = '2026-07-28T18:00:00+02:00' ) {
	return array(
		'post_id'  => $post_id,
		'modified' => $modified,
	);
}

wmds_section( 'Plan: anlegen, aktualisieren, ueberspringen' );

$plan = WMDS_Sync_Plan::build(
	array(
		ad( 'neu-1' ),
		ad( 'bekannt-unveraendert' ),
		ad( 'bekannt-geaendert', '2026-07-29T09:00:00+02:00' ),
	),
	array(
		'bekannt-unveraendert' => known( 11 ),
		'bekannt-geaendert'    => known( 12 ),
		'not-in-feed'          => known( 13 ),
	)
);

wmds_assert( 'ein neues Fahrzeug', 1, count( $plan['create'] ) );
wmds_assert( 'neues Fahrzeug richtig erkannt', 'neu-1', $plan['create'][0]['mobileAdId'] );
wmds_assert( 'ein geaendertes', 1, count( $plan['update'] ) );
wmds_assert( 'geaendertes richtig erkannt', 'bekannt-geaendert', $plan['update'][0]['mobileAdId'] );
wmds_assert( 'ein uebersprungenes', 1, count( $plan['skip'] ) );
wmds_assert( 'uebersprungenes richtig erkannt', 'bekannt-unveraendert', $plan['skip'][0] );
wmds_assert( 'drei gesehen', 3, count( $plan['seen'] ) );

wmds_section( 'Plan: force re-reads unchanged vehicles too' );

$forced = WMDS_Sync_Plan::build( array( ad( 'x' ) ), array( 'x' => known( 1 ) ), true );
wmds_assert( 'nichts uebersprungen', 0, count( $forced['skip'] ) );
wmds_assert( 'stattdessen aktualisiert', 1, count( $forced['update'] ) );

wmds_section( 'Plan: unusable entries are ignored' );

$junk = WMDS_Sync_Plan::build(
	array( ad( 'good' ), array( 'no' => 'id' ), 'string instead of array', array() ),
	array()
);
wmds_assert( 'only the usable ad', 1, count( $junk['create'] ) );
wmds_assert( 'only one seen ID', 1, count( $junk['seen'] ) );

wmds_section( 'Plan: a missing modificationDate forces an update' );

$ohne_datum = WMDS_Sync_Plan::build(
	array( array( 'mobileAdId' => 'x' ) ),
	array( 'x' => known( 1 ) )
);
wmds_assert( 'sicherheitshalber aktualisieren', 1, count( $ohne_datum['update'] ) );

wmds_section( 'Removal: never after a partial pass' );

$teillauf = WMDS_Sync_Plan::removals(
	array( 'a' ),
	array(
		'a' => known( 1 ),
		'b' => known( 2 ),
		'c' => known( 3 ),
	),
	false
);
wmds_assert( 'nichts zu entfernen', 0, count( $teillauf['remove'] ) );
wmds_assert_contains( 'with a reason', 'Partial pass', $teillauf['abort'] );

wmds_section( 'Removal: an empty result never deletes the inventory' );

$leer = WMDS_Sync_Plan::removals( array(), array( 'a' => known( 1 ) ), true );
wmds_assert( 'nichts zu entfernen', 0, count( $leer['remove'] ) );
wmds_assert_contains( 'with a reason', 'did not see a single vehicle', $leer['abort'] );

wmds_section( 'Loeschen: Normalfall' );

$normal = WMDS_Sync_Plan::removals(
	array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i' ),
	array(
		'a'        => known( 1 ),
		'b'        => known( 2 ),
		'c'        => known( 3 ),
		'd'        => known( 4 ),
		'e'        => known( 5 ),
		'f'        => known( 6 ),
		'g'        => known( 7 ),
		'h'        => known( 8 ),
		'i'        => known( 9 ),
		'verkauft' => known( 99 ),
	),
	true
);
wmds_assert( 'no abort', '', $normal['abort'] );
wmds_assert( 'genau ein Post', array( 99 ), $normal['remove'] );

wmds_section( 'Loeschen: Sicherheitsriegel bei unplausibler Menge' );

$bestand = array();
$gesehen = array();
for ( $i = 1; $i <= 100; $i++ ) {
	$bestand[ 'ad-' . $i ] = known( $i );
	if ( $i <= 50 ) {
		$gesehen[] = 'ad-' . $i;
	}
}
$riegel = WMDS_Sync_Plan::removals( $gesehen, $bestand, true );
wmds_assert( 'nichts geloescht', 0, count( $riegel['remove'] ) );
wmds_assert( 'Kandidaten trotzdem gezaehlt', 50, $riegel['candidates'] );
wmds_assert_contains( 'guard named', 'Safety guard', $riegel['abort'] );
wmds_assert_contains( 'numbers stated', '50 of 100', $riegel['abort'] );

wmds_section( 'Removal: just under the limit is allowed' );

$gesehen_70 = array();
for ( $i = 31; $i <= 100; $i++ ) {
	$gesehen_70[] = 'ad-' . $i;
}
$limit_case = WMDS_Sync_Plan::removals( $gesehen_70, $bestand, true );
wmds_assert( 'no abort', '', $limit_case['abort'] );
wmds_assert( '30 Posts entfernt', 30, count( $limit_case['remove'] ) );

wmds_section( 'Removal: a small inventory is not blocked by the percentage' );

$klein = array();
for ( $i = 1; $i <= 8; $i++ ) {
	$klein[ 'k-' . $i ] = known( $i );
}
$three = WMDS_Sync_Plan::removals( array( 'k-4', 'k-5', 'k-6', 'k-7', 'k-8' ), $klein, true );
wmds_assert( 'no abort', '', $three['abort'] );
wmds_assert( 'drei entfernt', 3, count( $three['remove'] ) );

$vier = WMDS_Sync_Plan::removals( array( 'k-5', 'k-6', 'k-7', 'k-8' ), $klein, true );
wmds_assert_contains( 'four of eight trips it', 'Safety guard', $vier['abort'] );

wmds_section( 'Removal: an empty inventory is harmless' );

$nothing = WMDS_Sync_Plan::removals( array( 'a' ), array(), true );
wmds_assert( 'no abort', '', $nothing['abort'] );
wmds_assert( 'nichts zu entfernen', 0, count( $nothing['remove'] ) );

wmds_section( 'Fingerabdruck erkennt echte Aenderungen' );

$a = array(
	'title'       => 'Audi A4',
	'description' => 'text',
	'meta'        => array(
		'price' => '10.000',
		'make'  => 'Audi',
	),
);
$b = array(
	'title'       => 'Audi A4',
	'description' => 'text',
	'meta'        => array(
		'make'  => 'Audi',
		'price' => '10.000',
	),
);
$c = array(
	'title'       => 'Audi A4',
	'description' => 'text',
	'meta'        => array(
		'make'  => 'Audi',
		'price' => '9.500',
	),
);

wmds_assert(
	'meta field order does not matter',
	WMDS_Sync_Plan::content_hash( $a ),
	WMDS_Sync_Plan::content_hash( $b )
);
wmds_assert(
	'geaenderter Preis ergibt anderen Hash',
	false,
	WMDS_Sync_Plan::content_hash( $a ) === WMDS_Sync_Plan::content_hash( $c )
);

wmds_section( 'Images: a swapped photo is detected via the hash' );

$images = array(
	array(
		'url'  => 'a.jpg',
		'hash' => 'h1',
	),
	array(
		'url'  => 'b.jpg',
		'hash' => 'h2',
	),
);

wmds_assert( 'unchanged', false, WMDS_Sync_Plan::images_changed( array( 'h1', 'h2' ), $images ) );
wmds_assert( 'ein Bild ausgetauscht', true, WMDS_Sync_Plan::images_changed( array( 'h1', 'x' ), $images ) );
wmds_assert( 'Reihenfolge geaendert', true, WMDS_Sync_Plan::images_changed( array( 'h2', 'h1' ), $images ) );
wmds_assert( 'Bild dazugekommen', true, WMDS_Sync_Plan::images_changed( array( 'h1' ), $images ) );
wmds_assert( 'never imported before', true, WMDS_Sync_Plan::images_changed( null, $images ) );
wmds_assert(
	'no hashes in the feed',
	false,
	WMDS_Sync_Plan::images_changed(
		array( 'h1' ),
		array(
			array(
				'url'  => 'a.jpg',
				'hash' => '',
			),
		)
	)
);

wmds_section( 'Watermark: newest value minus one minute' );

wmds_assert(
	'the newest of several dates',
	'2026-07-28T16:28:13+00:00',
	WMDS_Sync_Plan::next_watermark(
		array(
			'2026-07-20T10:00:00+02:00',
			'2026-07-28T18:29:13+02:00',
			'2026-07-25T12:00:00+02:00',
		)
	)
);
wmds_assert( 'nichts Verwertbares', '', WMDS_Sync_Plan::next_watermark( array() ) );
wmds_assert( 'nothing usable', '', WMDS_Sync_Plan::next_watermark( array( '', 'not a date' ) ) );

wmds_result();
