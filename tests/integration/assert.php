<?php
$GLOBALS['wmds_it_failures'] = array();

/**
 * @param string $label
 * @param bool   $condition
 * @param string $detail
 */
function wmds_it_ok( $label, $condition, $detail = '' ) {
	if ( $condition ) {
		WP_CLI::log( '   ok    ' . $label );
		return;
	}

	$GLOBALS['wmds_it_failures'][] = $label . ( '' !== $detail ? ' - ' . $detail : '' );
	WP_CLI::log( '   FAIL  ' . $label . ( '' !== $detail ? ' - ' . $detail : '' ) );
}

/**
 * @param string $label
 * @param mixed  $expected
 * @param mixed  $actual
 */
function wmds_it_same( $label, $expected, $actual ) {
	wmds_it_ok(
		$label,
		$expected === $actual,
		'expected ' . wp_json_encode( $expected ) . ', got ' . wp_json_encode( $actual )
	);
}

/**
 * @param string $status
 * @return array Ad ID => WP_Post
 */
function wmds_it_posts( $status = 'publish' ) {
	$found = array();

	$posts = get_posts(
		array(
			'post_type'   => WMDS_CPT,
			'post_status' => $status,
			'numberposts' => 50,
			'orderby'     => 'ID',
			'order'       => 'ASC',
		)
	);

	foreach ( $posts as $post ) {
		$found[ (string) get_post_meta( $post->ID, WMDS_Importer::META_AD_ID, true ) ] = $post;
	}

	return $found;
}

/**
 * @param int $post_id
 * @return int[]
 */
function wmds_it_attachment_ids( $post_id ) {
	$ids = array();
	foreach ( get_attached_media( 'image', $post_id ) as $attachment ) {
		$ids[] = (int) $attachment->ID;
	}
	sort( $ids );

	return $ids;
}

/** @return array */
function wmds_it_snapshot() {
	$file = rtrim( (string) getenv( 'WMDS_MOCK_STATE' ), '/' ) . '/snapshot.json';
	if ( ! is_readable( $file ) ) {
		return array();
	}

	$data = json_decode( (string) file_get_contents( $file ), true );

	return is_array( $data ) ? $data : array();
}

/**
 * @param array $data
 */
function wmds_it_write_snapshot( array $data ) {
	$file = rtrim( (string) getenv( 'WMDS_MOCK_STATE' ), '/' ) . '/snapshot.json';
	file_put_contents( $file, wp_json_encode( $data ) );
}

/** @return string */
function wmds_it_german() {
	return 'de_DE';
}

/**
 * @param string $key
 * @return mixed
 */
function wmds_it_last_run( $key ) {
	$last = get_option( WMDS_Importer::OPT_LAST_RUN, array() );

	return isset( $last[ $key ] ) ? $last[ $key ] : null;
}

$wmds_it_phase = (string) getenv( 'WMDS_IT_PHASE' );

WP_CLI::log( '-- assertions: ' . $wmds_it_phase );

