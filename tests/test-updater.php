<?php

require_once __DIR__ . '/bootstrap.php';

if ( ! defined( 'WMDS_VERSION' ) ) {
	define( 'WMDS_VERSION', '1.0.0' );
}

if ( ! defined( 'WMDS_FILE' ) ) {
	define( 'WMDS_FILE', '/wp-content/plugins/wp-mobile-de-sync/wp-mobile-de-sync.php' );
}

if ( ! defined( 'WMDS_URL' ) ) {
	define( 'WMDS_URL', 'https://example.invalid/wp-content/plugins/wp-mobile-de-sync/' );
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return ltrim( substr( $file, strpos( $file, 'plugins/' ) + 8 ), '/' );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['wmds_test_transients'][ $key ] );

		return true;
	}
}

$GLOBALS['wmds_http_calls'] = 0;
$GLOBALS['wmds_http_body']  = '';
$GLOBALS['wmds_http_code']  = 200;

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		++$GLOBALS['wmds_http_calls'];

		return array(
			'code' => $GLOBALS['wmds_http_code'],
			'body' => $GLOBALS['wmds_http_body'],
		);
	}

	function wp_remote_retrieve_response_code( $response ) {
		return isset( $response['code'] ) ? $response['code'] : 0;
	}

	function wp_remote_retrieve_body( $response ) {
		return isset( $response['body'] ) ? $response['body'] : '';
	}
}

require_once __DIR__ . '/../includes/class-wmds-updater.php';

/**
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

wmds_section( 'A valid release is evaluated' );

$release = WMDS_Updater::parse( 200, release_json() );

wmds_assert( 'erkannt', true, is_array( $release ) );
wmds_assert( 'version without the v', '2.1.0', $release['version'] );
wmds_assert_contains( 'package is the asset', 'wp-mobile-de-sync.zip', $release['package'] );
wmds_assert( 'Datum', '2026-08-01T10:00:00Z', $release['date'] );
wmds_assert( 'Requires PHP read from the notes', '7.2', $release['requires_php'] );
wmds_assert( 'Tested up to read from the notes', '6.9', $release['tested'] );
wmds_assert( 'Requires at least falls back to the default', '5.8', $release['requires'] );

wmds_section( 'No attached ZIP means no update' );

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

wmds_section( 'Release notes become plain HTML' );

$html = WMDS_Updater::changelog( "## Behoben\n- Erster Punkt\n- Zweiter Punkt\n\nEin **wichtiger** Hinweis." );

wmds_assert_contains( 'heading', '<h3>Behoben</h3>', $html );
wmds_assert_contains( 'list', '<ul><li>Erster Punkt</li><li>Zweiter Punkt</li></ul>', $html );
wmds_assert_contains( 'paragraph', '<p>Ein <strong>wichtiger</strong> Hinweis.</p>', $html );

wmds_assert_contains( 'empty notes', 'No notes', WMDS_Updater::changelog( '' ) );
wmds_assert_contains( 'whitespace only', 'No notes', WMDS_Updater::changelog( "  \n " ) );

/**
 * @param string $version   Version an earlier check found on GitHub.
 * @param string $installed Version of the plugin it ran under.
 */
function wmds_seed_lookup( $version, $installed = '1.0.0' ) {
	set_transient(
		WMDS_Updater::CACHE,
		array(
			'installed' => $installed,
			'checked'   => 1,
			'error'     => '',
			'release'   => array(
				'version'      => $version,
				'package'      => 'https://example.invalid/wp-mobile-de-sync.zip',
				'date'         => '2026-07-31T12:22:05Z',
				'changelog'    => '',
				'requires'     => '5.8',
				'requires_php' => '7.0',
				'tested'       => '6.9',
			),
		),
		3600
	);
}

WMDS_Updater::init();

$basename = 'wp-mobile-de-sync/wp-mobile-de-sync.php';

wmds_section( 'What WordPress is handed' );

wmds_seed_lookup( '2.1.0' );
$update = WMDS_Updater::check( false, array(), $basename );

wmds_assert( 'version', '2.1.0', $update['version'] );
wmds_assert( 'new_version alongside it', '2.1.0', $update['new_version'] );
wmds_assert( 'the WordPress version it needs', '5.8', $update['requires'] );
wmds_assert( 'the PHP version it needs', '7.0', $update['requires_php'] );
wmds_assert( 'slug', 'wp-mobile-de-sync', $update['slug'] );
wmds_assert( 'plugin file', $basename, $update['plugin'] );
wmds_assert_contains( 'ZIP', 'wp-mobile-de-sync.zip', $update['package'] );
wmds_assert( 'a different plugin is left alone', false, WMDS_Updater::check( false, array(), 'other/other.php' ) );

wmds_section( 'A cached lookup cannot hide a new release' );

$GLOBALS['wmds_http_code'] = 200;
$GLOBALS['wmds_http_body'] = release_json( array( 'tag_name' => 'v2.0.0' ) );

wmds_seed_lookup( '1.0.2' );
$GLOBALS['wmds_http_calls'] = 0;
$update                     = WMDS_Updater::check( false, array(), $basename );

wmds_assert( 'a lookup of the hour is reused', 0, $GLOBALS['wmds_http_calls'] );
wmds_assert( 'and answers with what it holds', '1.0.2', $update['version'] );

$_GET['force-check']        = '1';
$GLOBALS['wmds_http_calls'] = 0;
$update                     = WMDS_Updater::check( false, array(), $basename );
unset( $_GET['force-check'] );

wmds_assert( '"Check again" gets past it', 1, $GLOBALS['wmds_http_calls'] );
wmds_assert( 'and finds the release published since', '2.0.0', $update['version'] );

wmds_seed_lookup( '1.0.2', '0.9.0' );
$GLOBALS['wmds_http_calls'] = 0;
WMDS_Updater::check( false, array(), $basename );

wmds_assert( 'a lookup from before an update is discarded', 1, $GLOBALS['wmds_http_calls'] );

set_transient( WMDS_Updater::CACHE, 'error', 900 );
$GLOBALS['wmds_http_calls'] = 0;
WMDS_Updater::check( false, array(), $basename );

wmds_assert( 'so is what the earlier version cached', 1, $GLOBALS['wmds_http_calls'] );

wmds_section( 'A check that found nothing says why' );

wmds_assert_contains( 'rate limit', 'rate limit', WMDS_Updater::reason( 403, '' ) );
wmds_assert_contains( 'other status codes', 'HTTP 500', WMDS_Updater::reason( 500, '' ) );
wmds_assert_contains( 'unreachable', 'could not be reached', WMDS_Updater::reason( 0, '', 'cURL error 6' ) );
wmds_assert_contains(
	'pre-release',
	'pre-release',
	WMDS_Updater::reason( 200, release_json( array( 'prerelease' => true ) ) )
);
wmds_assert_contains(
	'no ZIP attached',
	'no installable ZIP',
	WMDS_Updater::reason( 200, release_json( array( 'assets' => array() ) ) )
);

WMDS_Updater::flush();
$GLOBALS['wmds_http_code'] = 403;
$GLOBALS['wmds_http_body'] = '{"message":"API rate limit exceeded"}';

wmds_assert( 'no update is claimed', false, WMDS_Updater::check( false, array(), $basename ) );

$state = WMDS_Updater::state();

wmds_assert_contains( 'the reason is kept for the admin', 'rate limit', $state['error'] );
wmds_assert( 'and no version is claimed with it', '', $state['version'] );

WMDS_Updater::flush();

wmds_assert( 'flushing leaves nothing behind', '', WMDS_Updater::state()['error'] );

wmds_result();
