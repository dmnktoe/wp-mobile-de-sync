<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Tab_Tools {
	/** @param array $status */
	public static function render( array $status ) {
		if ( $status['configured'] ) {
			self::connection();
		}

		self::maintenance();
		self::log();
		self::requirements();
		self::about();
	}

	private static function connection() {
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Connection', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Asks mobile.de for a single vehicle. Writes nothing.', 'wp-mobile-de-sync' ); ?></p>
			<p>
				<button type="button" class="button button-secondary" id="wmds-test">
					<?php esc_html_e( 'Test connection', 'wp-mobile-de-sync' ); ?>
				</button>
			</p>
			<div class="wmds-result" id="wmds-test-result" role="status" aria-live="polite"></div>
			<noscript>
				<?php WMDS_Admin_Ui::action_button( 'test', __( 'Test connection', 'wp-mobile-de-sync' ), 'secondary' ); ?>
			</noscript>
		</div>

		<?php
	}

	private static function maintenance() {
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Maintenance', 'wp-mobile-de-sync' ); ?></h2>
			<table class="wmds-tools">
				<tbody>
					<tr>
						<td>
							<strong><?php esc_html_e( 'Force a full sync', 'wp-mobile-de-sync' ); ?></strong>
							<p class="description">
								<?php esc_html_e( 'Resets the change marker. The next run reads all listings instead of only changed ones and removes sold vehicles.', 'wp-mobile-de-sync' ); ?>
							</p>
						</td>
						<td>
							<?php
							WMDS_Admin_Ui::action_button(
								'full',
								__( 'Force', 'wp-mobile-de-sync' ),
								'secondary',
								__( 'The change marker will be reset and the next run will read the entire inventory. Continue?', 'wp-mobile-de-sync' )
							);
							?>
						</td>
					</tr>
					<tr>
						<td>
							<strong><?php esc_html_e( 'Re-fetch reference data', 'wp-mobile-de-sync' ); ?></strong>
							<p class="description">
								<?php esc_html_e( 'Deletes the cached labels for transmission, fuel and colours, as well as the logo index.', 'wp-mobile-de-sync' ); ?>
							</p>
						</td>
						<td>
							<?php WMDS_Admin_Ui::action_button( 'flush', __( 'Discard', 'wp-mobile-de-sync' ), 'secondary' ); ?>
						</td>
					</tr>
					<?php $orphans = count( WMDS_Images::orphans() ); ?>
					<tr>
						<td>
							<strong><?php esc_html_e( 'Delete unused vehicle images', 'wp-mobile-de-sync' ); ?></strong>
							<p class="description">
								<?php
								echo esc_html(
									$orphans
										? sprintf(
											/* translators: %d: number of images. */
											_n(
												'%d picture of a vehicle that is no longer on the site is still in the media library. Pictures uploaded by hand are never touched.',
												'%d pictures of vehicles that are no longer on the site are still in the media library. Pictures uploaded by hand are never touched.',
												$orphans,
												'wp-mobile-de-sync'
											),
											$orphans
										)
										: __( 'The media library holds no pictures of vehicles that are no longer on the site.', 'wp-mobile-de-sync' )
								);
								?>
							</p>
						</td>
						<td>
							<?php
							if ( $orphans ) {
								WMDS_Admin_Ui::action_button(
									'clean-images',
									__( 'Delete', 'wp-mobile-de-sync' ),
									'secondary',
									__( 'Delete these pictures from the media library?', 'wp-mobile-de-sync' )
								);
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php WMDS_Admin_Ui::render_lock_notice(); ?>
		<?php
	}

	private static function log() {
		$log = WMDS_Log::entries();
		?>
		<div class="wmds-card" id="wmds-log">
			<div class="wmds-card-head">
				<h2><?php esc_html_e( 'Log', 'wp-mobile-de-sync' ); ?></h2>
				<?php if ( $log ) : ?>
					<div class="wmds-card-tools">
						<label class="wmds-log-filter">
							<input type="checkbox" id="wmds-log-errors-only">
							<?php esc_html_e( 'Problems only', 'wp-mobile-de-sync' ); ?>
						</label>
						<a class="button button-secondary" href="<?php echo esc_url( WMDS_Status::action_url( 'export-log' ) ); ?>">
							<?php esc_html_e( 'Download', 'wp-mobile-de-sync' ); ?>
						</a>
						<?php
						WMDS_Admin_Ui::action_button(
							'clear-log',
							__( 'Clear', 'wp-mobile-de-sync' ),
							'secondary',
							__( 'Clear the log?', 'wp-mobile-de-sync' ),
							'tools'
						);
						?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! $log ) : ?>
				<p><em><?php esc_html_e( 'The log is empty.', 'wp-mobile-de-sync' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped wmds-log">
					<thead>
						<tr>
							<th class="wmds-log-time"><?php esc_html_e( 'Time', 'wp-mobile-de-sync' ); ?></th>
							<th><?php esc_html_e( 'Message', 'wp-mobile-de-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $log as $entry ) : ?>
						<tr class="<?php echo WMDS_Log::is_problem( $entry ) ? 'wmds-log-problem' : ''; ?>">
							<td class="wmds-log-time">
								<?php WMDS_Admin_Ui::print_time( WMDS_Status::timestamp( $entry['time'] ), 'absolute', $entry['time'] ); ?>
							</td>
							<td><?php echo esc_html( $entry['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function requirements() {
		$checks = WMDS_Requirements::all();
		$tally  = WMDS_Requirements::tally();

		$grouped = array();
		foreach ( $checks as $check ) {
			$grouped[ $check['group'] ][] = $check;
		}
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Requirements', 'wp-mobile-de-sync' ); ?></h2>

			<?php if ( $tally['fail'] ) : ?>
				<p class="wmds-requirement-summary wmds-requirement-fail">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of failed requirement checks. */
							_n(
								'%d requirement is not met. Reliable synchronisation is not possible until it is resolved.',
								'%d requirements are not met. Reliable synchronisation is not possible until they are resolved.',
								$tally['fail'],
								'wp-mobile-de-sync'
							),
							$tally['fail']
						)
					);
					?>
				</p>
			<?php elseif ( $tally['warn'] ) : ?>
				<p class="wmds-requirement-summary wmds-requirement-warn">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of requirement checks worth a look. */
							_n(
								'All requirements are met. %d item should be reviewed.',
								'All requirements are met. %d items should be reviewed.',
								$tally['warn'],
								'wp-mobile-de-sync'
							),
							$tally['warn']
						)
					);
					?>
				</p>
			<?php else : ?>
				<p class="wmds-requirement-summary wmds-requirement-ok">
					<?php esc_html_e( 'Every check passed.', 'wp-mobile-de-sync' ); ?>
				</p>
			<?php endif; ?>

			<?php foreach ( $grouped as $group => $rows ) : ?>
				<h3><?php echo esc_html( $group ); ?></h3>
				<table class="widefat striped wmds-table wmds-requirements">
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr class="wmds-requirement-row-<?php echo esc_attr( $row['status'] ); ?>">
								<td class="wmds-requirement-label"><?php echo esc_html( $row['label'] ); ?></td>
								<td class="wmds-requirement-value">
									<span class="wmds-requirement-badge wmds-requirement-<?php echo esc_attr( $row['status'] ); ?>">
										<?php echo esc_html( WMDS_Admin_Ui::requirement_marker( $row['status'] ) ); ?>
									</span>
									<?php echo esc_html( $row['value'] ); ?>
									<?php if ( '' !== $row['hint'] ) : ?>
										<p class="description"><?php echo esc_html( $row['hint'] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the WordPress site health screen, already marked up. */
					esc_html__( 'WordPress provides further server information under %s.', 'wp-mobile-de-sync' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'site-health.php?tab=debug' ) ),
						esc_html__( 'Tools → Site Health → Info', 'wp-mobile-de-sync' )
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	private static function about() {
		$state  = WMDS_Updater::state();
		$newer  = '' !== $state['version'] && version_compare( $state['version'], WMDS_VERSION, '>' );
		$counts = wp_count_posts( WMDS_CPT );
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'mobile.de Sync', 'wp-mobile-de-sync' ); ?></h2>

			<p>
				<?php esc_html_e( 'Synchronises a dealer\'s inventory on mobile.de with this site. The mobile.de Search API is queried on a schedule, and each listing is created as a regular WordPress post, compatible with themes, FacetWP and the block editor.', 'wp-mobile-de-sync' ); ?>
			</p>

			<table class="widefat striped wmds-table">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Installed version', 'wp-mobile-de-sync' ); ?></td>
						<td>
							<?php echo esc_html( WMDS_VERSION ); ?>
							<?php if ( $newer ) : ?>
								<strong>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: version number of the available release. */
											__( '- version %s is available', 'wp-mobile-de-sync' ),
											$state['version']
										)
									);
									?>
								</strong>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Vehicles published', 'wp-mobile-de-sync' ); ?></td>
						<td><?php echo esc_html( isset( $counts->publish ) ? (string) (int) $counts->publish : '0' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Post type', 'wp-mobile-de-sync' ); ?></td>
						<td><code><?php echo esc_html( WMDS_CPT ); ?></code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Placing vehicles', 'wp-mobile-de-sync' ); ?></td>
						<td>
							<a href="<?php echo esc_url( WMDS_Status::settings_url( 'embed' ) ); ?>">
								<?php esc_html_e( 'Blocks, shortcodes and widgets', 'wp-mobile-de-sync' ); ?>
							</a>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Author', 'wp-mobile-de-sync' ); ?></td>
						<td>
							<a href="https://github.com/dmnktoe" target="_blank" rel="noopener noreferrer">Domenik Töfflinger</a>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Licence', 'wp-mobile-de-sync' ); ?></td>
						<td>GPL-2.0-or-later</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'Help and source', 'wp-mobile-de-sync' ); ?></h2>
			<ul class="wmds-links">
				<li>
					<a href="https://github.com/dmnktoe/wp-mobile-de-sync" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Source code on GitHub', 'wp-mobile-de-sync' ); ?>
					</a>
				</li>
				<li>
					<a href="https://github.com/dmnktoe/wp-mobile-de-sync/issues" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Report a problem', 'wp-mobile-de-sync' ); ?>
					</a>
				</li>
				<li>
					<a href="https://github.com/dmnktoe/wp-mobile-de-sync/releases" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Release notes', 'wp-mobile-de-sync' ); ?>
					</a>
				</li>
				<li>
					<a href="https://services.mobile.de/manual/search-api.html" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'mobile.de Search API documentation', 'wp-mobile-de-sync' ); ?>
					</a>
				</li>
			</ul>

			<p class="description">
				<?php esc_html_e( 'Not affiliated with mobile.de. Access to the Search API is arranged with mobile.de directly.', 'wp-mobile-de-sync' ); ?>
			</p>
		</div>
		<?php
	}
}
