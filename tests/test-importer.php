<?php

define( 'ABSPATH', __DIR__ . '/wp-stubs/' );
define( 'WMDS_CPT', 'fahrzeuge' );
define( 'WMDS_CRON_HOOK', 'wmds_import_event' );

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-str.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-num.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-date.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-client.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-creole.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-refdata.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-json-mapper.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-sync-plan.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-importer.php';
require_once __DIR__ . '/wp-fake.php';

/**
 * @param string $id
 * @param string $modified
 * @param array  $overrides
 * @return array
 */
function wmds_ad( $id, $modified = '2026-07-28T18:29:13+02:00', array $overrides = array() ) {
	return array_merge(
		array(
			'mobileAdId'        => $id,
			'modificationDate'  => $modified,
			'vehicleClass'      => 'Car',
			'category'          => 'EstateCar',
			'make'              => 'AUDI',
			'model'             => 'A4',
			'modelDescription'  => 'A4 Avant 40 TDI quattro',
			'condition'         => 'USED',
			'firstRegistration' => '2021-03',
			'mileage'           => 78000,
			'power'             => 150,
			'gearbox'           => 'AUTOMATIC_GEAR',
			'fuel'              => 'DIESEL',
			'price'             => array(
				'consumerPriceGross' => '32.900 €',
				'consumerPriceNet'   => '27.647 €',
				'currency'           => 'EUR',
				'vatRate'            => '19',
			),
			'images'            => array(
				array(
					'hash' => 'hash-' . $id . '-1',
					'xxxl' => 'https://img.example.invalid/' . $id . '-1.jpg',
				),
			),
			'seller'            => array( 'companyName' => 'Example Motors' ),
		),
		$overrides
	);
}

/**
 * @param string $id
 * @return array
 */
function wmds_detail( $id ) {
	return array(
		'mobileAdId'           => $id,
		'description'          => 'First line\\\\Second line',
		'plainTextDescription' => "First line\nSecond line",
		'seller'               => array(
			'companyName' => 'Example Motors',
			'email'       => 'kontakt@example.invalid',
			'phones'      => array(
				array(
					'internationalPrefix' => 49,
					'nationalPrefix'      => '069',
					'number'              => '1234567',
				),
			),
		),
	);
}

/**
 * @param array $ads       Ads of the search result.
 * @param array $overrides Further properties for the client.
 * @return array array( WMDS_Importer, WMDS_Fake_Client )
 */
function wmds_setup( array $ads, array $overrides = array() ) {
	$client        = new WMDS_Fake_Client();
	$client->pages = array(
		1 => array(
			'ads'       => $ads,
			'max_pages' => 1,
			'capped'    => false,
		),
	);

	foreach ( $ads as $ad ) {
		$client->details[ (string) $ad['mobileAdId'] ] = wmds_detail( (string) $ad['mobileAdId'] );
	}

	foreach ( $overrides as $key => $value ) {
		$client->$key = $value;
	}

	$mapper = new WMDS_Json_Mapper( new WMDS_Fake_Refdata() );

	return array( new WMDS_Importer( $client, $mapper ), $client );
}

function wmds_post_for( $ad_id ) {
	foreach ( wmds_fake( 'meta' ) as $post_id => $meta ) {
		if ( isset( $meta[ WMDS_Importer::META_AD_ID ] ) && (string) $ad_id === (string) $meta[ WMDS_Importer::META_AD_ID ] ) {
			return $post_id;
		}
	}

	return 0;
}

wmds_section( 'Preconditions' );

wmds_fake_reset();
list( $importer ) = wmds_setup( array(), array( 'credentials' => false ) );
$result           = $importer->run();
wmds_assert( 'no credentials: error', 'wmds_no_credentials', $result->get_error_code() );

wmds_fake_reset();
list( $importer ) = wmds_setup( array(), array( 'seller' => false ) );
$result           = $importer->run();
wmds_assert( 'no seller ID: error', 'wmds_no_seller', $result->get_error_code() );

