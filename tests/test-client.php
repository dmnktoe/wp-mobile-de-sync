<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/class-wmds-client.php';
require_once __DIR__ . '/../includes/class-wmds-refdata.php';

wmds_section( 'Search URL: prefers the documented customerId' );

$client = new WMDS_Client( 'user', 'pass', '12345678', 'MUSTERHAENDLER' );
$url    = $client->search_url( 1, 100 );

wmds_assert_contains( 'customerId set', 'customerId=12345678', $url );
wmds_assert( 'dealer NOT set', false, strpos( $url, 'dealer=' ) );
wmds_assert_contains( 'page size', 'page.size=100', $url );
wmds_assert_contains( 'page number', 'page.number=1', $url );

wmds_section( 'Search URL: falls back to dealer when no seller ID is present' );

$fallback = new WMDS_Client( 'user', 'pass', '', 'MUSTERHAENDLER' );
$url_fb   = $fallback->search_url();
wmds_assert_contains( 'dealer set', 'dealer=MUSTERHAENDLER', $url_fb );
wmds_assert( 'customerId NOT set', false, strpos( $url_fb, 'customerId=' ) );

wmds_section( 'Search URL: no double encoding' );

$umlaut = new WMDS_Client( 'user', 'pass', '', 'AUTO & MEHR' );
wmds_assert_contains( 'encoded once', 'dealer=AUTO%20%26%20MEHR', $umlaut->search_url() );
wmds_assert( 'not encoded twice', false, strpos( $umlaut->search_url(), '%2520' ) );

wmds_section( 'Search URL: page size is clamped to the API maximum' );

wmds_assert_contains( '500 -> 100', 'page.size=100', $client->search_url( 1, 500 ) );
wmds_assert_contains( '0 -> 1', 'page.size=1', $client->search_url( 1, 0 ) );
wmds_assert_contains( 'page 0 -> 1', 'page.number=1', $client->search_url( 0, 50 ) );

wmds_section( 'Search URL: incremental sync sets the time filter and sorting' );

$inc = $client->search_url( 1, 100, '2026-07-30T10:00:00+02:00' );
wmds_assert_contains( 'time filter set', 'modificationTime.min=', $inc );
wmds_assert_contains( 'plus sign encoded', '%2B02%3A00', $inc );
wmds_assert_contains( 'sorted ascending', 'sort.order=ASCENDING', $inc );
wmds_assert( 'no sorting without a time filter', false, strpos( $client->search_url(), 'sort.field' ) );

wmds_section( 'Response: valid search result' );

$ok = WMDS_Client::interpret( 200, '{"total":143,"currentPage":1,"maxPages":2,"pageSize":100,"ads":[{"mobileAdId":"424776053"}]}' );
wmds_assert( 'no error', false, is_wp_error( $ok ) );
wmds_assert( 'total read', 143, $ok['total'] );
wmds_assert( 'contains one ad', 1, count( $ok['ads'] ) );

wmds_section( 'Response: HTTP errors become WP_Error, never a fatal' );

$e401 = WMDS_Client::interpret( 401, '' );
wmds_assert( '401 is a WP_Error', true, is_wp_error( $e401 ) );
wmds_assert_contains( '401 carries a hint', 'check the credentials', $e401->get_error_message() );

$body406 = '{"detail":"Acceptable representations: [application/xml, application/vnd.de.mobile.api+xml, application/vnd.de.mobile.api+json].","instance":"/search","status":406,"title":"Not Acceptable"}';
$e406    = WMDS_Client::interpret( 406, $body406 );
wmds_assert( '406 is a WP_Error', true, is_wp_error( $e406 ) );
wmds_assert_contains( '406 states the reason', 'Not Acceptable', $e406->get_error_message() );
wmds_assert_contains( '406 lists the accepted formats', 'Acceptable representations', $e406->get_error_message() );

$body400 = '{"errors":[{"key":"invalid-search-parameter","message":"Invalid search parameter","args":[{"key":"field","value":"customerId"},{"key":"code","value":"typeMismatch"}]}]}';
$e400    = WMDS_Client::interpret( 400, $body400 );
wmds_assert( '400 is a WP_Error', true, is_wp_error( $e400 ) );
wmds_assert_contains( '400 states the key', 'invalid-search-parameter', $e400->get_error_message() );
wmds_assert_contains( '400 states the field', 'field=customerId', $e400->get_error_message() );

