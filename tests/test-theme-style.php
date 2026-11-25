<?php

require_once __DIR__ . '/bootstrap.php';

$GLOBALS['wmds_test_options']  = array();
$GLOBALS['wmds_test_filters']  = array();
$GLOBALS['wmds_test_template'] = '';
$GLOBALS['wmds_test_mods']     = array();
$GLOBALS['wmds_test_supports'] = array();
$GLOBALS['wmds_test_json']     = null;
$GLOBALS['wmds_test_palette']  = array();

function get_option( $key, $default = false ) {
	return isset( $GLOBALS['wmds_test_options'][ $key ] ) ? $GLOBALS['wmds_test_options'][ $key ] : $default;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wmds_test_filters'][ $hook ][] = $callback;

	return true;
}

function apply_filters( $hook, $value ) {
	if ( empty( $GLOBALS['wmds_test_filters'][ $hook ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['wmds_test_filters'][ $hook ] as $callback ) {
		$value = call_user_func( $callback, $value );
	}

	return $value;
}

function get_template() {
	return $GLOBALS['wmds_test_template'];
}

function get_theme_mod( $name, $default = false ) {
	return isset( $GLOBALS['wmds_test_mods'][ $name ] ) ? $GLOBALS['wmds_test_mods'][ $name ] : $default;
}

function current_theme_supports( $feature ) {
	return in_array( $feature, $GLOBALS['wmds_test_supports'], true );
}

function get_background_color() {
	return get_theme_mod( 'background_color', '' );
}

function wp_theme_has_theme_json() {
	return null !== $GLOBALS['wmds_test_json'];
}

function wp_get_global_styles( $path = array() ) {
	$data = is_array( $GLOBALS['wmds_test_json'] ) ? $GLOBALS['wmds_test_json'] : array();
	foreach ( $path as $key ) {
		if ( ! is_array( $data ) || ! isset( $data[ $key ] ) ) {
			return $GLOBALS['wmds_test_json'];
		}
		$data = $data[ $key ];
	}

	return $data;
}

function wp_get_global_settings( $path = array() ) {
	return array( 'theme' => $GLOBALS['wmds_test_palette'] );
}

class WP_Theme_JSON_Core_Stub {
	public function get_raw_data() {
		return array(
			'styles' => array(
				'elements' => array(
					'button' => array(
						'color' => array(
							'text'       => '#fff',
							'background' => '#32373c',
						),
					),
				),
			),
		);
	}
}

class WP_Theme_JSON_Resolver {
	public static function get_core_data() {
		return new WP_Theme_JSON_Core_Stub();
	}
}

require_once __DIR__ . '/../includes/class-wmds-settings.php';
require_once __DIR__ . '/../includes/class-wmds-theme-style.php';

/**
 * @param array $setup
 */
function wmds_theme( array $setup = array() ) {
	$GLOBALS['wmds_test_options']  = isset( $setup['options'] ) ? array( WMDS_Settings::OPTION => $setup['options'] ) : array();
	$GLOBALS['wmds_test_filters']  = array();
	$GLOBALS['wmds_test_template'] = isset( $setup['template'] ) ? $setup['template'] : 'some-theme';
	$GLOBALS['wmds_test_mods']     = isset( $setup['mods'] ) ? $setup['mods'] : array();
	$GLOBALS['wmds_test_supports'] = isset( $setup['supports'] ) ? $setup['supports'] : array();
	$GLOBALS['wmds_test_json']     = array_key_exists( 'json', $setup ) ? $setup['json'] : null;
	$GLOBALS['wmds_test_palette']  = isset( $setup['palette'] ) ? $setup['palette'] : array();
	WMDS_Theme_Style::reset();
}

wmds_section( 'A block theme lends its button' );

wmds_theme(
	array(
		'json'    => array(
			'color'    => array( 'background' => 'var:preset|color|base' ),
			'elements' => array(
				'button' => array(
					'color'  => array(
						'background' => 'var(--wp--preset--color--contrast)',
						'text'       => 'var(--wp--preset--color--base)',
					),
					'border' => array( 'radius' => '.33rem' ),
				),
			),
		),
		'palette' => array(
			array(
				'slug'  => 'base',
				'color' => '#FFFFFF',
			),
		),
	)
);
$css = WMDS_Theme_Style::css();
wmds_assert_contains( 'the button colour becomes the accent', '--wmds-theme-accent:var(--wp--preset--color--contrast);', $css );
wmds_assert_contains( 'its text colour the text on it', '--wmds-theme-on-accent:var(--wp--preset--color--base);', $css );
wmds_assert_contains( 'controls take its rounding', '--wmds-theme-radius-sm:5.28px;', $css );
wmds_assert_contains( 'cards round twice as much', '--wmds-theme-radius:10.56px;', $css );
wmds_assert( 'the variables sit on body, where theme presets resolve', 0, strpos( $css, 'body{' ) );
wmds_assert( 'a white page is not dark', false, WMDS_Theme_Style::is_dark() );

wmds_section( 'What WordPress itself sets is not the theme\'s' );

wmds_theme(
	array(
		'json' => array(
			'elements' => array(
				'button' => array(
					'color' => array(
						'text'       => '#fff',
						'background' => '#32373c',
					),
				),
			),
		),
	)
);
wmds_assert( 'the core grey button is ignored', '', WMDS_Theme_Style::css() );

wmds_section( 'Square and pill buttons' );

wmds_theme( array( 'json' => array( 'elements' => array( 'button' => array( 'border' => array( 'radius' => '0' ) ) ) ) ) );
wmds_assert_contains( 'square buttons make square cards', '--wmds-theme-radius:0;', WMDS_Theme_Style::css() );

wmds_theme( array( 'json' => array( 'elements' => array( 'button' => array( 'border' => array( 'radius' => '9999px' ) ) ) ) ) );
$css = WMDS_Theme_Style::css();
wmds_assert_contains( 'pill buttons give pill controls', '--wmds-theme-radius-sm:23px;', $css );
wmds_assert_contains( 'cards stay soft', '--wmds-theme-radius:20px;', $css );

wmds_theme( array( 'json' => array( 'elements' => array( 'button' => array( 'border' => array( 'radius' => '4px 4px 0 0' ) ) ) ) ) );
wmds_assert( 'a radius per corner is left alone', '', WMDS_Theme_Style::css() );

wmds_section( 'A dark background' );

wmds_theme(
	array(
		'json'    => array( 'color' => array( 'background' => 'var(--wp--preset--color--base)' ) ),
		'palette' => array(
			array(
				'slug'  => 'base',
				'color' => '#1B1B1B',
			),
		),
	)
);
$css = WMDS_Theme_Style::css();
wmds_assert( 'is recognised through the palette', true, WMDS_Theme_Style::is_dark() );
wmds_assert_contains( 'cards are a shade lighter than the page', '--wmds-theme-surface:#292929;', $css );
wmds_assert( 'the dark set is switched on', array( 'home', 'wmds-dark' ), WMDS_Theme_Style::body_class( array( 'home' ) ) );

wmds_theme(
	array(
		'template' => 'some-classic-theme',
		'supports' => array( 'custom-background' ),
		'mods'     => array( 'background_color' => '0f1d2b' ),
	)
);
wmds_assert( 'or through the Customizer', true, WMDS_Theme_Style::is_dark() );

wmds_theme(
	array(
		'supports' => array( 'custom-background' ),
		'mods'     => array( 'background_color' => 'd1e4dd' ),
	)
);
wmds_assert( 'a pale one is not', array( 'home' ), WMDS_Theme_Style::body_class( array( 'home' ) ) );

wmds_section( 'Classic themes' );

wmds_theme( array( 'template' => 'astra' ) );
wmds_assert_contains( 'Astra lends its primary colour', '--wmds-theme-accent:var(--ast-global-color-0);', WMDS_Theme_Style::css() );

wmds_theme( array( 'template' => 'GeneratePress' ) );
$css = WMDS_Theme_Style::css();
wmds_assert_contains( 'GeneratePress its accent', '--wmds-theme-accent:var(--accent);', $css );
wmds_assert_contains( 'and its square corners', '--wmds-theme-radius-sm:0;', $css );

wmds_theme(
	array(
		'template' => 'oceanwp',
		'mods'     => array( 'ocean_primary_color' => '#ffcc00' ),
	)
);
wmds_assert_contains( 'a light accent gets dark text', '--wmds-theme-on-accent:#14171c;', WMDS_Theme_Style::css() );

wmds_theme(
	array(
		'template' => 'salient',
		'options'  => array(),
	)
);
$GLOBALS['wmds_test_options']['salient_redux'] = array( 'accent-color' => '#3452ff' );
wmds_assert_contains( 'Salient its accent colour', '--wmds-theme-accent:#3452ff;', WMDS_Theme_Style::css() );

wmds_theme( array( 'template' => 'some-theme' ) );
wmds_assert( 'an unknown theme keeps the plugin\'s look', '', WMDS_Theme_Style::css() );

wmds_section( 'The site owner and the developer decide' );

wmds_theme(
	array(
		'template' => 'astra',
		'options'  => array( 'match_theme' => 'no' ),
	)
);
wmds_assert( 'switched off, nothing is taken from the theme', '', WMDS_Theme_Style::css() );

wmds_theme( array( 'template' => 'astra' ) );
add_filter( 'wmds_theme_style', 'wmds_test_brand' );
wmds_assert_contains( 'the filter has the last word', '--wmds-theme-accent:#c8102e;', WMDS_Theme_Style::css() );

wmds_theme( array( 'template' => 'astra' ) );
add_filter( 'wmds_theme_style', 'wmds_test_inject' );
$css = WMDS_Theme_Style::css();
wmds_assert( 'a value that would break out of the rule is dropped', false, strpos( $css, '}' ) !== strlen( $css ) - 1 );
wmds_assert( 'and so is the accent it came in', false, strpos( $css, '--wmds-theme-accent' ) );

/**
 * @param array $tokens
 * @return array
 */
function wmds_test_brand( $tokens ) {
	$tokens['accent'] = '#c8102e';

	return $tokens;
}

/**
 * @param array $tokens
 * @return array
 */
function wmds_test_inject( $tokens ) {
	$tokens['accent'] = 'red;}body{display:none';

	return $tokens;
}

wmds_result();
