<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Removed {
	const ARG = 'wmds_gone';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'redirect' ) );
	}

	public static function redirect() {
		if ( ! is_404() ) {
			return;
		}

		$name = self::requested_name( isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '', WMDS_Settings::slug() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed as a path and matched against a slug, never printed.
		if ( '' === $name ) {
			return;
		}

		$post_id = self::trashed_vehicle( $name );
		$target  = self::target( $post_id ? (string) get_post_meta( $post_id, 'make', true ) : '' );

		wp_safe_redirect( $target, $post_id ? 302 : 301 );
		exit;
	}

	/**
	 * @param string $uri
	 * @param string $slug
	 * @return string
	 */
	public static function requested_name( $uri, $slug ) {
		$path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
		$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

		if ( '' !== $home && 0 === strpos( $path . '/', $home . '/' ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}

		$parts = explode( '/', $path );

		if ( 2 !== count( $parts ) || $parts[0] !== $slug || '' === $parts[1] || 'page' === $parts[1] ) {
			return '';
		}

		return sanitize_title( rawurldecode( $parts[1] ) );
	}

	/**
	 * @param string $name
	 * @return int
	 */
	private static function trashed_vehicle( $name ) {
		$found = get_posts(
			array(
				'post_type'      => WMDS_CPT,
				'post_status'    => 'trash',
				'name'           => $name . '__trashed',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return $found ? (int) $found[0] : 0;
	}

	/**
	 * @param string $make
	 * @return string
	 */
	public static function target( $make ) {
		$url  = (string) get_post_type_archive_link( WMDS_CPT );
		$args = array( self::ARG => 1 );

		if ( '' !== $make && self::in_stock( $make ) ) {
			$args[ WMDS_Facets::PREFIX . 'make' ] = $make;
		}

		return add_query_arg( $args, $url );
	}

	/**
	 * @param string $make
	 * @return bool
	 */
	private static function in_stock( $make ) {
		return (bool) get_posts(
			array(
				'post_type'      => WMDS_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'make', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one row, once per missing vehicle page.
				'meta_value'     => $make, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
			)
		);
	}

	/** @return bool */
	public static function arrived() {
		return isset( $_GET[ self::ARG ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides whether a note is shown.
	}
}
