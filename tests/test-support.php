<?php

require_once __DIR__ . '/bootstrap.php';

$GLOBALS['wmds_test_options'] = array( 'admin_email' => 'admin@example.invalid' );
$GLOBALS['wmds_test_mail']    = array();

function is_email( $email ) {
	return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
}

function get_option( $key, $default = false ) {
	return isset( $GLOBALS['wmds_test_options'][ $key ] ) ? $GLOBALS['wmds_test_options'][ $key ] : $default;
}

function wp_mail( $to, $subject, $body, $headers = array() ) {
	$GLOBALS['wmds_test_mail'][] = array(
		'to'      => $to,
		'subject' => $subject,
		'body'    => $body,
		'headers' => $headers,
	);

	return true;
}

require_once __DIR__ . '/../includes/class-wmds-str.php';
require_once __DIR__ . '/../includes/class-wmds-num.php';
require_once __DIR__ . '/../includes/class-wmds-date.php';
require_once __DIR__ . '/../includes/class-wmds-mail.php';

wmds_section( 'Strings: measuring and cutting' );

wmds_assert( 'length counts characters, not bytes', 4, WMDS_Str::length( 'Öl 5W' ) - 1 );
wmds_assert( 'an umlaut is one character', 5, WMDS_Str::length( 'Größe' ) );
wmds_assert( 'nothing has no length', 0, WMDS_Str::length( null ) );
wmds_assert( 'an array has no length either', 0, WMDS_Str::length( array( 'a' ) ) );

wmds_assert( 'a short value is not cut', 'Audi', WMDS_Str::cut( 'Audi', 10 ) );
wmds_assert( 'a long one is', 'Aud', WMDS_Str::cut( 'Audi', 3 ) );
wmds_assert( 'a cut never splits a character', 'Grö', WMDS_Str::cut( 'Größe', 3 ) );
wmds_assert( 'a limit of zero means no limit', 'Audi', WMDS_Str::cut( 'Audi', 0 ) );

wmds_section( 'Strings: collapsing and scrubbing' );

wmds_assert( 'runs of whitespace become one space', 'a b c', WMDS_Str::collapse( "a  \n b\tc" ) );
wmds_assert( 'the ends are trimmed', 'a b', WMDS_Str::collapse( '  a b  ' ) );

wmds_assert( 'angle brackets come out', 'scriptalert(1)/script', WMDS_Str::scrub( '<script>alert(1)</script>' ) );
wmds_assert( 'control characters come out', 'ab', WMDS_Str::scrub( "a\x00\x1Fb" ) );
wmds_assert( 'a limit is applied after collapsing', 'a b', WMDS_Str::scrub( '  a   b   c', 3 ) );
wmds_assert( 'an array scrubs to nothing', '', WMDS_Str::scrub( array( 'a' ) ) );
wmds_assert( 'so does null', '', WMDS_Str::scrub( null ) );

wmds_section( 'Strings: shortening for a human' );

wmds_assert( 'a short text is left alone', 'Two words', WMDS_Str::shorten( 'Two words', 300 ) );
wmds_assert( 'a long one is cut at a word', 'one two…', WMDS_Str::shorten( 'one two three four', 10 ) );
wmds_assert( 'trailing punctuation goes with it', 'one two…', WMDS_Str::shorten( 'one two, three four', 11 ) );
wmds_assert(
	'a word longer than the limit is cut mid-word rather than to nothing',
	'donaudampf…',
	WMDS_Str::shorten( 'donaudampfschifffahrt', 10 )
);

wmds_section( 'Strings: what may go in a mail header' );

wmds_assert( 'a newline cannot smuggle a second header', 'Alex Bcc: victim@example.invalid', WMDS_Str::one_line( "Alex\r\nBcc: victim@example.invalid" ) );
wmds_assert( 'angle brackets cannot fake an address', 'Alex evil@example.invalid', WMDS_Str::one_line( 'Alex <evil@example.invalid>' ) );

wmds_section( 'Numbers: what a person typed' );

wmds_assert( 'a plain number', 5000.0, WMDS_Num::from_input( '5000' ) );
wmds_assert( 'a full stop is a thousands separator', 12500.0, WMDS_Num::from_input( '12.500' ) );
wmds_assert( 'so is a space', 12500.0, WMDS_Num::from_input( '12 500' ) );
wmds_assert( 'a comma is the decimal mark', 12.5, WMDS_Num::from_input( '12,5' ) );
wmds_assert( 'prose is not a number', null, WMDS_Num::from_input( 'cheap' ) );
wmds_assert( 'nor is nothing', null, WMDS_Num::from_input( '' ) );
wmds_assert( 'nor is an array', null, WMDS_Num::from_input( array( '1' ) ) );

wmds_section( 'Numbers: what the feed stated' );

wmds_assert( 'a whole number stays an integer', 118, WMDS_Num::from_feed( '118' ) );
wmds_assert(
	'a full stop is the decimal mark here, not a thousands separator',
	4.9,
	WMDS_Num::from_feed( '4.9' )
);
wmds_assert( 'a comma is read as one too', 4.9, WMDS_Num::from_feed( '4,9' ) );
wmds_assert( 'a trailing zero still yields an integer', 5, WMDS_Num::from_feed( '5.0' ) );
wmds_assert( 'prose is not a number', null, WMDS_Num::from_feed( 'unknown' ) );

