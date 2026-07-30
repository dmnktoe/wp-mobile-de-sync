<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes one import pass.
 *
 * Deliberately thin: the risky decisions belong to WMDS_Sync_Plan, field
 * mapping to WMDS_Json_Mapper, talking to the API to WMDS_Client. What is
 * left here is what WordPress needs - posts, meta, attachments, schedule.
 *
 * Sequence:
 *   1. Take a lock so two cron ticks cannot collide.
 *   2. Fetch the search result page by page. That is cheap and yields every
 *      ad including its modificationDate.
 *   3. WMDS_Sync_Plan decides per ad: create, update or skip.
 *   4. Detail call only for ads to create or update - description and seller
 *      data exist nowhere else.
 *   5. Upsert; images only when their hashes changed.
 *   6. After a full pass that actually finished: move vanished vehicles to
 *      the trash, guarded by the limits in the plan.
 *
 * When there is more to do than fits into one pass, the run ends cleanly and
 * reschedules itself. That keeps it inside any PHP timeout, no matter whether
 * WP-Cron or a system cron triggered it.
 */
class WMDS_Importer {

	/** Ad ID on the post. Part of the meta contract - do not rename. */
	const META_AD_ID = 'mobileAdId';

	/** Per-vehicle internal state. Leading underscore = hidden in the admin. */
	const META_MODIFIED = '_wmds_modified';
	const META_HASH     = '_wmds_hash';
	const META_IMAGES   = '_wmds_image_hashes';

	const LOCK          = 'wmds_import_lock';
	const OPT_LAST_RUN  = 'wmds_last_run';
	const OPT_WATERMARK = 'wmds_watermark';
	const OPT_LOG       = 'wmds_log';

	/** Upper bound on vehicles processed in a single pass. */
	const BATCH = 20;

	/** How long the lock lives, in case a run dies hard. */
	const LOCK_TTL = 900;

	/** Images per vehicle. */
	const MAX_IMAGES = 15;

	/** @var WMDS_Client */
	private $client;
	/** @var WMDS_Json_Mapper */
	private $mapper;

	/**
	 * @param WMDS_Client|null      $client Injectable for tests.
	 * @param WMDS_Json_Mapper|null $mapper Injectable for tests.
	 */
	public function __construct( $client = null, $mapper = null ) {
		$this->client = $client ? $client : WMDS_Settings::client();
		$this->mapper = $mapper ? $mapper : new WMDS_Json_Mapper( new WMDS_Refdata( WMDS_Settings::language() ) );
	}

	/**
	 * @param array $args full  Full reconciliation instead of incremental.
	 *                    force Re-read unchanged vehicles as well.
	 *                    batch Override the batch size for this pass.
	 * @return array|WP_Error Statistics, or an error.
	 */
	public function run( array $args = array() ) {
		if ( ! $this->client->has_credentials() ) {
			return new WP_Error( 'wmds_no_credentials', 'No credentials stored.' );
		}
		if ( ! $this->client->has_seller() ) {
			return new WP_Error( 'wmds_no_seller', 'Neither seller ID nor dealer name stored.' );
		}
		if ( get_transient( self::LOCK ) ) {
			return new WP_Error( 'wmds_locked', 'An import is already running.' );
		}

		set_transient( self::LOCK, time(), self::LOCK_TTL );

		$stats = $this->execute(
			! empty( $args['full'] ),
			! empty( $args['force'] ),
			isset( $args['batch'] ) ? max( 1, (int) $args['batch'] ) : self::BATCH
		);

		delete_transient( self::LOCK );

		return $stats;
	}

