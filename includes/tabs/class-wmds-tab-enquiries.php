<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Tab_Enquiries {
	public static function render() {
		WMDS_Admin_Ui::form_open( 'enquiries' );
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'The form on a vehicle page', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'A visitor can send an enquiry straight from the detail page. It reaches you by e-mail with the vehicle it is about, and is filed under Vehicles → Enquiries so nothing is lost when the mail is not delivered.', 'wp-mobile-de-sync' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enquiry form', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_enquiry_enabled" value="yes"
								<?php checked( 'no' !== WMDS_Settings::get( 'enquiry_enabled', 'yes' ) ); ?>>
							<?php esc_html_e( 'Show it on every vehicle page', 'wp-mobile-de-sync' ); ?>
						</label>
						<p class="description">
							<?php
							printf(
								/* translators: %s: the shortcode, already marked up. */
								esc_html__( 'Switched off, the form appears only where %s is placed.', 'wp-mobile-de-sync' ),
								'<code>[vehicle-enquiry]</code>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wmds_enquiry_recipient"><?php esc_html_e( 'Send to', 'wp-mobile-de-sync' ); ?></label>
					</th>
					<td>
						<input name="wmds_enquiry_recipient" id="wmds_enquiry_recipient" type="text" class="regular-text"
							placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"
							value="<?php echo esc_attr( (string) WMDS_Settings::get( 'enquiry_recipient', '' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Several addresses separated by commas. Empty means the site administrator.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Also to the seller', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_enquiry_copy_seller" value="yes"
								<?php checked( 'yes' === WMDS_Settings::get( 'enquiry_copy_seller', 'no' ) ); ?>>
							<?php esc_html_e( 'Copy the address the feed carries for that vehicle', 'wp-mobile-de-sync' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Confirmation', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_enquiry_autoreply" value="yes"
								<?php checked( 'yes' === WMDS_Settings::get( 'enquiry_autoreply', 'no' ) ); ?>>
							<?php esc_html_e( 'Send the enquirer a copy of what they wrote', 'wp-mobile-de-sync' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Keep a record', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_enquiry_store" value="yes"
								<?php checked( 'no' !== WMDS_Settings::get( 'enquiry_store', 'yes' ) ); ?>>
							<?php esc_html_e( 'File every enquiry in the database', 'wp-mobile-de-sync' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Without this an enquiry exists only as the e-mail it was sent as. If that e-mail fails, it is gone.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wmds_enquiry_consent"><?php esc_html_e( 'Privacy notice', 'wp-mobile-de-sync' ); ?></label>
					</th>
					<td>
						<textarea name="wmds_enquiry_consent" id="wmds_enquiry_consent" rows="3" class="large-text"><?php echo esc_textarea( (string) WMDS_Settings::get( 'enquiry_consent', '' ) ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Shown next to a checkbox the visitor has to tick. Leave empty to ask for no consent. A link is allowed.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'Save', 'wp-mobile-de-sync' ) ); ?>
		</form>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'What has come in', 'wp-mobile-de-sync' ); ?></h2>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . WMDS_Leads::CPT ) ); ?>">
					<?php esc_html_e( 'Open the enquiries', 'wp-mobile-de-sync' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
