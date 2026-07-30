<?php
/**
 * Tests for WMDS_Updater.
 *
 *     php tests/test-updater.php
 *
 * What is checked is the evaluation of the GitHub response - when an update
 * is offered and when it deliberately is not. A wrongly served package
 * deactivates the plugin on a live site.
 */

require_once __DIR__ . '/bootstrap.php';

if ( ! defined( 'WMDS_VERSION' ) ) {
	define( 'WMDS_VERSION', '1.0.0' );
}

require_once __DIR__ . '/../includes/class-wmds-updater.php';

/**
 * Builds a GitHub release API response.
 *
 * @param array $overrides
 * @return string
 */
function release_json( array $overrides = array() ) {
	$data = array_merge(
		array(
			'tag_name'     => 'v2.1.0',
			'draft'        => false,
			'prerelease'   => false,
			'published_at' => '2026-08-01T10:00:00Z',
			'body'         => "## Neu\n- Etwas\n\nRequires PHP: 7.2\nTested up to: 6.9\n",
			'assets'       => array(
				array(
					'name'                 => 'wp-mobile-de-sync.zip',
					'browser_download_url' => 'https://github.com/x/y/releases/download/v2.1.0/wp-mobile-de-sync.zip',
				),
			),
		),
		$overrides
	);

	return json_encode( $data );
}

// --------------------------------------------------------------------
// Gueltiges Release
// --------------------------------------------------------------------

wmds_section( 'A valid release is evaluated' );

$release = WMDS_Updater::parse( 200, release_json() );

wmds_assert( 'erkannt', true, is_array( $release ) );
wmds_assert( 'version without the v', '2.1.0', $release['version'] );
wmds_assert_contains( 'package is the asset', 'wp-mobile-de-sync.zip', $release['package'] );
wmds_assert( 'Datum', '2026-08-01T10:00:00Z', $release['date'] );
wmds_assert( 'Requires PHP read from the notes', '7.2', $release['requires_php'] );
wmds_assert( 'Tested up to read from the notes', '6.9', $release['tested'] );
wmds_assert( 'Requires at least falls back to the default', '5.8', $release['requires'] );

// --------------------------------------------------------------------
// Cases where no update is offered on purpose
// --------------------------------------------------------------------

wmds_section( 'No attached ZIP means no update' );

// GitHub's generated source download has owner-repo-commithash as its top
// folder. WordPress would unpack the plugin into a new directory and
// deactivate it. Better no update than a broken one.
wmds_assert( 'no assets', false, WMDS_Updater::parse( 200, release_json( array( 'assets' => array() ) ) ) );
wmds_assert(
	'fremdes Asset',
	false,
	WMDS_Updater::parse(
		200,
		release_json(
			array(
				'assets' => array(
					array(
						'name'                 => 'notizen.pdf',
						'browser_download_url' => 'https://example.invalid/x.pdf',
					),
				),
			)
		)
	)
);

wmds_section( 'Drafts and pre-releases are skipped' );

wmds_assert( 'draft', false, WMDS_Updater::parse( 200, release_json( array( 'draft' => true ) ) ) );
wmds_assert( 'prerelease', false, WMDS_Updater::parse( 200, release_json( array( 'prerelease' => true ) ) ) );

wmds_section( 'Unbrauchbare Antworten' );

wmds_assert( 'HTTP 404', false, WMDS_Updater::parse( 404, '{}' ) );
wmds_assert( 'Rate Limit', false, WMDS_Updater::parse( 403, '{"message":"API rate limit exceeded"}' ) );
wmds_assert( 'Netzwerkfehler', false, WMDS_Updater::parse( 0, '' ) );
wmds_assert( 'not JSON', false, WMDS_Updater::parse( 200, '<html>' ) );
wmds_assert( 'no tag_name', false, WMDS_Updater::parse( 200, '{"assets":[]}' ) );
wmds_assert(
	'tag without a version number',
	false,
	WMDS_Updater::parse( 200, release_json( array( 'tag_name' => 'nightly' ) ) )
);

wmds_section( 'Tag spellings' );

foreach ( array(
	'v2.1.0' => '2.1.0',
	'V2.1.0' => '2.1.0',
	'2.1.0'  => '2.1.0',
	'v3'     => '3',
) as $tag => $erwartet ) {
	$r = WMDS_Updater::parse( 200, release_json( array( 'tag_name' => $tag ) ) );
	wmds_assert( $tag, $erwartet, $r['version'] );
}

// --------------------------------------------------------------------
// Aenderungsprotokoll
// --------------------------------------------------------------------

wmds_section( 'Release notes become plain HTML' );

$html = WMDS_Updater::changelog( "## Behoben\n- Erster Punkt\n- Zweiter Punkt\n\nEin **wichtiger** Hinweis." );

wmds_assert_contains( 'heading', '<h3>Behoben</h3>', $html );
wmds_assert_contains( 'list', '<ul><li>Erster Punkt</li><li>Zweiter Punkt</li></ul>', $html );
wmds_assert_contains( 'paragraph', '<p>Ein <strong>wichtiger</strong> Hinweis.</p>', $html );

wmds_assert_contains( 'empty notes', 'No notes', WMDS_Updater::changelog( '' ) );
wmds_assert_contains( 'whitespace only', 'No notes', WMDS_Updater::changelog( "  \n " ) );

wmds_result();