wmds_assert(
	'the two readings disagree about 12.500, which is why there are two',
	true,
	WMDS_Num::from_input( '12.500' ) !== WMDS_Num::from_feed( '12.500' )
);

wmds_section( 'Dates' );

wmds_assert( 'the feed writes a month without a separator', '2027-05-01', WMDS_Date::iso_month( '202705' ) );
wmds_assert( 'or with one', '2019-06-01', WMDS_Date::iso_month( '2019-06' ) );
wmds_assert( 'a full date keeps its month', '2019-06-01', WMDS_Date::iso_month( '2019-06-14' ) );
wmds_assert( 'a German month is read too', '2019-06-01', WMDS_Date::iso_month( '06.2019' ) );
wmds_assert( 'anything else is no month', '', WMDS_Date::iso_month( 'soon' ) );
wmds_assert( 'and neither is nothing', '', WMDS_Date::iso_month( '' ) );

wmds_assert( 'a month reads back as MM.YYYY', '06.2019', WMDS_Date::month_year( '2019-06-01' ) );
wmds_assert( 'what is not a month is left alone', 'soon', WMDS_Date::month_year( 'soon' ) );

wmds_assert( 'a day reads back as DD.MM.YYYY', '14.06.2019', WMDS_Date::day( '2019-06-14' ) );
wmds_assert( 'what is not a day is left alone', 'later', WMDS_Date::day( 'later' ) );

wmds_assert( 'the year is a number', 2019, WMDS_Date::year( '2019-06-14' ) );
wmds_assert( 'no year, no number', '', WMDS_Date::year( 'nope' ) );

wmds_section( 'Mail: who it goes to' );

wmds_assert(
	'one configured address',
	array( 'sales@example.invalid' ),
	WMDS_Mail::addresses( 'sales@example.invalid' )
);
wmds_assert(
	'several, separated by commas and sloppy spacing',
	array( 'a@example.invalid', 'b@example.invalid' ),
	WMDS_Mail::addresses( ' a@example.invalid ,b@example.invalid ' )
);
wmds_assert(
	'anything that is not an address is dropped',
	array( 'a@example.invalid' ),
	WMDS_Mail::addresses( 'a@example.invalid, not-an-address' )
);
wmds_assert(
	'the same address twice is one recipient',
	array( 'a@example.invalid' ),
	WMDS_Mail::addresses( 'a@example.invalid, a@example.invalid' )
);
wmds_assert(
	'an empty field falls back to the administrator, never to nobody',
	array( 'admin@example.invalid' ),
	WMDS_Mail::addresses( '' )
);
wmds_assert(
	'so does a field with nothing usable in it',
	array( 'admin@example.invalid' ),
	WMDS_Mail::addresses( 'nonsense' )
);
wmds_assert(
	'an extra address is added after the configured ones',
	array( 'a@example.invalid', 'seller@example.invalid' ),
	WMDS_Mail::addresses( 'a@example.invalid', array( 'seller@example.invalid' ) )
);
wmds_assert(
	'an extra address already on the list is not added twice',
	array( 'a@example.invalid' ),
	WMDS_Mail::addresses( 'a@example.invalid', array( 'a@example.invalid' ) )
);

wmds_section( 'Mail: how it goes out' );

$GLOBALS['wmds_test_mail'] = array();

wmds_assert( 'a mail with a subject is sent', true, WMDS_Mail::send( array( 'a@example.invalid' ), 'Subject', 'Body' ) );
wmds_assert( 'it is plain text', 'Content-Type: text/plain; charset=UTF-8', $GLOBALS['wmds_test_mail'][0]['headers'][0] );

WMDS_Mail::send( array( 'a@example.invalid' ), 'Subject', 'Body', array( 'Reply-To: x@example.invalid' ) );
wmds_assert( 'extra headers survive', 'Reply-To: x@example.invalid', $GLOBALS['wmds_test_mail'][1]['headers'][1] );

wmds_assert( 'a mail to nobody is not sent', false, WMDS_Mail::send( array(), 'Subject', 'Body' ) );
wmds_assert( 'a mail without a subject is not sent', false, WMDS_Mail::send( array( 'a@example.invalid' ), '', 'Body' ) );
wmds_assert( 'and neither reached wp_mail', 2, count( $GLOBALS['wmds_test_mail'] ) );

wmds_assert(
	'a reply-to carries the name',
	'Reply-To: Alex <alex@example.invalid>',
	WMDS_Mail::reply_to( 'Alex', 'alex@example.invalid' )
);
wmds_assert(
	'without a name it is the address alone',
	'Reply-To: alex@example.invalid',
	WMDS_Mail::reply_to( '', 'alex@example.invalid' )
);
wmds_assert(
	'a name cannot inject a header',
	'Reply-To: Alex Bcc: victim@example.invalid <alex@example.invalid>',
	WMDS_Mail::reply_to( "Alex\nBcc: victim@example.invalid", 'alex@example.invalid' )
);

wmds_result();
