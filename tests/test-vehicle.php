<?php

define( 'ABSPATH', __DIR__ . '/wp-stubs/' );
define( 'WMDS_CPT', 'fahrzeuge' );
define( 'WMDS_CRON_HOOK', 'wmds_import_event' );
define( 'WMDS_DIR', dirname( __DIR__ ) . '/' );
define( 'WMDS_URL', 'https://example.invalid/plugins/wp-mobile-de-sync/' );

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-client.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-creole.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-refdata.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-json-mapper.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-sync-plan.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-importer.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-logos.php';
require_once dirname( __DIR__ ) . '/includes/class-wmds-vehicle.php';
require_once __DIR__ . '/wp-fake.php';

/**
 * @param array $meta
 * @return WMDS_Vehicle
 */
function wmds_vehicle_with( array $meta ) {
	$post_id = wp_insert_post(
		array(
			'post_type'  => WMDS_CPT,
			'post_title' => 'Test vehicle',
		)
	);

	foreach ( $meta as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}

	return new WMDS_Vehicle( $post_id );
}

wmds_section( 'Access to individual fields' );

wmds_fake_reset();
$v = wmds_vehicle_with(
	array(
		'make'  => 'Audi',
		'empty' => '',
		'space' => '   ',
	)
);

wmds_assert( 'field present', 'Audi', $v->get( 'make' ) );
wmds_assert( 'missing field is empty', '', $v->get( 'nosuchfield' ) );
wmds_assert( 'missing field with a default', '–', $v->get( 'nosuchfield', '–' ) );
wmds_assert( 'empty field falls back to the default', '–', $v->get( 'empty', '–' ) );
wmds_assert( 'whitespace only counts as empty', '–', $v->get( 'space', '–' ) );
wmds_assert( 'has() with a present field', true, $v->has( 'make' ) );
wmds_assert( 'has() with an empty field', false, $v->has( 'empty' ) );
wmds_assert( 'has() with whitespace', false, $v->has( 'space' ) );

wmds_section( 'Vehicle without a post' );

$none = new WMDS_Vehicle( 0 );
wmds_assert( 'get() returns the default', '', $none->get( 'make' ) );
wmds_assert( 'no images', array(), $none->images() );
wmds_assert( 'no highlights', array(), $none->highlights() );
wmds_assert( 'no features', array(), $none->features() );
wmds_assert( 'no warnings', array(), $none->warnings() );

wmds_section( 'Price' );

wmds_fake_reset();
$v = wmds_vehicle_with(
	array(
		'price'    => '32.900',
		'currency' => 'EUR',
	)
);
wmds_assert( 'euro as a symbol', '32.900 €', $v->price() );

$v = wmds_vehicle_with(
	array(
		'price'    => '31.000',
		'currency' => 'CHF',
	)
);
wmds_assert( 'other currency spelled out', '31.000 CHF', $v->price() );

$v = wmds_vehicle_with( array( 'price' => '9.500' ) );
wmds_assert( 'euro applies without a currency field', '9.500 €', $v->price() );

$v = wmds_vehicle_with( array( 'currency' => 'EUR' ) );
wmds_assert( 'no price, no text', '', $v->price() );

wmds_section( 'VAT' );

$v = wmds_vehicle_with(
	array(
		'vatable'  => 'true',
		'vat_rate' => '19',
	)
);
wmds_assert( 'reclaimable, with a rate', 'incl. 19% VAT', $v->vat_note() );

$v = wmds_vehicle_with(
	array(
		'vatable'  => 'true',
		'vat_rate' => '19.00',
	)
);
wmds_assert( 'trailing zeros dropped', 'incl. 19% VAT', $v->vat_note() );

$v = wmds_vehicle_with( array( 'vatable' => 'true' ) );
wmds_assert( 'reclaimable, no rate', 'incl. VAT', $v->vat_note() );

$v = wmds_vehicle_with( array( 'vatable' => 'false' ) );
wmds_assert( 'not reclaimable is stated', 'VAT not reclaimable', $v->vat_note() );

wmds_section( 'Key figures' );

$v = wmds_vehicle_with( array( 'mileage' => '78,000' ) );
wmds_assert( 'mileage with unit', '78,000 km', $v->mileage() );

