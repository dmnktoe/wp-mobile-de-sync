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

wmds_section( 'Plan: create, update, skip' );

$plan = WMDS_Sync_Plan::build(
	array(
		ad( 'new-1' ),
		ad( 'known-unchanged' ),
		ad( 'known-changed', '2026-07-29T09:00:00+02:00' ),
	),
	array(
		'known-unchanged' => known( 11 ),
		'known-changed'   => known( 12 ),
		'not-in-feed'     => known( 13 ),
	)
);

wmds_assert( 'one new vehicle', 1, count( $plan['create'] ) );
wmds_assert( 'the new vehicle is the right one', 'new-1', $plan['create'][0]['mobileAdId'] );
wmds_assert( 'one changed', 1, count( $plan['update'] ) );
wmds_assert( 'the changed one is the right one', 'known-changed', $plan['update'][0]['mobileAdId'] );
wmds_assert( 'one skipped', 1, count( $plan['skip'] ) );
wmds_assert( 'the skipped one is the right one', 'known-unchanged', $plan['skip'][0] );
wmds_assert( 'three seen', 3, count( $plan['seen'] ) );

wmds_section( 'Plan: force re-reads unchanged vehicles too' );

$forced = WMDS_Sync_Plan::build( array( ad( 'x' ) ), array( 'x' => known( 1 ) ), true );
wmds_assert( 'nothing skipped', 0, count( $forced['skip'] ) );
wmds_assert( 'updated instead', 1, count( $forced['update'] ) );

wmds_section( 'Plan: unusable entries are ignored' );

$junk = WMDS_Sync_Plan::build(
	array( ad( 'good' ), array( 'no' => 'id' ), 'string instead of array', array() ),
	array()
);
wmds_assert( 'only the usable ad', 1, count( $junk['create'] ) );
wmds_assert( 'only one seen ID', 1, count( $junk['seen'] ) );

wmds_section( 'Plan: a missing modificationDate forces an update' );

$undated = WMDS_Sync_Plan::build(
	array( array( 'mobileAdId' => 'x' ) ),
	array( 'x' => known( 1 ) )
);
wmds_assert( 'updated to be on the safe side', 1, count( $undated['update'] ) );

wmds_section( 'Removal: never after a partial pass' );

$partial = WMDS_Sync_Plan::removals(
	array( 'a' ),
	array(
		'a' => known( 1 ),
		'b' => known( 2 ),
		'c' => known( 3 ),
	),
	false
);
wmds_assert( 'nothing to remove', 0, count( $partial['remove'] ) );
wmds_assert_contains( 'with a reason', 'Partial listing', $partial['abort'] );

wmds_section( 'Removal: an empty result never deletes the inventory' );

$empty = WMDS_Sync_Plan::removals( array(), array( 'a' => known( 1 ) ), true );
wmds_assert( 'nothing to remove', 0, count( $empty['remove'] ) );
wmds_assert_contains( 'with a reason', 'did not see a single vehicle', $empty['abort'] );

wmds_section( 'Removal: the ordinary case' );

$normal = WMDS_Sync_Plan::removals(
	array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i' ),
	array(
		'a'    => known( 1 ),
		'b'    => known( 2 ),
		'c'    => known( 3 ),
		'd'    => known( 4 ),
		'e'    => known( 5 ),
		'f'    => known( 6 ),
		'g'    => known( 7 ),
		'h'    => known( 8 ),
		'i'    => known( 9 ),
		'sold' => known( 99 ),
	),
	true
);
wmds_assert( 'no abort', '', $normal['abort'] );
wmds_assert( 'exactly one post', array( 99 ), $normal['remove'] );

wmds_section( 'Removal: the safety guard on an implausible amount' );

$inventory = array();
$seen      = array();
for ( $i = 1; $i <= 100; $i++ ) {
	$inventory[ 'ad-' . $i ] = known( $i );
	if ( $i <= 50 ) {
		$seen[] = 'ad-' . $i;
	}
}
$guarded = WMDS_Sync_Plan::removals( $seen, $inventory, true );
wmds_assert( 'nothing removed', 0, count( $guarded['remove'] ) );
wmds_assert( 'candidates counted all the same', 50, $guarded['candidates'] );
wmds_assert_contains( 'guard named', 'Safety guard', $guarded['abort'] );
wmds_assert_contains( 'numbers stated', '50 of 100', $guarded['abort'] );

wmds_section( 'Removal: just under the limit is allowed' );

$seen_70 = array();
for ( $i = 31; $i <= 100; $i++ ) {
	$seen_70[] = 'ad-' . $i;
}
$limit_case = WMDS_Sync_Plan::removals( $seen_70, $inventory, true );
wmds_assert( 'no abort', '', $limit_case['abort'] );
wmds_assert( '30 posts removed', 30, count( $limit_case['remove'] ) );

wmds_section( 'Removal: a small inventory is not blocked by the percentage' );

$small = array();
for ( $i = 1; $i <= 8; $i++ ) {
	$small[ 'k-' . $i ] = known( $i );
}
$three = WMDS_Sync_Plan::removals( array( 'k-4', 'k-5', 'k-6', 'k-7', 'k-8' ), $small, true );
wmds_assert( 'no abort', '', $three['abort'] );
wmds_assert( 'three removed', 3, count( $three['remove'] ) );

$four = WMDS_Sync_Plan::removals( array( 'k-5', 'k-6', 'k-7', 'k-8' ), $small, true );
wmds_assert_contains( 'four of eight trips it', 'Safety guard', $four['abort'] );

wmds_section( 'Removal: an empty inventory is harmless' );

$nothing = WMDS_Sync_Plan::removals( array( 'a' ), array(), true );
wmds_assert( 'no abort', '', $nothing['abort'] );
wmds_assert( 'nothing to remove', 0, count( $nothing['remove'] ) );

wmds_section( 'The fingerprint detects real changes' );

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
	'a changed price gives a different hash',
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
wmds_assert( 'one image swapped', true, WMDS_Sync_Plan::images_changed( array( 'h1', 'x' ), $images ) );
wmds_assert( 'order changed', true, WMDS_Sync_Plan::images_changed( array( 'h2', 'h1' ), $images ) );
wmds_assert( 'an image added', true, WMDS_Sync_Plan::images_changed( array( 'h1' ), $images ) );
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
wmds_assert( 'no dates at all', '', WMDS_Sync_Plan::next_watermark( array() ) );
wmds_assert( 'nothing usable', '', WMDS_Sync_Plan::next_watermark( array( '', 'not a date' ) ) );

wmds_result();
