<?php
/**
 * What the settings screen does, pinned down before it is taken apart.
 *
 * The rest of the suite tests decisions: which health state applies, what a
 * request means, whether a mail is due. The admin class has none of that
 * under it - it is dispatch and markup, and a mistake there shows up as a
 * broken screen rather than a red test.
 *
 * So this file asserts the things a refactor can silently break: that every
 * tab still exists and still renders, that each one saves its own settings
 * and only its own, that every action resolves and lands on the right tab,
 * and that the screen is closed to anybody without the capability.
 */

require_once __DIR__ . '/bootstrap.php';

define( 'WMDS_DIR', dirname( __DIR__ ) . '/' );
define( 'WMDS_URL', 'https://example.invalid/wp-content/plugins/wp-mobile-de-sync/' );
define( 'WMDS_VERSION', '0.0.0-test' );
define( 'WMDS_FILE', dirname( __DIR__ ) . '/wp-mobile-de-sync.php' );
define( 'WMDS_CPT', 'fahrzeuge' );
define( 'WMDS_CRON_HOOK', 'wmds_import_event' );
define( 'WMDS_CRON_FULL_HOOK', 'wmds_full_sync_event' );

require_once __DIR__ . '/wp-admin-fake.php';

foreach ( array(
	'str',
	'num',
	'date',
	'mail',
	'settings',
	'creole',
	'refdata',
	'client',
	'json-mapper',
	'sync-plan',
	'log',
	'images',
	'importer',
	'logos',
	'stickers',
	'vehicle',
	'requirements',
	'status',
	'alerts',
	'leads',
	'lead-form',
	'lead-mail',
	'plugin',
	'updater',
	'posts',
	'admin-notices',
	'admin-ui',
	'admin-actions',
	'admin-ajax',
	'admin',
	'blocks',
	'catalog',
	'dashboard',
) as $wmds_class ) {
	require_once dirname( __DIR__ ) . '/includes/class-wmds-' . $wmds_class . '.php';
}

foreach ( array( 'smtp', 'cf7', 'facetwp' ) as $wmds_integration ) {
	require_once dirname( __DIR__ ) . '/includes/integrations/class-wmds-' . $wmds_integration . '.php';
}

foreach ( array( 'overview', 'settings', 'enquiries', 'embed', 'tools' ) as $wmds_tab ) {
	require_once dirname( __DIR__ ) . '/includes/tabs/class-wmds-tab-' . $wmds_tab . '.php';
}

/**
 * @param string $tab
 * @return string
 */
function wmds_render_tab( $tab ) {
	wmds_admin_fake_reset();
	$_GET['tab'] = $tab;

	ob_start();
	WMDS_Admin::render();

	return (string) ob_get_clean();
}

/**
 * Drives the real entry point and reports where it wanted to go.
 *
 * @param string $action
 * @param array  $post
 * @return string The redirect target, or the exception class when it did not
 *                get that far.
 */
function wmds_run_action( $action, array $post = array() ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is the test setting the request up, not reading one.
	$_REQUEST             = array_merge( array( 'wmds_action' => $action ), $post );
	$_POST                = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is the test setting a request up, not reading one.
	$_REQUEST['_wpnonce'] = 'test';

	$_SERVER['REQUEST_METHOD'] = 'POST';

	try {
		WMDS_Admin_Actions::handle();
	} catch ( WMDS_Test_Redirect $redirect ) {
		return $redirect->url;
	} catch ( WMDS_Test_Died $died ) {
		return 'died';
	}

	return 'no redirect';
}

wmds_section( 'A link cannot do what only a form may' );

wmds_admin_fake_reset();
WMDS_Settings::update( array( 'username' => 'dealer' ) );

$_REQUEST                  = array(
	'wmds_action' => 'save',
	'_wpnonce'    => 'test',
);
$_POST                     = array();
$_SERVER['REQUEST_METHOD'] = 'GET';

try {
	WMDS_Admin_Actions::handle();
	$wmds_outcome = 'handled';
} catch ( WMDS_Test_Redirect $redirect ) {
	$wmds_outcome = 'redirected';
} catch ( WMDS_Test_Died $died ) {
	$wmds_outcome = 'died';
}

wmds_assert( 'a save by GET is refused', 'died', $wmds_outcome );
wmds_assert( 'and the username is still there', 'dealer', WMDS_Settings::get( 'username' ) );

wmds_run_action( 'save', array() );
wmds_assert( 'a form without a tab saves nothing', 'dealer', WMDS_Settings::get( 'username' ) );

wmds_section( 'The five tabs, the overview first' );

wmds_admin_fake_reset();

$tabs = array( 'overview', 'settings', 'enquiries', 'embed', 'tools' );

wmds_assert( 'five tabs, in this order', $tabs, array_keys( WMDS_Admin::tabs() ) );

foreach ( $tabs as $tab ) {
	$markup = wmds_render_tab( $tab );

	wmds_assert( sprintf( 'the %s tab renders', $tab ), true, strlen( $markup ) > 200 );
	wmds_assert_contains( sprintf( 'the %s tab is the active one', $tab ), 'tab=' . $tab, $markup );
}

