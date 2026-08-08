<?php
/**
 * The three plugins the sync meets on a dealer site: Contact Form 7 for the
 * forms, an SMTP plugin for what happens to the mail, FacetWP for filtering
 * that predates our own.
 *
 * What is tested here is the part that runs without any of them installed:
 * reading a submission, naming a mail tag, describing a mailer, building
 * facet sources. The hooks around it are three lines each.
 */

require_once __DIR__ . '/bootstrap.php';

define( 'WMDS_CPT', 'fahrzeuge' );

$GLOBALS['wmds_test_options'] = array();
$GLOBALS['wmds_test_meta']    = array();

function is_email( $email ) {
	return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
}

function get_option( $key, $default = false ) {
	return isset( $GLOBALS['wmds_test_options'][ $key ] ) ? $GLOBALS['wmds_test_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['wmds_test_options'][ $key ] = $value;

	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['wmds_test_options'][ $key ] );

	return true;
}

function get_post_type( $post_id = 0 ) {
	return ( 42 === (int) $post_id ) ? WMDS_CPT : 'post';
}

function get_the_title( $post_id = 0 ) {
	return 'Land Rover Defender 110';
}

function get_permalink( $post_id = 0 ) {
	return 'https://example.invalid/fahrzeuge/defender/';
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	return isset( $GLOBALS['wmds_test_meta'] ) ? $GLOBALS['wmds_test_meta'] : array();
}

function get_posts( $args = array() ) {
	return array();
}

require_once __DIR__ . '/../includes/class-wmds-str.php';
require_once __DIR__ . '/../includes/class-wmds-num.php';
require_once __DIR__ . '/../includes/class-wmds-date.php';
require_once __DIR__ . '/../includes/class-wmds-mail.php';
require_once __DIR__ . '/../includes/class-wmds-leads.php';
require_once __DIR__ . '/../includes/class-wmds-cf7.php';
require_once __DIR__ . '/../includes/class-wmds-requirements.php';
require_once __DIR__ . '/../includes/class-wmds-smtp.php';
require_once __DIR__ . '/../includes/class-wmds-vehicle.php';
require_once __DIR__ . '/../includes/class-wmds-facetwp.php';

wmds_section( 'A mail tag names a property, or is not ours' );

wmds_assert( 'a known property', 'price', WMDS_Cf7::property( '_wmds_vehicle_price' ) );
wmds_assert( 'the listing number', 'listing', WMDS_Cf7::property( '_wmds_vehicle_listing' ) );
wmds_assert( 'one we do not have', '', WMDS_Cf7::property( '_wmds_vehicle_colour' ) );
wmds_assert( 'somebody else\'s tag', '', WMDS_Cf7::property( 'your-name' ) );
wmds_assert( 'the prefix on its own', '', WMDS_Cf7::property( '_wmds_vehicle_' ) );

wmds_section( 'The vehicle a form is looking at' );

$empty = WMDS_Cf7::context( 0 );

wmds_assert( 'every property is there', array_keys( WMDS_Cf7::properties() ), array_keys( $empty ) );
wmds_assert( 'and every one is empty', array(), array_filter( $empty ) );
wmds_assert( 'a post that is not a vehicle stays empty', array(), array_filter( WMDS_Cf7::context( 7 ) ) );

$GLOBALS['wmds_test_meta'] = array(
	'vehicleListingID' => array( '427312402' ),
	'price'            => array( '42.999' ),
	'currency'         => array( 'EUR' ),
	'make'             => array( 'Land Rover' ),
	'model'            => array( 'Defender' ),
	'mileage'          => array( '129.000' ),
	'fuel'             => array( 'Diesel' ),
	'seller_email'     => array( 'verkauf@example.invalid' ),
);

$context = WMDS_Cf7::context( 42 );

wmds_assert( 'the title', 'Land Rover Defender 110', WMDS_Cf7::value( 'title', $context ) );
wmds_assert( 'the address', 'https://example.invalid/fahrzeuge/defender/', WMDS_Cf7::value( 'url', $context ) );
wmds_assert( 'the post ID', '42', WMDS_Cf7::value( 'id', $context ) );
wmds_assert( 'the listing number', '427312402', WMDS_Cf7::value( 'listing', $context ) );
wmds_assert( 'the price, as it is printed', '42.999 €', WMDS_Cf7::value( 'price', $context ) );
wmds_assert( 'the make', 'Land Rover', WMDS_Cf7::value( 'make', $context ) );
wmds_assert( 'the seller address', 'verkauf@example.invalid', WMDS_Cf7::value( 'seller_email', $context ) );
wmds_assert( 'what the feed does not carry', '', WMDS_Cf7::value( 'gearbox', $context ) );
wmds_assert( 'a property nobody asked for', '', WMDS_Cf7::value( 'nonsense', $context ) );