	/**
	 * @param bool $full
	 * @param bool $force
	 * @param int  $batch
	 * @return array|WP_Error
	 */
	private function execute( $full, $force, $batch ) {
		// Without a stored watermark a run is necessarily a full one.
		$watermark = $full ? '' : (string) get_option( self::OPT_WATERMARK, '' );
		$is_full   = ( '' === $watermark );

		$ads    = array();
		$page   = 1;
		$pages  = 1;
		$capped = false;
		$guard  = 40; // 40 pages x 100 ads covers any realistic inventory

		do {
			$result = $this->client->search( $page, WMDS_Client::MAX_PAGE_SIZE, $watermark );
			if ( is_wp_error( $result ) ) {
				// Clean abort without deleting anything: a brief outage must
				// not touch the inventory.
				$this->log( 'Aborted on page ' . $page . ': ' . $result->get_error_message() );
				return $result;
			}

			$ads    = array_merge( $ads, $result['ads'] );
			$capped = $capped || ! empty( $result['capped'] );
			$pages  = max( 1, (int) $result['max_pages'] );
			++$page;
		} while ( $page <= $pages && $page <= $guard );

		if ( $capped ) {
			$this->log(
				sprintf(
					'Warning: the inventory exceeds the %d vehicles the API hands out across '
					. 'paginated pages. This reconciliation is incomplete.',
					WMDS_Client::PAGINATION_CAP
				)
			);
		}

		$known = $this->known_map();
		$plan  = WMDS_Sync_Plan::build( $ads, $known, $force );

		$todo    = array_merge( $plan['create'], $plan['update'] );
		$pending = max( 0, count( $todo ) - $batch );
		$todo    = array_slice( $todo, 0, $batch );

		$created = 0;
		$updated = 0;
		$images  = 0;
		$failed  = 0;

		foreach ( $todo as $ad ) {
			$outcome = $this->process( $ad, $known, $images );
			if ( 'created' === $outcome ) {
				++$created;
			} elseif ( 'updated' === $outcome ) {
				++$updated;
			} else {
				++$failed;
			}
		}

		// Removal happens only after a full pass that actually finished.
		$complete = ( 0 === $pending );
		$removal  = WMDS_Sync_Plan::removals( $plan['seen'], $known, $is_full && $complete );
		$removed  = 0;

		if ( '' !== $removal['abort'] ) {
			// On a partial pass the abort is the normal case and not worth
			// logging.
			if ( $is_full && $complete ) {
				$this->log( $removal['abort'] );
			}
		} else {
			foreach ( $removal['remove'] as $post_id ) {
				// Trash rather than delete: a bad run stays recoverable and
				// the URL survives for a while.
				wp_trash_post( $post_id );
				++$removed;
			}
		}

		// Only advance the watermark once nothing is pending - otherwise the
		// deferred vehicles would be lost.
		if ( $complete ) {
			$next = WMDS_Sync_Plan::next_watermark( wp_list_pluck( $ads, 'modificationDate' ) );
			if ( '' !== $next ) {
				update_option( self::OPT_WATERMARK, $next, false );
			}
		} else {
			$this->reschedule();
		}

		$stats = array(
			'seen'    => count( $plan['seen'] ),
			'created' => $created,
			'updated' => $updated,
			'skipped' => count( $plan['skip'] ),
			'removed' => $removed,
			'images'  => $images,
			'failed'  => $failed,
			'pending' => $pending,
			'full'    => $is_full,
			'time'    => current_time( 'mysql' ),
		);

		update_option( self::OPT_LAST_RUN, $stats, false );

		$this->log(
			sprintf(
				'%s: %d seen, %d created, %d updated, %d skipped, %d removed, %d images%s.',
				$is_full ? 'Full sync' : 'Incremental sync',
				$stats['seen'],
				$created,
				$updated,
				$stats['skipped'],
				$removed,
				$images,
				$pending ? sprintf( ', %d pending', $pending ) : ''
			)
		);

		return $stats;
	}