wmds_assert_contains( 'an unknown tab falls back to the overview', 'wmds-status-card', wmds_render_tab( 'nonsense' ) );
wmds_assert_contains( 'and so does no tab at all', 'wmds-status-card', wmds_render_tab( '' ) );

wmds_section( 'Each tab carries the fields it owns' );

$settings = wmds_render_tab( 'settings' );
foreach ( array( 'username', 'password', 'seller_id', 'dealer', 'interval', 'language', 'slug', 'similar_enabled', 'alerts_enabled', 'alerts_recipient', 'alerts_cooldown', 'alerts_weekly' ) as $field ) {
	wmds_assert_contains( 'settings: ' . $field, 'name="wmds_' . $field . '"', $settings );
}

$enquiries = wmds_render_tab( 'enquiries' );
foreach ( WMDS_Tab_Enquiries::fields() as $field ) {
	wmds_assert_contains( 'enquiries: ' . $field, 'name="wmds_' . $field . '"', $enquiries );
}
wmds_assert_contains( 'enquiries: the test mail', 'value="test-mail"', $enquiries );

$embed = wmds_render_tab( 'embed' );
foreach ( WMDS_Tab_Embed::fields() as $field ) {
	wmds_assert_contains( 'embed: ' . $field, 'name="wmds_' . $field . '"', $embed );
}
wmds_assert_contains( 'embed: a mail tag to copy', '[_wmds_vehicle_price]', $embed );
wmds_assert_contains( 'embed: a shortcode example', '[vehicles', $embed );
wmds_assert_contains( 'embed: the dashboard widget', 'newest vehicles', $embed );

$tools = wmds_render_tab( 'tools' );
wmds_assert_contains( 'tools: the log', 'id="wmds-log"', $tools );
wmds_assert_contains( 'tools: the requirements', 'wmds-requirements', $tools );
wmds_assert_contains( 'tools: forcing a full sync', 'value="full"', $tools );

wmds_assert_contains(
	'overview: the sync button once configured',
	'id="wmds-sync"',
	( function () {
		WMDS_Settings::update(
			array(
				'username'  => 'dealer',
				'password'  => 'secret',
				'seller_id' => '1',
			)
		);
		$_GET['tab'] = 'overview';
		ob_start();
		WMDS_Admin::render();
		return (string) ob_get_clean();
	} )()
);

wmds_section( 'Every form names the settings it holds, and nothing is held twice' );

$owned = array();
foreach ( WMDS_Admin::forms() as $tab => $keys ) {
	foreach ( $keys as $key ) {
		wmds_assert( sprintf( '%s is a known setting', $key ), true, array_key_exists( $key, WMDS_Settings::fields() ) );
		$owned[] = $key;
	}
}
wmds_assert( 'no setting belongs to two forms', count( $owned ), count( array_unique( $owned ) ) );

wmds_section( 'A tab saves its own settings' );

wmds_admin_fake_reset();
WMDS_Settings::update(
	array(
		'username'          => 'dealer',
		'password'          => 'secret',
		'seller_id'         => '12345678',
		'interval'          => 'daily',
		'language'          => 'de',
		'enquiry_recipient' => 'sales@example.invalid',
	)
);

wmds_run_action(
	'save',
	array(
		'wmds_tab'         => 'settings',
		'wmds_username'    => 'other',
		'wmds_seller_mode' => 'id',
		'wmds_seller_id'   => '87654321',
		'wmds_interval'    => 'wmds_30min',
		'wmds_language'    => 'fr',
	)
);

wmds_assert( 'the settings tab writes the username', 'other', WMDS_Settings::username() );
wmds_assert( 'and the seller ID', '87654321', WMDS_Settings::seller_id() );
wmds_assert( 'and the interval', 'wmds_30min', WMDS_Settings::interval() );
wmds_assert( 'and the language', 'fr', WMDS_Settings::get( 'language' ) );
wmds_assert( 'an empty password field keeps the stored one', 'secret', WMDS_Settings::password() );
wmds_assert( 'the event moves to the new interval', 'wmds_30min', isset( $GLOBALS['wmds_admin_recurrence'][ WMDS_CRON_HOOK ] ) ? $GLOBALS['wmds_admin_recurrence'][ WMDS_CRON_HOOK ] : 'wmds_30min' );

wmds_section( 'And leaves the settings the other tabs own alone' );

wmds_assert( 'the enquiry recipient survives saving the settings', 'sales@example.invalid', WMDS_Settings::get( 'enquiry_recipient' ) );

wmds_run_action(
	'save',
	array(
		'wmds_tab'               => 'enquiries',
		'wmds_enquiry_recipient' => 'verkauf@example.invalid, nonsense',
		'wmds_enquiry_enabled'   => 'yes',
	)
);

wmds_assert( 'the enquiries tab writes its recipient, without what is no address', 'verkauf@example.invalid', WMDS_Settings::get( 'enquiry_recipient' ) );
wmds_assert( 'the username survives that', 'other', WMDS_Settings::username() );
wmds_assert( 'and the interval', 'wmds_30min', WMDS_Settings::interval() );