$v = wmds_vehicle_with( array() );
wmds_assert( 'no mileage, no text', '', $v->mileage() );

$v = wmds_vehicle_with( array( 'power' => '150' ) );
wmds_assert( 'power in kW and hp', '150 kW (204 hp)', $v->power() );

$v = wmds_vehicle_with( array( 'power' => '0' ) );
wmds_assert( 'zero kW yields no text', '', $v->power() );

$v = wmds_vehicle_with( array( 'firstRegistration' => '2021-03-01' ) );
wmds_assert( 'first registration as MM.YYYY', '03.2021', $v->first_registration() );

$v = wmds_vehicle_with( array( 'firstRegistration' => 'unknown' ) );
wmds_assert( 'unparseable date survives', 'unknown', $v->first_registration() );

wmds_section( 'Inspection: both cases' );

$v = wmds_vehicle_with( array( 'nextInspection' => '2027-11-01' ) );
wmds_assert( 'with a date', 'Inspection valid until 11.2027', $v->inspection() );

$v = wmds_vehicle_with( array( 'newHuAu' => 'New inspection on purchase' ) );
wmds_assert( 'falls back to newHuAu', 'New inspection on purchase', $v->inspection() );

$v = wmds_vehicle_with(
	array(
		'nextInspection' => '2027-11-01',
		'newHuAu'        => 'New inspection on purchase',
	)
);
wmds_assert( 'the date takes precedence', 'Inspection valid until 11.2027', $v->inspection() );

$v = wmds_vehicle_with( array() );
wmds_assert( 'neither', '', $v->inspection() );

wmds_section( 'Highlights' );

$v          = wmds_vehicle_with(
	array(
		'mileage'           => '78,000',
		'firstRegistration' => '2021-03-01',
		'power'             => '150',
		'gearbox'           => 'Automatic',
	)
);
$highlights = $v->highlights();

wmds_assert( 'empty rows are dropped', 4, count( $highlights ) );
wmds_assert( 'no fuel entry', false, isset( $highlights['Fuel'] ) );
wmds_assert( 'order is preserved', 'Mileage', key( $highlights ) );
wmds_assert( 'value is formatted', '78,000 km', $highlights['Mileage'] );

wmds_section( 'Specifications, grouped' );

$v     = wmds_vehicle_with(
	array(
		'condition'                        => 'Used vehicle',
		'cubic_capacity'                   => '1,968',
		'emissionFuelConsumption_Combined' => '5.4',
		'emissionFuelConsumption_CO2'      => '142',
		'combinedPowerConsumption'         => '',
	)
);
$specs = $v->specifications();

wmds_assert( 'only populated groups', array( 'Vehicle', 'Engine & transmission', 'Energy & environment' ), array_keys( $specs ) );
wmds_assert( 'displacement with unit', '1,968 cm³', $specs['Engine & transmission']['Displacement'] );
wmds_assert( 'consumption with unit', '5.4 l/100 km', $specs['Energy & environment']['Consumption, combined'] );
wmds_assert( 'CO2 with its own unit', '142 g/km', $specs['Energy & environment']['CO₂ emissions, combined'] );
wmds_assert( 'empty power consumption is dropped', false, isset( $specs['Energy & environment']['Power consumption, combined'] ) );
wmds_assert( 'an empty group disappears entirely', false, isset( $specs['Other'] ) );

wmds_section( 'Features' );

$keys     = WMDS_Json_Mapper::feature_keys();
$v        = wmds_vehicle_with(
	array(
		$keys[0] => 'Central locking',
		$keys[1] => '',
		$keys[2] => 'ABS',
	)
);
$features = $v->features();

wmds_assert( 'only set features', 2, count( $features ) );
wmds_assert( 'sorted alphabetically', array( 'ABS', 'Central locking' ), $features );

$stored = wmds_vehicle_with(
	array(
		'feature_keys' => 'BLUETOOTH',
		'BLUETOOTH'    => 'Bluetooth',
		$keys[0]       => 'Central locking',
	)
);
wmds_assert( 'the stored index decides', array( 'Bluetooth' ), $stored->features() );

$emptied = wmds_vehicle_with(
	array(
		'feature_keys' => '',
		'ABS'          => 'ABS',
	)
);
wmds_assert( 'an emptied index is an answer, not a gap', array(), $emptied->features() );

