<?php
/**
 * Tests for WMDS_Creole.
 *
 *     php tests/test-creole.php
 *
 * The main case is a real, unmodified vehicle description from the feed.
 * That is exactly where a Markdown parser falls apart.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/class-wmds-creole.php';

/* ------------------------------------------------------------------ *
 *  A real description from the feed
 * ------------------------------------------------------------------ */

wmds_section( 'A real vehicle description from the feed' );

// This is how the value sits in memory after json_decode(): the four
// backslashes between "etc." and "Further" are two CREOLE breaks, the two
// before "Driver" are one.
$real = "**The following modifications were made: 108 kW with intercooler, suspension, wheels and tyres, sports steering wheel, Recaro seats, LED headlights, navigation, sound system, camera, auxiliary heating, boot conversion with commercial registration etc.\\\\\\\\Further equipment:**\\\\Driver/passenger airbag, brake assist, body: 3-door, tinted glazing";

$html = WMDS_Creole::to_html( $real );

wmds_assert( 'no backslash survives', false, strpos( $html, '\\' ) );
wmds_assert_contains( 'bold opened', '<strong>', $html );
wmds_assert_contains( 'bold closed', '</strong>', $html );
wmds_assert_contains( 'text at the start', 'The following modifications were made', $html );
wmds_assert_contains( 'text at the end', 'tinted glazing', $html );
// The double break inside the bold run separates two paragraphs.
wmds_assert( 'two paragraphs', 2, substr_count( $html, '<p>' ) );
wmds_assert( 'one break in the second paragraph', 1, substr_count( $html, '<br>' ) );

/* ------------------------------------------------------------------ *
 *  Individual rules
 * ------------------------------------------------------------------ */

wmds_section( 'Line break: two backslashes' );

wmds_assert( 'one break', '<p>A<br>B</p>', WMDS_Creole::to_html( 'A\\\\B' ) );
wmds_assert( 'two breaks = paragraph', '<p>A</p><p>B</p>', WMDS_Creole::to_html( 'A\\\\\\\\B' ) );

wmds_section( 'Bold' );

wmds_assert( 'einfach', '<p><strong>fett</strong></p>', WMDS_Creole::to_html( '**fett**' ) );
wmds_assert(
	'spanning a line break',
	'<p><strong>eins<br>zwei</strong></p>',
	WMDS_Creole::to_html( '**eins\\\\zwei**' )
);
wmds_assert( 'unpaarig bleibt stehen', '<p>**halb</p>', WMDS_Creole::to_html( '**halb' ) );

wmds_section( 'Horizontal rule' );

wmds_assert( 'allein', '<hr>', WMDS_Creole::to_html( '----' ) );
wmds_assert( 'between text', '<p>A</p><hr><p>B</p>', WMDS_Creole::to_html( 'A\\\\----\\\\B' ) );

wmds_section( 'Bullet list' );

wmds_assert(
	'zwei Eintraege',
	'<ul><li>eins</li><li>zwei</li></ul>',
	WMDS_Creole::to_html( '* eins\\\\* zwei\\\\' )
);
wmds_assert(
	'paragraph before the list',
	'<p>Ausstattung:</p><ul><li>Navi</li></ul>',
	WMDS_Creole::to_html( 'Ausstattung:\\\\* Navi\\\\' )
);
// An asterisk with no following space is not a bullet.
wmds_assert( 'asterisk without a space', '<p>*not a bullet</p>', WMDS_Creole::to_html( '*not a bullet' ) );

/* ------------------------------------------------------------------ *
 *  Entities and safety
 * ------------------------------------------------------------------ */

wmds_section( 'HTML entities from the feed' );

// A real value from the feed: the API leaves &quot; inside the colour name.
wmds_assert(
	'manufacturerColorName',
	'BLACK SOLID "STONE" / Solid',
	WMDS_Creole::decode_field( 'BLACK SOLID &quot;STONE&quot; / Solid' )
);
wmds_assert_contains(
	'entity in the description is escaped correctly',
	'&quot;STONE&quot;',
	WMDS_Creole::to_html( 'Farbe &quot;STONE&quot;' )
);

wmds_section( 'HTML from the feed is displayed, not executed' );

$evil = WMDS_Creole::to_html( '<script>alert(1)</script>' );
wmds_assert( 'no open script tag', false, strpos( $evil, '<script>' ) );
wmds_assert_contains( 'maskiert dargestellt', '&lt;script&gt;', $evil );

$onclick = WMDS_Creole::to_html( '<a href="#" onclick="boom()">Klick</a>' );
wmds_assert( 'no anchor tag', false, strpos( $onclick, '<a href' ) );

wmds_section( 'Leere Eingaben' );

wmds_assert( 'leerer String', '', WMDS_Creole::to_html( '' ) );
wmds_assert( 'whitespace only', '', WMDS_Creole::to_html( "  \n  " ) );
wmds_assert( 'null', '', WMDS_Creole::to_html( null ) );

wmds_result();