wmds_fake_reset();
set_transient( WMDS_Importer::LOCK, time(), 900 );
list( $importer, $client ) = wmds_setup( array( wmds_ad( '1' ) ) );
$result                    = $importer->run();
wmds_assert( 'import already running: error', 'wmds_locked', $result->get_error_code() );
wmds_assert( 'a locked run does not call the API', 0, count( $client->searched ) );

wmds_section( 'Initial import: empty site, two vehicles in the feed' );

wmds_fake_reset();
list( $importer, $client ) = wmds_setup( array( wmds_ad( '111' ), wmds_ad( '222' ) ) );
$stats                     = $importer->run();

wmds_assert( 'no error', false, is_wp_error( $stats ) );
wmds_assert( 'two seen', 2, $stats['seen'] );
wmds_assert( 'two created', 2, $stats['created'] );
wmds_assert( 'nothing updated', 0, $stats['updated'] );
wmds_assert( 'nothing removed', 0, $stats['removed'] );
wmds_assert( 'nothing pending', 0, $stats['pending'] );
wmds_assert( 'counted as a full sync', true, $stats['full'] );
wmds_assert( 'two posts exist', 2, count( wmds_fake( 'posts' ) ) );

$post_111 = wmds_post_for( '111' );
wmds_assert( 'post for 111 exists', true, $post_111 > 0 );
wmds_assert(
	'title from make and modelDescription',
	'L:AUDI A4 Avant 40 TDI quattro',
	wmds_fake( 'posts' )[ $post_111 ]['post_title']
);
wmds_assert( 'ad ID in meta', '111', get_post_meta( $post_111, WMDS_Importer::META_AD_ID, true ) );
wmds_assert( 'mileage in meta', '78,000', get_post_meta( $post_111, 'mileage', true ) );
wmds_assert( 'raw value for the facet', 78000, get_post_meta( $post_111, 'mileage_raw', true ) );

wmds_section( 'Initial import: detail call, description, images' );

wmds_assert( 'details fetched for both', 2, count( $client->fetched ) );
wmds_assert_contains(
	'description from the detail call in the post content',
	'First line',
	wmds_fake( 'posts' )[ $post_111 ]['post_content']
);
wmds_assert_contains(
	'CREOLE break became <br> in the rendered field',
	'First line<br>Second line',
	get_post_meta( $post_111, 'enriched_description', true )
);
wmds_assert( 'phone number from the detail call', '1234567', get_post_meta( $post_111, 'seller_phone_number', true ) );
wmds_assert( 'seller name from the detail call', 'Example Motors', get_post_meta( $post_111, 'seller', true ) );
wmds_assert( 'two images downloaded', 2, $stats['images'] );
wmds_assert( 'featured image set', true, has_post_thumbnail( $post_111 ) );
wmds_assert(
	'image hashes remembered',
	array( 'hash-111-1' ),
	get_post_meta( $post_111, WMDS_Importer::META_IMAGES, true )
);

wmds_section( 'Initial import: state after the run' );

wmds_assert( 'lock released again', false, get_transient( WMDS_Importer::LOCK ) );
wmds_assert( 'watermark set', true, '' !== (string) get_option( WMDS_Importer::OPT_WATERMARK, '' ) );
wmds_assert( 'statistics stored', 2, get_option( WMDS_Importer::OPT_LAST_RUN )['created'] );
wmds_assert_contains( 'log entry written', 'Full sync', get_option( WMDS_Importer::OPT_LOG )[0]['message'] );
wmds_assert( 'exactly one inventory query', 1, $GLOBALS['wpdb']->queries );
wmds_assert( 'no follow-up scheduled', 0, count( wmds_fake( 'scheduled' ) ) );

wmds_section( 'Follow-up run: nothing changed' );

$GLOBALS['wpdb']->queries  = 0;
list( $importer, $client ) = wmds_setup( array( wmds_ad( '111' ), wmds_ad( '222' ) ) );
$stats                     = $importer->run();

