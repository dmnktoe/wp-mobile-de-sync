<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Tab_Schedule {
	/**
	 * @param array $status
	 */
	public static function render( array $status ) {
		WMDS_Admin_Ui::form_open( 'schedule' );
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Interval', 'wp-mobile-de-sync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wmds_interval"><?php esc_html_e( 'Run every', 'wp-mobile-de-sync' ); ?></label></th>
					<td>
						<select name="wmds_interval" id="wmds_interval">
							<?php
							$intervals = array(
								'wmds_5min'  => __( 'Every 5 minutes', 'wp-mobile-de-sync' ),
								'wmds_15min' => __( 'Every 15 minutes (recommended)', 'wp-mobile-de-sync' ),
								'wmds_30min' => __( 'Every 30 minutes', 'wp-mobile-de-sync' ),
								'wmds_60min' => __( 'Hourly', 'wp-mobile-de-sync' ),
								'daily'      => __( 'Once a day', 'wp-mobile-de-sync' ),
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
					<th scope="row"><label for="wmds_language"><?php esc_html_e( 'Label language', 'wp-mobile-de-sync' ); ?></label></th>
					<td>
						<select name="wmds_language" id="wmds_language">
							<option value="" <?php selected( '', (string) WMDS_Settings::get( 'language' ) ); ?>>
								<?php esc_html_e( 'Site language', 'wp-mobile-de-sync' ); ?>
							</option>
							<?php foreach ( self::languages() as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( (string) WMDS_Settings::get( 'language' ), $code ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Language of the reference-data labels (transmission, fuel, colours …). These are resolved at import time, so a change takes effect after the next full sync.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save', 'wp-mobile-de-sync' ) ); ?>
		</div>
		</form>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'How WordPress triggers this', 'wp-mobile-de-sync' ); ?></h2>
			<?php self::render_cron_explainer( $status ); ?>
		</div>
		<?php
	}

	/** @return array<string,string> */
	private static function languages() {
		return array(
			'de' => __( 'German', 'wp-mobile-de-sync' ),
			'en' => __( 'English', 'wp-mobile-de-sync' ),
			'fr' => __( 'French', 'wp-mobile-de-sync' ),
			'it' => __( 'Italian', 'wp-mobile-de-sync' ),
			'es' => __( 'Spanish', 'wp-mobile-de-sync' ),
			'nl' => __( 'Dutch', 'wp-mobile-de-sync' ),
			'pl' => __( 'Polish', 'wp-mobile-de-sync' ),
		);
	}

	/**
	 * @param array $status
	 */
	private static function render_cron_explainer( array $status ) {
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

		echo '<p>';
		if ( $disabled ) {
			echo wp_kses_post(
				sprintf(
					/* translators: 1: DISABLE_WP_CRON, 2: wp cron event run --due-now, 3: wp-cron.php - all marked up as code. */
					__( '<strong>WP-Cron is disabled</strong> (%1$s). That is the recommended setting – provided a system cron calls %2$s or %3$s regularly. Without that, no sync happens at all.', 'wp-mobile-de-sync' ),
					'<code>DISABLE_WP_CRON</code>',
					'<code>wp cron event run --due-now</code>',
					'<code>wp-cron.php</code>'
				)
			);
		} else {
			echo wp_kses_post(
				sprintf(
					/* translators: %s: the DISABLE_WP_CRON define, already marked up as code. */
					__( '<strong>WP-Cron is triggered by page views, not by a clock.</strong> With little traffic on the site, runs are delayed accordingly. More reliable is %s plus a cron job at your host.', 'wp-mobile-de-sync' ),
					'<code>define( \'DISABLE_WP_CRON\', true );</code>'
				)
			);
		}
		echo '</p>';

		printf(
			'<p>%s <strong>%s</strong></p>',
			esc_html__( 'Next scheduled run:', 'wp-mobile-de-sync' ),
			esc_html( $status['next'] ? WMDS_Status::local_time( $status['next'] ) : __( 'not scheduled', 'wp-mobile-de-sync' ) )
		);

		echo '<pre class="wmds-code">*/15 * * * * cd /path/to/site &amp;&amp; wp cron event run --due-now</pre>';
	}
}
