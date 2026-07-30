<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility layer for pre-existing theme templates.
 *
 * Some established sites ship their own vehicle templates in a child theme
 * that call helper functions from an earlier solution. So those templates
 * keep working untouched, equivalent replacements live here - but only when
 * they are not already defined elsewhere.
 *
 * The bundled templates need none of this; they use WMDS_Vehicle. This file
 * exists purely so that switching plugins on an existing site does not break
 * the front end, and it can go once every template has been migrated.
 */

if ( ! function_exists( 'more_fields' ) ) {
	/**
	 * Iterates over the current post's image attachments.
	 *
	 * Returns the next image on every call, in the shape the existing
	 * templates expect:
	 *
	 *     [ 'file' => URL, 'sizes' => [ 'thumbnail' => [ 'file' => URL ] ] ]
	 *
	 * Calling with true resets the pointer.
	 *
	 * @param bool $reset
	 * @return array|false
	 */
	function more_fields( $reset = false ) {
		static $images = null;
		static $index  = 0;

		if ( $reset ) {
			$images = null;
			$index  = 0;
			return false;
		}

		if ( null === $images ) {
			$images  = array();
			$post_id = get_the_ID();

			if ( $post_id ) {
				$attachments = get_posts(
					array(
						'post_type'      => 'attachment',
						'post_mime_type' => 'image',
						'posts_per_page' => -1,
						'post_parent'    => $post_id,
						'orderby'        => 'menu_order ID',
						'order'          => 'ASC',
						'fields'         => 'ids',
					)
				);

				foreach ( $attachments as $attachment_id ) {
					$full = wp_get_attachment_image_url( $attachment_id, 'large' );
					if ( ! $full ) {
						continue;
					}
					$thumb = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

					$images[] = array(
						'file'  => $full,
						'sizes' => array(
							'thumbnail' => array( 'file' => $thumb ? $thumb : $full ),
						),
					);
				}
			}
		}

		if ( isset( $images[ $index ] ) ) {
			$item = $images[ $index ];
			++$index;
			return $item;
		}

		return false;
	}
}

if ( ! function_exists( 'custom_taxonomies_terms_links' ) ) {
	/**
	 * Placeholder. Older templates call this function but expect nothing in
	 * particular back.
	 *
	 * @return string
	 */
	function custom_taxonomies_terms_links() {
		return '';
	}
}
