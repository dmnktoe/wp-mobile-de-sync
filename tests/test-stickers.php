<?php

require_once __DIR__ . '/bootstrap.php';

define( 'WMDS_DIR', dirname( __DIR__ ) . '/' );
define( 'WMDS_URL', 'https://example.invalid/wp-content/plugins/wp-mobile-de-sync/' );

require_once __DIR__ . '/../includes/class-wmds-stickers.php';

wmds_section( 'The feed key decides the colour' );

wmds_assert( 'green', 'green', WMDS_Stickers::normalize( 'EMISSIONSSTICKER_GREEN' ) );
wmds_assert( 'yellow', 'yellow', WMDS_Stickers::normalize( 'EMISSIONSSTICKER_YELLOW' ) );
wmds_assert( 'red', 'red', WMDS_Stickers::normalize( 'EMISSIONSSTICKER_RED' ) );
wmds_assert( 'lower case works too', 'green', WMDS_Stickers::normalize( 'green' ) );
wmds_assert( 'group 4 is the green one', 'green', WMDS_Stickers::normalize( '4' ) );
wmds_assert( 'group 3 is yellow', 'yellow', WMDS_Stickers::normalize( '3' ) );
wmds_assert( 'group 2 is red', 'red', WMDS_Stickers::normalize( '2' ) );
wmds_assert( 'group 1 has no sticker', '', WMDS_Stickers::normalize( '1' ) );
wmds_assert( 'none stays empty', '', WMDS_Stickers::normalize( 'EMISSIONSSTICKER_NONE' ) );
wmds_assert( 'nothing stored', '', WMDS_Stickers::normalize( '' ) );

wmds_section( 'A resolved label still finds its file' );

wmds_assert( 'a resolved label leads with the group', 'green', WMDS_Stickers::normalize( '4 (Gruen)' ) );
wmds_assert( 'and in English too', 'yellow', WMDS_Stickers::normalize( '3 (yellow)' ) );

wmds_section( 'URLs point at files that exist' );

foreach ( array( 'red', 'yellow', 'green' ) as $wmds_colour ) {
	$url = WMDS_Stickers::url( 'EMISSIONSSTICKER_' . strtoupper( $wmds_colour ) );

	wmds_assert_contains( $wmds_colour . ' has a URL', 'assets/emission-stickers/' . $wmds_colour . '.svg', $url );
	wmds_assert(
		$wmds_colour . '.svg is shipped',
		true,
		file_exists( WMDS_DIR . 'assets/emission-stickers/' . $wmds_colour . '.svg' )
	);
}

wmds_assert( 'no sticker, no URL', '', WMDS_Stickers::url( 'EMISSIONSSTICKER_NONE' ) );

wmds_section( 'Labels' );

wmds_assert( 'green is named', 'Emission sticker green', WMDS_Stickers::label( 'EMISSIONSSTICKER_GREEN' ) );
wmds_assert( 'nothing to name', '', WMDS_Stickers::label( '' ) );

wmds_result();