wmds_assert( 'both skipped', 2, $stats['skipped'] );
wmds_assert( 'nothing created', 0, $stats['created'] );
wmds_assert( 'nothing updated', 0, $stats['updated'] );
wmds_assert( 'no detail call', 0, count( $client->fetched ) );
wmds_assert( 'no images re-downloaded', 0, $stats['images'] );
wmds_assert( 'still two posts', 2, count( wmds_fake( 'posts' ) ) );
wmds_assert( 'watermark was sent along', true, '' !== $client->searched[0]['since'] );

wmds_section( 'A vehicle changed on mobile.de' );

$before                    = get_post_meta( wmds_post_for( '111' ), WMDS_Importer::META_HASH, true );
list( $importer, $client ) = wmds_setup(
	array(
		wmds_ad( '111', '2026-07-29T10:00:00+02:00', array( 'mileage' => 79500 ) ),
		wmds_ad( '222' ),
	)
);
$stats                     = $importer->run();

wmds_assert( 'one updated', 1, $stats['updated'] );
wmds_assert( 'one skipped', 1, $stats['skipped'] );
wmds_assert( 'only the changed one is fetched', array( '111' ), $client->fetched );
wmds_assert( 'new mileage', '79,500', get_post_meta( wmds_post_for( '111' ), 'mileage', true ) );
wmds_assert( 'content hash changed', true, get_post_meta( wmds_post_for( '111' ), WMDS_Importer::META_HASH, true ) !== $before );
wmds_assert( 'same images, no reload', 0, $stats['images'] );
wmds_assert( 'no second post for 111', 2, count( wmds_fake( 'posts' ) ) );

wmds_section( 'The dealer swaps a photo' );

list( $importer, $client ) = wmds_setup(
	array(
		wmds_ad(
			'111',
			'2026-07-29T12:00:00+02:00',
			array(
				'images' => array(
					array(
						'hash' => 'hash-111-NEU',
						'xxxl' => 'https://img.example.invalid/111-neu.jpg',
					),
				),
			)
		),
	)
);
$stats                     = $importer->run();

wmds_assert( 'image re-downloaded', 1, $stats['images'] );
wmds_assert(
	'new hash stored',
	array( 'hash-111-NEU' ),
	get_post_meta( wmds_post_for( '111' ), WMDS_Importer::META_IMAGES, true )
);
wmds_assert( 'only one attachment on the post', 1, count( wmds_fake( 'attachments' )[ wmds_post_for( '111' ) ] ) );

wmds_section( 'More work than fits into one pass' );

wmds_fake_reset();
list( $importer, $client ) = wmds_setup(
	array( wmds_ad( '1' ), wmds_ad( '2' ), wmds_ad( '3' ), wmds_ad( '4' ), wmds_ad( '5' ) )
);
$stats                     = $importer->run( array( 'batch' => 2 ) );

wmds_assert( 'two in this pass', 2, $stats['created'] );
wmds_assert( 'three remain pending', 3, $stats['pending'] );
wmds_assert( 'follow-up scheduled', array( WMDS_CRON_HOOK ), wmds_fake( 'scheduled' ) );
wmds_assert(
	'watermark is held back',
	'',
	(string) get_option( WMDS_Importer::OPT_WATERMARK, '' )
);

$stats = $importer->run( array( 'batch' => 2 ) );
wmds_assert( 'next two', 2, $stats['created'] );
wmds_assert( 'one remains pending', 1, $stats['pending'] );

$stats = $importer->run( array( 'batch' => 2 ) );
wmds_assert( 'last vehicle', 1, $stats['created'] );
wmds_assert( 'nothing pending any more', 0, $stats['pending'] );
wmds_assert( 'all five created', 5, count( wmds_fake( 'posts' ) ) );
wmds_assert( 'only now the watermark', true, '' !== (string) get_option( WMDS_Importer::OPT_WATERMARK, '' ) );

wmds_section( 'The API answers with an error' );

wmds_fake_reset();
wmds_fake_seed_vehicle( '111' );
wmds_fake_seed_vehicle( '222' );

list( $importer, $client ) = wmds_setup(
	array(),
	array( 'search_error' => new WP_Error( 'wmds_http_503', 'Service Unavailable' ) )
);
$result                    = $importer->run( array( 'full' => true ) );

