<?php
/**
 * The admin half of WordPress, in process memory.
 *
 * tests/wp-fake.php stands in for the post and media functions the importer
 * needs. This file stands in for what an admin screen touches: options, the
 * schedule, nonces, the escaping helpers and the two ways a request ends.
 *
 * A redirect and wp_die() throw rather than exit, so a test can drive
 * WMDS_Admin::handle() through its real entry point and then look at where
 * it wanted to go.
 */
class WMDS_Test_Redirect extends Exception {
	/** @var string */
	public $url;

	public function __construct( $url ) {
		$this->url = (string) $url;
		parent::__construct( 'redirect' );
	}
}

class WMDS_Test_Died extends Exception {
}

$GLOBALS['wmds_admin_options']  = array();
$GLOBALS['wmds_admin_schedule'] = array();
$GLOBALS['wmds_admin_can']      = true;
$GLOBALS['wmds_admin_counts']   = 0;

function wmds_admin_fake_reset() {
	$GLOBALS['wmds_admin_options']   = array(
		'admin_email' => 'admin@example.invalid',
		'blogname'    => 'Autohaus Example',
		'date_format' => 'Y-m-d',
		'time_format' => 'H:i',
	);
	$GLOBALS['wmds_admin_schedule']  = array();
	$GLOBALS['wmds_admin_can']       = true;
	$GLOBALS['wmds_admin_counts']    = 0;
	$GLOBALS['wmds_test_transients'] = array();
	$_GET                            = array();
	$_POST                           = array();
	$_REQUEST                        = array();
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['wmds_admin_options'] )
			? $GLOBALS['wmds_admin_options'][ $key ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['wmds_admin_options'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['wmds_admin_options'][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['wmds_test_transients'][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return (bool) $GLOBALS['wmds_admin_can'];
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		return true;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ) {
		throw new WMDS_Test_Died( (string) $message );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $url, $status = 302 ) {
		throw new WMDS_Test_Redirect( $url );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.invalid/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'self_admin_url' ) ) {
	function self_admin_url( $path = '' ) {
		return admin_url( $path );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '/' ) {
		return 'https://example.invalid' . $path;
	}
}

if ( ! function_exists( 'wp_get_referer' ) ) {
	function wp_get_referer() {
		return 'https://example.invalid/wp-admin/edit.php';
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action = -1 ) {
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . '_wpnonce=test';
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		$field = '<input type="hidden" name="' . $name . '" value="test">';
		if ( $display ) {
			echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $field;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'test-nonce';
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return 1;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special = true, $extra = false ) {
		return str_repeat( 'a', (int) $length );
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = 'Save', $type = 'primary', $name = 'submit', $wrap = true ) {
		printf( '<button type="submit" class="button-%s">%s</button>', esc_attr( $type ), esc_html( $text ) );
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $display = true ) {
		$result = ( (string) $checked === (string) $current || ( true === $current && $checked ) ) ? ' checked' : '';
		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $display = true ) {
		$result = ( (string) $selected === (string) $current ) ? ' selected' : '';
		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr( $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'wp_count_posts' ) ) {
	function wp_count_posts( $type = 'post' ) {
		return (object) array( 'publish' => (int) $GLOBALS['wmds_admin_counts'] );
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) {
		return isset( $GLOBALS['wmds_admin_schedule'][ $hook ] )
			? $GLOBALS['wmds_admin_schedule'][ $hook ]
			: false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		$GLOBALS['wmds_admin_schedule'][ $hook ] = $timestamp;

		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( $hook, $args = array() ) {
		unset( $GLOBALS['wmds_admin_schedule'][ $hook ] );
	}
}

if ( ! function_exists( 'wp_get_schedule' ) ) {
	function wp_get_schedule( $hook, $args = array() ) {
		return isset( $GLOBALS['wmds_admin_schedule'][ $hook ] ) ? 'wmds_15min' : false;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) {
		return true;
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) {
		return true;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return 'wp-mobile-de-sync/wp-mobile-de-sync.php';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return str_replace( array( '"', "'", '<', '>' ), '', (string) $url );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return trim( (string) $email );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class, $fallback = '' ) {
		$class = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $class );

		return ( '' === $class ) ? $fallback : $class;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'stripslashes', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'human_time_diff' ) ) {
	function human_time_diff( $from, $to = 0 ) {
		return (string) abs( (int) $to - (int) $from ) . ' seconds';
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null ) {
		return gmdate( $format, null === $timestamp ? time() : (int) $timestamp );
	}
}

if ( ! function_exists( 'get_gmt_from_date' ) ) {
	function get_gmt_from_date( $date, $format = 'Y-m-d H:i:s' ) {
		$stamp = strtotime( $date . ' UTC' );

		return ( 'U' === $format ) ? (string) $stamp : gmdate( $format, $stamp );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql', $gmt = 0 ) {
		return ( 'timestamp' === $type ) ? time() : gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'wp_specialchars_decode' ) ) {
	function wp_specialchars_decode( $text, $quote_style = ENT_NOQUOTES ) {
		return html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers() {
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $body, $headers = array() ) {
		$GLOBALS['wmds_admin_mail'][] = compact( 'to', 'subject', 'body', 'headers' );

		return true;
	}
}

if ( ! function_exists( 'register_post_type' ) ) {
	function register_post_type( $type, $args = array() ) {
		return true;
	}
}

if ( ! function_exists( 'add_meta_box' ) ) {
	function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default' ) {
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $single ? '' : array();
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post = 0 ) {
		return 'Vehicle';
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) {
		return 'https://example.invalid/fahrzeuge/one/';
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key = '', $single = false ) {
		$store = isset( $GLOBALS['wmds_admin_user_meta'][ $key ] ) ? $GLOBALS['wmds_admin_user_meta'][ $key ] : '';

		return $single ? $store : array( $store );
	}
}

if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $key, $value ) {
		$GLOBALS['wmds_admin_user_meta'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_user_meta' ) ) {
	function delete_user_meta( $user_id, $key ) {
		unset( $GLOBALS['wmds_admin_user_meta'][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'wp_is_writable' ) ) {
	function wp_is_writable( $path ) {
		return true;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '', $filter = 'raw' ) {
		return ( 'version' === $show ) ? '6.8' : 'Autohaus Example';
	}
}

if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return 'de_DE';
	}
}

if ( ! function_exists( 'get_post_type_object' ) ) {
	function get_post_type_object( $type ) {
		return (object) array( 'label' => 'Vehicles' );
	}
}

if ( ! function_exists( 'get_post_type_archive_link' ) ) {
	function get_post_type_archive_link( $type ) {
		return 'https://example.invalid/fahrzeuge/';
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		return new WP_Error( 'wmds_test_no_http', 'No HTTP in the test harness.' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return '';
	}
}

if ( ! function_exists( 'size_format' ) ) {
	function size_format( $bytes, $decimals = 0 ) {
		return (string) $bytes;
	}
}

if ( ! function_exists( 'get_plugin_data' ) ) {
	function get_plugin_data( $file, $markup = true, $translate = true ) {
		return array( 'Version' => WMDS_VERSION );
	}
}

if ( ! function_exists( 'delete_site_transient' ) ) {
	function delete_site_transient( $key ) {
		return true;
	}
}

if ( ! function_exists( 'get_site_transient' ) ) {
	function get_site_transient( $key ) {
		return false;
	}
}

if ( ! function_exists( 'set_site_transient' ) ) {
	function set_site_transient( $key, $value, $ttl = 0 ) {
		return true;
	}
}
