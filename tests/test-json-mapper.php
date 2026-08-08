<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/class-wmds-str.php';
require_once __DIR__ . '/../includes/class-wmds-num.php';
require_once __DIR__ . '/../includes/class-wmds-date.php';
require_once __DIR__ . '/../includes/class-wmds-creole.php';
require_once __DIR__ . '/../includes/class-wmds-refdata.php';
require_once __DIR__ . '/../includes/class-wmds-json-mapper.php';

class WMDS_Refdata_Fake extends WMDS_Refdata {
	protected function fetch( $path ) {
		$tables = array(
			'gearboxes'              => array(
				'MANUAL_GEAR'    => 'Schaltgetriebe',
				'AUTOMATIC_GEAR' => 'Automatik',
			),
			'fuels'                  => array( 'DIESEL' => 'Diesel' ),
			'colors'                 => array( 'BLACK' => 'Schwarz' ),
			'interiorcolors'         => array( 'BLACK' => 'Schwarz' ),
			'interiortypes'          => array(
				'PARTIAL_LEATHER' => 'Teilleder',
				'LEATHER'         => 'Leder',
			),
			'conditions'             => array( 'USED' => 'Gebraucht' ),
			'doorcounts'             => array(
				'TWO_OR_THREE' => '2/3',
				'FOUR_OR_FIVE' => '4/5',
			),
			'emissionclasses'        => array(
				'EURO5'  => 'Euro5',
				'EURO6D' => 'Euro6d',
			),
			'emissionstickers'       => array( 'EMISSIONSSTICKER_GREEN' => '4 (Gruen)' ),
			'classes/Car/categories' => array(
				'OffRoad'   => 'SUV/Gelaendewagen',
				'EstateCar' => 'Kombi',
			),
			'classes/Car/makes'      => array(
				'LAND ROVER' => 'Land Rover',
				'VOLVO'      => 'Volvo',
			),
		);
		return isset( $tables[ $path ] ) ? $tables[ $path ] : array();
	}
}

$mapper = new WMDS_Json_Mapper( new WMDS_Refdata_Fake( 'de' ) );

$search = json_decode( file_get_contents( __DIR__ . '/fixtures/search-2-ads.json' ), true );
$detail = json_decode( file_get_contents( __DIR__ . '/fixtures/ad-detail.json' ), true );

if ( ! $search || ! $detail ) {
	fwrite( STDERR, "Fixtures could not be parsed.\n" );
	exit( 1 );
}

$defender = $mapper->map( $search['ads'][0] );
$volvo    = $mapper->map( $search['ads'][1] );

wmds_section( 'Vehicle 1: basics and refdata resolution' );

wmds_assert( 'ad_id', '424776053', $defender['ad_id'] );
wmds_assert( 'title without a duplicated model', 'Land Rover Defender 90 Standheizung Kamera LED Recaro', $defender['title'] );
wmds_assert( 'Marke uebersetzt', 'Land Rover', $defender['meta']['make'] );
wmds_assert( 'make_key sprachunabhaengig', 'LAND ROVER', $defender['meta']['make_key'] );
wmds_assert( 'Kategorie uebersetzt', 'SUV/Gelaendewagen', $defender['meta']['category'] );
wmds_assert( 'Zustand uebersetzt', 'Gebraucht', $defender['meta']['condition'] );
wmds_assert( 'Getriebe uebersetzt', 'Schaltgetriebe', $defender['meta']['gearbox'] );
wmds_assert( 'Kraftstoff uebersetzt', 'Diesel', $defender['meta']['fuel'] );
wmds_assert( 'Aussenfarbe uebersetzt', 'Schwarz', $defender['meta']['exteriorColor'] );
wmds_assert( 'modificationDate durchgereicht', '2026-07-28T18:29:13+02:00', $defender['modified'] );

wmds_section( 'Vehicle 2: separate data, no bleed-through' );

wmds_assert( 'ad_id', '427312402', $volvo['ad_id'] );
wmds_assert( 'Marke', 'Volvo', $volvo['meta']['make'] );
wmds_assert( 'Kategorie', 'Kombi', $volvo['meta']['category'] );
wmds_assert( 'Getriebe', 'Automatik', $volvo['meta']['gearbox'] );

wmds_section( 'Numbers and formatting' );

wmds_assert( 'mileage formatted', '129,000', $defender['meta']['mileage'] );
wmds_assert( 'mileage raw (FacetWP)', 129000, $defender['meta']['mileage_raw'] );
wmds_assert( 'Leistung', 108, $defender['meta']['power'] );
wmds_assert( 'price formatted', '42,999', $defender['meta']['price'] );
wmds_assert( 'price raw (FacetWP)', 42999, $defender['meta']['price_raw'] );
wmds_assert( 'Preisbereich', '30.000 – 50.000 €', $defender['meta']['price_raw_short'] );
wmds_assert( 'Erstzulassung strtotime-faehig', '2014-09-01', $defender['meta']['firstRegistration'] );
wmds_assert( 'Erstzulassung Jahr (FacetWP)', 2014, $defender['meta']['firstRegistration_year'] );
wmds_assert( 'Verbrauch kombiniert', '10', $defender['meta']['emissionFuelConsumption_Combined'] );
wmds_assert( 'CO2', '265', $defender['meta']['emissionFuelConsumption_CO2'] );
wmds_assert( 'Verbrauch innerorts fehlt -> leer', '', $defender['meta']['emissionFuelConsumption_Inner'] );

