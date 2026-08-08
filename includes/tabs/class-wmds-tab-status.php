<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Tab_Status {
	/**
	 * @param array $status
	 */
	public static function render( array $status ) {
		$log = get_option( WMDS_Importer::OPT_LOG, array() );
		$log = is_array( $log ) ? $log : array();

		self::render_alerts_card();
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Last run', 'wp-mobile-de-sync' ); ?></h2>
			<?php if ( ! $status['last'] ) : ?>
				<p><em><?php esc_html_e( 'No run has completed yet.', 'wp-mobile-de-sync' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped wmds-table">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Time', 'wp-mobile-de-sync' ); ?></td>
							<td><?php WMDS_Admin_Ui::print_time( $status['last_ts'] ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Type', 'wp-mobile-de-sync' ); ?></td>
							<td>
								<?php
								echo esc_html(
									! empty( $status['last']['full'] )
										? __( 'full reconciliation', 'wp-mobile-de-sync' )
										: __( 'incremental', 'wp-mobile-de-sync' )
								);
								?>
							</td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Result', 'wp-mobile-de-sync' ); ?></td>
							<td><?php echo esc_html( WMDS_Status::summarise( $status['last'] ) ); ?></td>
						</tr>
						<?php if ( ! empty( $status['last']['failed'] ) ) : ?>
							<tr class="wmds-row-warning">
								<td><?php esc_html_e( 'Failed', 'wp-mobile-de-sync' ); ?></td>
								<td>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: number of vehicles that could not be processed. */
											__( '%d vehicles could not be processed', 'wp-mobile-de-sync' ),
											(int) $status['last']['failed']
										)
									);
									?>
								</td>
							</tr>
						<?php endif; ?>
						<?php if ( $status['pending'] > 0 ) : ?>
							<tr>
								<td><?php esc_html_e( 'Still queued', 'wp-mobile-de-sync' ); ?></td>
								<td>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: number of vehicles still pending. */
											__( '%d vehicles', 'wp-mobile-de-sync' ),
											$status['pending']
										)
									);
									?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="wmds-card">
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
							__( 'Clear the log?', 'wp-mobile-de-sync' )
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
					<?php foreach ( array_slice( $log, 0, 50 ) as $entry ) : ?>
						<tr class="<?php echo self::is_problem( $entry['message'] ) ? 'wmds-log-problem' : ''; ?>">
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

	private static function render_alerts_card() {
		$cooldowns = array(
			3600  => __( 'At most once an hour', 'wp-mobile-de-sync' ),
			21600 => __( 'At most every six hours', 'wp-mobile-de-sync' ),
			43200 => __( 'At most every twelve hours', 'wp-mobile-de-sync' ),
			86400 => __( 'At most once a day', 'wp-mobile-de-sync' ),
		);

		WMDS_Admin_Ui::form_open( 'status' );
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Tell me when something is wrong', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'A sync that stops is invisible until somebody looks at this screen. Switched on, a failed or overdue run is sent by e-mail — once, not once per run — and so is the moment it works again.', 'wp-mobile-de-sync' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Notifications', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_alerts_enabled" value="yes"
								<?php checked( WMDS_Alerts::enabled() ); ?>>
							<?php esc_html_e( 'Send an e-mail when the sync needs attention', 'wp-mobile-de-sync' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wmds_alerts_recipient"><?php esc_html_e( 'Send to', 'wp-mobile-de-sync' ); ?></label>
					</th>
					<td>
						<input name="wmds_alerts_recipient" id="wmds_alerts_recipient" type="text" class="regular-text"
							placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"
							value="<?php echo esc_attr( (string) WMDS_Settings::get( 'alerts_recipient', '' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Several addresses separated by commas. Empty means the site administrator.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wmds_alerts_cooldown"><?php esc_html_e( 'How often at most', 'wp-mobile-de-sync' ); ?></label>
					</th>
					<td>
						<select name="wmds_alerts_cooldown" id="wmds_alerts_cooldown">
							<?php foreach ( $cooldowns as $seconds => $label ) : ?>
								<option value="<?php echo esc_attr( $seconds ); ?>"
									<?php selected( WMDS_Alerts::cooldown(), $seconds ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Applies to the same problem reported again. A different problem, and the recovery, are sent straight away.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Weekly summary', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_alerts_weekly" value="yes"
								<?php checked( 'yes' === WMDS_Settings::get( 'alerts_weekly', 'no' ) ); ?>>
							<?php esc_html_e( 'Once a week, send the state of the inventory even when nothing is wrong', 'wp-mobile-de-sync' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save', 'wp-mobile-de-sync' ), 'secondary' ); ?>
		</div>
		</form>

		<?php if ( WMDS_Alerts::enabled() ) : ?>
			<div class="wmds-card">
				<h2><?php esc_html_e( 'Does it arrive', 'wp-mobile-de-sync' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Sends the mail an alert would send right now, marked as a test. A WordPress installation that cannot send mail at all fails here rather than on the day it matters.', 'wp-mobile-de-sync' ); ?>
				</p>
				<?php WMDS_Admin_Ui::action_button( 'test-alert', __( 'Send a test alert', 'wp-mobile-de-sync' ), 'secondary' ); ?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * @param string $message
	 * @return bool
	 */
	private static function is_problem( $message ) {
		foreach ( array( 'Aborted', 'Warning', 'failed', 'could not' ) as $needle ) {
			if ( false !== stripos( $message, $needle ) ) {
				return true;
			}
		}

		return false;
	}
}
