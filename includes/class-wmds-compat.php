<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'more_fields' ) ) {
	/**
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
	 * @return string
	 */
	function custom_taxonomies_terms_links() {
		return '';
	}
}