wmds_section( 'VAT: derived from vatRate, not from a dedicated field' );

wmds_assert( 'without vatRate -> false', 'false', $defender['meta']['vatable'] );
wmds_assert( 'mit vatRate -> true', 'true', $volvo['meta']['vatable'] );
wmds_assert( 'Satz mitgefuehrt', '19.00', $volvo['meta']['vat_rate'] );

wmds_section( 'Inspection: generalInspection absent, newHuAu carries the statement' );

wmds_assert( 'no inspection date', '', $defender['meta']['nextInspection'] );
wmds_assert( 'newHuAu as text', 'New inspection on purchase', $defender['meta']['newHuAu'] );
wmds_assert( 'inspection date is mapped when present', '2027-05-01', WMDS_Json_Mapper::format_month( '202705' ) );

wmds_section( 'Vorbesitzer' );

wmds_assert( 'not stated -> empty', '', $defender['meta']['owners'] );
wmds_assert( 'angegeben', '1', $volvo['meta']['owners'] );

wmds_section( 'Features: all 28 template keys are covered' );

wmds_assert( 'Anzahl Regeln', 28, count( WMDS_Json_Mapper::feature_keys() ) );

wmds_section( 'Ausstattung: Booleans' );

wmds_assert( 'Auxiliary heating', 'Auxiliary heating', $defender['meta']['AUXILIARY_HEATING'] );
wmds_assert( 'satnav', 'Navigation system', $defender['meta']['NAVIGATION_SYSTEM'] );
wmds_assert( 'metallic paint', 'Metallic paint', $defender['meta']['METALLIC'] );
wmds_assert( 'Defender has no ABS in the feed', false, isset( $defender['meta']['ABS'] ) );
wmds_assert( 'Volvo hat ABS', 'ABS', $volvo['meta']['ABS'] );
wmds_assert( 'Volvo hat ESP', 'ESP', $volvo['meta']['ESP'] );
wmds_assert( 'Defender has no ESP', false, isset( $defender['meta']['ESP'] ) );

wmds_section( 'Ausstattung: Enums' );

wmds_assert( 'no xenon when LED', false, isset( $defender['meta']['XENON_HEADLIGHTS'] ) );
wmds_assert( 'daytime running lights detected', 'Daytime running lights', $defender['meta']['DAYTIME_RUNNING_LIGHTS'] );
wmds_assert( 'cruise control only on the Volvo', 'Cruise control', $volvo['meta']['CRUISE_CONTROL'] );
wmds_assert( 'Defender without cruise control', false, isset( $defender['meta']['CRUISE_CONTROL'] ) );

wmds_section( 'Features: lists - a reversing camera is not parking sensors' );

wmds_assert( 'camera only -> no parking sensors', false, isset( $defender['meta']['PARKING_SENSORS'] ) );
wmds_assert( 'with sensors -> parking sensors', 'Parking sensors', $volvo['meta']['PARKING_SENSORS'] );

wmds_section( 'HTML entities in a field value are decoded' );

wmds_assert(
	'Farbbezeichnung',
	'BLACK SOLID "STONE" / Solid',
	$volvo['meta']['manufacturer_color_name']
);

wmds_section( 'Bilder: groesste Repraesentation plus Hash' );

wmds_assert( 'ein Bild', 1, count( $defender['images'] ) );
wmds_assert( 'groesste gewaehlt', 'https://img.example.invalid/a-xxxl.jpg', $defender['images'][0]['url'] );
wmds_assert( 'Hash uebernommen', '99c3997d960b89eae1b66fb104c8d8a2', $defender['images'][0]['hash'] );
wmds_assert( 'Rueckfall auf vorhandene Groesse', 'https://img.example.invalid/b-xxxl.jpg', $volvo['images'][0]['url'] );

wmds_section( 'A search result has neither description nor seller name' );

wmds_assert( 'no description', '', $defender['meta']['enriched_description'] );
wmds_assert( 'no seller name', '', $defender['meta']['seller'] );
wmds_assert( 'Telefon fehlt', false, isset( $defender['meta']['seller_phone_number'] ) );
wmds_assert( 'e-mail is present', 'kontakt@example.invalid', $defender['meta']['seller_email'] );

wmds_section( 'Detail call layered over the search result' );

$merged = $mapper->map( array_merge( $search['ads'][0], $detail ) );