wmds_assert( 'run ends with a WP_Error', true, is_wp_error( $result ) );
wmds_assert( 'nothing moved to trash', 0, count( wmds_fake( 'trashed' ) ) );
wmds_assert( 'inventory untouched', 2, count( wmds_fake( 'posts' ) ) );
wmds_assert_contains( 'reason in the log', 'Service Unavailable', get_option( WMDS_Importer::OPT_LOG )[0]['message'] );
wmds_assert( 'lock released regardless', false, get_transient( WMDS_Importer::LOCK ) );

wmds_section( 'The detail call fails, the search result stands' );

wmds_fake_reset();
list( $importer, $client ) = wmds_setup( array( wmds_ad( '111' ) ) );
$client->details           = array();
$stats                     = $importer->run();

wmds_assert( 'vehicle created regardless', 1, $stats['created'] );
wmds_assert( 'fields from the search result present', '78,000', get_post_meta( wmds_post_for( '111' ), 'mileage', true ) );
wmds_assert( 'without a description', '', wmds_fake( 'posts' )[ wmds_post_for( '111' ) ]['post_content'] );
wmds_assert_contains( 'failure logged', 'Detail call failed', get_option( WMDS_Importer::OPT_LOG )[1]['message'] );

wmds_section( 'One image cannot be downloaded' );

wmds_fake_reset();
$GLOBALS['wp_fake']['unloadable'] = array( 'https://img.example.invalid/111-2.jpg' );

list( $importer, $client ) = wmds_setup(
	array(
		wmds_ad(
			'111',
			'2026-07-28T18:29:13+02:00',
			array(
				'images' => array(
					array(
						'hash' => 'a',
						'xxxl' => 'https://img.example.invalid/111-1.jpg',
					),
					array(
						'hash' => 'b',
						'xxxl' => 'https://img.example.invalid/111-2.jpg',
					),
				),
			)
		),
	)
);
$stats                     = $importer->run();

wmds_assert( 'vehicle created', 1, $stats['created'] );
wmds_assert( 'one image instead of two', 1, $stats['images'] );
wmds_assert( 'featured image set regardless', true, has_post_thumbnail( wmds_post_for( '111' ) ) );
wmds_assert_contains( 'noted in the log', 'Image could not be downloaded', get_option( WMDS_Importer::OPT_LOG )[1]['message'] );

wmds_section( 'The API reports more vehicles than it hands out' );

wmds_fake_reset();
list( $importer, $client )  = wmds_setup( array( wmds_ad( '111' ) ) );
$client->pages[1]['capped'] = true;
$importer->run();

wmds_assert_contains(
	'warning in the log',
	'This reconciliation is incomplete',
	get_option( WMDS_Importer::OPT_LOG )[1]['message']
);

wmds_section( 'Removal: the normal case after a full sync' );

wmds_fake_reset();
foreach ( array( '1', '2', '3', '4', '5', '6', '7', '8', '9', '10' ) as $id ) {
	wmds_fake_seed_vehicle( $id );
}
$sold = wmds_post_for( '10' );

$ads = array();
foreach ( array( '1', '2', '3', '4', '5', '6', '7', '8', '9' ) as $id ) {
	$ads[] = wmds_ad( $id );
}
list( $importer, $client ) = wmds_setup( $ads );
$stats                     = $importer->run( array( 'full' => true ) );

wmds_assert( 'one removed', 1, $stats['removed'] );
wmds_assert( 'exactly the sold post', array( $sold ), wmds_fake( 'trashed' ) );
wmds_assert( 'trashed, not deleted', 'trash', wmds_fake( 'posts' )[ $sold ]['post_status'] );
wmds_assert( 'all posts still present', 10, count( wmds_fake( 'posts' ) ) );

wmds_section( 'Guard 1: never remove after a partial pass' );

wmds_fake_reset();
foreach ( array( '1', '2', '3', '4', '5' ) as $id ) {
	wmds_fake_seed_vehicle( $id );
}
update_option( WMDS_Importer::OPT_WATERMARK, '2026-07-01T00:00:00Z' );

