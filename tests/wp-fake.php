<?php
/**
 * WordPress in process memory.
 *
 * Enough to run WMDS_Importer::run() end to end: posts, post meta, options,
 * attachments, trash, schedule and downloads all live in one array and can be
 * inspected after the run.
 *
 * WHAT THIS DOES NOT COVER: the SQL query in known_map(). $wpdb is a stub that
 * assembles the inventory from memory instead of executing the query it was
 * handed. The method's contract is covered, its SQL is not - that needs a real
 * database.
 *
 * Expects tests/bootstrap.php to be loaded already (WP_Error, is_wp_error,
 * transients, assertion helpers).
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp-stubs/' );
}

/**
 * Resets the stub to its initial state.
 */
function wmds_fake_reset() {
	$GLOBALS['wp_fake']              = array(
		'posts'       => array(),
		'meta'        => array(),
		'options'     => array(),
		'attachments' => array(),
		'thumbs'      => array(),
		'trashed'     => array(),
		'scheduled'   => array(),
		'downloaded'  => array(),
		'unloadable'  => array(),
		'next_id'     => 100,
	);
	$GLOBALS['wmds_test_transients'] = array();
}

wmds_fake_reset();

/** Convenient access to the state. */
function wmds_fake( $bucket = null ) {
	return null === $bucket ? $GLOBALS['wp_fake'] : $GLOBALS['wp_fake'][ $bucket ];
}

/**
 * Creates an inventory post the way an earlier run would have left it.
 *
 * @param string $ad_id
 * @param string $modified
 * @return int Post ID
 */
function wmds_fake_seed_vehicle( $ad_id, $modified = '2026-01-01T00:00:00.000Z' ) {
	$post_id = wp_insert_post(
		array(
			'post_type'   => WMDS_CPT,
			'post_status' => 'publish',
			'post_title'  => 'Existing vehicle ' . $ad_id,
		)
	);
	update_post_meta( $post_id, WMDS_Importer::META_AD_ID, (string) $ad_id );
	update_post_meta( $post_id, WMDS_Importer::META_MODIFIED, $modified );

	return $post_id;
}

// --------------------------------------------------------------------
// Posts
// --------------------------------------------------------------------

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $postarr, $wp_error = false ) {
		$id = $GLOBALS['wp_fake']['next_id']++;

		$GLOBALS['wp_fake']['posts'][ $id ]       = array_merge(
			array(
				'ID'           => $id,
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => '',
				'post_content' => '',
			),
			$postarr
		);
		$GLOBALS['wp_fake']['posts'][ $id ]['ID'] = $id;

		return $id;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $postarr, $wp_error = false ) {
		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( ! $id || ! isset( $GLOBALS['wp_fake']['posts'][ $id ] ) ) {
			return $wp_error ? new WP_Error( 'invalid_post', 'Unknown post.' ) : 0;
		}

		$GLOBALS['wp_fake']['posts'][ $id ] = array_merge(
			$GLOBALS['wp_fake']['posts'][ $id ],
			$postarr
		);

		return $id;
	}
}

if ( ! function_exists( 'wp_trash_post' ) ) {
	function wp_trash_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! isset( $GLOBALS['wp_fake']['posts'][ $post_id ] ) ) {
			return false;
		}
		$GLOBALS['wp_fake']['posts'][ $post_id ]['post_status'] = 'trash';
		$GLOBALS['wp_fake']['trashed'][]                        = $post_id;

		return true;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post_id = 0 ) {
		$post_id = (int) $post_id;

		return isset( $GLOBALS['wp_fake']['posts'][ $post_id ]['post_title'] )
			? $GLOBALS['wp_fake']['posts'][ $post_id ]['post_title']
			: '';
	}
}

if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return isset( $GLOBALS['wp_fake']['current_post'] ) ? $GLOBALS['wp_fake']['current_post'] : 0;
	}
}