wmds_assert( 'Verkaeufername vorhanden', 'Musterhaendler GmbH', $merged['meta']['seller'] );
wmds_assert( 'Telefon Landesvorwahl', '49', $merged['meta']['seller_phone_country_calling_code'] );
wmds_assert( 'Telefon Vorwahl', '000', $merged['meta']['seller_phone_area_code'] );
wmds_assert( 'Telefon Nummer', '0000000', $merged['meta']['seller_phone_number'] );
wmds_assert( 'two images from the detail call', 2, count( $merged['images'] ) );
wmds_assert( 'Ad-ID unveraendert', '424776053', $merged['ad_id'] );

wmds_section( 'The description is rendered as HTML' );

$desc = $merged['meta']['enriched_description'];
wmds_assert_contains( 'Fettdruck', '<strong>', $desc );
wmds_assert( 'no backslash', false, strpos( $desc, '\\' ) );
wmds_assert( 'two paragraphs', 2, substr_count( $desc, '<p>' ) );
wmds_assert_contains( 'special characters preserved', 'intercooler', $desc );
wmds_assert_contains( 'post_content is the plain text', 'The following modifications', $merged['description'] );

wmds_section( 'Incomplete ads do not blow up' );

$empty = $mapper->map( array() );
wmds_assert( 'no ad_id', '', $empty['ad_id'] );
wmds_assert( 'fallback title', 'Vehicle ', $empty['title'] );
wmds_assert( 'no images', 0, count( $empty['images'] ) );
wmds_assert( 'Preis leer', '', $empty['meta']['price'] );
wmds_assert( 'no features', false, isset( $empty['meta']['ABS'] ) );

$ohne_refdata = new WMDS_Json_Mapper();
$raw          = $ohne_refdata->map( $search['ads'][0] );
wmds_assert( 'without refdata the key survives', 'MANUAL_GEAR', $raw['meta']['gearbox'] );

wmds_section( 'Whatever the feed sets counts as a feature, rule or no rule' );

wmds_assert( 'alloyWheels has no rule and arrives anyway', 'Alloy wheels', $merged['meta']['ALLOY_WHEELS'] );
wmds_assert( 'the index lists what was found', true, false !== strpos( $merged['meta']['feature_keys'], 'ALLOY_WHEELS' ) );
wmds_assert( 'newHuAu keeps the key the templates read', 'New inspection on purchase', $merged['meta']['HU_AU_NEU'] );

$unknown = $mapper->map( array( 'heatedWindscreen' => true ) );
wmds_assert( 'an unknown boolean gets a readable label', 'Heated windscreen', $unknown['meta']['HEATED_WINDSCREEN'] );

$legacy = $mapper->map( array( 'nonSmokerVehicle' => true ) );
wmds_assert( 'an alias resolves to the key the templates read', 'Non-smoker vehicle', $legacy['meta']['NONSMOKER_VEHICLE'] );
wmds_assert( 'and only to that one', false, isset( $legacy['meta']['NON_SMOKER_VEHICLE'] ) );
wmds_assert( 'the index carries it once', 'NONSMOKER_VEHICLE', $legacy['meta']['feature_keys'] );

$off = $mapper->map( array( 'sunroof' => false ) );
wmds_assert( 'a false boolean is no feature', false, isset( $off['meta']['SUNROOF'] ) );

$damaged = $mapper->map( array( 'damageUnrepaired' => true ) );
wmds_assert( 'damage is not equipment', false, isset( $damaged['meta']['DAMAGE_UNREPAIRED'] ) );

wmds_section( 'The fields the earlier templates read' );

wmds_assert( 'cubicCapacity beside cubic_capacity', $merged['meta']['cubic_capacity'], $merged['meta']['cubicCapacity'] );
wmds_assert( 'roadworthy beside roadWorthy', $merged['meta']['roadWorthy'], $merged['meta']['roadworthy'] );
wmds_assert( 'seller_company_name beside seller', $merged['meta']['seller'], $merged['meta']['seller_company_name'] );
wmds_assert( 'the vehicle class is readable', 'Car', $merged['meta']['class'] );
wmds_assert( 'the class key survives too', 'Car', $merged['meta']['class_key'] );
wmds_assert( 'the street', 'Musterstrasse 1', $merged['meta']['seller_street'] );
wmds_assert( 'postcode', '12345', $merged['meta']['seller_zipcode'] );
wmds_assert( 'city', 'Musterstadt', $merged['meta']['seller_city'] );
wmds_assert( 'the seller ID', '99999999', $merged['meta']['seller_id'] );
wmds_assert( 'selling since', '06.04.2016', $merged['meta']['seller_since'] );
wmds_assert( 'climatisation', 'MANUAL_CLIMATISATION', $merged['meta']['climatisation'] );
wmds_assert( 'parking assistants read as prose', 'Rear view cam', $merged['meta']['parking-assistants'] );
wmds_assert( 'a field the feed omits stays empty', '', $merged['meta']['axles'] );

wmds_result();
