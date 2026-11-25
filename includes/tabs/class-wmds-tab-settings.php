<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Tab_Settings {
	/** @return string[] */
	public static function fields() {
		return array(
			'username',
			'password',
			'seller_id',
			'dealer',
			'interval',
			'language',
			'slug',
			'similar_enabled',
			'alerts_enabled',
			'alerts_recipient',
			'alerts_cooldown',
			'alerts_weekly',
		);
	}

	/** @param array $status */
	public static function render( array $status ) {
		WMDS_Admin_Ui::form_open( 'settings' );

		self::credentials();
		self::inventory();
		self::schedule( $status );
		self::website();
		self::alerts();

		submit_button( __( 'Save settings', 'wp-mobile-de-sync' ) );
		echo '</form>';

		if ( WMDS_Settings::is_configured() ) {
			self::forget();
		}
	}

	/** @return array<string,string> */
	public static function languages() {
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

	private static function credentials() {
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'API credentials', 'wp-mobile-de-sync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wmds_username"><?php esc_html_e( 'Username', 'wp-mobile-de-sync' ); ?> <span class="wmds-required">*</span></label>
					</th>
					<td>
						<input name="wmds_username" id="wmds_username" type="text" class="regular-text"
							value="<?php echo esc_attr( WMDS_Settings::username() ); ?>">
						<p class="description"><?php esc_html_e( 'Your mobile.de API username.', 'wp-mobile-de-sync' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wmds_password"><?php esc_html_e( 'Password', 'wp-mobile-de-sync' ); ?> <span class="wmds-required">*</span></label>
					</th>
					<td>
						<span class="wmds-password">
							<input name="wmds_password" id="wmds_password" type="password" class="regular-text"
								autocomplete="new-password"
								placeholder="<?php echo WMDS_Settings::password() ? esc_attr__( '········ (stored)', 'wp-mobile-de-sync' ) : ''; ?>">
							<button type="button" class="button button-secondary wmds-toggle-password"
								data-target="wmds_password"
								aria-label="<?php esc_attr_e( 'Show password', 'wp-mobile-de-sync' ); ?>">
								<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
							</button>
						</span>
						<p class="description">
							<?php esc_html_e( 'Stored unencrypted in the WordPress database, as is usual for options. Leave empty to keep the stored password.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	private static function inventory() {
		$mode = ( '' === WMDS_Settings::seller_id() && '' !== WMDS_Settings::dealer() ) ? 'dealer' : 'id';
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Which inventory', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Use only one of the two options. Saving clears the other.', 'wp-mobile-de-sync' ); ?>
			</p>

			<fieldset class="wmds-seller-modes">
				<label class="wmds-mode">
					<input type="radio" name="wmds_seller_mode" value="id" <?php checked( $mode, 'id' ); ?>>
					<span><strong><?php esc_html_e( 'Seller ID', 'wp-mobile-de-sync' ); ?></strong>
						<em><?php esc_html_e( 'recommended', 'wp-mobile-de-sync' ); ?></em></span>
				</label>
				<div class="wmds-mode-body" data-wmds-mode="id">
					<input name="wmds_seller_id" id="wmds_seller_id" type="text" class="regular-text"
						inputmode="numeric" placeholder="<?php esc_attr_e( 'e.g. 12345678', 'wp-mobile-de-sync' ); ?>"
						value="<?php echo esc_attr( WMDS_Settings::seller_id() ); ?>">
					<p class="description">
						<?php esc_html_e( 'The dealer\'s numeric identifier. This is the method mobile.de documents for querying an inventory.', 'wp-mobile-de-sync' ); ?>
					</p>
				</div>

				<label class="wmds-mode">
					<input type="radio" name="wmds_seller_mode" value="dealer" <?php checked( $mode, 'dealer' ); ?>>
					<span><strong><?php esc_html_e( 'Dealer name (vanity)', 'wp-mobile-de-sync' ); ?></strong>
						<em><?php esc_html_e( 'undocumented', 'wp-mobile-de-sync' ); ?></em></span>
				</label>
				<div class="wmds-mode-body" data-wmds-mode="dealer">
					<input name="wmds_dealer" id="wmds_dealer" type="text" class="regular-text"
						placeholder="<?php esc_attr_e( 'e.g. EXAMPLEDEALER', 'wp-mobile-de-sync' ); ?>"
						value="<?php echo esc_attr( WMDS_Settings::dealer() ); ?>">
					<p class="description">
						<?php
						printf(
							/* translators: %s: the URL pattern home.mobile.de/NAME, already marked up. */
							esc_html__( 'The name from %s. This parameter is undocumented and may not be supported by mobile.de in the long term.', 'wp-mobile-de-sync' ),
							'<code>home.mobile.de/<strong>NAME</strong></code>'
						);
						?>
					</p>
				</div>
			</fieldset>
		</div>
		<?php
	}

	/** @param array $status */
	private static function schedule( array $status ) {
		$intervals = array(
			'wmds_5min'  => __( 'Every 5 minutes', 'wp-mobile-de-sync' ),
			'wmds_15min' => __( 'Every 15 minutes (recommended)', 'wp-mobile-de-sync' ),
			'wmds_30min' => __( 'Every 30 minutes', 'wp-mobile-de-sync' ),
			'wmds_60min' => __( 'Hourly', 'wp-mobile-de-sync' ),
			'daily'      => __( 'Once a day', 'wp-mobile-de-sync' ),
		);
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Synchronisation', 'wp-mobile-de-sync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wmds_interval"><?php esc_html_e( 'Run every', 'wp-mobile-de-sync' ); ?></label></th>
					<td>
						<select name="wmds_interval" id="wmds_interval">
							<?php foreach ( $intervals as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( WMDS_Settings::interval(), $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Each run retrieves the listings changed since the last one and checks whether mobile.de still lists as many vehicles as the site shows. If not, the run reads the whole inventory and removes the vehicles that are gone. A full sync also runs once a day.', 'wp-mobile-de-sync' ); ?>
						</p>
						<p class="description">
							<?php
							printf(
								'%s <strong>%s</strong>',
								esc_html__( 'Next scheduled run:', 'wp-mobile-de-sync' ),
								esc_html( $status['next'] ? WMDS_Status::local_time( $status['next'] ) : __( 'not scheduled', 'wp-mobile-de-sync' ) )
							);
							?>
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
							<?php esc_html_e( 'Language of the reference data labels (transmission, fuel, colours …). Labels are applied during import, so a change takes effect after the next full sync.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'How runs are triggered', 'wp-mobile-de-sync' ); ?></th>
					<td><?php self::cron_explainer(); ?></td>
				</tr>
			</table>
		</div>
		<?php
	}

	private static function cron_explainer() {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			echo '<p class="description">';
			echo wp_kses_post(
				sprintf(
					/* translators: 1: DISABLE_WP_CRON, 2: wp cron event run --due-now, 3: wp-cron.php - all marked up as code. */
					__( '<strong>WP-Cron is disabled</strong> (%1$s). This is the recommended setting, provided a system cron calls %2$s or %3$s regularly. Otherwise, no synchronisation takes place.', 'wp-mobile-de-sync' ),
					'<code>DISABLE_WP_CRON</code>',
					'<code>wp cron event run --due-now</code>',
					'<code>wp-cron.php</code>'
				)
			);
			echo '</p>';

			return;
		}

		echo '<p class="description">';
		echo wp_kses_post(
			sprintf(
				/* translators: %s: the DISABLE_WP_CRON define, already marked up as code. */
				__( '<strong>WP-Cron is triggered by page views, not by a timer.</strong> On sites with little traffic, runs are delayed accordingly. A more reliable option is %s together with a cron job at your host.', 'wp-mobile-de-sync' ),
				'<code>define( \'DISABLE_WP_CRON\', true );</code>'
			)
		);
		echo '</p>';
		echo '<pre class="wmds-code">*/15 * * * * cd /path/to/site &amp;&amp; wp cron event run --due-now</pre>';
	}

	private static function website() {
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Website', 'wp-mobile-de-sync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wmds_slug"><?php esc_html_e( 'Address', 'wp-mobile-de-sync' ); ?></label></th>
					<td>
						<code><?php echo esc_html( untrailingslashit( home_url() ) ); ?>/</code>
						<input type="text" name="wmds_slug" id="wmds_slug" class="regular-text code"
							value="<?php echo esc_attr( WMDS_Settings::slug() ); ?>"
							placeholder="<?php echo esc_attr( WMDS_Settings::DEFAULT_SLUG ); ?>">
						<code>/</code>
						<p class="description">
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: %s: the default slug. */
									esc_html__( 'The archive and all vehicle pages are available under this address. If empty, %s is used. After a change, links to the previous address no longer work.', 'wp-mobile-de-sync' ),
									'<code>' . esc_html( WMDS_Settings::DEFAULT_SLUG ) . '</code>'
								)
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Similar vehicles', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_similar_enabled" value="yes"
								<?php checked( WMDS_Settings::flag( 'similar_enabled' ) ); ?>>
							<?php esc_html_e( 'Show similar vehicles at the end of a vehicle page', 'wp-mobile-de-sync' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Vehicles of the same make first, then those closest in price.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	private static function alerts() {
		$cooldowns = array(
			3600  => __( 'At most once an hour', 'wp-mobile-de-sync' ),
			21600 => __( 'At most every six hours', 'wp-mobile-de-sync' ),
			43200 => __( 'At most every twelve hours', 'wp-mobile-de-sync' ),
			86400 => __( 'At most once a day', 'wp-mobile-de-sync' ),
		);
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Notifications about problems', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'When enabled, an e-mail is sent if a run fails or is overdue — once per problem, not for every run. An e-mail is also sent once the sync is working again.', 'wp-mobile-de-sync' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Notifications', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_alerts_enabled" value="yes"
								<?php checked( WMDS_Alerts::enabled() ); ?>>
							<?php esc_html_e( 'Send an e-mail notification when the sync encounters a problem', 'wp-mobile-de-sync' ); ?>
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
							value="<?php echo esc_attr( (string) WMDS_Settings::get( 'alerts_recipient' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Separate multiple addresses with commas. If empty, the site administrator\'s e-mail address is used.', 'wp-mobile-de-sync' ); ?>
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
							<?php esc_html_e( 'Applies when the same problem recurs. New problems and the recovery notice are sent immediately.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Weekly summary', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_alerts_weekly" value="yes"
								<?php checked( WMDS_Settings::flag( 'alerts_weekly' ) ); ?>>
							<?php esc_html_e( 'Send a weekly summary, even if there are no problems', 'wp-mobile-de-sync' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	private static function forget() {
		?>
		<div class="wmds-card wmds-danger-card">
			<h2><?php esc_html_e( 'Delete credentials', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Removes the username, password and seller ID or dealer name from the database. Vehicles already imported are kept.', 'wp-mobile-de-sync' ); ?>
			</p>
			<?php
			WMDS_Admin_Ui::action_button(
				'forget',
				__( 'Delete credentials', 'wp-mobile-de-sync' ),
				'delete',
				__( 'Delete the stored credentials?', 'wp-mobile-de-sync' )
			);
			?>
		</div>
		<?php
	}
}