wmds_section( 'Reading a submission that was built without us' );

$posted = WMDS_Cf7::fields(
	array(
		'your-name'    => 'Alex Beispiel',
		'your-email'   => 'alex@example.invalid',
		'your-phone'   => '+49 30 1234567',
		'your-message' => "Is the car still available?\nWhen could I come by?",
		'_wpcf7'       => 91,
		'_wpcf7_unit'  => 'wpcf7-f91-p42',
	)
);

wmds_assert( 'the name', 'Alex Beispiel', $posted['name'] );
wmds_assert( 'the address', 'alex@example.invalid', $posted['email'] );
wmds_assert( 'the phone number', '+49 30 1234567', $posted['phone'] );
wmds_assert( 'the message keeps its lines', "Is the car still available?\nWhen could I come by?", $posted['message'] );

$named = WMDS_Cf7::fields(
	array(
		'ihr-name'       => 'Chris Beispiel',
		'ihre-email'     => 'chris@example.invalid',
		'telefon'        => '030 7654321',
		'ihre-nachricht' => 'A question about the Defender.',
	)
);

wmds_assert( 'a form in another language', 'Chris Beispiel', $named['name'] );
wmds_assert( 'its address', 'chris@example.invalid', $named['email'] );
wmds_assert( 'its phone number', '030 7654321', $named['phone'] );
wmds_assert( 'its message, found by substring', 'A question about the Defender.', $named['message'] );

$odd = WMDS_Cf7::fields(
	array(
		'wie-heissen-sie' => 'Kim Beispiel',
		'kontaktadresse'  => 'kim@example.invalid',
		'anliegen'        => 'Please call me back.',
	)
);

wmds_assert( 'an address is recognised wherever it sits', 'kim@example.invalid', $odd['email'] );
wmds_assert( 'nothing is invented for the name', '', $odd['name'] );

$lists = WMDS_Cf7::fields(
	array(
		'your-name'    => array( 'Alex', 'Beispiel' ),
		'your-email'   => 'alex@example.invalid',
		'your-message' => 'Hello',
	)
);

wmds_assert( 'a field that posts a list', 'Alex, Beispiel', $lists['name'] );

$empty_form = WMDS_Cf7::fields( array( '_wpcf7' => 91 ) );

wmds_assert( 'internals are not fields', '', $empty_form['name'] . $empty_form['email'] . $empty_form['message'] );

$long = WMDS_Cf7::fields(
	array(
		'your-name'    => str_repeat( 'a', 400 ),
		'your-message' => str_repeat( 'b', 9000 ),
	)
);

wmds_assert( 'a name is cut to what a name can be', WMDS_Leads::MAX_FIELD, WMDS_Str::length( $long['name'] ) );
wmds_assert( 'a message too', WMDS_Leads::MAX_MESSAGE, WMDS_Str::length( $long['message'] ) );

wmds_section( 'How mail leaves this site' );

wmds_assert( 'nothing installed', 'PHP mail()', WMDS_Smtp::describe( array(), '' ) );
wmds_assert( 'nothing installed is not sound', false, WMDS_Smtp::is_authenticated( array(), '' ) );

$plugin = array(
	'key'     => 'wp-mail-smtp',
	'name'    => 'WP Mail SMTP',
	'version' => '4.1.0',
);

wmds_assert( 'a plugin sending through SMTP', 'WP Mail SMTP 4.1.0, sending through smtp', WMDS_Smtp::describe( $plugin, 'smtp' ) );
wmds_assert( 'and that is sound', true, WMDS_Smtp::is_authenticated( $plugin, 'smtp' ) );

wmds_assert( 'a plugin left on PHP mail()', 'WP Mail SMTP 4.1.0, still set to PHP mail()', WMDS_Smtp::describe( $plugin, 'mail' ) );
wmds_assert( 'which is not sound', false, WMDS_Smtp::is_authenticated( $plugin, 'mail' ) );

wmds_assert( 'a plugin we cannot read', 'WP Mail SMTP 4.1.0', WMDS_Smtp::describe( $plugin, '' ) );
wmds_assert( 'and is not going to complain about', true, WMDS_Smtp::is_authenticated( $plugin, '' ) );

