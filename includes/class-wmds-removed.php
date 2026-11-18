<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The address of a vehicle that is no longer on the site.
 *
 * A vehicle gone from mobile.de goes to the trash, and its page with it. The
 * links to it do not: search results, bookmarks, a link sent by mail. Instead
 * of a "page not found", whoever follows one lands on the vehicle overview,
 * filtered by the make they were looking for where there is still one, with
 * a short note that this vehicle is no longer available.
 */
class WMDS_Removed {
	/** Query argument the overview reads to show the note. */
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

		// A vehicle in the trash can come back into the feed, under the same
		// address: that is a temporary redirect. One deleted for good is not
		// coming back.
		wp_safe_redirect( $target, $post_id ? 302 : 301 );
		exit;
	}

	/**
	 * @param string $uri  The request URI.
	 * @param string $slug The base the vehicles are served under.
	 * @return string The vehicle's post name, empty when the address is not
	 *                one of a vehicle.
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
	 * @return int The vehicle in the trash under that name, 0 when there is none.
	 */
	private static function trashed_vehicle( $name ) {
		// WordPress renames what it trashes, so the address is free for
		// something new; the old name is kept with a suffix.
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
	 * @param string $make The make of the vehicle that was asked for.
	 * @return string The overview, filtered by that make while there is one.
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

	/** @return bool Whether the overview was reached from a removed vehicle. */
	public static function arrived() {
		return isset( $_GET[ self::ARG ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides whether a note is shown.
	}
}