wmds_section( 'Response: broken or empty bodies' );

wmds_assert( 'empty body', true, is_wp_error( WMDS_Client::interpret( 200, '   ' ) ) );
wmds_assert( 'not JSON', true, is_wp_error( WMDS_Client::interpret( 200, '<html>Maintenance</html>' ) ) );
wmds_assert( 'JSON scalar instead of an object', true, is_wp_error( WMDS_Client::interpret( 200, '"just a string"' ) ) );
wmds_assert( 'error list despite HTTP 200', true, is_wp_error( WMDS_Client::interpret( 200, '{"errors":[{"key":"deprecated-parameter"}]}' ) ) );

wmds_section( 'Response: an empty but valid result is not an error' );

$empty = WMDS_Client::interpret( 200, '{"total":0,"currentPage":1,"maxPages":1,"pageSize":100,"ads":[]}' );
wmds_assert( 'no error', false, is_wp_error( $empty ) );
wmds_assert( 'total 0', 0, $empty['total'] );

wmds_section( 'Refdata: a real /refdata/gearboxes response' );

$gearboxes = '{"values":[{"name":"MANUAL_GEAR","description":"Schaltgetriebe","url":"https://services.mobile.de/refdata/gearboxes/MANUAL_GEAR"},{"name":"SEMIAUTOMATIC_GEAR","description":"Halbautomatik","url":"https://services.mobile.de/refdata/gearboxes/SEMIAUTOMATIC_GEAR"},{"name":"AUTOMATIC_GEAR","description":"Automatik","url":"https://services.mobile.de/refdata/gearboxes/AUTOMATIC_GEAR"}]}';
$parsed    = WMDS_Refdata::parse( $gearboxes );

wmds_assert( 'three entries', 3, count( $parsed ) );
wmds_assert( 'MANUAL_GEAR', 'Schaltgetriebe', $parsed['MANUAL_GEAR'] );
wmds_assert( 'AUTOMATIC_GEAR', 'Automatik', $parsed['AUTOMATIC_GEAR'] );

wmds_section( 'Refdata: unusable responses yield an empty table' );

wmds_assert( 'not JSON', array(), WMDS_Refdata::parse( '<html>' ) );
wmds_assert( 'values missing', array(), WMDS_Refdata::parse( '{"foo":1}' ) );
wmds_assert( 'entry without a description is dropped', array(), WMDS_Refdata::parse( '{"values":[{"name":"X"}]}' ) );

wmds_section( 'Refdata: falls back to the key when the table is missing' );

class WMDS_Refdata_Stub extends WMDS_Refdata {
	protected function fetch( $path ) {
		if ( 'gearboxes' === $path ) {
			return array( 'MANUAL_GEAR' => 'Schaltgetriebe' );
		}
		return array();
	}
}

$rd = new WMDS_Refdata_Stub( 'de' );
wmds_assert( 'known key is translated', 'Schaltgetriebe', $rd->label( 'gearbox', 'MANUAL_GEAR' ) );
wmds_assert( 'unknown key survives unchanged', 'DUAL_CLUTCH', $rd->label( 'gearbox', 'DUAL_CLUTCH' ) );
wmds_assert( 'table unreachable -> key', 'DIESEL', $rd->label( 'fuel', 'DIESEL' ) );
wmds_assert( 'unknown field -> unchanged', 'ANYTHING', $rd->label( 'gibtsnicht', 'ANYTHING' ) );
wmds_assert( 'empty value stays empty', '', $rd->label( 'gearbox', '' ) );

wmds_section( 'Refdata: class-dependent tables receive the vehicleClass' );

class WMDS_Refdata_Path extends WMDS_Refdata {
	public $seen = array();
	protected function fetch( $path ) {
		$this->seen[] = $path;
		return array();
	}
}

$path_spy = new WMDS_Refdata_Path( 'de' );
$path_spy->label( 'make', 'AUDI', 'Car' );
$path_spy->label( 'category', 'EstateCar', 'Motorbike' );
wmds_assert( 'make path carries the class', 'classes/Car/makes', $path_spy->seen[0] );
wmds_assert( 'category path carries the class', 'classes/Motorbike/categories', $path_spy->seen[1] );

wmds_result();
