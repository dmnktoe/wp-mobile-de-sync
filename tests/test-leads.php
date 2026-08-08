<?php

require_once __DIR__ . '/bootstrap.php';

define( 'WMDS_CPT', 'fahrzeuge' );

function is_email( $email ) {
	return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
}

function add_shortcode_stub() {
}

require_once __DIR__ . '/../includes/class-wmds-str.php';
require_once __DIR__ . '/../includes/class-wmds-num.php';
require_once __DIR__ . '/../includes/class-wmds-date.php';
require_once __DIR__ . '/../includes/class-wmds-str.php';
require_once __DIR__ . '/../includes/class-wmds-mail.php';
require_once __DIR__ . '/../includes/class-wmds-leads.php';

/**
 * @param array $overrides
 * @return array
 */
function wmds_lead_data( array $overrides = array() ) {
	return array_merge(
		array(
			'name'    => 'Alex Beispiel',
			'email'   => 'alex@example.invalid',
			'phone'   => '+49 30 1234567',
			'message' => 'When could I come and see the car?',
			'consent' => '1',
		),
		$overrides
	);
}

wmds_section( 'What a submission has to carry' );

wmds_assert( 'a complete one passes', array(), WMDS_Leads::validate( wmds_lead_data() ) );

wmds_assert(
	'a name is required',
	true,
	isset( WMDS_Leads::validate( wmds_lead_data( array( 'name' => '   ' ) ) )['name'] )
);
wmds_assert(
	'an address is required',
	true,
	isset( WMDS_Leads::validate( wmds_lead_data( array( 'email' => '' ) ) )['email'] )
);
wmds_assert(
	'and it has to look like one',
	true,
	isset( WMDS_Leads::validate( wmds_lead_data( array( 'email' => 'alex at example' ) ) )['email'] )
);
wmds_assert(
	'a message is required',
	true,
	isset( WMDS_Leads::validate( wmds_lead_data( array( 'message' => '' ) ) )['message'] )
);
wmds_assert(
	'a message of two characters is not a message',
	true,
	isset( WMDS_Leads::validate( wmds_lead_data( array( 'message' => 'hi' ) ) )['message'] )
);
wmds_assert(
	'nor is one of six thousand',
	true,
	isset( WMDS_Leads::validate( wmds_lead_data( array( 'message' => str_repeat( 'a', 6000 ) ) ) )['message'] )
);

wmds_assert(
	'a phone number is optional',
	array(),
	WMDS_Leads::validate( wmds_lead_data( array( 'phone' => '' ) ) )
);
wmds_assert(
	'but prose in that field is not a number',
	true,
	isset( WMDS_Leads::validate( wmds_lead_data( array( 'phone' => 'call me' ) ) )['phone'] )
);
wmds_assert(
	'the usual punctuation is fine',
	array(),
	WMDS_Leads::validate( wmds_lead_data( array( 'phone' => '030 / 123-45 (67)' ) ) )
);

wmds_section( 'Consent, when the site asks for it' );

wmds_assert(
	'unticked is refused',
	true,
	isset( WMDS_Leads::validate( wmds_lead_data( array( 'consent' => '' ) ), true )['consent'] )
);
wmds_assert(
	'ticked is accepted',
	array(),
	WMDS_Leads::validate( wmds_lead_data(), true )
);
wmds_assert(
	'and nothing is asked when no notice is configured',
	array(),
	WMDS_Leads::validate( wmds_lead_data( array( 'consent' => '' ) ), false )
);

wmds_section( 'The checks a person never notices' );

$now = 1786000000;

wmds_assert(
	'a filled honeypot is a robot',
	true,
	WMDS_Leads::is_spam(
		array(
			'website' => 'https://example.invalid',
			'opened'  => $now - 60,
		),
		$now
	)
);

wmds_assert(
	'a form submitted within a second is a robot',
	true,
	WMDS_Leads::is_spam(
		array(
			'website' => '',
			'opened'  => $now - 1,
		),
		$now
	)
);

wmds_assert(
	'a form filled in for a minute is a person',
	false,
	WMDS_Leads::is_spam(
		array(
			'website' => '',
			'opened'  => $now - 60,
		),
		$now
	)
);

wmds_assert(
	'a missing timestamp is refused rather than trusted',
	true,
	WMDS_Leads::is_spam( array( 'website' => '' ), $now )
);

wmds_assert(
	'so is one from the future',
	true,
	WMDS_Leads::is_spam(
		array(
			'website' => '',
			'opened'  => $now + 600,
		),
		$now
	)
);

wmds_section( 'How often one visitor may send' );

wmds_assert( 'the first is fine', false, WMDS_Leads::over_limit( 0 ) );
wmds_assert( 'the fifth is still fine', false, WMDS_Leads::over_limit( 4 ) );
wmds_assert( 'the sixth is not', true, WMDS_Leads::over_limit( 5 ) );

wmds_section( 'The e-mail that goes out' );

$vehicle = array(
	'title' => 'Audi A4 Avant',
	'url'   => 'https://example.invalid/fahrzeuge/audi-a4/',
	'ad_id' => '427312402',
	'price' => '24,900 €',
);

$mail = WMDS_Leads::compose( wmds_lead_data(), $vehicle, 'Autohaus Example' );

wmds_assert( 'the subject names site and vehicle', '[Autohaus Example] Enquiry: Audi A4 Avant', $mail['subject'] );
wmds_assert_contains( 'the body carries the vehicle', 'Audi A4 Avant', $mail['body'] );
wmds_assert_contains( 'and the listing number', '427312402', $mail['body'] );
wmds_assert_contains( 'and a link back to it', 'https://example.invalid/fahrzeuge/audi-a4/', $mail['body'] );
wmds_assert_contains( 'and the price', '24,900 €', $mail['body'] );
wmds_assert_contains( 'and who is asking', 'alex@example.invalid', $mail['body'] );
wmds_assert_contains( 'and the phone number that was given', '+49 30 1234567', $mail['body'] );
wmds_assert_contains( 'and what they asked', 'When could I come and see the car?', $mail['body'] );

$without_phone = WMDS_Leads::compose( wmds_lead_data( array( 'phone' => '' ) ), $vehicle, 'Autohaus Example' );
wmds_assert( 'an empty phone line is left out', false, strpos( $without_phone['body'], 'Phone' ) );

$no_vehicle = WMDS_Leads::compose( wmds_lead_data(), array(), 'Autohaus Example' );
wmds_assert( 'an enquiry about nothing in particular still has a subject', '[Autohaus Example] Enquiry', $no_vehicle['subject'] );
wmds_assert_contains( 'and still carries the message', 'When could I come', $no_vehicle['body'] );

wmds_section( 'The confirmation the enquirer gets' );

$reply = WMDS_Leads::compose_confirmation( wmds_lead_data(), $vehicle, 'Autohaus Example' );

wmds_assert( 'it says who it is from', '[Autohaus Example] We have your enquiry', $reply['subject'] );
wmds_assert_contains( 'it greets them by name', 'Alex Beispiel', $reply['body'] );
wmds_assert_contains( 'it names the vehicle', 'Audi A4 Avant', $reply['body'] );
wmds_assert_contains( 'and repeats what they sent', 'When could I come and see the car?', $reply['body'] );

wmds_result();
