<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Tab_Tools {
	/**
	 * @param array $status
	 */
	public static function render( array $status ) {
		if ( ! $status['configured'] ) {
			printf(
				'<div class="wmds-card"><p><em>%s</em> <a href="%s">%s</a></p></div>',
				esc_html__( 'Store the credentials first.', 'wp-mobile-de-sync' ),
				esc_url( WMDS_Status::settings_url( 'connection' ) ),
				esc_html__( 'Go to the connection settings', 'wp-mobile-de-sync' )
			);
			return;
		}
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

		<div class="wmds-card">
			<h2><?php esc_html_e( 'Import', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Runs the sync in batches of 20 vehicles and keeps going until nothing is left. Leaving this page stops it — the schedule picks the rest up on its own.', 'wp-mobile-de-sync' ); ?>
			</p>
			<p>
				<button type="button" class="button button-primary" id="wmds-sync">
					<?php esc_html_e( 'Sync now', 'wp-mobile-de-sync' ); ?>
				</button>
			</p>
			<div class="wmds-progress" id="wmds-progress" hidden>
				<div class="wmds-progress-bar"><span id="wmds-progress-fill"></span></div>
				<p class="wmds-progress-text" id="wmds-progress-text" role="status" aria-live="polite"></p>
			</div>
			<div class="wmds-result" id="wmds-sync-result" role="status" aria-live="polite"></div>
			<noscript>
				<?php WMDS_Admin_Ui::action_button( 'sync', __( 'Sync now', 'wp-mobile-de-sync' ), 'primary' ); ?>
			</noscript>
			<?php
			$locked_since = WMDS_Importer::locked_since();
			if ( $locked_since ) :
				?>
				<div class="wmds-result wmds-result-error">
					<p>
						<?php
						printf(
							/* translators: %s: how long ago the running import started, e.g. "20 mins". */
							esc_html__( 'An import has held the lock since %s. A run that was cut short by a PHP time limit leaves it behind; releasing it lets the next run start.', 'wp-mobile-de-sync' ),
							esc_html( WMDS_Status::relative_time( $locked_since ) )
						);
						?>
					</p>
					<?php
					WMDS_Admin_Ui::action_button(
						'unlock',
						__( 'Release the lock', 'wp-mobile-de-sync' ),
						'secondary',
						__( 'Release the lock only once you are sure no import is still running. Two runs writing the same vehicles at once will conflict. Continue?', 'wp-mobile-de-sync' )
					);
					?>
				</div>
			<?php endif; ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: the WP-CLI command, already marked up. */
					esc_html__( 'For the very first import of a large inventory the command line has no time limit and is the better route: %s', 'wp-mobile-de-sync' ),
					'<code>wp wmds sync --full --all</code>'
				);
				?>
			</p>
		</div>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'Maintenance', 'wp-mobile-de-sync' ); ?></h2>
			<table class="wmds-tools">
				<tbody>
					<tr>
						<td>
							<strong><?php esc_html_e( 'Force a full sync', 'wp-mobile-de-sync' ); ?></strong>
							<p class="description">
								<?php esc_html_e( 'Discards the change marker. The next run reads every ad again instead of only the changed ones, and it is the first one allowed to remove sold vehicles.', 'wp-mobile-de-sync' ); ?>
							</p>
						</td>
						<td>
							<?php
							WMDS_Admin_Ui::action_button(
								'full',
								__( 'Force', 'wp-mobile-de-sync' ),
								'secondary',
								__( 'This discards the change marker, so the next run re-reads the whole inventory. Continue?', 'wp-mobile-de-sync' )
							);
							?>
						</td>
					</tr>
					<tr>
						<td>
							<strong><?php esc_html_e( 'Re-fetch reference data', 'wp-mobile-de-sync' ); ?></strong>
							<p class="description">
								<?php esc_html_e( 'Throws away the cached labels for transmission, fuel and colours, plus the logo index.', 'wp-mobile-de-sync' ); ?>
							</p>
						</td>
						<td>
							<?php WMDS_Admin_Ui::action_button( 'flush', __( 'Discard', 'wp-mobile-de-sync' ), 'secondary' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
