<?php

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../includes/class-wmds-spam.php';

$wmds_words = WMDS_Spam::builtin_words();

/**
 * @param array $overrides
 * @return array
 */
function wmds_spam_data( array $overrides = array() ) {
	return array_merge(
		array(
			'name'    => 'Alex Beispiel',
			'email'   => 'alex@example.invalid',
			'phone'   => '',
			'message' => 'Guten Tag, Wagen unfallfrei? Besichtigung am Samstag möglich?',
		),
		$overrides
	);
}

wmds_section( 'A genuine enquiry gets through' );

wmds_assert( 'a question about the car', '', WMDS_Spam::reason( wmds_spam_data(), array(), $wmds_words ) );
wmds_assert(
	'with a link to the car it is about',
	'',
	WMDS_Spam::reason( wmds_spam_data( array( 'message' => 'Is https://example.invalid/fahrzeuge/bmw-520d/ still available?' ) ), array(), $wmds_words )
);
wmds_assert(
	'with two links, the car and the trade-in',
	'',
	WMDS_Spam::reason( wmds_spam_data( array( 'message' => 'This one https://a.invalid/1 against mine www.b.invalid/2 ?' ) ), array(), $wmds_words )
);
wmds_assert(
	'deregistering a car is not unsubscribing',
	'',
	WMDS_Spam::reason( wmds_spam_data( array( 'message' => 'Fahrzeug abmelden, bitte, Überführung möglich?' ) ), array(), $wmds_words )
);
wmds_assert(
	'and Seoul is not SEO',
	'',
	WMDS_Spam::reason( wmds_spam_data( array( 'message' => 'I am moving to Seoul, can you ship the car?' ) ), array(), $wmds_words )
);

wmds_section( 'Advertising does not' );

$wmds_pitch = "Hi team,\n\nIf I were a real customer, would you call me back in the next 5 minutes?\nSee it in action: open https://leads.invalid\n\nNot a fit? Unsubscribe here: https://leads.invalid/unsubscribe.aspx";

wmds_assert(
	'an unsubscribe line gives it away',
	'Contains "unsubscribe"',
	WMDS_Spam::reason( wmds_spam_data( array( 'message' => $wmds_pitch ) ), array(), $wmds_words )
);
wmds_assert(
	'in any letter case',
	'Contains "seo"',
	WMDS_Spam::reason( wmds_spam_data( array( 'message' => 'We offer SEO for car dealers.' ) ), array(), $wmds_words )
);
wmds_assert(
	'and in the name field too',
	'Contains "backlinks"',
	WMDS_Spam::reason( wmds_spam_data( array( 'name' => 'Cheap Backlinks' ) ), array(), $wmds_words )
);
wmds_assert(
	'a word of the dealer\'s own counts as well',
	'Contains "lead gen"',
	WMDS_Spam::reason( wmds_spam_data( array( 'message' => 'Kim from Acme Lead Gen here.' ) ), array(), array_merge( $wmds_words, array( 'lead gen' ) ) )
);
wmds_assert(
	'three links are three too many',
	'Contains 3 links',
	WMDS_Spam::reason( wmds_spam_data( array( 'message' => 'https://a.invalid https://b.invalid www.c.invalid' ) ), array(), $wmds_words )
);

wmds_section( 'The blocklist' );

wmds_assert(
	'an address on it is caught',
	'Sender is on the blocklist (alex@example.invalid)',
	WMDS_Spam::reason( wmds_spam_data(), array( 'alex@example.invalid' ), $wmds_words )
);
wmds_assert( 'in any letter case', 'alex@example.invalid', WMDS_Spam::blocked( 'Alex@Example.INVALID', array( 'alex@example.invalid' ) ) );
wmds_assert( 'a domain covers every address there', '@example.invalid', WMDS_Spam::blocked( 'kim@example.invalid', array( '@example.invalid' ) ) );
wmds_assert( 'with or without the @', 'example.invalid', WMDS_Spam::blocked( 'kim@example.invalid', array( 'example.invalid' ) ) );
wmds_assert( 'and its subdomains', '@example.invalid', WMDS_Spam::blocked( 'kim@mail.example.invalid', array( '@example.invalid' ) ) );
wmds_assert( 'but not a domain that merely ends the same', '', WMDS_Spam::blocked( 'kim@notexample.invalid', array( '@example.invalid' ) ) );
wmds_assert( 'an address blocks only itself', '', WMDS_Spam::blocked( 'sam@example.invalid', array( 'alex@example.invalid' ) ) );
wmds_assert( 'and no address is never blocked', '', WMDS_Spam::blocked( '', array( '@example.invalid' ) ) );

wmds_section( 'Marking as spam teaches the list' );

wmds_assert( 'a company is blocked by its domain', array( '@leadseller.invalid' ), WMDS_Spam::learn( array(), 'hello@leadseller.invalid' ) );
wmds_assert( 'somebody at a shared provider only by the address', array( 'spammer@gmail.com' ), WMDS_Spam::learn( array(), 'Spammer@gmail.com' ) );
wmds_assert( 'an address already covered is not added twice', array( '@leadseller.invalid' ), WMDS_Spam::learn( array( '@leadseller.invalid' ), 'other@leadseller.invalid' ) );
wmds_assert( 'nor is an address that is none', array(), WMDS_Spam::learn( array(), 'nobody' ) );

$wmds_full = array();
for ( $wmds_i = 0; $wmds_i < WMDS_Spam::MAX_BLOCKED; $wmds_i++ ) {
	$wmds_full[] = '@d' . $wmds_i . '.invalid';
}
$wmds_full = WMDS_Spam::learn( $wmds_full, 'x@new.invalid' );
wmds_assert( 'a full list stays at its size', WMDS_Spam::MAX_BLOCKED, count( $wmds_full ) );
wmds_assert( 'by dropping the oldest entry', array( '@d1.invalid', '@new.invalid' ), array( $wmds_full[0], end( $wmds_full ) ) );

wmds_section( 'Not spam undoes it' );

wmds_assert(
	'the entry that caught the sender comes off',
	array( 'other@gmail.com' ),
	WMDS_Spam::forget( array( '@example.invalid', 'other@gmail.com' ), 'alex@example.invalid' )
);
wmds_assert(
	'and so does the address itself',
	array(),
	WMDS_Spam::forget( array( 'alex@example.invalid' ), 'ALEX@example.invalid' )
);

wmds_section( 'A list as typed' );

wmds_assert(
	'one entry a line, commas too, trimmed, lower case, once',
	array( 'seo', 'lead gen', 'crypto' ),
	WMDS_Spam::lines( "SEO\r\n  lead gen , crypto\n\nseo\n" )
);
wmds_assert( 'an empty text is an empty list', array(), WMDS_Spam::lines( '' ) );

wmds_result();
