<?php
/**
 * The pieces 2.5.0 took out of larger classes or added: the log, the settings
 * table, the address of a removed vehicle, similar vehicles and the status of
 * an enquiry.
 */

$GLOBALS['wmds_mod_options']   = array();
$GLOBALS['wmds_mod_meta']      = array();
$GLOBALS['wmds_mod_posts']     = array();
$GLOBALS['wmds_mod_get_posts'] = array();

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['wmds_mod_options'] ) ? $GLOBALS['wmds_mod_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['wmds_mod_options'][ $key ] = $value;

	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['wmds_mod_options'][ $key ] );

	return true;
}

function current_time( $type ) {
	return '2026-11-18 15:00:00';
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	return isset( $GLOBALS['wmds_mod_meta'][ $post_id ][ $key ] ) ? $GLOBALS['wmds_mod_meta'][ $post_id ][ $key ] : '';
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['wmds_mod_meta'][ $post_id ][ $key ] = $value;

	return true;
}

function get_post_field( $field, $post_id ) {
	return isset( $GLOBALS['wmds_mod_posts'][ $post_id ][ $field ] ) ? $GLOBALS['wmds_mod_posts'][ $post_id ][ $field ] : '';
}

function get_the_title( $post_id = 0 ) {
	return isset( $GLOBALS['wmds_mod_posts'][ $post_id ]['post_title'] ) ? $GLOBALS['wmds_mod_posts'][ $post_id ]['post_title'] : '';
}

function get_posts( $args = array() ) {
	$next = array_shift( $GLOBALS['wmds_mod_get_posts'] );

	return $next ? $next : array();
}

function home_url( $path = '' ) {
	return 'https://example.invalid' . $path;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- this is the fake of the wrapper.
}

function get_post_type_archive_link( $type ) {
	return 'https://example.invalid/fahrzeuge/';
}

function sanitize_text_field( $text ) {
	return trim( (string) $text );
}

function sanitize_title( $title ) {
	return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' );
}

function is_email( $email ) {
	return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
}

require_once __DIR__ . '/bootstrap.php';

define( 'WMDS_CPT', 'fahrzeuge' );

foreach ( array( 'log', 'mail', 'settings', 'facets', 'removed', 'similar', 'leads' ) as $wmds_class ) {
	require_once dirname( __DIR__ ) . '/includes/class-wmds-' . $wmds_class . '.php';
}

wmds_section( 'The log keeps the newest first, and says which lines are problems' );

WMDS_Log::write( 'first' );
WMDS_Log::problem( 'second' );

$wmds_log = WMDS_Log::entries();
wmds_assert( 'two entries', 2, count( $wmds_log ) );
wmds_assert( 'newest first', 'second', $wmds_log[0]['message'] );
wmds_assert( 'the problem is marked as one', true, WMDS_Log::is_problem( $wmds_log[0] ) );
wmds_assert( 'the other is not', false, WMDS_Log::is_problem( $wmds_log[1] ) );
wmds_assert( 'a line for mails and downloads', '2026-11-18 15:00:00 second', WMDS_Log::line( $wmds_log[0] ) );
wmds_assert( 'a limit returns that many', 1, count( WMDS_Log::entries( 1 ) ) );

foreach ( range( 1, WMDS_Log::MAX + 10 ) as $wmds_n ) {
	WMDS_Log::write( 'line ' . $wmds_n );
}
wmds_assert( 'the log keeps a fixed number of lines', WMDS_Log::MAX, count( WMDS_Log::entries() ) );

WMDS_Log::clear();
wmds_assert( 'and can be cleared', array(), WMDS_Log::entries() );

wmds_section( 'Every setting is read and cleaned by its type' );

$wmds_values = WMDS_Settings::from_request(
	array( 'username', 'password', 'seller_id', 'enquiry_recipient', 'enquiry_store', 'enquiry_enabled', 'interval', 'slug', 'unknown' ),
	array(
		'wmds_username'          => '  dealer ',
		'wmds_password'          => '',
		'wmds_seller_id'         => '12 34-56',
		'wmds_enquiry_recipient' => 'a@example.invalid, nonsense, b@example.invalid',
		'wmds_enquiry_store'     => 'yes',
		'wmds_interval'          => 'every second',
		'wmds_slug'              => 'Unsere Autos',
		'wmds_unknown'           => 'x',
	)
);

