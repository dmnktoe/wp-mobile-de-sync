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
define( 'WMDS_ICONS', 'https://example.invalid/icons/' );

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
	'importer',
	'logos',
	'stickers',
	'vehicle',
	'requirements',
	'status',
	'alerts',
	'leads',
	'updater',
	'posts',
	'admin',
) as $wmds_class ) {
	require_once dirname( __DIR__ ) . '/includes/class-wmds-' . $wmds_class . '.php';
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

	try {
		WMDS_Admin::handle();
	} catch ( WMDS_Test_Redirect $redirect ) {
		return $redirect->url;
	} catch ( WMDS_Test_Died $died ) {
		return 'died';
	}

	return 'no redirect';
}

wmds_section( 'Every tab is still there, in the order somebody learned' );

wmds_admin_fake_reset();

$tabs = array( 'connection', 'schedule', 'enquiries', 'status', 'tools', 'system', 'about' );

foreach ( $tabs as $tab ) {
	$markup = wmds_render_tab( $tab );

	wmds_assert( sprintf( 'the %s tab renders', $tab ), true, strlen( $markup ) > 200 );
	wmds_assert_contains( sprintf( 'the %s tab is the active one', $tab ), 'tab=' . $tab, $markup );
}

wmds_assert_contains( 'an unknown tab falls back to the connection', 'wmds_username', wmds_render_tab( 'nonsense' ) );
wmds_assert_contains( 'and so does no tab at all', 'wmds_username', wmds_render_tab( '' ) );

wmds_section( 'Each tab carries the fields it owns' );

wmds_assert_contains( 'connection: the username', 'name="wmds_username"', wmds_render_tab( 'connection' ) );
wmds_assert_contains( 'connection: the seller ID', 'name="wmds_seller_id"', wmds_render_tab( 'connection' ) );
wmds_assert_contains( 'schedule: the interval', 'name="wmds_interval"', wmds_render_tab( 'schedule' ) );
wmds_assert_contains( 'schedule: the label language', 'name="wmds_language"', wmds_render_tab( 'schedule' ) );
wmds_assert_contains( 'enquiries: the recipient', 'name="wmds_enquiry_recipient"', wmds_render_tab( 'enquiries' ) );
wmds_assert_contains( 'enquiries: the privacy notice', 'name="wmds_enquiry_consent"', wmds_render_tab( 'enquiries' ) );
wmds_assert_contains( 'status: the alert switch', 'name="wmds_alerts_enabled"', wmds_render_tab( 'status' ) );
wmds_assert_contains( 'status: the cooldown', 'name="wmds_alerts_cooldown"', wmds_render_tab( 'status' ) );

wmds_section( 'A tab saves its own settings' );

wmds_admin_fake_reset();
WMDS_Settings::update(
	array(
		'username'  => 'dealer',
		'password'  => 'secret',
		'seller_id' => '12345678',
		'interval'  => 'daily',
		'language'  => 'de',
	)
);

wmds_run_action(
	'save',
	array(
		'wmds_tab'      => 'schedule',
		'wmds_interval' => 'wmds_30min',
		'wmds_language' => 'fr',
	)
);

wmds_assert( 'the schedule tab writes the interval', 'wmds_30min', WMDS_Settings::interval() );
wmds_assert( 'and the language', 'fr', WMDS_Settings::get( 'language' ) );

wmds_section( 'And leaves the settings the other tabs own alone' );

wmds_assert( 'the username survives saving the schedule', 'dealer', WMDS_Settings::username() );
wmds_assert( 'so does the seller ID', '12345678', WMDS_Settings::seller_id() );
wmds_assert( 'and the password', 'secret', WMDS_Settings::password() );

wmds_run_action(
	'save',
	array(
		'wmds_tab'         => 'connection',
		'wmds_username'    => 'other',
		'wmds_seller_mode' => 'id',
		'wmds_seller_id'   => '87654321',
	)
);

wmds_assert( 'the connection tab writes the username', 'other', WMDS_Settings::username() );
wmds_assert( 'the interval survives saving the connection', 'wmds_30min', WMDS_Settings::interval() );
wmds_assert( 'an empty password field keeps the stored one', 'secret', WMDS_Settings::password() );

wmds_run_action(
	'save',
	array(
		'wmds_tab'               => 'enquiries',
		'wmds_enquiry_recipient' => 'sales@example.invalid',
		'wmds_enquiry_enabled'   => 'yes',
	)
);

wmds_assert( 'the enquiries tab writes its recipient', 'sales@example.invalid', WMDS_Settings::get( 'enquiry_recipient' ) );
wmds_assert( 'the username survives that too', 'other', WMDS_Settings::username() );
wmds_assert( 'and the interval', 'wmds_30min', WMDS_Settings::interval() );

wmds_run_action(
	'save',
	array(
		'wmds_tab'             => 'status',
		'wmds_alerts_enabled'  => 'yes',
		'wmds_alerts_cooldown' => '3600',
	)
);

wmds_assert( 'the status tab writes the alert settings', true, WMDS_Alerts::enabled() );
wmds_assert( 'and the cooldown', 3600, WMDS_Alerts::cooldown() );
wmds_assert( 'the enquiry recipient survives', 'sales@example.invalid', WMDS_Settings::get( 'enquiry_recipient' ) );

wmds_section( 'An unticked checkbox is a no, not an absence' );

wmds_run_action( 'save', array( 'wmds_tab' => 'status' ) );
wmds_assert( 'unticking the alert switch switches it off', false, WMDS_Alerts::enabled() );

wmds_section( 'Every action lands where it says it does' );

$targets = array(
	'save'      => 'tab=connection',
	'test'      => 'tab=connection',
	'sync'      => 'tab=status',
	'full'      => 'tab=status',
	'clear-log' => 'tab=status',
	'flush'     => 'tab=tools',
	'unlock'    => 'tab=tools',
	'forget'    => 'tab=connection',
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
wmds_assert( 'no capability, no action', 'died', wmds_run_action( 'save', array( 'wmds_tab' => 'connection' ) ) );

wmds_result();
