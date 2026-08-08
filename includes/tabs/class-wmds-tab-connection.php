<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Tab_Connection {
	public static function render() {
		$mode = ( '' === WMDS_Settings::seller_id() && '' !== WMDS_Settings::dealer() ) ? 'dealer' : 'id';

		WMDS_Admin_Ui::form_open( 'connection' );
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'API credentials', 'wp-mobile-de-sync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wmds_username"><?php esc_html_e( 'Username', 'wp-mobile-de-sync' ); ?> <span class="wmds-required">*</span></label>
					</th>
					<td>
						<input name="wmds_username" id="wmds_username" type="text" class="regular-text" required
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

		<div class="wmds-card">
			<h2><?php esc_html_e( 'Which inventory', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Pick one route. Saving clears the other one, so what this form shows is what the plugin actually sends.', 'wp-mobile-de-sync' ); ?>
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
						<?php esc_html_e( 'The dealer\'s numeric identifier and the only route mobile.de documents for addressing an inventory.', 'wp-mobile-de-sync' ); ?>
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
							esc_html__( 'The name from %s. This parameter is undocumented and may disappear at any time.', 'wp-mobile-de-sync' ),
							'<code>home.mobile.de/<strong>NAME</strong></code>'
						);
						?>
					</p>
				</div>
			</fieldset>
		</div>

		<?php submit_button( __( 'Save', 'wp-mobile-de-sync' ) ); ?>
		</form>

		<?php if ( WMDS_Settings::is_configured() ) : ?>
			<div class="wmds-card wmds-danger-card">
				<h2><?php esc_html_e( 'Delete credentials', 'wp-mobile-de-sync' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Removes username, password and the addressing from the database. Already imported vehicles stay untouched.', 'wp-mobile-de-sync' ); ?>
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
		<?php endif; ?>
		<?php
	}
}
