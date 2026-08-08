<?php

require_once __DIR__ . '/bootstrap.php';

define( 'WMDS_DIR', dirname( __DIR__ ) . '/' );

require_once __DIR__ . '/../includes/class-wmds-autoloader.php';

/**
 * The search itself, over the directories the loader really has.
 *
 * Reading the list rather than repeating it is the point: a directory added
 * to the loader and forgotten here would leave this test passing on a tree it
 * no longer describes.
 *
 * @param string $class
 * @return string The file the autoloader would load, '' when it loads none.
 */
function wmds_autoload_target( $class ) {
	$file = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';

	foreach ( WMDS_Autoloader::dirs() as $dir ) {
		$path = WMDS_DIR . 'includes/' . $dir . $file;
		if ( is_readable( $path ) ) {
			return $path;
		}
	}

	return '';
}

wmds_section( 'Every class file in the tree is reachable by its name' );

$files = array();

foreach ( WMDS_Autoloader::dirs() as $wmds_dir ) {
	$files = array_merge( $files, glob( WMDS_DIR . 'includes/' . $wmds_dir . 'class-wmds-*.php' ) );
}

wmds_assert( 'the loader looks in every directory that holds classes', 3, count( WMDS_Autoloader::dirs() ) );

wmds_assert( 'there are class files to check', true, count( $files ) > 20 );

$unreachable = array();

foreach ( $files as $file ) {
	$source = (string) file_get_contents( $file );

	if ( ! preg_match( '/^class (WMDS_\w+)/m', $source, $m ) ) {
		continue;
	}

	if ( wmds_autoload_target( $m[1] ) !== $file ) {
		$unreachable[] = $m[1] . ' (' . basename( $file ) . ')';
	}
}

wmds_assert( 'every class is found under the name it declares', array(), $unreachable );

wmds_section( 'And nothing else is' );

wmds_assert( 'a class from another plugin is ignored', '', wmds_autoload_target( 'WP_Query' ) );
wmds_assert( 'a WMDS class that does not exist resolves to nothing', '', wmds_autoload_target( 'WMDS_Does_Not_Exist' ) );

wmds_assert(
	'asking for a class that does not exist does not fatal',
	null,
	WMDS_Autoloader::load( 'WMDS_Does_Not_Exist' )
);
wmds_assert(
	'and neither does asking for somebody else\'s',
	null,
	WMDS_Autoloader::load( 'Some_Other_Plugin_Thing' )
);

wmds_section( 'A file that acts when it loads is loaded on purpose' );

/*
 * class-wmds-compat.php holds functions rather than a class, and the logo and
 * sticker files register a shortcode at file level. Nothing would mention
 * those classes on a page that only uses the shortcode, so leaving them to
 * the autoloader would mean the shortcode is never registered. The plugin
 * header requires them outright, and this is what says so if somebody tidies
 * that away.
 */
$header = (string) file_get_contents( WMDS_DIR . 'wp-mobile-de-sync.php' );

foreach ( array( 'compat', 'logos', 'stickers', 'cli' ) as $eager ) {
	wmds_assert_contains(
		sprintf( 'class-wmds-%s.php is required outright', $eager ),
		'includes/class-wmds-' . $eager . ".php'",
		$header
	);
}

foreach ( array( 'logos', 'stickers' ) as $eager ) {
	wmds_assert_contains(
		sprintf( 'because class-wmds-%s.php registers a shortcode when it loads', $eager ),
		'add_shortcode(',
		(string) file_get_contents( WMDS_DIR . 'includes/class-wmds-' . $eager . '.php' )
	);
}

wmds_section( 'The plugin header no longer keeps a list to forget' );

wmds_assert(
	'only the files that cannot be autoloaded are required',
	5,
	preg_match_all( "/require_once WMDS_DIR \. 'includes\//", $header )
);

wmds_assert_contains(
	'and the WP-CLI file is one of them, because it registers its command at file level',
	'WP_CLI::add_command(',
	(string) file_get_contents( WMDS_DIR . 'includes/class-wmds-cli.php' )
);

wmds_result();
