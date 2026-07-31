<?php
/**
 * Plugin Name: wmds integration test transport
 * Description: Routes every mobile.de request at the local mock server and blocks the rest.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param false|array|WP_Error $preempt
 * @param array                $args
 * @param string               $url
 * @return false|array|WP_Error
 */
function wmds_mock_transport( $preempt, $args, $url ) {
	if ( false !== $preempt ) {
		return $preempt;
	}

	$base = rtrim( (string) getenv( 'WMDS_MOCK_BASE' ), '/' );
	if ( '' === $base || 0 === strpos( $url, $base ) ) {
		return $preempt;
	}

	foreach ( array( 'https://services.mobile.de', 'https://img.example.invalid' ) as $host ) {
		if ( 0 === strpos( $url, $host . '/' ) ) {
			$args['reject_unsafe_urls'] = false;

			return wp_remote_request( $base . substr( $url, strlen( $host ) ), $args );
		}
	}

	return new WP_Error(
		'wmds_mock_blocked',
		'Blocked by the integration test harness: ' . $url
	);
}
add_filter( 'pre_http_request', 'wmds_mock_transport', 10, 3 );
