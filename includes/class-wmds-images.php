<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Images {
	const META_LIST = '_wmds_image_hashes';

	const META_HASH = '_wmds_image_hash';

	const MAX = 15;

	public static function init() {
		add_action( 'before_delete_post', array( __CLASS__, 'delete_for' ) );
	}

	/**
	 * @param int    $post_id
	 * @param array  $images
	 * @param string $title
	 * @return array{hashes:string[],downloaded:int}
	 */
	public static function sync( $post_id, array $images, $title ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		list( $existing, $unnamed ) = self::attached( $post_id );

		$kept       = array();
		$hashes     = array();
		$downloaded = 0;

		foreach ( array_values( $images ) as $index => $image ) {
			$hash = isset( $image['hash'] ) ? (string) $image['hash'] : '';

			if ( '' !== $hash && isset( $existing[ $hash ] ) ) {
				$attachment = $existing[ $hash ];
			} else {
				$attachment = self::sideload( $post_id, $image, $title, $index );
				if ( ! $attachment ) {
					continue;
				}

				++$downloaded;
				if ( '' !== $hash ) {
					update_post_meta( $attachment, self::META_HASH, $hash );
				}
			}

			wp_update_post(
				array(
					'ID'         => $attachment,
					'menu_order' => count( $kept ),
				)
			);

			$kept[]   = $attachment;
			$hashes[] = $hash;
		}

		if ( $images && ! $kept ) {
			return array(
				'hashes'     => array(),
				'downloaded' => 0,
			);
		}

		foreach ( array_merge( array_values( $existing ), $unnamed ) as $old ) {
			if ( ! in_array( $old, $kept, true ) ) {
				wp_delete_attachment( $old, true );
			}
		}

		if ( $kept ) {
			set_post_thumbnail( $post_id, $kept[0] );
		} else {
			delete_post_thumbnail( $post_id );
		}

		return array(
			'hashes'     => $hashes,
			'downloaded' => $downloaded,
		);
	}

	/**
	 * @param int $post_id
	 * @return array[]
	 */
	private static function attached( $post_id ) {
		$by_hash = array();
		$unnamed = array();

		foreach ( get_attached_media( 'image', $post_id ) as $attachment ) {
			$hash = (string) get_post_meta( $attachment->ID, self::META_HASH, true );

			if ( '' === $hash || isset( $by_hash[ $hash ] ) ) {
				$unnamed[] = (int) $attachment->ID;
			} else {
				$by_hash[ $hash ] = (int) $attachment->ID;
			}
		}

		return array( $by_hash, $unnamed );
	}

	/**
	 * @param int $post_id
	 * @return int
	 */
	public static function release( $post_id ) {
		$post_id = (int) $post_id;
		$deleted = 0;

		foreach ( get_attached_media( 'image', $post_id ) as $attachment ) {
			if ( wp_delete_attachment( $attachment->ID, true ) ) {
				++$deleted;
			}
		}

		delete_post_thumbnail( $post_id );
		delete_post_meta( $post_id, self::META_LIST );

		return $deleted;
	}

	/** @param int $post_id */
	public static function delete_for( $post_id ) {
		if ( WMDS_CPT !== get_post_type( (int) $post_id ) ) {
			return;
		}

		self::release( $post_id );
	}

	/** @return int[] */
	public static function orphans() {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT a.ID
				 FROM {$wpdb->posts} a
				 INNER JOIN {$wpdb->postmeta} h ON h.post_id = a.ID AND h.meta_key = %s
				 LEFT JOIN {$wpdb->posts} v ON v.ID = a.post_parent
				 WHERE a.post_type = 'attachment'
				   AND ( v.ID IS NULL OR v.post_status = 'trash' OR v.post_type != %s )",
				self::META_HASH,
				WMDS_CPT
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/** @return int */
	public static function delete_orphans() {
		$deleted = 0;

		foreach ( self::orphans() as $id ) {
			if ( wp_delete_attachment( $id, true ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * @param int    $post_id
	 * @param array  $image
	 * @param string $title
	 * @param int    $index
	 * @return int
	 */
	private static function sideload( $post_id, array $image, $title, $index ) {
		$tmp = download_url( $image['url'], 30 );
		if ( is_wp_error( $tmp ) ) {
			/* translators: 1: image address, 2: the error message. */
			WMDS_Log::problem( sprintf( __( 'Image could not be downloaded: %1$s (%2$s)', 'wp-mobile-de-sync' ), $image['url'], $tmp->get_error_message() ) );

			return 0;
		}

		$file = array(
			'name'     => sanitize_file_name( $title . '-' . ( $index + 1 ) . '.jpg' ),
			'tmp_name' => $tmp,
		);

		$attachment = media_handle_sideload( $file, $post_id, $title );
		if ( is_wp_error( $attachment ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}

			/* translators: 1: image address, 2: the error message. */
			WMDS_Log::problem( sprintf( __( 'Image could not be stored: %1$s (%2$s)', 'wp-mobile-de-sync' ), $image['url'], $attachment->get_error_message() ) );

			return 0;
		}

		return (int) $attachment;
	}
}