if ( 'unconfigured' === $wmds_it_phase ) {
	$status = WMDS_Status::get();

	wmds_it_same( 'level', WMDS_Status::UNCONFIGURED, $status['level'] );
	wmds_it_same( 'not configured', false, $status['configured'] );
	wmds_it_same( 'nothing imported yet', 0, $status['count'] );
	wmds_it_same( 'no incremental state', false, $status['incremental'] );
	wmds_it_ok( 'the state asks to be acted on', WMDS_Status::is_actionable( $status['level'] ) );
	wmds_it_ok( 'sync event scheduled by activation', false !== wp_next_scheduled( WMDS_CRON_HOOK ) );
} elseif ( 'full-sync' === $wmds_it_phase ) {
	$published = wmds_it_posts();

	wmds_it_same( 'two vehicles published', 2, count( $published ) );
	wmds_it_ok( 'ad 424776053 has a post', isset( $published['424776053'] ) );
	wmds_it_ok( 'ad 427312402 has a post', isset( $published['427312402'] ) );

	if ( isset( $published['424776053'] ) && isset( $published['427312402'] ) ) {
		$rover = $published['424776053'];
		$volvo = $published['427312402'];

		wmds_it_same( 'make resolved through refdata', 'Land Rover Defender 90 Standheizung Kamera LED Recaro', $rover->post_title );
		wmds_it_same( 'second title stays independent', 'Volvo V90 Kombi Navi Leder LED Kamera 1.Hand AHK', $volvo->post_title );

		wmds_it_same( 'modification date stored', '2026-07-28T18:29:13+02:00', get_post_meta( $rover->ID, WMDS_Importer::META_MODIFIED, true ) );
		wmds_it_ok( 'content hash stored', '' !== (string) get_post_meta( $rover->ID, WMDS_Importer::META_HASH, true ) );

		wmds_it_same( 'fuel label from refdata', 'Diesel', get_post_meta( $rover->ID, 'fuel', true ) );
		wmds_it_same( 'gearbox label from refdata', 'Manual', get_post_meta( $rover->ID, 'gearbox', true ) );
		wmds_it_same( 'category label from refdata', 'Off-Road/Pick-up', get_post_meta( $rover->ID, 'category', true ) );
		wmds_it_same( 'price formatted', '42,999', get_post_meta( $rover->ID, 'price', true ) );
		wmds_it_same( 'mileage formatted', '129,000', get_post_meta( $rover->ID, 'mileage', true ) );
		wmds_it_same( 'previous owners from the second ad', '1', get_post_meta( $volvo->ID, 'owners', true ) );

		wmds_it_same( 'seller name comes from the detail call', 'Musterhaendler GmbH', get_post_meta( $rover->ID, 'seller', true ) );
		wmds_it_ok( 'post content filled from the detail call', false !== strpos( $rover->post_content, 'intercooler' ) );
		wmds_it_ok(
			'CREOLE markup rendered to HTML',
			false !== strpos( (string) get_post_meta( $rover->ID, 'enriched_description', true ), '<strong>' )
		);
		wmds_it_ok(
			'list markup rendered for the second ad',
			false !== strpos( (string) get_post_meta( $volvo->ID, 'enriched_description', true ), '<li>' )
		);

		$rover_images = wmds_it_attachment_ids( $rover->ID );
		$volvo_images = wmds_it_attachment_ids( $volvo->ID );

		wmds_it_same( 'both images of the first ad imported', 2, count( $rover_images ) );
		wmds_it_same( 'both images of the second ad imported', 2, count( $volvo_images ) );

		wmds_it_same(
			'image hashes recorded in order',
			array( '99c3997d960b89eae1b66fb104c8d8a2', '626f22c8cd526fa930ad9542eb510f85' ),
			get_post_meta( $rover->ID, WMDS_Importer::META_IMAGES, true )
		);

		wmds_it_ok( 'featured image set', in_array( (int) get_post_thumbnail_id( $rover->ID ), $rover_images, true ) );

		$thumb_file = get_attached_file( (int) get_post_thumbnail_id( $rover->ID ) );
		wmds_it_ok( 'image file written to the uploads folder', $thumb_file && file_exists( $thumb_file ) && filesize( $thumb_file ) > 0, (string) $thumb_file );

		$meta = wp_get_attachment_metadata( (int) get_post_thumbnail_id( $rover->ID ) );
		wmds_it_same( 'attachment metadata generated', 160, isset( $meta['width'] ) ? (int) $meta['width'] : 0 );

		wmds_it_write_snapshot(
			array(
				'posts'  => array(
					'424776053' => (int) $rover->ID,
					'427312402' => (int) $volvo->ID,
				),
				'images' => array(
					'424776053' => $rover_images,
					'427312402' => $volvo_images,
				),
			)
		);
	}

	wmds_it_same( 'watermark advanced', '2026-07-28T20:06:51+00:00', (string) get_option( WMDS_Importer::OPT_WATERMARK, '' ) );
	wmds_it_same( 'run reported as full', true, wmds_it_last_run( 'full' ) );
	wmds_it_same( 'two vehicles seen', 2, wmds_it_last_run( 'seen' ) );
	wmds_it_same( 'two vehicles created', 2, wmds_it_last_run( 'created' ) );
	wmds_it_same( 'four images counted', 4, wmds_it_last_run( 'images' ) );
	wmds_it_same( 'nothing failed', 0, wmds_it_last_run( 'failed' ) );

	wmds_it_ok(
		'reference data cached in a transient',
		is_array( get_transient( WMDS_Refdata::PREFIX . 'en_' . md5( 'fuels' ) ) )
	);
	wmds_it_ok( 'sync event scheduled by activation', false !== wp_next_scheduled( WMDS_CRON_HOOK ) );
	wmds_it_ok( 'lock released', false === get_transient( WMDS_Importer::LOCK ) );

	$status = WMDS_Status::get();
	wmds_it_same( 'status level', WMDS_Status::OK, $status['level'] );
	wmds_it_same( 'status counts the inventory', 2, $status['count'] );
	wmds_it_same( 'status sees the watermark', true, $status['incremental'] );
	wmds_it_same( 'status reports no run in flight', false, $status['running'] );
	wmds_it_ok( 'status carries the next run', false !== $status['next'] );
	wmds_it_ok( 'the state needs no action', ! WMDS_Status::is_actionable( $status['level'] ) );
} elseif ( 'unchanged' === $wmds_it_phase ) {
	$snapshot  = wmds_it_snapshot();
	$published = wmds_it_posts();

	wmds_it_same( 'still two vehicles', 2, count( $published ) );
	wmds_it_same( 'incremental pass', false, wmds_it_last_run( 'full' ) );
	wmds_it_same( 'only the ad above the watermark seen', 1, wmds_it_last_run( 'seen' ) );
	wmds_it_same( 'it was skipped', 1, wmds_it_last_run( 'skipped' ) );
	wmds_it_same( 'nothing created', 0, wmds_it_last_run( 'created' ) );
	wmds_it_same( 'nothing updated', 0, wmds_it_last_run( 'updated' ) );
	wmds_it_same( 'nothing removed', 0, wmds_it_last_run( 'removed' ) );
	wmds_it_same( 'no image re-downloaded', 0, wmds_it_last_run( 'images' ) );

	foreach ( array( '424776053', '427312402' ) as $ad_id ) {
		wmds_it_same(
			'post ' . $ad_id . ' kept its ID',
			isset( $snapshot['posts'][ $ad_id ] ) ? (int) $snapshot['posts'][ $ad_id ] : 0,
			isset( $published[ $ad_id ] ) ? (int) $published[ $ad_id ]->ID : 0
		);
		wmds_it_same(
			'attachments of ' . $ad_id . ' untouched',
			isset( $snapshot['images'][ $ad_id ] ) ? $snapshot['images'][ $ad_id ] : array(),
			isset( $published[ $ad_id ] ) ? wmds_it_attachment_ids( $published[ $ad_id ]->ID ) : array()
		);
	}
} elseif ( 'updated' === $wmds_it_phase ) {
	$snapshot  = wmds_it_snapshot();
	$published = wmds_it_posts();

	wmds_it_same( 'still two vehicles', 2, count( $published ) );
	wmds_it_same( 'one vehicle updated', 1, wmds_it_last_run( 'updated' ) );
	wmds_it_same( 'nothing created', 0, wmds_it_last_run( 'created' ) );

	if ( isset( $published['427312402'] ) ) {
		$volvo = $published['427312402'];

		wmds_it_same(
			'the same post was reused',
			isset( $snapshot['posts']['427312402'] ) ? (int) $snapshot['posts']['427312402'] : 0,
			(int) $volvo->ID
		);
		wmds_it_same( 'new title stored', 'Volvo V90 Facelift Edition', $volvo->post_title );
		wmds_it_same( 'new modification date stored', '2026-07-29T09:00:00+02:00', get_post_meta( $volvo->ID, WMDS_Importer::META_MODIFIED, true ) );
		wmds_it_same(
			'unchanged images were not imported again',
			isset( $snapshot['images']['427312402'] ) ? $snapshot['images']['427312402'] : array(),
			wmds_it_attachment_ids( $volvo->ID )
		);
	}

	wmds_it_same( 'watermark followed the change', '2026-07-29T06:59:00+00:00', (string) get_option( WMDS_Importer::OPT_WATERMARK, '' ) );
} elseif ( 'reloaded' === $wmds_it_phase ) {
	$snapshot  = wmds_it_snapshot();
	$published = wmds_it_posts();

	wmds_it_same( 'still two vehicles', 2, count( $published ) );

	if ( isset( $published['424776053'] ) ) {
		$rover = $published['424776053'];

		wmds_it_same(
			'the same post was reused',
			isset( $snapshot['posts']['424776053'] ) ? (int) $snapshot['posts']['424776053'] : 0,
			(int) $rover->ID
		);
		wmds_it_same( 'the freshly read title landed', 'Land Rover Defender 90 Works V8', $rover->post_title );
		wmds_it_same( 'the detail fields survived', 'Musterhaendler GmbH', get_post_meta( $rover->ID, 'seller', true ) );
		wmds_it_same(
			'unchanged images were not imported again',
			isset( $snapshot['images']['424776053'] ) ? $snapshot['images']['424776053'] : array(),
			wmds_it_attachment_ids( $rover->ID )
		);
	}

	wmds_it_same(
		'the other vehicle was left alone',
		'Volvo V90 Facelift Edition',
		isset( $published['427312402'] ) ? $published['427312402']->post_title : ''
	);

	$log   = get_option( WMDS_Importer::OPT_LOG, array() );
	$first = is_array( $log ) && $log ? (string) $log[0]['message'] : '';
	wmds_it_ok( 'the reload is in the log', false !== strpos( $first, 'Reloaded 424776053 on request' ), $first );

	wmds_it_same( 'the run statistics were not touched', 1, wmds_it_last_run( 'updated' ) );
} elseif ( 'removed' === $wmds_it_phase ) {
	$snapshot  = wmds_it_snapshot();
	$published = wmds_it_posts();
	$trashed   = wmds_it_posts( 'trash' );

	wmds_it_same( 'one vehicle left', 1, count( $published ) );
	wmds_it_ok( 'the remaining one is the first ad', isset( $published['424776053'] ) );
	wmds_it_same(
		'it kept its ID',
		isset( $snapshot['posts']['424776053'] ) ? (int) $snapshot['posts']['424776053'] : 0,
		isset( $published['424776053'] ) ? (int) $published['424776053']->ID : 0
	);
	wmds_it_ok( 'the withdrawn ad went to the trash', isset( $trashed['427312402'] ) );
	wmds_it_same( 'one removal reported', 1, wmds_it_last_run( 'removed' ) );
	wmds_it_same( 'full pass reported', true, wmds_it_last_run( 'full' ) );
	wmds_it_same(
		'watermark recomputed from what is left',
		'2026-07-28T16:28:13+00:00',
		(string) get_option( WMDS_Importer::OPT_WATERMARK, '' )
	);
} elseif ( 'api-error' === $wmds_it_phase ) {
	$snapshot = wmds_it_snapshot();

	wmds_it_same( 'the surviving vehicle is still published', 1, count( wmds_it_posts() ) );
	wmds_it_same( 'the trashed one stayed in the trash', 1, count( wmds_it_posts( 'trash' ) ) );
	wmds_it_same( 'watermark untouched', '2026-07-28T16:28:13+00:00', (string) get_option( WMDS_Importer::OPT_WATERMARK, '' ) );
	wmds_it_same( 'lock released after the failure', false, get_transient( WMDS_Importer::LOCK ) );

	$log   = get_option( WMDS_Importer::OPT_LOG, array() );
	$first = is_array( $log ) && $log ? (string) $log[0]['message'] : '';
	wmds_it_ok( 'failure written to the log', false !== strpos( $first, 'HTTP 500' ), $first );

	wmds_it_ok( 'snapshot still readable', isset( $snapshot['posts'] ) );
} elseif ( 'translation' === $wmds_it_phase ) {
	$mofile = WMDS_DIR . 'languages/wp-mobile-de-sync-de_DE.mo';

	wmds_it_ok( 'the compiled catalogue ships', file_exists( $mofile ), $mofile );

	unload_textdomain( 'wp-mobile-de-sync' );
	add_filter( 'locale', 'wmds_it_german' );
	load_plugin_textdomain( 'wp-mobile-de-sync', false, 'wp-mobile-de-sync/languages' );

	wmds_it_same( 'WordPress resolves the locale', 'de_DE', get_locale() );
	wmds_it_same( 'post type label', 'Fahrzeuge', __( 'Vehicles', 'wp-mobile-de-sync' ) );
	wmds_it_same( 'settings screen title', 'Sync-Einstellungen', __( 'Sync Settings', 'wp-mobile-de-sync' ) );
	wmds_it_same( 'an untranslated string falls through', 'wmds nonexistent', __( 'wmds nonexistent', 'wp-mobile-de-sync' ) );

	remove_filter( 'locale', 'wmds_it_german' );
} elseif ( 'uninstalled' === $wmds_it_phase ) {
	$active = (array) get_option( 'active_plugins', array() );
	wmds_it_ok(
		'the plugin is deactivated',
		! in_array( 'wp-mobile-de-sync/wp-mobile-de-sync.php', $active, true )
	);

	foreach ( array( 'wmds_settings', 'wmds_last_run', 'wmds_watermark', 'wmds_log', 'wmds_dashboard' ) as $option ) {
		wmds_it_same( 'option ' . $option . ' removed', false, get_option( $option, false ) );
	}

	foreach ( array( 'wmds_import_lock', 'wmds_makes', 'wmds_notices_probe' ) as $transient ) {
		wmds_it_same( 'transient ' . $transient . ' removed', false, get_transient( $transient ) );
	}

	wmds_it_same(
		'the reference data cache is gone',
		false,
		get_transient( 'wmds_refdata_en_' . md5( 'fuels' ) )
	);

	$admin = get_user_by( 'login', 'admin' );
	wmds_it_same(
		'the dismissed notice is forgotten',
		'',
		$admin ? (string) get_user_meta( $admin->ID, 'wmds_dismissed', true ) : 'no admin user'
	);

	foreach ( array( 'wmds_import_event', 'wmds_full_sync_event' ) as $hook ) {
		wmds_it_same( 'event ' . $hook . ' unscheduled', false, wp_next_scheduled( $hook ) );
	}

	$vehicles = get_posts(
		array(
			'post_type'   => 'fahrzeuge',
			'post_status' => 'publish',
			'numberposts' => 50,
		)
	);

	wmds_it_same( 'the vehicles stayed', 1, count( $vehicles ) );
	wmds_it_same(
		'with their meta',
		'424776053',
		$vehicles ? (string) get_post_meta( $vehicles[0]->ID, 'mobileAdId', true ) : ''
	);
	wmds_it_ok(
		'and their images',
		$vehicles && count( get_attached_media( 'image', $vehicles[0]->ID ) ) > 0
	);
} else {
	WP_CLI::error( 'Unknown phase: ' . $wmds_it_phase );
}

if ( $GLOBALS['wmds_it_failures'] ) {
	WP_CLI::error( count( $GLOBALS['wmds_it_failures'] ) . ' assertion(s) failed in phase ' . $wmds_it_phase . '.' );
}

WP_CLI::success( 'Phase ' . $wmds_it_phase . ' passed.' );
