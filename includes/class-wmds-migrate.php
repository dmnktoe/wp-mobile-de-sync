<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Moves the inventory onto the English post type and keeps old URLs working.
 *
 * Until 2.3.0 the post type and its rewrite slug were both "fahrzeuge". Sites
 * that already have posts under that name need them rewritten once; visitors
 * who bookmarked /fahrzeuge/ need a permanent redirect to /vehicles/.
 */
class WMDS_Migrate {
	const OPTION = 'wmds_schema';

	const SCHEMA = 'vehicles-1';

	const LEGACY_CPT = 'fahrzeuge';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_run' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy' ), 1 );
	}

	public static function maybe_run() {
		if ( self::SCHEMA === (string) get_option( self::OPTION, '' ) ) {
			return;
		}

		self::rename_post_type();
		flush_rewrite_rules( false );
		update_option( self::OPTION, self::SCHEMA, true );
	}

	/**
	 * Runs from the activation hook as well, so a fresh install never leaves
	 * rewrite rules pointing at the old slug.
	 */
	public static function activate() {
		self::rename_post_type();
		flush_rewrite_rules();
		update_option( self::OPTION, self::SCHEMA, true );
	}

	private static function rename_post_type() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-shot schema migration on upgrade.
		$wpdb->update(
			$wpdb->posts,
			array( 'post_type' => WMDS_CPT ),
			array( 'post_type' => self::LEGACY_CPT ),
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * /fahrzeuge/ and /fahrzeuge/slug/ permanently redirect to /vehicles/.
	 */
	public static function redirect_legacy() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$path = self::request_path();
		if ( null === $path ) {
			return;
		}

		if ( ! preg_match( '#^/fahrzeuge(?:/(.*))?$#', $path, $matches ) ) {
			return;
		}

		$rest = isset( $matches[1] ) ? trim( (string) $matches[1], '/' ) : '';

		if ( '' === $rest ) {
			$target = get_post_type_archive_link( WMDS_CPT );
		} elseif ( preg_match( '#^page/([0-9]+)$#', $rest, $page ) ) {
			$archive = get_post_type_archive_link( WMDS_CPT );
			$target  = $archive
				? user_trailingslashit( trailingslashit( $archive ) . 'page/' . (int) $page[1] )
				: false;
		} else {
			$target = home_url( user_trailingslashit( '/' . WMDS_CPT . '/' . $rest ) );
		}

		if ( ! $target ) {
			return;
		}

		wp_safe_redirect( $target, 301 );

		/**
		 * Whether to end the request after a legacy redirect.
		 *
		 * The suite turns this off so a redirect can be asserted without
		 * killing the process.
		 *
		 * @param bool $exit
		 */
		if ( apply_filters( 'wmds_migrate_exit', true ) ) {
			exit;
		}
	}

	/**
	 * @return string|null Path beginning with /, or null when it cannot be read.
	 */
	private static function request_path() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- guarded.
			return null;
		}

		$uri  = wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only the path is kept.
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return null;
		}

		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home = is_string( $home ) ? untrailingslashit( $home ) : '';

		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
			$path = '' === $path ? '/' : $path;
		}

		if ( '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}

		return $path;
	}
}