list( $importer, $client ) = wmds_setup( array( wmds_ad( '1' ) ) );
$stats                     = $importer->run();

wmds_assert( 'recognised as an incremental sync', false, $stats['full'] );
wmds_assert( 'nothing removed', 0, $stats['removed'] );
wmds_assert( 'trash empty', 0, count( wmds_fake( 'trashed' ) ) );

wmds_section( 'Guard 2: a run that saw no vehicle at all' );

wmds_fake_reset();
foreach ( array( '1', '2', '3', '4', '5' ) as $id ) {
	wmds_fake_seed_vehicle( $id );
}
list( $importer, $client ) = wmds_setup( array() );
$stats                     = $importer->run( array( 'full' => true ) );

wmds_assert( 'no vehicle seen', 0, $stats['seen'] );
wmds_assert( 'nothing removed', 0, $stats['removed'] );
wmds_assert( 'inventory complete', 5, count( wmds_fake( 'posts' ) ) );
wmds_assert_contains(
	'abort explained',
	'did not see a single vehicle',
	get_option( WMDS_Importer::OPT_LOG )[1]['message']
);

wmds_section( 'Guard 3: more than 30 percent at once' );

wmds_fake_reset();
foreach ( range( 1, 20 ) as $id ) {
	wmds_fake_seed_vehicle( (string) $id );
}
$ads = array();
foreach ( range( 1, 12 ) as $id ) {
	$ads[] = wmds_ad( (string) $id );
}
list( $importer, $client ) = wmds_setup( $ads );
$stats                     = $importer->run(
	array(
		'full'  => true,
		'batch' => 100,
	)
);

wmds_assert( 'eight of 20 would have gone', 0, $stats['removed'] );
wmds_assert( 'nothing in the trash', 0, count( wmds_fake( 'trashed' ) ) );
wmds_assert_contains(
	'abort states the count and the limit',
	'8 of 20 vehicles would be removed (limit 6)',
	get_option( WMDS_Importer::OPT_LOG )[1]['message']
);

wmds_section( 'Floor: small inventories stay workable' );

wmds_fake_reset();
foreach ( array( '1', '2', '3', '4' ) as $id ) {
	wmds_fake_seed_vehicle( $id );
}
list( $importer, $client ) = wmds_setup( array( wmds_ad( '1' ), wmds_ad( '2' ), wmds_ad( '3' ) ) );
$stats                     = $importer->run( array( 'full' => true ) );

wmds_assert( 'a quarter exceeds 30 percent but stays under the floor', 1, $stats['removed'] );

wmds_section( 'A vehicle reappears' );

wmds_fake_reset();
$post_id = wmds_fake_seed_vehicle( '111' );
wp_trash_post( $post_id );
$GLOBALS['wp_fake']['trashed'] = array();

list( $importer, $client ) = wmds_setup( array( wmds_ad( '111' ) ) );
$stats                     = $importer->run( array( 'full' => true ) );

wmds_assert( 'no second post', 1, count( wmds_fake( 'posts' ) ) );
wmds_assert( 'the existing one was updated', 1, $stats['updated'] );
wmds_assert( 'published again', 'publish', wmds_fake( 'posts' )[ $post_id ]['post_status'] );

wmds_section( 'force: re-read unchanged vehicles anyway' );

wmds_fake_reset();
wmds_fake_seed_vehicle( '111', '2026-07-28T18:29:13+02:00' );

list( $importer, $client ) = wmds_setup( array( wmds_ad( '111' ) ) );
$stats                     = $importer->run( array( 'force' => true ) );

wmds_assert( 'nothing skipped', 0, $stats['skipped'] );
wmds_assert( 'updated', 1, $stats['updated'] );
wmds_assert( 'detail call happened', array( '111' ), $client->fetched );

wmds_section( 'A partial image failure is not recorded as complete' );

wmds_fake_reset();
$GLOBALS['wp_fake']['unloadable'] = array( 'https://img.example.invalid/111-b.jpg' );