	/**
	 * Fetches an ad's details and writes it to the database.
	 *
	 * @param array $ad
	 * @param array $known
	 * @param int   $images Counter, incremented in place.
	 * @return string created|updated|error
	 */
	private function process( array $ad, array $known, &$images ) {
		$ad_id = (string) $ad['mobileAdId'];

		// Description and seller data exist only in the single-ad call: in a
		// search result plainTextDescription is an empty string and
		// seller.phones an empty array.
		$detail = $this->client->ad( $ad_id );
		if ( is_wp_error( $detail ) ) {
			$this->log( 'Detail call failed for ' . $ad_id . ': ' . $detail->get_error_message() );
		} else {
			$ad = array_merge( $ad, $detail );
		}

		$mapped = $this->mapper->map( $ad );
		if ( '' === $mapped['ad_id'] ) {
			return 'error';
		}

		$post_id = isset( $known[ $ad_id ]['post_id'] ) ? (int) $known[ $ad_id ]['post_id'] : 0;
		$mode    = $post_id ? 'updated' : 'created';

		$postarr = array(
			'post_type'    => WMDS_CPT,
			'post_status'  => 'publish',
			'post_title'   => $mapped['title'],
			'post_content' => $mapped['description'],
		);

		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			$result        = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $result ) || ! $result ) {
			$this->log( 'Could not save post for ' . $ad_id );
			return 'error';
		}

		$post_id = (int) $result;

		update_post_meta( $post_id, self::META_AD_ID, $ad_id );
		update_post_meta( $post_id, self::META_MODIFIED, $mapped['modified'] );

		// Write only when something actually changed. For an unchanged
		// vehicle that saves roughly 95 write operations.
		$hash = WMDS_Sync_Plan::content_hash( $mapped );
		if ( (string) get_post_meta( $post_id, self::META_HASH, true ) !== $hash ) {
			$this->write_meta( $post_id, $mapped['meta'] );
			update_post_meta( $post_id, self::META_HASH, $hash );
		}

		$stored = get_post_meta( $post_id, self::META_IMAGES, true );
		if ( WMDS_Sync_Plan::images_changed( $stored, $mapped['images'] ) || ! has_post_thumbnail( $post_id ) ) {
			$count = $this->import_images( $post_id, $mapped['images'], $mapped['title'] );
			if ( $count ) {
				$images += $count;
				update_post_meta( $post_id, self::META_IMAGES, wp_list_pluck( $mapped['images'], 'hash' ) );
			}
		}

		return $mode;
	}

	/**
	 * Writes the mapped meta fields and clears out what the feed no longer
	 * supplies.
	 *
	 * @param int   $post_id
	 * @param array $meta
	 */
	private function write_meta( $post_id, array $meta ) {
		foreach ( $meta as $key => $value ) {
			if ( '' === $value || null === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Downloads the images and sets the first one as the featured image.
	 *
	 * When photos are swapped the previous attachments are removed first,
	 * otherwise orphans pile up in the media library.
	 *
	 * @param int    $post_id
	 * @param array  $images
	 * @param string $title
	 * @return int Number of images imported.
	 */
	private function import_images( $post_id, array $images, $title ) {
		if ( ! $images ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		foreach ( get_attached_media( 'image', $post_id ) as $old ) {
			wp_delete_attachment( $old->ID, true );
		}

		$first = 0;
		$count = 0;

		foreach ( array_slice( $images, 0, self::MAX_IMAGES ) as $index => $image ) {
			$tmp = download_url( $image['url'], 30 );
			if ( is_wp_error( $tmp ) ) {
				$this->log( 'Image could not be downloaded: ' . $image['url'] );
				continue;
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
				continue;
			}

			++$count;
			if ( ! $first ) {
				$first = (int) $attachment;
			}
		}

		if ( $first ) {
			set_post_thumbnail( $post_id, $first );
		}

		return $count;
	}

	/**
	 * Known inventory as ad_id => ['post_id', 'modified'].
	 *
	 * One query instead of one per vehicle. A meta query per ad means
	 * hundreds of queries per run on a three-digit inventory every 15
	 * minutes, without anything necessarily having changed.
	 *
	 * Trashed posts are included deliberately: when a vehicle reappears the
	 * existing post is reused instead of a second one being created.
	 *
	 * @return array
	 */
	private function known_map() {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, a.meta_value AS ad_id, COALESCE( m.meta_value, '' ) AS modified
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status != 'auto-draft'",
				self::META_AD_ID,
				self::META_MODIFIED,
				WMDS_CPT
			)
		);

		$map = array();
		foreach ( (array) $rows as $row ) {
			if ( '' === (string) $row->ad_id ) {
				continue;
			}
			$map[ (string) $row->ad_id ] = array(
				'post_id'  => (int) $row->ID,
				'modified' => (string) $row->modified,
			);
		}

		return $map;
	}

	/** Kick off the next pass while something is still pending. */
	private function reschedule() {
		wp_schedule_single_event( time() + 60, WMDS_CRON_HOOK );
	}

	/**
	 * Writes to a rolling log in the database.
	 *
	 * Writing only to error_log() is not enough: what a run did has to be
	 * visible from the admin screen, without shell access to the server.
	 *
	 * @param string $message
	 */
	private function log( $message ) {
		$log = get_option( self::OPT_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			array(
				'time'    => current_time( 'mysql' ),
				'message' => (string) $message,
			)
		);

		update_option( self::OPT_LOG, array_slice( $log, 0, 50 ), false );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[wp-mobile-de-sync] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only under WP_DEBUG; the durable log is the option above.
		}
	}
}