wmds_section( 'Warnings' );

$v = wmds_vehicle_with( array( 'damageRepaired' => 'true' ) );
wmds_assert( 'unrepaired damage', array( 'Damaged, unrepaired' ), $v->warnings() );

$v = wmds_vehicle_with( array( 'roadWorthy' => 'false' ) );
wmds_assert( 'not roadworthy', array( 'Not roadworthy' ), $v->warnings() );

$v = wmds_vehicle_with(
	array(
		'damageRepaired' => 'false',
		'roadWorthy'     => 'true',
	)
);
wmds_assert( 'unremarkable vehicle', array(), $v->warnings() );

$v = wmds_vehicle_with( array() );
wmds_assert( 'no data, no warning', array(), $v->warnings() );

wmds_section( 'Phone number' );

$v = wmds_vehicle_with(
	array(
		'seller_phone_country_calling_code' => '49',
		'seller_phone_area_code'            => '069',
		'seller_phone_number'               => '1234567',
	)
);
wmds_assert( 'leading zero dropped before the country code', '+49 69 1234567', $v->seller_phone() );

$v = wmds_vehicle_with(
	array(
		'seller_phone_area_code' => '069',
		'seller_phone_number'    => '1234567',
	)
);
wmds_assert( 'without a country code', '069 1234567', $v->seller_phone() );

$v = wmds_vehicle_with(
	array(
		'seller_phone_country_calling_code' => '49',
		'seller_phone_area_code'            => '069',
	)
);
wmds_assert( 'no number, no fragment', '', $v->seller_phone() );

wmds_section( 'Images' );

wmds_fake_reset();
$post_id                                       = wp_insert_post(
	array(
		'post_type'  => WMDS_CPT,
		'post_title' => 'Audi A4',
	)
);
$GLOBALS['wp_fake']['attachments'][ $post_id ] = array( 501, 502 );

$v      = new WMDS_Vehicle( $post_id );
$images = $v->images();

wmds_assert( 'both attachments', 2, count( $images ) );
wmds_assert( 'large variant', 'https://example.invalid/bild-501-large.jpg', $images[0]['url'] );
wmds_assert( 'thumbnail at medium size', 'https://example.invalid/bild-501-medium.jpg', $images[0]['thumb'] );
wmds_assert( 'alt text from the title', 'Audi A4', $images[0]['alt'] );

wmds_section( 'End-to-end with a real ad from the fixtures' );

wmds_fake_reset();

$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/search-2-ads.json' ), true );
$detail  = json_decode( file_get_contents( __DIR__ . '/fixtures/ad-detail.json' ), true );

$client          = new WMDS_Fake_Client();
$client->pages   = array(
	1 => array(
		'ads'       => array( $fixture['ads'][0] ),
		'max_pages' => 1,
		'capped'    => false,
	),
);
$client->details = array( (string) $fixture['ads'][0]['mobileAdId'] => $detail );

$importer = new WMDS_Importer( $client, new WMDS_Json_Mapper( new WMDS_Fake_Refdata() ) );
$stats    = $importer->run();

wmds_assert( 'one vehicle created', 1, $stats['created'] );

$post_id = array_keys( wmds_fake( 'posts' ) )[0];
$v       = new WMDS_Vehicle( $post_id );

wmds_assert( 'price readable', true, '' !== $v->price() );
wmds_assert( 'mileage readable', true, '' !== $v->mileage() );
wmds_assert( 'power readable', true, '' !== $v->power() );
wmds_assert( 'first registration readable', true, '' !== $v->first_registration() );
wmds_assert( 'inspection readable', true, '' !== $v->inspection() );
wmds_assert( 'seller readable', true, '' !== $v->seller() );
wmds_assert( 'phone number readable', true, '' !== $v->seller_phone() );
wmds_assert( 'highlights complete', 5, count( $v->highlights() ) );
wmds_assert( 'several specification groups', true, count( $v->specifications() ) >= 3 );
wmds_assert( 'features detected', true, count( $v->features() ) > 5 );
wmds_assert( 'images from the detail call', 2, count( $v->images() ) );
wmds_assert( 'VAT note present', true, '' !== $v->vat_note() );

wmds_result();
