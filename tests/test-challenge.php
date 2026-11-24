<?php
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- the answer is base64 by the ALTCHA format; this builds it as the browser does.
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- runs the browser's solver under node, when node is there.

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../includes/class-wmds-challenge.php';

$wmds_key = str_repeat( 'k', 64 );
$wmds_now = 1786000000;

/**
 * @param array $challenge
 * @param int   $number
 * @return string
 */
function wmds_answer( array $challenge, $number ) {
	return base64_encode(
		json_encode(
			array(
				'algorithm' => $challenge['algorithm'],
				'challenge' => $challenge['challenge'],
				'number'    => $number,
				'salt'      => $challenge['salt'],
				'signature' => $challenge['signature'],
			)
		)
	);
}

wmds_section( 'What the browser is handed' );

$wmds_challenge = WMDS_Challenge::create( $wmds_key, $wmds_now, 4242 );

wmds_assert( 'the algorithm ALTCHA names', 'SHA-256', $wmds_challenge['algorithm'] );
wmds_assert( 'the hash of salt and number', hash( 'sha256', $wmds_challenge['salt'] . '4242' ), $wmds_challenge['challenge'] );
wmds_assert( 'the range to search', WMDS_Challenge::MAX_NUMBER, $wmds_challenge['maxnumber'] );
wmds_assert( 'an expiry inside the salt', $wmds_now + WMDS_Challenge::LIFETIME, WMDS_Challenge::expires( $wmds_challenge['salt'] ) );
wmds_assert( 'and never the number itself', false, isset( $wmds_challenge['number'] ) );
$wmds_one = WMDS_Challenge::create( $wmds_key, $wmds_now );
$wmds_two = WMDS_Challenge::create( $wmds_key, $wmds_now );
wmds_assert( 'two challenges never share a salt', false, $wmds_one['salt'] === $wmds_two['salt'] );

wmds_section( 'What is accepted' );

wmds_assert( 'the right number', true, WMDS_Challenge::verify( wmds_answer( $wmds_challenge, 4242 ), $wmds_key, $wmds_now + 60 ) );
wmds_assert( 'until the last second', true, WMDS_Challenge::verify( wmds_answer( $wmds_challenge, 4242 ), $wmds_key, $wmds_now + WMDS_Challenge::LIFETIME ) );

wmds_section( 'What is not' );

wmds_assert( 'a wrong number', false, WMDS_Challenge::verify( wmds_answer( $wmds_challenge, 4243 ), $wmds_key, $wmds_now ) );
wmds_assert( 'the right number too late', false, WMDS_Challenge::verify( wmds_answer( $wmds_challenge, 4242 ), $wmds_key, $wmds_now + WMDS_Challenge::LIFETIME + 1 ) );
wmds_assert( 'a challenge signed with another key', false, WMDS_Challenge::verify( wmds_answer( $wmds_challenge, 4242 ), str_repeat( 'x', 64 ), $wmds_now ) );

$wmds_forged              = $wmds_challenge;
$wmds_forged['salt']      = 'cafe?expires=' . ( $wmds_now + 999999 );
$wmds_forged['challenge'] = hash( 'sha256', $wmds_forged['salt'] . '1' );
wmds_assert( 'a puzzle the visitor made up', false, WMDS_Challenge::verify( wmds_answer( $wmds_forged, 1 ), $wmds_key, $wmds_now ) );

$wmds_later         = $wmds_challenge;
$wmds_later['salt'] = str_replace( 'expires=' . ( $wmds_now + WMDS_Challenge::LIFETIME ), 'expires=' . ( $wmds_now + 999999 ), $wmds_challenge['salt'] );
wmds_assert( 'an expiry moved into the future', false, WMDS_Challenge::verify( wmds_answer( $wmds_later, 4242 ), $wmds_key, $wmds_now + WMDS_Challenge::LIFETIME + 1 ) );

wmds_assert( 'an empty field', false, WMDS_Challenge::verify( '', $wmds_key, $wmds_now ) );
wmds_assert( 'something that is not base64', false, WMDS_Challenge::verify( '%%%', $wmds_key, $wmds_now ) );
wmds_assert( 'base64 of something that is not JSON', false, WMDS_Challenge::verify( base64_encode( 'hello' ), $wmds_key, $wmds_now ) );
wmds_assert(
	'a number written as text',
	null,
	WMDS_Challenge::decode( base64_encode( json_encode( array_merge( $wmds_challenge, array( 'number' => '4242' ) ) ) ) )
);
wmds_assert( 'a salt without an expiry', 0, WMDS_Challenge::expires( 'cafe' ) );

wmds_section( 'The browser can solve it' );

$wmds_node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $wmds_node ) {
	echo "  skip  node is not installed; the solver in assets/enquiry.js is not run\n";
} else {
	$wmds_random = WMDS_Challenge::create( $wmds_key, $wmds_now );
	$wmds_script = 'var s = require(' . json_encode( dirname( __DIR__ ) . '/assets/enquiry.js' ) . ');'
		. 'var c = ' . json_encode( $wmds_random ) . ';'
		. 'global.btoa = function (t) { return Buffer.from(t, "binary").toString("base64"); };'
		. 's.solve(c, function (n) { process.stdout.write(s.payload(c, n)); });';

	$wmds_posted = (string) shell_exec( escapeshellarg( $wmds_node ) . ' -e ' . escapeshellarg( $wmds_script ) );

	wmds_assert( 'its SHA-256 is SHA-256', true, 0 === strpos( (string) shell_exec( escapeshellarg( $wmds_node ) . ' -e ' . escapeshellarg( 'process.stdout.write(require(' . json_encode( dirname( __DIR__ ) . '/assets/enquiry.js' ) . ').sha256("abc"))' ) ), hash( 'sha256', 'abc' ) ) );
	wmds_assert( 'and its answer is accepted', true, WMDS_Challenge::verify( $wmds_posted, $wmds_key, $wmds_now + 10 ) );
}

wmds_result();
