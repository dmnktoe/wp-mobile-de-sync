<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Importer {
	const META_AD_ID = 'mobileAdId';

	const META_MODIFIED = '_wmds_modified';
	const META_HASH     = '_wmds_hash';
	const META_IMAGES   = '_wmds_image_hashes';
	const META_KEYS     = '_wmds_keys';
	const META_RETRIES  = '_wmds_image_retries';

	/** On an attachment: the hash mobile.de gave the picture it was made from. */
	const META_IMAGE_HASH = '_wmds_image_hash';

	/** Passes a vehicle is retried for a picture that will not download. */
	const IMAGE_RETRIES = 3;

	const LOCK          = 'wmds_import_lock';
	const OPT_LAST_RUN  = 'wmds_last_run';
	const OPT_WATERMARK = 'wmds_watermark';

	/**
	 * The event a pass books when it leaves vehicles for the next one. It is
	 * not the regular event: WordPress refuses a single event within ten
	 * minutes of an identical one, so a follow-up booked on the regular hook
	 * was swallowed whenever the next regular run was close, and a backlog
	 * moved forward one batch every fifteen minutes.
	 */
	const FOLLOWUP_HOOK = 'wmds_import_followup';
	const OPT_LOG       = 'wmds_log';

	const BATCH = 20;

	const LOCK_TTL = 900;

	const MAX_IMAGES = 15;

	const BUDGET_SHARE = 0.6;
	const MAX_RUNTIME  = 300;
	const MIN_RUNTIME  = 20;

	/** @var WMDS_Client */
	private $client;
	/** @var WMDS_Json_Mapper */
	private $mapper;

	/** @var bool Whether this request holds the lock right now. */
	private static $held = false;

	/**
	 * @param WMDS_Client|null      $client Injectable for tests.
	 * @param WMDS_Json_Mapper|null $mapper Injectable for tests.
	 */
	public function __construct( $client = null, $mapper = null ) {
		$this->client = $client ? $client : WMDS_Settings::client();
		$this->mapper = $mapper ? $mapper : new WMDS_Json_Mapper( new WMDS_Refdata( WMDS_Settings::language() ) );
	}

	/**
	 * @param array $args full       Full reconciliation instead of incremental.
	 *                    force      Re-read unchanged vehicles as well.
	 *                    batch      Override the batch size for this pass.
	 *                    budget     Seconds the vehicle loop may spend,
	 *                               overriding what the execution limit
	 *                               allows. 0 stops after the first vehicle.
	 *                    reschedule False when the caller drives the
	 *                               follow-up passes itself.
	 * @return array|WP_Error Statistics, or an error.
	 */
	public function run( array $args = array() ) {
		if ( ! $this->client->has_credentials() ) {
			return new WP_Error( 'wmds_no_credentials', __( 'No credentials stored.', 'wp-mobile-de-sync' ) );
		}
		if ( ! $this->client->has_seller() ) {
			return new WP_Error( 'wmds_no_seller', __( 'Neither seller ID nor dealer name stored.', 'wp-mobile-de-sync' ) );
		}
		if ( get_transient( self::LOCK ) ) {
			return new WP_Error( 'wmds_locked', __( 'An import is already running.', 'wp-mobile-de-sync' ) );
		}

		self::touch_lock();
		self::guard_lock();

		// Full and forced are states, not flags. A large inventory takes
		// several passes, and the later ones are started by cron or by the
		// next AJAX step without any argument: whatever has to survive until
		// the last pass has to be on disk. No watermark means "full" until a
		// pass completes; no stored modification date means "read it again".
		if ( ! empty( $args['full'] ) || ! empty( $args['force'] ) ) {
			delete_option( self::OPT_WATERMARK );
		}
		if ( ! empty( $args['force'] ) ) {
			delete_post_meta_by_key( self::META_MODIFIED );
		}

		// Labels and number formats end up in post meta. They have to be in
		// the language of the site, not of whoever pressed the button.
		$switched = function_exists( 'switch_to_locale' ) && switch_to_locale( get_locale() );

		$stats = $this->execute(
			isset( $args['batch'] ) ? max( 1, (int) $args['batch'] ) : self::BATCH,
			time() + ( isset( $args['budget'] ) ? max( 0, (int) $args['budget'] ) : self::budget() ),
			! isset( $args['reschedule'] ) || $args['reschedule']
		);

		if ( $switched ) {
			restore_previous_locale();
		}

		if ( is_wp_error( $stats ) ) {
			// An aborted run is a run. Without a record the status keeps
			// showing the last good one until it goes stale, and then blames
			// the schedule for what was a revoked password.
			$record = array(
				'seen'    => 0,
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'removed' => 0,
				'images'  => 0,
				'failed'  => 0,
				'pending' => 0,
				'full'    => '' === (string) get_option( self::OPT_WATERMARK, '' ),
				'error'   => $stats->get_error_message(),
				'time'    => current_time( 'mysql' ),
			);

			update_option( self::OPT_LAST_RUN, $record, false );
		} else {
			$record = $stats;
		}

		self::unlock();

		/**
		 * Fires when a run has ended and the lock is released, whether it
		 * completed, stopped early or was aborted.
		 *
		 * @param array $record Statistics; `error` is set when it was aborted.
		 */
		do_action( 'wmds_run_finished', $record );

		return $stats;
	}

	/**
	 * @return int Seconds this pass may spend on vehicles.
	 */
	private static function budget() {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled on many hosts, the budget below covers that case.
		@set_time_limit( 0 );

		$limit = (int) ini_get( 'max_execution_time' );

		$budget = ( $limit > 0 ) ? (int) floor( $limit * self::BUDGET_SHARE ) : self::MAX_RUNTIME;

		return max( self::MIN_RUNTIME, min( self::MAX_RUNTIME, $budget ) );
	}

	private static function guard_lock() {
		register_shutdown_function( array( __CLASS__, 'release_on_fatal' ) );
	}

	public static function release_on_fatal() {
		$error = error_get_last();

		if ( ! $error || ! in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
			return;
		}
		// Only the request that took the lock may give it back: the guard
		// stays registered after the run, and by then the lock may be
		// somebody else's.
		if ( ! self::$held || ! self::locked_since() ) {
			return;
		}

		self::unlock();
		self::schedule_followup();
	}

	public static function touch_lock() {
		$started = self::locked_since();

		set_transient( self::LOCK, $started ? $started : time(), self::LOCK_TTL );

		self::$held = true;
	}

	public static function unlock() {
		delete_transient( self::LOCK );

		self::$held = false;
	}

	/**
	 * @return int Unix time the running import started, 0 when none is held.
	 */
	public static function locked_since() {
		$since = get_transient( self::LOCK );

		return is_numeric( $since ) ? (int) $since : 0;
	}

	/**
	 * @param int  $batch
	 * @param int  $deadline   Unix time the vehicle loop has to stop by.
	 * @param bool $reschedule Whether an unfinished pass books its own follow-up.
	 * @return array|WP_Error
	 */
	private function execute( $batch, $deadline = 0, $reschedule = true ) {
		$watermark = (string) get_option( self::OPT_WATERMARK, '' );
		$is_full   = ( '' === $watermark );

		$ads       = array();
		$page      = 1;
		$pages     = 1;
		$capped    = false;
		$truncated = false;
		$guard     = 40;

		do {
			self::touch_lock();

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled on many hosts, the deadline below covers that case.
			@set_time_limit( 0 );

			$result = $this->client->search( $page, WMDS_Client::MAX_PAGE_SIZE, $watermark );
			if ( is_wp_error( $result ) ) {
				/* translators: 1: page number, 2: the error message. */
				$this->log( sprintf( __( 'Aborted on page %1$d: %2$s', 'wp-mobile-de-sync' ), $page, $result->get_error_message() ) );
				return $result;
			}

			$ads    = array_merge( $ads, $result['ads'] );
			$capped = $capped || ! empty( $result['capped'] );
			$pages  = max( 1, (int) $result['max_pages'] );
			++$page;

			if ( $deadline && $page <= $pages && $page <= $guard && time() >= $deadline ) {
				$truncated = true;

				$this->log(
					sprintf(
						/* translators: 1: pages read, 2: pages in total. */
						__( 'Listing stopped after %1$d of %2$d pages because of the execution time limit. This pass only processes what it has read and removes nothing.', 'wp-mobile-de-sync' ),
						$page - 1,
						$pages
					)
				);
				break;
			}
		} while ( $page <= $pages && $page <= $guard );

		if ( $capped ) {
			$this->log(
				sprintf(
					/* translators: %d: the maximum number of vehicles the API returns. */
					__( 'Warning: the inventory exceeds the %d vehicles the API returns across paginated requests. This reconciliation is incomplete.', 'wp-mobile-de-sync' ),
					WMDS_Client::PAGINATION_CAP
				)
			);
		}

		list( $known, $trashed ) = $this->known_map();

		$plan = WMDS_Sync_Plan::build( $ads, $known );

		$todo    = array_merge( $plan['create'], $plan['update'] );
		$pending = max( 0, count( $todo ) - $batch );
		$todo    = array_slice( $todo, 0, $batch );

		$created = 0;
		$updated = 0;
		$images  = 0;
		$failed  = 0;
		$done    = 0;

		foreach ( $todo as $ad ) {
			if ( $deadline && $done && time() >= $deadline ) {
				$left     = count( $todo ) - $done;
				$pending += $left;

				$this->log(
					sprintf(
						/* translators: 1: vehicles processed, 2: vehicles left. */
						__( 'Pass stopped after %1$d vehicles because of the execution time limit; %2$d remaining.', 'wp-mobile-de-sync' ),
						$done,
						$left
					)
				);
				break;
			}

			self::touch_lock();

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled on many hosts, the deadline above covers that case.
			@set_time_limit( 0 );

			++$done;

			$outcome = $this->process( $ad, $known, $images, null, $trashed );
			if ( 'created' === $outcome ) {
				++$created;
			} elseif ( 'updated' === $outcome ) {
				++$updated;
			} else {
				++$failed;
			}
		}

		$complete = ( 0 === $pending ) && ! $truncated;

		// Whether a vehicle is sold is settled by the listing, not by the
		// batches: a full listing read to its last page names every ad there
		// is, and the batches after it only rewrite text and pictures. So the
		// sold ones go in the first pass of a full sync - waiting for the last
		// batch kept them published for hours on a large backlog, next to the
		// new ones already added.
		//
		// What the API did not hand out is not gone. A listing cut short
		// names only part of the inventory, and past the pagination cap it is
		// a window on it; in both, everything outside would look sold.
		$reconcile = $is_full && ! $truncated && ! $capped;
		$removal   = WMDS_Sync_Plan::removals( $plan['seen'], $known, $reconcile );
		$removed   = 0;

		if ( '' !== $removal['abort'] ) {
			if ( $reconcile ) {
				$this->log( $removal['abort'] );
			}
		} else {
			foreach ( $removal['remove'] as $post_id ) {
				if ( wp_trash_post( $post_id ) ) {
					++$removed;
				}
			}
		}

		if ( $complete && ! $failed ) {
			$dates = array();
			foreach ( $ads as $ad ) {
				if ( is_array( $ad ) && isset( $ad['modificationDate'] ) ) {
					$dates[] = $ad['modificationDate'];
				}
			}

			$next = WMDS_Sync_Plan::next_watermark( $dates );
			if ( '' !== $next ) {
				update_option( self::OPT_WATERMARK, $next, false );
			}
		} elseif ( ! $complete && $reschedule ) {
			// A vehicle that failed keeps the watermark where it was, so the
			// next scheduled run lists it again; only an unfinished pass
			// needs one sooner than that.
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

		// Written in the site's language, which run() has switched to: the
		// log is read on a German screen and in German alert mails, and an
		// English line among them reads like an error.
		$summary = sprintf(
			/* translators: 1: vehicles seen, 2: created, 3: updated, 4: skipped, 5: removed, 6: images. */
			__( '%1$d seen, %2$d created, %3$d updated, %4$d skipped, %5$d removed, %6$d images', 'wp-mobile-de-sync' ),
			$stats['seen'],
			$created,
			$updated,
			$stats['skipped'],
			$removed,
			$images
		);

		if ( $pending ) {
			/* translators: %d: vehicles still to be processed. */
			$summary .= sprintf( __( ', %d still to process', 'wp-mobile-de-sync' ), $pending );
		}

		$this->log(
			sprintf(
				/* translators: 1: kind of run, 2: the counts. */
				__( '%1$s: %2$s.', 'wp-mobile-de-sync' ),
				$is_full ? __( 'Full sync', 'wp-mobile-de-sync' ) : __( 'Changes sync', 'wp-mobile-de-sync' ),
				$summary
			)
		);

		return $stats;
	}

	/**
	 * @param string $ad_id
	 * @return array|WP_Error
	 */
	public function refresh( $ad_id ) {
		$ad_id = trim( (string) $ad_id );

		if ( '' === $ad_id ) {
			return new WP_Error( 'wmds_no_ad_id', __( 'No ad ID given.', 'wp-mobile-de-sync' ) );
		}
		if ( ! $this->client->has_credentials() ) {
			return new WP_Error( 'wmds_no_credentials', __( 'No credentials stored.', 'wp-mobile-de-sync' ) );
		}

		$detail = $this->client->ad( $ad_id );
		if ( is_wp_error( $detail ) ) {
			/* translators: 1: mobile.de ad ID, 2: the error message. */
			$this->log( sprintf( __( 'Reload failed for %1$s: %2$s', 'wp-mobile-de-sync' ), $ad_id, $detail->get_error_message() ) );
			return $detail;
		}

		$switched = function_exists( 'switch_to_locale' ) && switch_to_locale( get_locale() );

		list( $known, $trashed ) = $this->known_map();

		$images  = 0;
		$outcome = $this->process( array( 'mobileAdId' => $ad_id ), $known, $images, $detail, $trashed );

		if ( $switched ) {
			restore_previous_locale();
		}

		if ( 'error' === $outcome ) {
			return new WP_Error( 'wmds_refresh_failed', __( 'The vehicle could not be saved.', 'wp-mobile-de-sync' ) );
		}

		/* translators: 1: mobile.de ad ID, 2: outcome (created, updated, skipped), 3: number of images. */
		$this->log( sprintf( __( 'Reloaded %1$s on request: %2$s, %3$d images.', 'wp-mobile-de-sync' ), $ad_id, self::outcome_label( $outcome ), $images ) );

		return array(
			'outcome' => $outcome,
			'images'  => $images,
		);
	}

	/**
	 * @param array      $ad
	 * @param array      $known
	 * @param int        $images  Counter, incremented in place.
	 * @param array|null $detail  Pre-fetched single-ad response; fetched here
	 *                            when null.
	 * @param array      $trashed Vehicles in the bin, by ad ID.
	 * @return string created|updated|error
	 */
	private function process( array $ad, array $known, &$images, $detail = null, array $trashed = array() ) {
		$ad_id = (string) $ad['mobileAdId'];

		$listed = isset( $ad['modificationDate'] ) ? (string) $ad['modificationDate'] : '';

		if ( null === $detail ) {
			$detail = $this->client->ad( $ad_id );
		}

		// The listing carries a fraction of the ad. Writing it on its own
		// would empty the description and delete every field only the detail
		// call knows, and then mark the vehicle as up to date.
		if ( is_wp_error( $detail ) ) {
			/* translators: 1: mobile.de ad ID, 2: the error message. */
			$this->log( sprintf( __( 'Detail call failed for %1$s: %2$s', 'wp-mobile-de-sync' ), $ad_id, $detail->get_error_message() ) );

			return 'error';
		}

		$ad     = array_merge( $ad, (array) $detail );
		$mapped = $this->mapper->map( $ad );
		if ( '' === $mapped['ad_id'] ) {
			return 'error';
		}

		$post_id = isset( $known[ $ad_id ]['post_id'] ) ? (int) $known[ $ad_id ]['post_id'] : 0;
		$mode    = $post_id ? 'updated' : 'created';

		// Back in the feed after it was binned: the same post, so its address
		// and whatever links to it survive.
		$revived = false;
		if ( ! $post_id && isset( $trashed[ $ad_id ]['post_id'] ) && wp_untrash_post( (int) $trashed[ $ad_id ]['post_id'] ) ) {
			$post_id = (int) $trashed[ $ad_id ]['post_id'];
			$mode    = 'updated';
			$revived = true;
		}

		$postarr = array(
			'post_type'    => WMDS_CPT,
			'post_title'   => $mapped['title'],
			'post_content' => $mapped['description'],
		);

		// An update leaves the status alone: a vehicle somebody set to draft
		// is not put back on the site by the next price change.
		if ( 'created' === $mode || $revived ) {
			$postarr['post_status'] = 'publish';
		}

		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$result = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $result ) || ! $result ) {
			$this->log(
				is_wp_error( $result )
					/* translators: 1: mobile.de ad ID, 2: the error message. */
					? sprintf( __( 'Could not save the vehicle %1$s: %2$s', 'wp-mobile-de-sync' ), $ad_id, $result->get_error_message() )
					/* translators: %s: mobile.de ad ID. */
					: sprintf( __( 'Could not save the vehicle %s.', 'wp-mobile-de-sync' ), $ad_id )
			);
			return 'error';
		}

		$post_id = (int) $result;

		update_post_meta( $post_id, self::META_AD_ID, $ad_id );

		$hash = WMDS_Sync_Plan::content_hash( $mapped );
		if ( (string) get_post_meta( $post_id, self::META_HASH, true ) !== $hash ) {
			$this->write_meta( $post_id, $mapped['meta'] );
			update_post_meta( $post_id, self::META_HASH, $hash );
		}

		$wanted   = array_slice( $mapped['images'], 0, self::MAX_IMAGES );
		$stored   = get_post_meta( $post_id, self::META_IMAGES, true );
		$complete = true;

		if ( ! $wanted ) {
			if ( $stored ) {
				$this->sync_images( $post_id, array(), $mapped['title'] );
				delete_post_meta( $post_id, self::META_IMAGES );
			}
		} elseif ( WMDS_Sync_Plan::images_changed( $stored, $wanted ) || ! has_post_thumbnail( $post_id ) ) {
			$result   = $this->sync_images( $post_id, $wanted, $mapped['title'] );
			$images  += $result['downloaded'];
			$complete = count( $result['hashes'] ) === count( $wanted );

			update_post_meta( $post_id, self::META_IMAGES, $result['hashes'] );
		}

		// Last, and only once everything is in: this is the mark that makes
		// the next run skip the vehicle. Set any earlier, a pass that dies
		// between here and the pictures leaves half a gallery nobody retries.
		// A picture that will not download is retried a few passes and then
		// let go, so one dead link does not keep a vehicle in the queue.
		$retries = $complete ? 0 : (int) get_post_meta( $post_id, self::META_RETRIES, true ) + 1;

		if ( $complete || $retries >= self::IMAGE_RETRIES ) {
			update_post_meta( $post_id, self::META_MODIFIED, wp_slash( '' !== $listed ? $listed : $mapped['modified'] ) );
			delete_post_meta( $post_id, self::META_RETRIES );
		} else {
			delete_post_meta( $post_id, self::META_MODIFIED );
			update_post_meta( $post_id, self::META_RETRIES, $retries );
		}

		/**
		 * Fires once a vehicle is completely written — post, meta and images.
		 *
		 * Not the same moment as save_post: the post is saved first and the
		 * meta follows, so anything that reads the meta has to wait for this
		 * rather than for WordPress.
		 *
		 * @param int    $post_id
		 * @param string $ad_id
		 * @param string $mode   created or updated.
		 */
		do_action( 'wmds_vehicle_stored', $post_id, $ad_id, $mode );

		return $mode;
	}

	/**
	 * @param int   $post_id
	 * @param array $meta
	 */
	private function write_meta( $post_id, array $meta ) {
		$written = array();

		foreach ( $meta as $key => $value ) {
			if ( '' === $value || null === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, wp_slash( $value ) );
				$written[] = (string) $key;
			}
		}

		// A feature the dealer took off the ad is not an empty value in the
		// new mapping, it is simply not there. What was written last time and
		// is missing now has to go, or the vehicle keeps its navigation
		// system forever.
		$before = get_post_meta( $post_id, self::META_KEYS, true );
		foreach ( array_diff( is_array( $before ) ? $before : array(), $written ) as $gone ) {
			delete_post_meta( $post_id, (string) $gone );
		}

		update_post_meta( $post_id, self::META_KEYS, $written );
	}

	/**
	 * Brings the attached pictures in line with what the ad shows.
	 *
	 * A picture that is already here is kept — mobile.de names each one by a
	 * hash, so an ad that gained one photo costs one download, not fifteen.
	 * Whatever the ad no longer shows is deleted, and only then: nothing is
	 * removed before its replacement is in place.
	 *
	 * @param int    $post_id
	 * @param array  $images Already capped to MAX_IMAGES by the caller.
	 * @param string $title
	 * @return array{hashes:string[],downloaded:int} Hashes now attached, in
	 *               order — shorter than $images when a download failed.
	 */
	private function sync_images( $post_id, array $images, $title ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$existing = array();
		$unnamed  = array();

		foreach ( get_attached_media( 'image', $post_id ) as $attachment ) {
			$hash = (string) get_post_meta( $attachment->ID, self::META_IMAGE_HASH, true );

			if ( '' === $hash || isset( $existing[ $hash ] ) ) {
				$unnamed[] = (int) $attachment->ID;
			} else {
				$existing[ $hash ] = (int) $attachment->ID;
			}
		}

		$kept       = array();
		$hashes     = array();
		$downloaded = 0;

		foreach ( array_values( $images ) as $index => $image ) {
			$hash = isset( $image['hash'] ) ? (string) $image['hash'] : '';

			if ( '' !== $hash && isset( $existing[ $hash ] ) ) {
				$attachment = $existing[ $hash ];
			} else {
				$attachment = $this->sideload( $post_id, $image, $title, $index );
				if ( ! $attachment ) {
					continue;
				}

				++$downloaded;
				if ( '' !== $hash ) {
					update_post_meta( $attachment, self::META_IMAGE_HASH, $hash );
				}
			}

			// The gallery reads the attachments by menu_order.
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
			// Nothing came through. The pictures already there are better
			// than none.
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
	 * @param int    $post_id
	 * @param array  $image
	 * @param string $title
	 * @param int    $index
	 * @return int Attachment ID, 0 when the picture could not be fetched.
	 */
	private function sideload( $post_id, array $image, $title, $index ) {
		$tmp = download_url( $image['url'], 30 );
		if ( is_wp_error( $tmp ) ) {
			/* translators: 1: image address, 2: the error message. */
			$this->log( sprintf( __( 'Image could not be downloaded: %1$s (%2$s)', 'wp-mobile-de-sync' ), $image['url'], $tmp->get_error_message() ) );

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
			$this->log( sprintf( __( 'Image could not be stored: %1$s (%2$s)', 'wp-mobile-de-sync' ), $image['url'], $attachment->get_error_message() ) );

			return 0;
		}

		return (int) $attachment;
	}

	/**
	 * The vehicles WordPress holds, by ad ID, in one query.
	 *
	 * Those in the bin come back separately. They are not part of the
	 * inventory: counted in, every sold vehicle would be a removal candidate
	 * again on each full sync until the bin is emptied, and enough of them
	 * trip the safety guard.
	 *
	 * @return array[] Two maps: the live vehicles, then the binned ones.
	 */
	private function known_map() {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_status, a.meta_value AS ad_id, COALESCE( m.meta_value, '' ) AS modified
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status != 'auto-draft'
				 ORDER BY p.ID DESC",
				self::META_AD_ID,
				self::META_MODIFIED,
				WMDS_CPT
			)
		);

		$live = array();
		$bin  = array();

		foreach ( (array) $rows as $row ) {
			if ( '' === (string) $row->ad_id ) {
				continue;
			}

			$entry = array(
				'post_id'  => (int) $row->ID,
				'modified' => (string) $row->modified,
			);

			if ( isset( $row->post_status ) && 'trash' === $row->post_status ) {
				$bin[ (string) $row->ad_id ] = $entry;
			} else {
				$live[ (string) $row->ad_id ] = $entry;
			}
		}

		return array( $live, $bin );
	}

	/**
	 * @param int $post_id
	 */
	public static function delete_attachments( $post_id ) {
		$post_id = (int) $post_id;

		if ( WMDS_CPT !== get_post_type( $post_id ) ) {
			return;
		}

		foreach ( get_attached_media( 'image', $post_id ) as $attachment ) {
			wp_delete_attachment( $attachment->ID, true );
		}
	}

	private function reschedule() {
		self::schedule_followup();
	}

	/**
	 * @param string $outcome As process() returns it.
	 * @return string The same, in words.
	 */
	private static function outcome_label( $outcome ) {
		$labels = array(
			'created' => __( 'created', 'wp-mobile-de-sync' ),
			'updated' => __( 'updated', 'wp-mobile-de-sync' ),
		);

		return isset( $labels[ $outcome ] ) ? $labels[ $outcome ] : (string) $outcome;
	}

	/** Books the next pass in a minute, unless one is booked already. */
	public static function schedule_followup() {
		if ( ! wp_next_scheduled( self::FOLLOWUP_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::FOLLOWUP_HOOK );
		}
	}

	/**
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
