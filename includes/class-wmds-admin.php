<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings screen: credentials, schedule diagnostics, manual actions, log.
 */
class WMDS_Admin {

	const PAGE  = 'wmds-settings';
	const NONCE = 'wmds_admin';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . WMDS_CPT,
			'mobile.de Sync',
			__( 'Sync Settings', 'wp-mobile-de-sync' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/* ------------------------------------------------------------------ *
	 *  Actions
	 * ------------------------------------------------------------------ */

	public static function handle() {
		if ( ! isset( $_POST['wmds_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( self::NONCE );

		$action = sanitize_key( wp_unslash( $_POST['wmds_action'] ) );

		if ( 'save' === $action ) {
			self::save();
		} elseif ( 'test' === $action ) {
			self::test();
		} elseif ( 'sync' === $action ) {
			self::sync();
		} elseif ( 'flush' === $action ) {
			WMDS_Refdata::flush();
			WMDS_Logos::flush();
			add_settings_error(
				'wmds',
				'flush',
				__( 'Reference data discarded. It will be fetched again on the next run.', 'wp-mobile-de-sync' ),
				'updated'
			);
		}
	}

	private static function save() {
		$values = array(
			'username'  => sanitize_text_field( wp_unslash( $_POST['wmds_username'] ?? '' ) ),
			'seller_id' => preg_replace( '/\D/', '', wp_unslash( $_POST['wmds_seller_id'] ?? '' ) ),
			'dealer'    => sanitize_text_field( wp_unslash( $_POST['wmds_dealer'] ?? '' ) ),
			'language'  => sanitize_key( wp_unslash( $_POST['wmds_language'] ?? '' ) ),
			'interval'  => sanitize_key( wp_unslash( $_POST['wmds_interval'] ?? 'wmds_15min' ) ),
		);

		// Do NOT run the password through sanitize_text_field: it strips angle
		// brackets and surrounding whitespace, silently breaking valid
		// passwords. An empty field means "leave unchanged", so the stored
		// value never has to appear in the form.
		$password = (string) wp_unslash( $_POST['wmds_password'] ?? '' );
		if ( '' !== $password ) {
			$values['password'] = $password;
		}

		WMDS_Settings::update( $values );
		self::resync_schedule();

		add_settings_error( 'wmds', 'saved', __( 'Settings saved.', 'wp-mobile-de-sync' ), 'updated' );
	}

	/** Apply an interval change to the scheduled cron event. */
	private static function resync_schedule() {
		$current = wp_get_schedule( WMDS_CRON_HOOK );
		$wanted  = WMDS_Settings::interval();

		if ( $current === $wanted ) {
			return;
		}

		wp_clear_scheduled_hook( WMDS_CRON_HOOK );
		wp_schedule_event( time() + 60, $wanted, WMDS_CRON_HOOK );
	}

	private static function test() {
		$result = WMDS_Settings::client()->search( 1, 1 );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'wmds',
				'test',
				sprintf(
					/* translators: %s: error message returned by the API. */
					__( 'Connection test failed: %s', 'wp-mobile-de-sync' ),
					$result->get_error_message()
				),
				'error'
			);
			return;
		}

		$message = sprintf(
			/* translators: %d: number of vehicles in the inventory. */
			__( 'Connection OK – %d vehicles in the inventory.', 'wp-mobile-de-sync' ),
			$result['total']
		);

		if ( ! empty( $result['capped'] ) ) {
			$message .= ' ' . sprintf(
				/* translators: %d: maximum number of ads reachable through pagination. */
				__( 'Warning: across paginated pages the API hands out at most %d vehicles, so a reconciliation would be incomplete.', 'wp-mobile-de-sync' ),
				WMDS_Client::PAGINATION_CAP
			);
		}

		add_settings_error( 'wmds', 'test', $message, 'updated' );
	}

	private static function sync() {
		@set_time_limit( 0 );

		$importer = new WMDS_Importer();
		$stats    = $importer->run( array( 'full' => true ) );

		if ( is_wp_error( $stats ) ) {
			add_settings_error(
				'wmds',
				'sync',
				sprintf(
					/* translators: %s: error message from the import run. */
					__( 'Run failed: %s', 'wp-mobile-de-sync' ),
					$stats->get_error_message()
				),
				'error'
			);
			return;
		}

		$message = sprintf(
			/* translators: 1: seen, 2: created, 3: updated, 4: skipped, 5: trashed, 6: images. */
			__( '%1$d seen · %2$d created · %3$d updated · %4$d skipped · %5$d trashed · %6$d images.', 'wp-mobile-de-sync' ),
			$stats['seen'],
			$stats['created'],
			$stats['updated'],
			$stats['skipped'],
			$stats['removed'],
			$stats['images']
		);

		if ( $stats['pending'] > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of vehicles still pending. */
				__( '%d vehicles still pending – the rest continues automatically. For an initial import <code>wp wmds sync --full --all</code> on the command line is the better route.', 'wp-mobile-de-sync' ),
				$stats['pending']
			);
		}

		add_settings_error( 'wmds', 'sync', $message, 'updated' );
	}

	/* ------------------------------------------------------------------ *
	 *  Output
	 * ------------------------------------------------------------------ */

	public static function render() {
		$last = get_option( WMDS_Importer::OPT_LAST_RUN, array() );
		$log  = get_option( WMDS_Importer::OPT_LOG, array() );
		$next = wp_next_scheduled( WMDS_CRON_HOOK );
		?>
		<div class="wrap">
			<h1>mobile.de Sync</h1>
			<?php settings_errors( 'wmds' ); ?>

			<?php if ( ! WMDS_Settings::is_configured() ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Not ready yet.', 'wp-mobile-de-sync' ); ?></strong>
						<?php esc_html_e( 'Username, password and the seller ID (or a dealer name instead) are missing. The automatic run starts once everything is stored.', 'wp-mobile-de-sync' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php self::render_cron_notice(); ?>

			<h2><?php esc_html_e( 'Credentials', 'wp-mobile-de-sync' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="wmds_action" value="save">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wmds_username">
								<?php esc_html_e( 'Username', 'wp-mobile-de-sync' ); ?>
								<span style="color:#d63638">*</span>
							</label>
						</th>
						<td>
							<input name="wmds_username" id="wmds_username" type="text" class="regular-text" required
								value="<?php echo esc_attr( WMDS_Settings::username() ); ?>">
							<p class="description"><?php esc_html_e( 'Your mobile.de API username.', 'wp-mobile-de-sync' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wmds_password">
								<?php esc_html_e( 'Password', 'wp-mobile-de-sync' ); ?>
								<span style="color:#d63638">*</span>
							</label>
						</th>
						<td>
							<input name="wmds_password" id="wmds_password" type="password" class="regular-text"
								autocomplete="new-password"
								placeholder="<?php echo WMDS_Settings::password() ? esc_attr__( '········ (stored)', 'wp-mobile-de-sync' ) : ''; ?>">
							<p class="description">
								<?php esc_html_e( 'Stored unencrypted in the WordPress database, as is usual for options. Leave empty to keep the stored password.', 'wp-mobile-de-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wmds_seller_id"><?php esc_html_e( 'Seller ID', 'wp-mobile-de-sync' ); ?></label></th>
						<td>
							<input name="wmds_seller_id" id="wmds_seller_id" type="text" class="regular-text"
								inputmode="numeric" placeholder="<?php esc_attr_e( 'e.g. 12345678', 'wp-mobile-de-sync' ); ?>"
								value="<?php echo esc_attr( WMDS_Settings::seller_id() ); ?>">
							<p class="description">
								<?php esc_html_e( 'The dealer\'s numeric identifier. The only route mobile.de documents for addressing an inventory, and therefore preferred.', 'wp-mobile-de-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wmds_dealer"><?php esc_html_e( 'Dealer name (vanity)', 'wp-mobile-de-sync' ); ?></label></th>
						<td>
							<input name="wmds_dealer" id="wmds_dealer" type="text" class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. EXAMPLEDEALER', 'wp-mobile-de-sync' ); ?>"
								value="<?php echo esc_attr( WMDS_Settings::dealer() ); ?>">
							<p class="description">
								<?php
								printf(
									/* translators: %s: the URL pattern home.mobile.de/NAME, already marked up. */
									esc_html__( 'Only needed when no seller ID is available. The name from %s. This parameter is undocumented and may disappear at any time.', 'wp-mobile-de-sync' ),
									'<code>home.mobile.de/<strong>NAME</strong></code>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wmds_interval"><?php esc_html_e( 'Interval', 'wp-mobile-de-sync' ); ?></label></th>
						<td>
							<select name="wmds_interval" id="wmds_interval">
								<?php
								$intervals = array(
									'wmds_5min'  => __( 'Every 5 minutes', 'wp-mobile-de-sync' ),
									'wmds_15min' => __( 'Every 15 minutes (recommended)', 'wp-mobile-de-sync' ),
									'wmds_30min' => __( 'Every 30 minutes', 'wp-mobile-de-sync' ),
									'wmds_60min' => __( 'Hourly', 'wp-mobile-de-sync' ),
								);
								foreach ( $intervals as $key => $label ) {
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $key ),
										selected( WMDS_Settings::interval(), $key, false ),
										esc_html( $label )
									);
								}
								?>
							</select>
							<p class="description">
								<?php esc_html_e( 'A run fetches only what changed and is therefore cheap. Once a day a full reconciliation runs in addition – only after one of those are sold vehicles moved to the trash.', 'wp-mobile-de-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wmds_language"><?php esc_html_e( 'Language', 'wp-mobile-de-sync' ); ?></label></th>
						<td>
							<input name="wmds_language" id="wmds_language" type="text" class="small-text"
								value="<?php echo esc_attr( WMDS_Settings::language() ); ?>">
							<p class="description">
								<?php esc_html_e( 'Language of the reference-data labels (transmission, fuel …). Defaults to the site language.', 'wp-mobile-de-sync' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save', 'wp-mobile-de-sync' ) ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Actions', 'wp-mobile-de-sync' ); ?></h2>
			<?php if ( WMDS_Settings::is_configured() ) : ?>
				<?php self::button( 'test', __( 'Test connection', 'wp-mobile-de-sync' ), 'secondary' ); ?>
				<?php self::button( 'sync', __( 'Sync now', 'wp-mobile-de-sync' ), 'primary' ); ?>
				<?php self::button( 'flush', __( 'Re-fetch reference data', 'wp-mobile-de-sync' ), 'secondary' ); ?>
				<p class="description" style="margin-top:8px">
					<?php
					printf(
						/* translators: %s: the WP-CLI command, already marked up. */
						esc_html__( 'For the initial import of a complete inventory the command line is the better route – there is no time limit there: %s', 'wp-mobile-de-sync' ),
						'<code>wp wmds sync --full --all</code>'
					);
					?>
				</p>
			<?php else : ?>
				<p><em><?php esc_html_e( 'Please save the credentials first.', 'wp-mobile-de-sync' ); ?></em></p>
			<?php endif; ?>

			<hr>
			<h2><?php esc_html_e( 'Status', 'wp-mobile-de-sync' ); ?></h2>
			<table class="widefat striped" style="max-width:700px">
				<tbody>
					<tr>
						<td style="width:40%"><?php esc_html_e( 'Next automatic run', 'wp-mobile-de-sync' ); ?></td>
						<td>
							<?php
							if ( $next ) {
								echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ), 'Y-m-d H:i' ) );
							} else {
								echo '<strong>' . esc_html__( 'not scheduled', 'wp-mobile-de-sync' ) . '</strong>';
							}
							?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Next run will be a', 'wp-mobile-de-sync' ); ?></td>
						<td>
							<?php
							echo get_option( WMDS_Importer::OPT_WATERMARK, '' )
								? esc_html__( 'incremental sync', 'wp-mobile-de-sync' )
								: esc_html__( 'full sync', 'wp-mobile-de-sync' );
							?>
						</td>
					</tr>
					<?php if ( $last ) : ?>
						<tr>
							<td><?php esc_html_e( 'Last run', 'wp-mobile-de-sync' ); ?></td>
							<td><?php echo esc_html( isset( $last['time'] ) ? $last['time'] : '–' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Result', 'wp-mobile-de-sync' ); ?></td>
							<td>
								<?php
								echo esc_html( sprintf(
									/* translators: 1: seen, 2: created, 3: updated, 4: skipped, 5: removed, 6: images. */
									__( '%1$d seen · %2$d created · %3$d updated · %4$d skipped · %5$d removed · %6$d images', 'wp-mobile-de-sync' ),
									isset( $last['seen'] ) ? $last['seen'] : 0,
									isset( $last['created'] ) ? $last['created'] : 0,
									isset( $last['updated'] ) ? $last['updated'] : 0,
									isset( $last['skipped'] ) ? $last['skipped'] : 0,
									isset( $last['removed'] ) ? $last['removed'] : 0,
									isset( $last['images'] ) ? $last['images'] : 0
								) );
								?>
							</td>
						</tr>
						<?php if ( ! empty( $last['pending'] ) ) : ?>
							<tr>
								<td><?php esc_html_e( 'Still pending', 'wp-mobile-de-sync' ); ?></td>
								<td>
									<?php
									echo esc_html( sprintf(
										/* translators: %d: number of vehicles still pending. */
										__( '%d vehicles', 'wp-mobile-de-sync' ),
										(int) $last['pending']
									) );
									?>
								</td>
							</tr>
						<?php endif; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( is_array( $log ) && $log ) : ?>
				<h2><?php esc_html_e( 'Log', 'wp-mobile-de-sync' ); ?></h2>
				<table class="widefat striped" style="max-width:900px">
					<thead>
						<tr>
							<th style="width:160px"><?php esc_html_e( 'Time', 'wp-mobile-de-sync' ); ?></th>
							<th><?php esc_html_e( 'Message', 'wp-mobile-de-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( array_slice( $log, 0, 20 ) as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['time'] ); ?></td>
							<td><?php echo esc_html( $entry['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Points out when the schedule is effectively not running.
	 *
	 * WP-Cron is triggered by page views. On a low-traffic dealer site "every
	 * 15 minutes" therefore means "eventually" - something that otherwise only
	 * surfaces when somebody reports a missing vehicle.
	 */
	private static function render_cron_notice() {
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$last     = get_option( WMDS_Importer::OPT_LAST_RUN, array() );
		$stale    = false;

		if ( ! empty( $last['time'] ) ) {
			$stale = ( strtotime( $last['time'] ) < time() - 6 * HOUR_IN_SECONDS );
		}

		if ( $disabled ) {
			echo '<div class="notice notice-info"><p>' . wp_kses_post( sprintf(
				/* translators: 1: DISABLE_WP_CRON, 2: wp cron event run --due-now, 3: wp-cron.php - all marked up as code. */
				__( '<strong>WP-Cron is disabled</strong> (%1$s). That is the recommended setting – provided a system cron calls %2$s or %3$s regularly. Without that, no sync happens at all.', 'wp-mobile-de-sync' ),
				'<code>DISABLE_WP_CRON</code>',
				'<code>wp cron event run --due-now</code>',
				'<code>wp-cron.php</code>'
			) ) . '</p></div>';
		} else {
			echo '<div class="notice notice-info"><p>' . wp_kses_post( sprintf(
				/* translators: %s: the DISABLE_WP_CRON define, already marked up as code. */
				__( '<strong>A note on scheduling.</strong> WP-Cron is triggered by page views, not by a clock. With little traffic on the site, runs are delayed accordingly. More reliable is %s plus a cron job at your host.', 'wp-mobile-de-sync' ),
				'<code>define( \'DISABLE_WP_CRON\', true );</code>'
			) ) . '</p></div>';
		}

		if ( $stale ) {
			echo '<div class="notice notice-warning"><p>' . wp_kses_post(
				__( '<strong>The last run was more than six hours ago.</strong> The schedule is most likely not being triggered.', 'wp-mobile-de-sync' )
			) . '</p></div>';
		}

		if ( get_transient( WMDS_Importer::LOCK ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'A sync is currently running.', 'wp-mobile-de-sync' ) . '</p></div>';
		}
	}

	/**
	 * @param string $action
	 * @param string $label
	 * @param string $type
	 */
	private static function button( $action, $label, $type ) {
		echo '<form method="post" style="display:inline-block;margin-right:8px">';
		wp_nonce_field( self::NONCE );
		printf( '<input type="hidden" name="wmds_action" value="%s">', esc_attr( $action ) );
		submit_button( $label, $type, 'submit', false );
		echo '</form>';
	}
}