$GLOBALS['wmds_test_options']['wp_mail_smtp'] = array( 'mail' => array( 'mailer' => 'gmail' ) );

wmds_assert( 'the transport is read from the plugin', 'gmail', WMDS_Smtp::transport( 'wp-mail-smtp' ) );
wmds_assert( 'a plugin that keeps it elsewhere', '', WMDS_Smtp::transport( 'mailgun' ) );

wmds_assert( 'nothing is detected on a bare site', array(), WMDS_Smtp::detect() );

define( 'WPMS_PLUGIN_VER', '4.1.0' );

wmds_assert(
	'a plugin is recognised by its constant',
	array(
		'key'     => 'wp-mail-smtp',
		'name'    => 'WP Mail SMTP',
		'version' => '4.1.0',
	),
	WMDS_Smtp::detect()
);

wmds_section( 'A delivery failure is kept until one succeeds' );

wmds_assert( 'nothing on record', array(), WMDS_Smtp::failure() );

WMDS_Smtp::record( new WP_Error( 'wp_mail_failed', "SMTP connect() failed.\nCheck the host." ) );

$failure = WMDS_Smtp::failure();

wmds_assert( 'the message is kept, on one line', 'SMTP connect() failed. Check the host.', $failure['message'] );
wmds_assert( 'with the time it happened', true, $failure['time'] > 0 );

WMDS_Smtp::record( 'not an error at all' );

wmds_assert( 'anything that is not an error is ignored', 'SMTP connect() failed. Check the host.', WMDS_Smtp::failure()['message'] );

WMDS_Smtp::clear();

wmds_assert( 'a mail that goes out clears it', array(), WMDS_Smtp::failure() );

wmds_section( 'Every check the System tab renders has a shape' );

$rows     = WMDS_Smtp::checks();
$statuses = array( WMDS_Requirements::OK, WMDS_Requirements::WARN, WMDS_Requirements::FAIL );
$complete = true;

foreach ( $rows as $row ) {
	foreach ( array( 'key', 'group', 'label', 'value', 'status', 'hint' ) as $field ) {
		if ( ! isset( $row[ $field ] ) ) {
			$complete = false;
		}
	}
	if ( ! in_array( $row['status'], $statuses, true ) ) {
		$complete = false;
	}
}

wmds_assert( 'there is at least the delivery row', true, count( $rows ) >= 1 );
wmds_assert( 'and it is complete', true, $complete );

wmds_section( 'FacetWP reads the same meta keys' );

$definitions = array(
	'q'     => array(
		'type'  => 'search',
		'label' => 'Search',
	),
	'make'  => array(
		'type'  => 'select',
		'label' => 'Make',
		'meta'  => 'make',
	),
	'price' => array(
		'type'  => 'range',
		'label' => 'Price',
		'meta'  => 'price_raw',
	),
);

$choices = WMDS_Facetwp::choices( $definitions, array( 'owners' => 'Previous owners' ) );

wmds_assert( 'the search box is no source', false, isset( $choices['cf/'] ) );
wmds_assert( 'a select is', 'Make', $choices['cf/make'] );
wmds_assert( 'a range is', 'Price', $choices['cf/price_raw'] );
wmds_assert( 'and a field with no component of its own', 'Previous owners', $choices['cf/owners'] );
wmds_assert( 'three of them', 3, count( $choices ) );

$sorts = WMDS_Facetwp::sorts(
	array(
		'newest'    => array(
			'label' => 'Newest first',
			'meta'  => '',
			'order' => 'DESC',
			'type'  => '',
		),
		'price-asc' => array(
			'label' => 'Price, lowest first',
			'meta'  => 'price_raw',
			'order' => 'ASC',
			'type'  => 'NUMERIC',
		),
	)
);

wmds_assert( 'the key is prefixed and safe to use', true, isset( $sorts['wmds_price_asc'] ) );
wmds_assert( 'a numeric sort orders numerically', 'meta_value_num', $sorts['wmds_price_asc']['query_args']['orderby'] );
wmds_assert( 'on the right key', 'price_raw', $sorts['wmds_price_asc']['query_args']['meta_key'] );
wmds_assert( 'in the right direction', 'ASC', $sorts['wmds_price_asc']['query_args']['order'] );
wmds_assert( 'a sort without a meta key uses the date', 'date', $sorts['wmds_newest']['query_args']['orderby'] );
wmds_assert( 'and keeps its label', 'Newest first', $sorts['wmds_newest']['label'] );

wmds_result();