// --------------------------------------------------------------------
// Post meta
// --------------------------------------------------------------------

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['wp_fake']['meta'][ (int) $post_id ][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$post_id = (int) $post_id;
		$all     = isset( $GLOBALS['wp_fake']['meta'][ $post_id ] )
			? $GLOBALS['wp_fake']['meta'][ $post_id ]
			: array();

		// Without a key WordPress returns key => array( value ) - exactly the
		// shape WMDS_Vehicle reads.
		if ( '' === $key ) {
			$out = array();
			foreach ( $all as $k => $v ) {
				$out[ $k ] = array( $v );
			}
			return $out;
		}

		if ( ! array_key_exists( $key, $all ) ) {
			return $single ? '' : array();
		}

		return $single ? $all[ $key ] : array( $all[ $key ] );
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $key ) {
		unset( $GLOBALS['wp_fake']['meta'][ (int) $post_id ][ $key ] );

		return true;
	}
}

// --------------------------------------------------------------------
// Options and transients
// --------------------------------------------------------------------

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['wp_fake']['options'] )
			? $GLOBALS['wp_fake']['options'][ $key ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['wp_fake']['options'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['wp_fake']['options'][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['wmds_test_transients'][ $key ] );

		return true;
	}
}

// --------------------------------------------------------------------
// Attachments and images
// --------------------------------------------------------------------

if ( ! function_exists( 'get_attached_media' ) ) {
	function get_attached_media( $type, $post_id ) {
		$post_id = (int) $post_id;
		$ids     = isset( $GLOBALS['wp_fake']['attachments'][ $post_id ] )
			? $GLOBALS['wp_fake']['attachments'][ $post_id ]
			: array();

		$out = array();
		foreach ( $ids as $id ) {
			$out[] = (object) array( 'ID' => $id );
		}

		return $out;
	}
}

if ( ! function_exists( 'wp_delete_attachment' ) ) {
	function wp_delete_attachment( $id, $force = false ) {
		foreach ( $GLOBALS['wp_fake']['attachments'] as $parent => $ids ) {
			$GLOBALS['wp_fake']['attachments'][ $parent ] = array_values(
				array_diff( $ids, array( (int) $id ) )
			);
		}

		return true;
	}
}

if ( ! function_exists( 'has_post_thumbnail' ) ) {
	function has_post_thumbnail( $post_id = 0 ) {
		return ! empty( $GLOBALS['wp_fake']['thumbs'][ (int) $post_id ] );
	}
}

if ( ! function_exists( 'set_post_thumbnail' ) ) {
	function set_post_thumbnail( $post_id, $attachment_id ) {
		$GLOBALS['wp_fake']['thumbs'][ (int) $post_id ] = (int) $attachment_id;

		return true;
	}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
		return 'https://example.invalid/bild-' . (int) $id . '-' . $size . '.jpg';
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		$parent = isset( $args['post_parent'] ) ? (int) $args['post_parent'] : 0;

		return isset( $GLOBALS['wp_fake']['attachments'][ $parent ] )
			? $GLOBALS['wp_fake']['attachments'][ $parent ]
			: array();
	}
}

if ( ! function_exists( 'download_url' ) ) {
	/**
	 * Downloads nothing, only records the URL. URLs listed in 'unloadable'
	 * fail, which is how the partial-failure path gets exercised.
	 */
	function download_url( $url, $timeout = 300 ) {
		$GLOBALS['wp_fake']['downloaded'][] = $url;

		if ( in_array( $url, $GLOBALS['wp_fake']['unloadable'], true ) ) {
			return new WP_Error( 'http_404', 'Not found.' );
		}

		return '/tmp/wmds-fake-' . md5( $url ) . '.jpg';
	}
}

if ( ! function_exists( 'media_handle_sideload' ) ) {
	function media_handle_sideload( $file, $post_id, $desc = null ) {
		$id      = $GLOBALS['wp_fake']['next_id']++;
		$post_id = (int) $post_id;

		if ( ! isset( $GLOBALS['wp_fake']['attachments'][ $post_id ] ) ) {
			$GLOBALS['wp_fake']['attachments'][ $post_id ] = array();
		}
		$GLOBALS['wp_fake']['attachments'][ $post_id ][] = $id;

		return $id;
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $path ) {
		return true;
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $name ) {
		$name = preg_replace( '/[^A-Za-z0-9._-]+/', '-', $name );

		return trim( $name, '-' );
	}
}

// --------------------------------------------------------------------
// Miscellaneous
// --------------------------------------------------------------------