wmds_assert( 'text is trimmed', 'dealer', $wmds_values['username'] );
wmds_assert( 'an empty password is left out, so the stored one stays', false, array_key_exists( 'password', $wmds_values ) );
wmds_assert( 'digits keep only digits', '123456', $wmds_values['seller_id'] );
wmds_assert( 'an address list keeps only addresses', 'a@example.invalid, b@example.invalid', $wmds_values['enquiry_recipient'] );
wmds_assert( 'a ticked box is yes', 'yes', $wmds_values['enquiry_store'] );
wmds_assert( 'a box that was not posted is no', 'no', $wmds_values['enquiry_enabled'] );
wmds_assert( 'a choice nobody offered falls back to the default', 'wmds_15min', $wmds_values['interval'] );
wmds_assert( 'a slug is made a slug', 'unsere-autos', $wmds_values['slug'] );
wmds_assert( 'an unknown setting is not taken', false, array_key_exists( 'unknown', $wmds_values ) );

wmds_assert( 'an unset setting reads as its default', 'yes', WMDS_Settings::get( 'similar_enabled' ) );
wmds_assert( 'and a bool as a bool', true, WMDS_Settings::flag( 'similar_enabled' ) );
wmds_assert( 'an explicit fallback still wins', 'other', WMDS_Settings::get( 'unknown', 'other' ) );

wmds_section( 'The address of a removed vehicle' );

wmds_assert( 'a vehicle page', 'bmw-116i-limousine', WMDS_Removed::requested_name( '/fahrzeuge/bmw-116i-limousine/', 'fahrzeuge' ) );
wmds_assert( 'with a query string', 'audi-a4', WMDS_Removed::requested_name( '/fahrzeuge/audi-a4/?utm_source=x', 'fahrzeuge' ) );
wmds_assert( 'the archive itself is not one', '', WMDS_Removed::requested_name( '/fahrzeuge/', 'fahrzeuge' ) );
wmds_assert( 'nor a page of it', '', WMDS_Removed::requested_name( '/fahrzeuge/page/2/', 'fahrzeuge' ) );
wmds_assert( 'nor anything outside the slug', '', WMDS_Removed::requested_name( '/kontakt/audi-a4/', 'fahrzeuge' ) );
wmds_assert( 'nor anything deeper', '', WMDS_Removed::requested_name( '/fahrzeuge/a/b/', 'fahrzeuge' ) );

$GLOBALS['wmds_mod_get_posts'] = array( array( 7 ) );
wmds_assert(
	'to the overview filtered by the make while there is one',
	'https://example.invalid/fahrzeuge/?wmds_gone=1&wmds_make=BMW',
	WMDS_Removed::target( 'BMW' )
);

$GLOBALS['wmds_mod_get_posts'] = array( array() );
wmds_assert(
	'and unfiltered when the make is gone too',
	'https://example.invalid/fahrzeuge/?wmds_gone=1',
	WMDS_Removed::target( 'LADA' )
);

wmds_section( 'Similar vehicles are the closest in price' );

wmds_assert(
	'closest first',
	array( 3, 1, 2 ),
	WMDS_Similar::closest(
		array(
			1 => 20000,
			2 => 30000,
			3 => 21000,
		),
		21500
	)
);
wmds_assert(
	'a vehicle without a price comes last',
	array( 2, 1 ),
	WMDS_Similar::closest(
		array(
			1 => 0,
			2 => 50000,
		),
		21500
	)
);

wmds_section( 'An enquiry is new until somebody answers it' );

$GLOBALS['wmds_mod_meta'][5]  = array(
	'_wmds_email'   => 'alex@example.invalid',
	'_wmds_name'    => 'Alex',
	'_wmds_vehicle' => 9,
);
$GLOBALS['wmds_mod_posts'][5] = array( 'post_content' => "Is it still there?\nWhen can I come?" );
$GLOBALS['wmds_mod_posts'][9] = array( 'post_title' => 'Ford Ranger' );

wmds_assert( 'new without a status', true, WMDS_Leads::is_new( 5 ) );
wmds_assert( 'labelled so', 'New', WMDS_Leads::status_label( 5 ) );

WMDS_Leads::set_status( 5, WMDS_Leads::ANSWERED );
wmds_assert( 'answered once marked', false, WMDS_Leads::is_new( 5 ) );

WMDS_Leads::set_status( 5, 'whatever' );
wmds_assert( 'anything else is new again', true, WMDS_Leads::is_new( 5 ) );

$wmds_reply = WMDS_Leads::reply_url( 5 );
wmds_assert_contains( 'the reply goes to the enquirer', 'mailto:alex%40example.invalid', $wmds_reply );
wmds_assert_contains( 'names the vehicle in the subject', rawurlencode( 'Your enquiry about Ford Ranger' ), $wmds_reply );
wmds_assert_contains( 'greets them by name', rawurlencode( 'Hello Alex,' ), $wmds_reply );
wmds_assert_contains( 'and quotes what they wrote', rawurlencode( "> Is it still there?\n> When can I come?" ), $wmds_reply );

$GLOBALS['wmds_mod_meta'][6] = array();
wmds_assert( 'no address, no reply link', '', WMDS_Leads::reply_url( 6 ) );

wmds_result();