wmds_run_action(
	'save',
	array(
		'wmds_tab'             => 'settings',
		'wmds_username'        => 'other',
		'wmds_seller_id'       => '87654321',
		'wmds_alerts_enabled'  => 'yes',
		'wmds_alerts_cooldown' => '3600',
	)
);

wmds_assert( 'the alert settings are saved with the settings tab', true, WMDS_Alerts::enabled() );
wmds_assert( 'and the cooldown', 3600, WMDS_Alerts::cooldown() );
wmds_assert( 'a cooldown nobody offered falls back to the default', 21600, WMDS_Settings::from_request( array( 'alerts_cooldown' ), array( 'wmds_alerts_cooldown' => '5' ) )['alerts_cooldown'] );

wmds_section( 'The dealer name and the seller ID exclude each other' );

wmds_run_action(
	'save',
	array(
		'wmds_tab'         => 'settings',
		'wmds_username'    => 'other',
		'wmds_seller_mode' => 'dealer',
		'wmds_seller_id'   => '87654321',
		'wmds_dealer'      => 'EXAMPLEDEALER',
	)
);

wmds_assert( 'the dealer name is kept', 'EXAMPLEDEALER', WMDS_Settings::dealer() );
wmds_assert( 'the seller ID is cleared', '', WMDS_Settings::seller_id() );

wmds_section( 'The slug' );

wmds_admin_fake_reset();
wmds_assert( 'fahrzeuge until one is set', 'fahrzeuge', WMDS_Settings::slug() );

$GLOBALS['wmds_admin_options']['rewrite_rules'] = array( 'fahrzeuge/?$' => 'index.php?post_type=fahrzeuge' );

wmds_run_action(
	'save',
	array(
		'wmds_tab'  => 'settings',
		'wmds_slug' => '',
	)
);
wmds_assert( 'saving no slug keeps the rewrite rules', true, isset( $GLOBALS['wmds_admin_options']['rewrite_rules'] ) );

wmds_run_action(
	'save',
	array(
		'wmds_tab'  => 'settings',
		'wmds_slug' => ' Unsere Autos/ ',
	)
);
wmds_assert( 'a typed slug is cleaned up', 'unsere-autos', WMDS_Settings::slug() );
wmds_assert( 'a new slug drops the rewrite rules so they are rebuilt', false, isset( $GLOBALS['wmds_admin_options']['rewrite_rules'] ) );

wmds_run_action(
	'save',
	array(
		'wmds_tab'  => 'settings',
		'wmds_slug' => '///',
	)
);
wmds_assert( 'a slug that cleans up to nothing falls back to fahrzeuge', 'fahrzeuge', WMDS_Settings::slug() );
wmds_assert_contains( 'the settings tab shows the slug in use', 'value="fahrzeuge"', wmds_render_tab( 'settings' ) );
wmds_assert_contains( 'the embed tab names the archive under it', '/fahrzeuge/', wmds_render_tab( 'embed' ) );

wmds_section( 'An unticked checkbox is a no, not an absence' );

wmds_run_action(
	'save',
	array(
		'wmds_tab'            => 'settings',
		'wmds_alerts_enabled' => 'yes',
	)
);
wmds_run_action( 'save', array( 'wmds_tab' => 'settings' ) );
wmds_assert( 'unticking the alert switch switches it off', false, WMDS_Alerts::enabled() );
wmds_assert( 'and the similar vehicles', false, WMDS_Settings::flag( 'similar_enabled' ) );

wmds_section( 'Every action lands where it says it does' );

$targets = array(
	'save'         => 'tab=settings',
	'forget'       => 'tab=settings',
	'test'         => 'tab=tools',
	'sync'         => 'tab=overview',
	'full'         => 'tab=tools',
	'clear-log'    => 'tab=tools',
	'flush'        => 'tab=tools',
	'unlock'       => 'tab=tools',
	'clean-images' => 'tab=tools',
	'test-mail'    => 'tab=enquiries',
);

foreach ( $targets as $action => $expected ) {
	wmds_admin_fake_reset();
	wmds_assert_contains( sprintf( '%s returns to its own tab', $action ), $expected, wmds_run_action( $action ) );
}

wmds_admin_fake_reset();
wmds_assert_contains( 'check-update goes to the updates screen', 'update-core.php', wmds_run_action( 'check-update' ) );

wmds_admin_fake_reset();
wmds_assert_contains( 'refresh goes back where it came from', 'edit.php', wmds_run_action( 'refresh' ) );

wmds_admin_fake_reset();
wmds_assert_contains( 'an unknown action still redirects rather than hanging', 'page=wmds-settings', wmds_run_action( 'nonsense' ) );

wmds_section( 'The screen is shut to anybody without the capability' );

wmds_admin_fake_reset();
$GLOBALS['wmds_admin_can'] = false;
wmds_assert( 'no capability, no action', 'died', wmds_run_action( 'save', array( 'wmds_tab' => 'settings' ) ) );

wmds_result();