if ( ! function_exists( 'wp_list_pluck' ) ) {
	function wp_list_pluck( $list, $field ) {
		$out = array();
		foreach ( (array) $list as $item ) {
			if ( is_array( $item ) && isset( $item[ $field ] ) ) {
				$out[] = $item[ $field ];
			} elseif ( is_object( $item ) && isset( $item->$field ) ) {
				$out[] = $item->$field;
			}
		}

		return $out;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql' ) {
		return 'mysql' === $type ? '2026-07-30 12:00:00' : 1785585600;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
		$GLOBALS['wp_fake']['scheduled'][] = $hook;

		return true;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return $url;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

// --------------------------------------------------------------------
// $wpdb
// --------------------------------------------------------------------

/**
 * Answers known_map()'s query from memory.
 *
 * The SQL it is handed is deliberately not evaluated - that would need a real
 * database. What is checked is the contract: which vehicles the importer
 * considers known, trashed ones included.
 */
class WMDS_Fake_WPDB {

	public $posts    = 'wp_posts';
	public $postmeta = 'wp_postmeta';

	/** @var int How often it was queried. One run may ask exactly once. */
	public $queries = 0;

	public function prepare( $sql ) {
		return $sql;
	}

	public function get_results( $sql ) {
		++$this->queries;

		$rows = array();
		foreach ( $GLOBALS['wp_fake']['posts'] as $id => $post ) {
			if ( WMDS_CPT !== $post['post_type'] || 'auto-draft' === $post['post_status'] ) {
				continue;
			}

			$meta = isset( $GLOBALS['wp_fake']['meta'][ $id ] ) ? $GLOBALS['wp_fake']['meta'][ $id ] : array();
			if ( empty( $meta[ WMDS_Importer::META_AD_ID ] ) ) {
				continue;
			}

			$rows[] = (object) array(
				'ID'       => $id,
				'ad_id'    => (string) $meta[ WMDS_Importer::META_AD_ID ],
				'modified' => isset( $meta[ WMDS_Importer::META_MODIFIED ] )
					? (string) $meta[ WMDS_Importer::META_MODIFIED ]
					: '',
			);
		}

		return $rows;
	}
}

$GLOBALS['wpdb'] = new WMDS_Fake_WPDB();

// --------------------------------------------------------------------
// Stubs for the importer's collaborators
// --------------------------------------------------------------------

/**
 * Client stub. Serves prepared pages and detail calls, and records what the
 * importer actually requested.
 */
class WMDS_Fake_Client {

	/** @var array page => array('ads'=>array,'max_pages'=>int,'capped'=>bool) */
	public $pages = array();

	/** @var array ad_id => array|WP_Error */
	public $details = array();

	public $credentials = true;
	public $seller      = true;

	/** @var WP_Error|null Error instead of a search result. */
	public $search_error = null;

	/** @var array Record of the calls made. */
	public $searched = array();
	public $fetched  = array();

	public function has_credentials() {
		return $this->credentials;
	}

	public function has_seller() {
		return $this->seller;
	}

	public function search( $page = 1, $per_page = 100, $modified_since = '' ) {
		$this->searched[] = array(
			'page'  => $page,
			'since' => $modified_since,
		);

		if ( $this->search_error ) {
			return $this->search_error;
		}

		return isset( $this->pages[ $page ] )
			? $this->pages[ $page ]
			: array(
				'ads'       => array(),
				'max_pages' => 1,
				'capped'    => false,
			);
	}

	public function ad( $ad_id ) {
		$this->fetched[] = (string) $ad_id;

		return isset( $this->details[ (string) $ad_id ] )
			? $this->details[ (string) $ad_id ]
			: new WP_Error( 'wmds_not_found', 'No detail response registered.' );
	}
}

/**
 * Reference-data stub: returns the key with a prefix, so a test can see
 * whether a label was resolved or not.
 */
class WMDS_Fake_Refdata extends WMDS_Refdata {

	public function __construct( $lang = 'de' ) {
		// Deliberately no parent call: nothing gets loaded.
	}

	public function label( $field, $key, $vehicle_class = 'Car' ) {
		return ( '' === (string) $key ) ? '' : 'L:' . $key;
	}
}