$three_images = array(
	array(
		'hash' => 'a',
		'xxxl' => 'https://img.example.invalid/111-a.jpg',
	),
	array(
		'hash' => 'b',
		'xxxl' => 'https://img.example.invalid/111-b.jpg',
	),
	array(
		'hash' => 'c',
		'xxxl' => 'https://img.example.invalid/111-c.jpg',
	),
);

list( $importer, $client ) = wmds_setup(
	array( wmds_ad( '111', '2026-07-28T18:29:13+02:00', array( 'images' => $three_images ) ) )
);
$stats                     = $importer->run();
$post_id                   = wmds_post_for( '111' );

wmds_assert( 'two of three landed', 2, $stats['images'] );
wmds_assert( 'only the successful hashes stored', array( 'a', 'c' ), get_post_meta( $post_id, WMDS_Importer::META_IMAGES, true ) );
wmds_assert(
	'the gap is still visible to the next run',
	true,
	WMDS_Sync_Plan::images_changed( get_post_meta( $post_id, WMDS_Importer::META_IMAGES, true ), $three_images )
);

wmds_section( 'The next run repairs it' );

$GLOBALS['wp_fake']['unloadable'] = array();
$GLOBALS['wp_fake']['downloaded'] = array();

list( $importer, $client ) = wmds_setup(
	array( wmds_ad( '111', '2026-07-28T18:29:13+02:00', array( 'images' => $three_images ) ) )
);
$stats                     = $importer->run( array( 'force' => true ) );

wmds_assert( 'all three now', 3, $stats['images'] );
wmds_assert( 'complete set on file', array( 'a', 'b', 'c' ), get_post_meta( $post_id, WMDS_Importer::META_IMAGES, true ) );

wmds_section( 'A complete set is not fetched again' );

$GLOBALS['wp_fake']['downloaded'] = array();
list( $importer, $client )        = wmds_setup(
	array( wmds_ad( '111', '2026-07-28T18:29:13+02:00', array( 'images' => $three_images ) ) )
);
$stats                            = $importer->run();

wmds_assert( 'nothing re-downloaded', 0, count( wmds_fake( 'downloaded' ) ) );
wmds_assert( 'skipped as unchanged', 1, $stats['skipped'] );

wmds_section( 'More images than the cap does not loop forever' );

wmds_fake_reset();
$many = array();
foreach ( range( 1, WMDS_Importer::MAX_IMAGES + 5 ) as $n ) {
	$many[] = array(
		'hash' => 'h' . $n,
		'xxxl' => 'https://img.example.invalid/111-' . $n . '.jpg',
	);
}

list( $importer, $client ) = wmds_setup(
	array( wmds_ad( '111', '2026-07-28T18:29:13+02:00', array( 'images' => $many ) ) )
);
$stats                     = $importer->run();
$post_id                   = wmds_post_for( '111' );

wmds_assert( 'capped at MAX_IMAGES', WMDS_Importer::MAX_IMAGES, $stats['images'] );

$GLOBALS['wp_fake']['downloaded'] = array();
list( $importer, $client )        = wmds_setup(
	array( wmds_ad( '111', '2026-07-28T18:29:13+02:00', array( 'images' => $many ) ) )
);
$stats                            = $importer->run( array( 'force' => true ) );

wmds_assert( 'a forced re-read does not re-download', 0, count( wmds_fake( 'downloaded' ) ) );
wmds_assert( 'and adds no images', 0, $stats['images'] );

wmds_section( 'A sold vehicle goes to the trash with its gallery intact' );

wmds_fake_reset();
foreach ( array( '1', '2', '3', '4', '5' ) as $seed ) {
	wmds_fake_seed_vehicle( $seed );
}

$ads = array();
foreach ( array( '1', '2', '3', '4' ) as $seed ) {
	$ads[] = wmds_ad( $seed );
}
list( $importer, $client ) = wmds_setup( $ads );

$sold                                       = wmds_post_for( '5' );
$GLOBALS['wp_fake']['attachments'][ $sold ] = array( 901, 902 );

$importer->run( array( 'full' => true ) );

