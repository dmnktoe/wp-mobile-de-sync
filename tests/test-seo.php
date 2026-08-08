<?php

require_once __DIR__ . '/bootstrap.php';

define( 'WMDS_CPT', 'fahrzeuge' );

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

require_once __DIR__ . '/../includes/class-wmds-str.php';
require_once __DIR__ . '/../includes/class-wmds-num.php';
require_once __DIR__ . '/../includes/class-wmds-date.php';
require_once __DIR__ . '/../includes/class-wmds-seo.php';

/**
 * @param array $overrides
 * @return array
 */
function wmds_seo_data( array $overrides = array() ) {
	return array_merge(
		array(
			'title'         => 'Audi A4 Avant 2.0 TDI',
			'url'           => 'https://example.invalid/fahrzeuge/audi-a4/',
			'description'   => 'One owner, full service history.',
			'images'        => array(
				'https://example.invalid/one.jpg',
				'https://example.invalid/two.jpg',
			),
			'ad_id'         => '427312402',
			'make'          => 'Audi',
			'model'         => 'A4',
			'body_type'     => 'Estate',
			'colour'        => 'Black',
			'doors'         => '5',
			'seats'         => '5',
			'fuel'          => 'Diesel',
			'gearbox'       => 'Automatic',
			'power'         => '140',
			'displacement'  => '1968',
			'mileage'       => '129000',
			'owners'        => '1',
			'co2'           => '118',
			'registered'    => '2019-06-01',
			'year'          => '2019',
			'condition'     => 'Used',
			'condition_key' => 'USED',
			'price'         => '24900',
			'currency'      => 'EUR',
			'seller'        => 'Autohaus Example',
			'site'          => 'Autohaus Example',
		),
		$overrides
	);
}

wmds_section( 'The vehicle, as a search engine reads it' );

$schema = WMDS_Seo::vehicle_schema( wmds_seo_data() );

wmds_assert( 'it is a Car', 'Car', $schema['@type'] );
wmds_assert( 'with the context stated', 'https://schema.org', $schema['@context'] );
wmds_assert( 'named after the vehicle', 'Audi A4 Avant 2.0 TDI', $schema['name'] );
wmds_assert( 'the listing number is the sku', '427312402', $schema['sku'] );
wmds_assert( 'the make is a brand', 'Audi', $schema['brand']['name'] );
wmds_assert( 'the model is the model', 'A4', $schema['model'] );
wmds_assert( 'the body type carries over', 'Estate', $schema['bodyType'] );
wmds_assert( 'so does the transmission', 'Automatic', $schema['vehicleTransmission'] );
wmds_assert( 'every photo is listed', 2, count( $schema['image'] ) );

wmds_section( 'Numbers stay numbers, with the unit stated' );

wmds_assert( 'the mileage is a quantity', 'QuantitativeValue', $schema['mileageFromOdometer']['@type'] );
wmds_assert( 'read as a number, not a string', 129000, $schema['mileageFromOdometer']['value'] );
wmds_assert( 'in kilometres', 'KMT', $schema['mileageFromOdometer']['unitCode'] );
wmds_assert( 'the power is in kilowatts', 'KWT', $schema['vehicleEngine']['enginePower']['unitCode'] );
wmds_assert( 'the displacement in cubic centimetres', 'CMQ', $schema['vehicleEngine']['engineDisplacement']['unitCode'] );
wmds_assert( 'doors are counted', 5, $schema['numberOfDoors'] );
wmds_assert( 'so are previous owners', 1, $schema['numberOfPreviousOwners'] );
wmds_assert( 'CO2 is a plain number', 118, $schema['emissionsCO2'] );
wmds_assert( 'the first registration is a date', '2019-06-01', $schema['dateVehicleFirstRegistered'] );

wmds_section( 'The offer' );

wmds_assert( 'the price is a number', 24900, $schema['offers']['price'] );
wmds_assert( 'in the currency the feed states', 'EUR', $schema['offers']['priceCurrency'] );
wmds_assert( 'the vehicle is in stock', 'https://schema.org/InStock', $schema['offers']['availability'] );
wmds_assert( 'the seller is a dealer', 'AutoDealer', $schema['offers']['seller']['@type'] );

$free = WMDS_Seo::vehicle_schema( wmds_seo_data( array( 'price' => '' ) ) );
wmds_assert( 'no price, no offer claimed', false, isset( $free['offers'] ) );

