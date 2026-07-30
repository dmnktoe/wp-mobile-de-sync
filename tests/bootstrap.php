<?php
/**
 * Minimal WordPress stubs plus a small assertion helper.
 *
 * Deliberately without PHPUnit and without Composer: the tests must run on
 * any PHP 7 with no setup (`php tests/<file>.php`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// --------------------------------------------------------------------
// WordPress stubs
// --------------------------------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Simplified version: encodes the values the way WordPress does. That is
	 * exactly why the client must not pre-encode anything.
	 */
	function add_query_arg( $args, $url ) {
		$sep   = ( false === strpos( $url, '?' ) ) ? '?' : '&';
		$pairs = array();
		foreach ( $args as $key => $value ) {
			$pairs[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
		}
		return $url . $sep . implode( '&', $pairs );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( 'wpautop' ) ) {
	function wpautop( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		return true;
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array(
			'basedir' => sys_get_temp_dir() . '/wmds-test-uploads',
			'baseurl' => 'https://example.invalid/uploads',
		);
	}
}

/** In-process transient stub. */
$GLOBALS['wmds_test_transients'] = array();

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return isset( $GLOBALS['wmds_test_transients'][ $key ] )
			? $GLOBALS['wmds_test_transients'][ $key ]
			: false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['wmds_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	/** English-locale formatting, matching the default source language. */
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( $number, $decimals, '.', ',' );
	}
}

if ( ! function_exists( '__' ) ) {
	/** Translation stubs: source language is English, so pass through. */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html__( $text, $domain );
	}
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
	}
	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}
}

// --------------------------------------------------------------------
// Assertion helpers
// --------------------------------------------------------------------

$GLOBALS['wmds_checks']   = 0;
$GLOBALS['wmds_failures'] = 0;

if ( ! function_exists( 'wmds_section' ) ) {
	function wmds_section( $title ) {
		printf( "\n%s\n", $title );
	}
}

if ( ! function_exists( 'wmds_assert' ) ) {
	function wmds_assert( $label, $expected, $actual ) {
		++$GLOBALS['wmds_checks'];
		if ( $expected === $actual ) {
			printf( "  ok    %s\n", $label );
			return;
		}
		++$GLOBALS['wmds_failures'];
		printf(
			"  FAIL  %s\n        expected: %s\n        actual:   %s\n",
			$label,
			var_export( $expected, true ),
			var_export( $actual, true )
		);
	}
}

if ( ! function_exists( 'wmds_assert_contains' ) ) {
	function wmds_assert_contains( $label, $needle, $haystack ) {
		++$GLOBALS['wmds_checks'];
		if ( is_string( $haystack ) && false !== strpos( $haystack, $needle ) ) {
			printf( "  ok    %s\n", $label );
			return;
		}
		++$GLOBALS['wmds_failures'];
		printf(
			"  FAIL  %s\n        expected to contain: %s\n        actual:              %s\n",
			$label,
			var_export( $needle, true ),
			var_export( $haystack, true )
		);
	}
}

if ( ! function_exists( 'wmds_result' ) ) {
	function wmds_result() {
		printf(
			"\n%s  %d checks, %d failures\n",
			$GLOBALS['wmds_failures'] ? 'FAILED' : 'PASSED',
			$GLOBALS['wmds_checks'],
			$GLOBALS['wmds_failures']
		);
		exit( $GLOBALS['wmds_failures'] ? 1 : 0 );
	}
}