wmds_assert( 'the sold one is in the trash', 'trash', wmds_fake( 'posts' )[ $sold ]['post_status'] );
wmds_assert( 'its images survive the trashing', 2, count( wmds_fake( 'attachments' )[ $sold ] ) );

wmds_section( 'Permanent deletion takes the images with it' );

WMDS_Importer::delete_attachments( $sold );
wmds_assert( 'attachments gone', 0, count( wmds_fake( 'attachments' )[ $sold ] ) );

wmds_section( 'Reloading a single vehicle' );

wmds_fake_reset();
list( $importer, $client ) = wmds_setup( array( wmds_ad( '777' ) ) );
$importer->run();

$post_id = wmds_post_for( '777' );
wmds_assert( 'imported first', true, $post_id > 0 );

$client->details['777'] = array_merge(
	wmds_ad( '777' ),
	wmds_detail( '777' ),
	array( 'mileage' => 91000 )
);

delete_post_meta( $post_id, WMDS_Importer::META_HASH );
$before  = count( $client->searched );
$outcome = $importer->refresh( '777' );

wmds_assert( 'no error', false, is_wp_error( $outcome ) );
wmds_assert( 'recognised as an update', 'updated', $outcome['outcome'] );
wmds_assert( 'the new mileage landed', 91000, (int) get_post_meta( $post_id, 'mileage_raw', true ) );
wmds_assert( 'no search call was needed', $before, count( $client->searched ) );

wmds_section( 'Reloading fails loudly' );

wmds_assert( 'without an ad ID', 'wmds_no_ad_id', $importer->refresh( '' )->get_error_code() );
wmds_assert( 'for an unknown ad', 'wmds_not_found', $importer->refresh( 'does-not-exist' )->get_error_code() );

wmds_fake_reset();
list( $importer ) = wmds_setup( array(), array( 'credentials' => false ) );
wmds_assert( 'without credentials', 'wmds_no_credentials', $importer->refresh( '777' )->get_error_code() );

wmds_section( 'A pass stops on the clock instead of being killed by it' );

wmds_fake_reset();
$ads = array();
foreach ( array( '1', '2', '3', '4', '5' ) as $seed ) {
	$ads[] = wmds_ad( $seed );
}

list( $importer, $client ) = wmds_setup( $ads );
$stats                     = $importer->run( array( 'budget' => 0 ) );

wmds_assert( 'only the first vehicle was done', 1, $stats['created'] );
wmds_assert( 'the rest is pending', 4, $stats['pending'] );
wmds_assert( 'and comes back on the next run', true, in_array( WMDS_CRON_HOOK, wmds_fake( 'scheduled' ), true ) );
wmds_assert( 'the lock is released either way', 0, WMDS_Importer::locked_since() );

wmds_section( 'A pass cut short does not count as a full reconciliation' );

wmds_fake_reset();
foreach ( array( '1', '2', '3', '4', '5' ) as $seed ) {
	wmds_fake_seed_vehicle( $seed, '2026-01-01T00:00:00.000Z' );
}
$gone = wmds_fake_seed_vehicle( '6', '2026-01-01T00:00:00.000Z' );

$ads = array();
foreach ( array( '1', '2', '3', '4', '5' ) as $seed ) {
	$ads[] = wmds_ad( $seed );
}

list( $importer, $client ) = wmds_setup( $ads );
$stats                     = $importer->run(
	array(
		'full'   => true,
		'budget' => 0,
	)
);

wmds_assert( 'work is left over', true, $stats['pending'] > 0 );
wmds_assert( 'nothing was removed', 0, $stats['removed'] );
wmds_assert( 'the vanished vehicle is still published', 'publish', wmds_fake( 'posts' )[ $gone ]['post_status'] );
wmds_assert( 'the watermark did not move', '', (string) get_option( WMDS_Importer::OPT_WATERMARK, '' ) );

wmds_section( 'A listing cut short removes nothing' );

wmds_fake_reset();
foreach ( array( '1', '2', '9' ) as $seed ) {
	wmds_fake_seed_vehicle( $seed, '2026-01-01T00:00:00.000Z' );
}
$unread = wmds_post_for( '9' );

