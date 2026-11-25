<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Importer {
	const META_AD_ID    = 'mobileAdId';
	const META_MODIFIED = '_wmds_modified';
	const META_HASH     = '_wmds_hash';
	const META_KEYS     = '_wmds_keys';
	const META_RETRIES  = '_wmds_image_retries';

	const OPT_LAST_RUN  = 'wmds_last_run';
	const OPT_WATERMARK = 'wmds_watermark';

	const LOCK          = 'wmds_import_lock';
	const LOCK_TTL      = 900;
	const FOLLOWUP_HOOK = 'wmds_import_followup';

	const BATCH         = 20;
	const IMAGE_RETRIES = 3;

	const BUDGET_SHARE = 0.6;
	const MAX_RUNTIME  = 300;
	const MIN_RUNTIME  = 20;

	/** @var WMDS_Client */
	private $client;
	/** @var WMDS_Json_Mapper */
	private $mapper;

	/** @var bool */
	private static $held = false;

	/**
	 * @param WMDS_Client|null      $client
	 * @param WMDS_Json_Mapper|null $mapper
	 */
	public function __construct( $client = null, $mapper = null ) {
		$this->client = $client ? $client : WMDS_Settings::client();
		$this->mapper = $mapper ? $mapper : new WMDS_Json_Mapper( new WMDS_Refdata( WMDS_Settings::language() ) );
	}

	/**
	 * @param array $args
	 * @return array|WP_Error
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

		if ( ! empty( $args['full'] ) || ! empty( $args['force'] ) ) {
			delete_option( self::OPT_WATERMARK );
		}
		if ( ! empty( $args['force'] ) ) {
			delete_post_meta_by_key( self::META_MODIFIED );
		}

		$switched = function_exists( 'switch_to_locale' ) && switch_to_locale( get_locale() );

		$stats = $this->execute(
			isset( $args['batch'] ) ? max( 1, (int) $args['batch'] ) : self::BATCH,
			time() + ( isset( $args['budget'] ) ? max( 0, (int) $args['budget'] ) : self::budget() ),
			! isset( $args['reschedule'] ) || $args['reschedule']
		);

		if ( $switched ) {
			restore_previous_locale();
		}

		$record = is_wp_error( $stats ) ? self::record_abort( $stats ) : $stats;

		self::unlock();

		do_action( 'wmds_run_finished', $record );

		return $stats;
	}

	/**
	 * @param WP_Error $error
	 * @return array
	 */
	private static function record_abort( $error ) {
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
			'error'   => $error->get_error_message(),
			'time'    => current_time( 'mysql' ),
		);

		update_option( self::OPT_LAST_RUN, $record, false );

		return $record;
	}

	/** @return int */
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
		if ( ! self::$held || ! self::locked_since() ) {
			return;
		}

		self::unlock();
		self::schedule_followup();
	}

	private static function keep_alive() {
		self::touch_lock();

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled on many hosts, the deadline of the pass covers that case.
		@set_time_limit( 0 );
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

	/** @return int */
	public static function locked_since() {
		$since = get_transient( self::LOCK );

		return is_numeric( $since ) ? (int) $since : 0;
	}

	/**
	 * @param int  $batch
	 * @param int  $deadline
	 * @param bool $reschedule
	 * @return array|WP_Error
	 */
	private function execute( $batch, $deadline = 0, $reschedule = true ) {
		list( $known, $trashed ) = $this->known_map();

		$inventory = $this->inventory( $known, $deadline );
		if ( is_wp_error( $inventory ) ) {
			return $inventory;
		}

		$plan   = $inventory['plan'];
		$counts = $this->process_batch( array_merge( $plan['create'], $plan['update'] ), $batch, $deadline, $known, $trashed );

		$complete  = ( 0 === $counts['pending'] ) && ! $inventory['truncated'];
		$reconcile = $inventory['full'] && ! $inventory['truncated'] && ! $inventory['capped'];
		$removed   = self::remove_gone( $plan['seen'], $known, $reconcile );

		if ( $complete && ! $counts['failed'] ) {
			self::advance_watermark( $inventory['ads'] );
		} elseif ( ! $complete && $reschedule ) {
			self::schedule_followup();
		}

		$stats = array(
			'seen'    => count( $plan['seen'] ),
			'created' => $counts['created'],
			'updated' => $counts['updated'],
			'skipped' => count( $plan['skip'] ),
			'removed' => $removed,
			'images'  => $counts['images'],
			'failed'  => $counts['failed'],
			'pending' => $counts['pending'],
			'full'    => $inventory['full'],
			'time'    => current_time( 'mysql' ),
		);

		update_option( self::OPT_LAST_RUN, $stats, false );

		WMDS_Log::write( self::describe_run( $stats ), $stats['failed'] ? WMDS_Log::PROBLEM : WMDS_Log::INFO );

		return $stats;
	}

	/**
	 * @param array $known
	 * @param int   $deadline
	 * @return array{ads:array[],capped:bool,truncated:bool,full:bool,plan:array}|WP_Error
	 */
	private function inventory( array $known, $deadline ) {
		$watermark = (string) get_option( self::OPT_WATERMARK, '' );
		$is_full   = ( '' === $watermark );

		$listing = $this->listing( $watermark, $deadline );
		if ( is_wp_error( $listing ) ) {
			return $listing;
		}

		$plan = WMDS_Sync_Plan::build( $listing['ads'], $known );

		if ( ! $is_full && $this->inventory_differs( count( $known ) + count( $plan['create'] ) ) ) {
			$is_full = true;
			$listing = $this->listing( '', $deadline );
			if ( is_wp_error( $listing ) ) {
				return $listing;
			}

			$plan = WMDS_Sync_Plan::build( $listing['ads'], $known );
		}

		if ( $listing['capped'] ) {
			WMDS_Log::problem(
				sprintf(
					/* translators: %d: the maximum number of vehicles the API returns. */
					__( 'Warning: the inventory exceeds the %d vehicles the API returns across paginated requests. This reconciliation is incomplete.', 'wp-mobile-de-sync' ),
					WMDS_Client::PAGINATION_CAP
				)
			);
		}

		$listing['full'] = $is_full;
		$listing['plan'] = $plan;

		return $listing;
	}

	/**
	 * @param array[] $todo
	 * @param int     $batch
	 * @param int     $deadline
	 * @param array   $known
	 * @param array   $trashed
	 * @return array{created:int,updated:int,failed:int,images:int,pending:int}
	 */
	private function process_batch( array $todo, $batch, $deadline, array $known, array $trashed ) {
		$counts = array(
			'created' => 0,
			'updated' => 0,
			'failed'  => 0,
			'images'  => 0,
			'pending' => max( 0, count( $todo ) - $batch ),
		);

		$todo = array_slice( $todo, 0, $batch );
		$done = 0;

		foreach ( $todo as $ad ) {
			if ( $deadline && $done && time() >= $deadline ) {
				$left               = count( $todo ) - $done;
				$counts['pending'] += $left;

				WMDS_Log::write(
					sprintf(
						/* translators: 1: vehicles processed, 2: vehicles left. */
						__( 'Pass stopped after %1$d vehicles because of the execution time limit; %2$d remaining.', 'wp-mobile-de-sync' ),
						$done,
						$left
					)
				);
				break;
			}

			self::keep_alive();

			++$done;

			$outcome = $this->process( $ad, $known, $counts['images'], null, $trashed );

			if ( 'created' === $outcome || 'updated' === $outcome ) {
				++$counts[ $outcome ];
			} else {
				++$counts['failed'];
			}
		}

		return $counts;
	}

	/**
	 * @param string[] $seen
	 * @param array    $known
	 * @param bool     $reconcile
	 * @return int
	 */
	private static function remove_gone( array $seen, array $known, $reconcile ) {
		$removal = WMDS_Sync_Plan::removals( $seen, $known, $reconcile );

		if ( '' !== $removal['abort'] ) {
			if ( $reconcile ) {
				WMDS_Log::problem( $removal['abort'] );
			}

			return 0;
		}

		$removed = 0;

		foreach ( $removal['remove'] as $post_id ) {
			if ( wp_trash_post( $post_id ) ) {
				WMDS_Images::release( $post_id );
				++$removed;
			}
		}

		return $removed;
	}

	/** @param array[] $ads */
	private static function advance_watermark( array $ads ) {
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
	}

	/**
	 * @param array $stats
	 * @return string
	 */
	public static function describe_run( array $stats ) {
		$summary = sprintf(
			/* translators: 1: vehicles seen, 2: created, 3: updated, 4: skipped, 5: removed, 6: images. */
			__( '%1$d seen, %2$d created, %3$d updated, %4$d skipped, %5$d removed, %6$d images', 'wp-mobile-de-sync' ),
			$stats['seen'],
			$stats['created'],
			$stats['updated'],
			$stats['skipped'],
			$stats['removed'],
			$stats['images']
		);

		if ( ! empty( $stats['failed'] ) ) {
			/* translators: %d: vehicles that could not be processed. */
			$summary .= sprintf( __( ', %d failed', 'wp-mobile-de-sync' ), $stats['failed'] );
		}

		if ( ! empty( $stats['pending'] ) ) {
			/* translators: %d: vehicles still to be processed. */
			$summary .= sprintf( __( ', %d still to process', 'wp-mobile-de-sync' ), $stats['pending'] );
		}

		return sprintf(
			/* translators: 1: kind of run, 2: the counts. */
			__( '%1$s: %2$s.', 'wp-mobile-de-sync' ),
			$stats['full'] ? __( 'Full sync', 'wp-mobile-de-sync' ) : __( 'Changes sync', 'wp-mobile-de-sync' ),
			$summary
		);
	}

	/**
	 * @param string $watermark
	 * @param int    $deadline
	 * @return array{ads:array[],capped:bool,truncated:bool}|WP_Error
	 */
	private function listing( $watermark, $deadline ) {
		$ads       = array();
		$page      = 1;
		$pages     = 1;
		$capped    = false;
		$truncated = false;
		$guard     = 40;

		do {
			self::keep_alive();

			$result = $this->client->search( $page, WMDS_Client::MAX_PAGE_SIZE, $watermark );
			if ( is_wp_error( $result ) ) {
				/* translators: 1: page number, 2: the error message. */
				WMDS_Log::problem( sprintf( __( 'Aborted on page %1$d: %2$s', 'wp-mobile-de-sync' ), $page, $result->get_error_message() ) );
				return $result;
			}

			$ads    = array_merge( $ads, $result['ads'] );
			$capped = $capped || ! empty( $result['capped'] );
			$pages  = max( 1, (int) $result['max_pages'] );
			++$page;

			if ( $deadline && $page <= $pages && $page <= $guard && time() >= $deadline ) {
				$truncated = true;

				WMDS_Log::write(
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

		return array(
			'ads'       => $ads,
			'capped'    => $capped,
			'truncated' => $truncated,
		);
	}

	/**
	 * @param int $expected
	 * @return bool
	 */
	private function inventory_differs( $expected ) {
		$total = $this->client->total();

		if ( ! is_int( $total ) || $total === (int) $expected ) {
			return false;
		}

		WMDS_Log::write(
			sprintf(
				/* translators: 1: vehicles listed on mobile.de, 2: vehicles on the site. */
				__( 'mobile.de lists %1$d vehicles, the site would hold %2$d. This pass reads the full inventory.', 'wp-mobile-de-sync' ),
				$total,
				(int) $expected
			)
		);

		return true;
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
			WMDS_Log::problem( sprintf( __( 'Reload failed for %1$s: %2$s', 'wp-mobile-de-sync' ), $ad_id, $detail->get_error_message() ) );
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
		WMDS_Log::write( sprintf( __( 'Reloaded %1$s on request: %2$s, %3$d images.', 'wp-mobile-de-sync' ), $ad_id, self::outcome_label( $outcome ), $images ) );

		return array(
			'outcome' => $outcome,
			'images'  => $images,
		);
	}

	/**
	 * @param array      $ad
	 * @param array      $known
	 * @param int        $images
	 * @param array|null $detail
	 * @param array      $trashed
	 * @return string
	 */
	private function process( array $ad, array $known, &$images, $detail = null, array $trashed = array() ) {
		$ad_id  = (string) $ad['mobileAdId'];
		$listed = isset( $ad['modificationDate'] ) ? (string) $ad['modificationDate'] : '';

		if ( null === $detail ) {
			$detail = $this->client->ad( $ad_id );
		}

		if ( is_wp_error( $detail ) ) {
			/* translators: 1: mobile.de ad ID, 2: the error message. */
			WMDS_Log::problem( sprintf( __( 'Detail call failed for %1$s: %2$s', 'wp-mobile-de-sync' ), $ad_id, $detail->get_error_message() ) );

			return 'error';
		}

		$mapped = $this->mapper->map( array_merge( $ad, (array) $detail ) );
		if ( '' === $mapped['ad_id'] ) {
			return 'error';
		}

		$saved = $this->save_post( $ad_id, $mapped, $known, $trashed );
		if ( ! $saved ) {
			return 'error';
		}

		list( $post_id, $mode ) = $saved;

		update_post_meta( $post_id, self::META_AD_ID, $ad_id );

		$hash = WMDS_Sync_Plan::content_hash( $mapped );
		if ( (string) get_post_meta( $post_id, self::META_HASH, true ) !== $hash ) {
			$this->write_meta( $post_id, $mapped['meta'] );
			update_post_meta( $post_id, self::META_HASH, $hash );
		}

		$complete = self::store_images( $post_id, $mapped, $images );

		self::settle( $post_id, $complete, '' !== $listed ? $listed : $mapped['modified'] );

		do_action( 'wmds_vehicle_stored', $post_id, $ad_id, $mode );

		return $mode;
	}

	/**
	 * @param string $ad_id
	 * @param array  $mapped
	 * @param array  $known
	 * @param array  $trashed
	 * @return array|null
	 */
	private function save_post( $ad_id, array $mapped, array $known, array $trashed ) {
		$post_id = isset( $known[ $ad_id ]['post_id'] ) ? (int) $known[ $ad_id ]['post_id'] : 0;
		$revived = false;

		if ( ! $post_id && isset( $trashed[ $ad_id ]['post_id'] ) && wp_untrash_post( (int) $trashed[ $ad_id ]['post_id'] ) ) {
			$post_id = (int) $trashed[ $ad_id ]['post_id'];
			$revived = true;
		}

		$mode    = $post_id ? 'updated' : 'created';
		$postarr = array(
			'post_type'    => WMDS_CPT,
			'post_title'   => $mapped['title'],
			'post_content' => $mapped['description'],
		);

		if ( ! $post_id || $revived ) {
			$postarr['post_status'] = 'publish';
		}

		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$result = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $result ) || ! $result ) {
			WMDS_Log::problem(
				is_wp_error( $result )
					/* translators: 1: mobile.de ad ID, 2: the error message. */
					? sprintf( __( 'Could not save the vehicle %1$s: %2$s', 'wp-mobile-de-sync' ), $ad_id, $result->get_error_message() )
					/* translators: %s: mobile.de ad ID. */
					: sprintf( __( 'Could not save the vehicle %s.', 'wp-mobile-de-sync' ), $ad_id )
			);

			return null;
		}

		return array( (int) $result, $mode );
	}

	/**
	 * @param int   $post_id
	 * @param array $mapped
	 * @param int   $images
	 * @return bool
	 */
	private static function store_images( $post_id, array $mapped, &$images ) {
		$wanted = array_slice( $mapped['images'], 0, WMDS_Images::MAX );
		$stored = get_post_meta( $post_id, WMDS_Images::META_LIST, true );

		if ( ! $wanted ) {
			if ( $stored ) {
				WMDS_Images::sync( $post_id, array(), $mapped['title'] );
				delete_post_meta( $post_id, WMDS_Images::META_LIST );
			}

			return true;
		}

		if ( ! WMDS_Sync_Plan::images_changed( $stored, $wanted ) && has_post_thumbnail( $post_id ) ) {
			return true;
		}

		$result  = WMDS_Images::sync( $post_id, $wanted, $mapped['title'] );
		$images += $result['downloaded'];

		update_post_meta( $post_id, WMDS_Images::META_LIST, $result['hashes'] );

		return count( $result['hashes'] ) === count( $wanted );
	}

	/**
	 * @param int    $post_id
	 * @param bool   $complete
	 * @param string $modified
	 */
	private static function settle( $post_id, $complete, $modified ) {
		$retries = $complete ? 0 : (int) get_post_meta( $post_id, self::META_RETRIES, true ) + 1;

		if ( $complete || $retries >= self::IMAGE_RETRIES ) {
			update_post_meta( $post_id, self::META_MODIFIED, wp_slash( $modified ) );
			delete_post_meta( $post_id, self::META_RETRIES );
		} else {
			delete_post_meta( $post_id, self::META_MODIFIED );
			update_post_meta( $post_id, self::META_RETRIES, $retries );
		}
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

		$before = get_post_meta( $post_id, self::META_KEYS, true );
		foreach ( array_diff( is_array( $before ) ? $before : array(), $written ) as $gone ) {
			delete_post_meta( $post_id, (string) $gone );
		}

		update_post_meta( $post_id, self::META_KEYS, $written );
	}

	/** @return array[] */
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
	 * @param string $outcome
	 * @return string
	 */
	private static function outcome_label( $outcome ) {
		$labels = array(
			'created' => __( 'created', 'wp-mobile-de-sync' ),
			'updated' => __( 'updated', 'wp-mobile-de-sync' ),
		);

		return isset( $labels[ $outcome ] ) ? $labels[ $outcome ] : (string) $outcome;
	}

	public static function schedule_followup() {
		if ( ! wp_next_scheduled( self::FOLLOWUP_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::FOLLOWUP_HOOK );
		}
	}
}