wmds_section( 'New or used' );

wmds_assert(
	'the key says used',
	'https://schema.org/UsedCondition',
	WMDS_Seo::condition( wmds_seo_data() )
);
wmds_assert(
	'the key says new',
	'https://schema.org/NewCondition',
	WMDS_Seo::condition( wmds_seo_data( array( 'condition_key' => 'NEW' ) ) )
);
wmds_assert(
	'a demonstration car is used',
	'https://schema.org/UsedCondition',
	WMDS_Seo::condition( wmds_seo_data( array( 'condition_key' => 'DEMONSTRATION_VEHICLE' ) ) )
);
wmds_assert(
	'without a key, a vehicle that has been driven is used',
	'https://schema.org/UsedCondition',
	WMDS_Seo::condition(
		wmds_seo_data(
			array(
				'condition_key' => '',
				'mileage'       => '80000',
			)
		)
	)
);
wmds_assert(
	'and one that has not is new',
	'https://schema.org/NewCondition',
	WMDS_Seo::condition(
		wmds_seo_data(
			array(
				'condition_key' => '',
				'mileage'       => '10',
			)
		)
	)
);

wmds_section( 'What is missing is left out, not guessed' );

$sparse = WMDS_Seo::vehicle_schema(
	array(
		'title' => 'Vehicle 12345',
		'url'   => 'https://example.invalid/fahrzeuge/12345/',
	)
);

wmds_assert( 'the name survives', 'Vehicle 12345', $sparse['name'] );
wmds_assert( 'no brand is invented', false, isset( $sparse['brand'] ) );
wmds_assert( 'no mileage is invented', false, isset( $sparse['mileageFromOdometer'] ) );
wmds_assert( 'no engine is invented', false, isset( $sparse['vehicleEngine'] ) );
wmds_assert( 'no images key on a vehicle without photos', false, isset( $sparse['image'] ) );

wmds_assert(
	'a power of zero is not an engine specification',
	false,
	isset(
		WMDS_Seo::vehicle_schema(
			wmds_seo_data(
				array(
					'power'        => '0',
					'displacement' => '',
				)
			)
		)['vehicleEngine']
	)
);

wmds_section( 'The archive, as a list' );

$list = WMDS_Seo::item_list(
	array( wmds_seo_data(), wmds_seo_data( array( 'title' => 'BMW 320d' ) ) ),
	'https://example.invalid/fahrzeuge/'
);

wmds_assert( 'it is an ItemList', 'ItemList', $list['@type'] );
wmds_assert( 'it says how many', 2, $list['numberOfItems'] );
wmds_assert( 'positions start at one', 1, $list['itemListElement'][0]['position'] );
wmds_assert( 'and count up', 2, $list['itemListElement'][1]['position'] );
wmds_assert( 'each entry is the vehicle itself', 'BMW 320d', $list['itemListElement'][1]['item']['name'] );
wmds_assert( 'the list knows its own URL', 'https://example.invalid/fahrzeuge/', $list['url'] );

wmds_section( 'What a scraper gets' );

$meta = WMDS_Seo::social_meta( wmds_seo_data() );

wmds_assert( 'a product, not an article', 'product', $meta['og:type'] );
wmds_assert( 'titled after the vehicle', 'Audi A4 Avant 2.0 TDI', $meta['og:title'] );
wmds_assert( 'pointing at the vehicle', 'https://example.invalid/fahrzeuge/audi-a4/', $meta['og:url'] );
wmds_assert( 'with the first photo', 'https://example.invalid/one.jpg', $meta['og:image'] );
wmds_assert( 'a large card on Twitter', 'summary_large_image', $meta['twitter:card'] );
wmds_assert( 'the price is stated', '24900', $meta['product:price:amount'] );
wmds_assert( 'and its currency', 'EUR', $meta['product:price:currency'] );

$no_price = WMDS_Seo::social_meta( wmds_seo_data( array( 'price' => '' ) ) );
wmds_assert( 'no price, none claimed', false, isset( $no_price['product:price:amount'] ) );

$no_image = WMDS_Seo::social_meta( wmds_seo_data( array( 'images' => array() ) ) );
wmds_assert( 'no photo, no empty image tag', false, isset( $no_image['og:image'] ) );

wmds_result();