list( $importer, $client ) = wmds_setup( array( wmds_ad( '1' ) ) );
$client->pages             = array(
	1 => array(
		'ads'       => array( wmds_ad( '1' ) ),
		'max_pages' => 3,
		'capped'    => false,
	),
	2 => array(
		'ads'       => array( wmds_ad( '2' ) ),
		'max_pages' => 3,
		'capped'    => false,
	),
	3 => array(
		'ads'       => array( wmds_ad( '9' ) ),
		'max_pages' => 3,
		'capped'    => false,
	),
);

$stats = $importer->run(
	array(
		'full'   => true,
		'budget' => 0,
	)
);

wmds_assert( 'it stopped before the last page', true, count( $client->searched ) < 3 );
wmds_assert( 'the unread vehicle was not trashed', 'publish', wmds_fake( 'posts' )[ $unread ]['post_status'] );
wmds_assert( 'nothing was removed at all', 0, $stats['removed'] );
wmds_assert( 'the watermark did not move', '', (string) get_option( WMDS_Importer::OPT_WATERMARK, '' ) );
wmds_assert( 'and the run comes back', true, in_array( WMDS_CRON_HOOK, wmds_fake( 'scheduled' ), true ) );

wmds_section( 'Images are only replaced once the new ones are in' );

wmds_fake_reset();
list( $importer, $client ) = wmds_setup( array( wmds_ad( '111' ) ) );
$importer->run();

$post_id = wmds_post_for( '111' );
$before  = wmds_fake( 'attachments' )[ $post_id ];

wmds_assert( 'imported to begin with', true, count( $before ) > 0 );

$GLOBALS['wp_fake']['unloadable'] = array( 'https://img.example.invalid/111-x.jpg' );

$vanished = array(
	array(
		'hash' => 'x',
		'xxxl' => 'https://img.example.invalid/111-x.jpg',
	),
);

list( $importer, $client ) = wmds_setup(
	array( wmds_ad( '111', '2026-07-29T10:00:00+02:00', array( 'images' => $vanished ) ) )
);
$stats                     = $importer->run();

wmds_assert( 'nothing new landed', 0, $stats['images'] );
wmds_assert( 'the old gallery is still there', $before, wmds_fake( 'attachments' )[ $post_id ] );
wmds_assert( 'and so is the featured image', true, has_post_thumbnail( $post_id ) );

wmds_section( 'The date stored is the one the next run compares against' );

wmds_fake_reset();
$GLOBALS['wp_fake']['unloadable'] = array();

$listed                    = '2026-07-28T18:29:13+02:00';
list( $importer, $client ) = wmds_setup( array( wmds_ad( '111', $listed ) ) );

$client->details['111'] = array_merge(
	wmds_detail( '111' ),
	array( 'modificationDate' => '2026-07-28T16:29:13.000Z' )
);

$importer->run();
$post_id = wmds_post_for( '111' );

wmds_assert( 'the search result wins', $listed, get_post_meta( $post_id, WMDS_Importer::META_MODIFIED, true ) );

list( $importer, $client ) = wmds_setup( array( wmds_ad( '111', $listed ) ) );
$client->details['111']    = array_merge(
	wmds_detail( '111' ),
	array( 'modificationDate' => '2026-07-28T16:29:13.000Z' )
);
$stats                     = $importer->run();

wmds_assert( 'so the vehicle comes out as unchanged', 1, $stats['skipped'] );
wmds_assert( 'and is not written again', 0, $stats['updated'] );

wmds_section( 'Other post types are left alone' );

wmds_fake_reset();
$other                                       = wp_insert_post(
	array(
		'post_type'  => 'post',
		'post_title' => 'An ordinary post',
	)
);
$GLOBALS['wp_fake']['attachments'][ $other ] = array( 901, 902 );

WMDS_Importer::delete_attachments( $other );
wmds_assert( 'untouched', 2, count( wmds_fake( 'attachments' )[ $other ] ) );

wmds_result();
