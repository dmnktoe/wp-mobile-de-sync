<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Tab_Enquiries {
	/** @return string[] */
	public static function fields() {
		return array(
			'enquiry_enabled',
			'enquiry_recipient',
			'enquiry_copy_seller',
			'enquiry_autoreply',
			'enquiry_store',
			'enquiry_consent',
		);
	}

	public static function render() {
		self::received();

		WMDS_Admin_Ui::form_open( 'enquiries' );
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'The form on a vehicle page', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Visitors can send an enquiry directly from the vehicle page. You receive it by e-mail, including the vehicle concerned; it is also stored under Vehicles → Enquiries so that it is retained if the e-mail is not delivered.', 'wp-mobile-de-sync' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enquiry form', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_enquiry_enabled" value="yes"
								<?php checked( WMDS_Settings::flag( 'enquiry_enabled' ) ); ?>>
							<?php esc_html_e( 'Show it on every vehicle page', 'wp-mobile-de-sync' ); ?>
						</label>
						<p class="description">
							<?php
							printf(
								/* translators: %s: the shortcode, already marked up. */
								esc_html__( 'When disabled, the form only appears where %s is inserted.', 'wp-mobile-de-sync' ),
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
							value="<?php echo esc_attr( (string) WMDS_Settings::get( 'enquiry_recipient' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Separate multiple addresses with commas. If empty, the site administrator\'s e-mail address is used.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Also to the seller', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_enquiry_copy_seller" value="yes"
								<?php checked( WMDS_Settings::flag( 'enquiry_copy_seller' ) ); ?>>
							<?php esc_html_e( 'Send a copy to the address stored in the feed for this vehicle', 'wp-mobile-de-sync' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Confirmation', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_enquiry_autoreply" value="yes"
								<?php checked( WMDS_Settings::flag( 'enquiry_autoreply' ) ); ?>>
							<?php esc_html_e( 'Send the enquirer a copy of their message', 'wp-mobile-de-sync' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Keep a record', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_enquiry_store" value="yes"
								<?php checked( WMDS_Settings::flag( 'enquiry_store' ) ); ?>>
							<?php esc_html_e( 'Store every enquiry in the database', 'wp-mobile-de-sync' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Without this option, an enquiry exists only as an e-mail. If delivery fails, it is no longer available.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wmds_enquiry_consent"><?php esc_html_e( 'Privacy notice', 'wp-mobile-de-sync' ); ?></label>
					</th>
					<td>
						<textarea name="wmds_enquiry_consent" id="wmds_enquiry_consent" rows="3" class="large-text"><?php echo esc_textarea( (string) WMDS_Settings::get( 'enquiry_consent' ) ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Displayed next to a checkbox that must be ticked. Leave empty if no consent is required. Links are allowed.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'Save', 'wp-mobile-de-sync' ) ); ?>
		</form>

		<?php
		self::mail();
	}

	private static function received() {
		$new = WMDS_Leads::count_new();
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Received enquiries', 'wp-mobile-de-sync' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					$new
						/* translators: %d: number of enquiries not yet answered. */
						? sprintf( _n( '%d enquiry has not been answered yet.', '%d enquiries have not been answered yet.', $new, 'wp-mobile-de-sync' ), $new )
						: __( 'All enquiries have been answered.', 'wp-mobile-de-sync' )
				);
				?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . WMDS_Leads::CPT ) ); ?>">
					<?php esc_html_e( 'Open the enquiries', 'wp-mobile-de-sync' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	private static function mail() {
		$found     = WMDS_Smtp::detect();
		$transport = $found ? WMDS_Smtp::transport( $found['key'] ) : '';
		$sound     = WMDS_Smtp::is_authenticated( $found, $transport );
		$failure   = WMDS_Smtp::failure();
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Mail delivery', 'wp-mobile-de-sync' ); ?></h2>

			<p class="wmds-requirement-summary <?php echo $sound ? 'wmds-requirement-ok' : 'wmds-requirement-warn'; ?>">
				<span class="wmds-requirement-badge <?php echo $sound ? 'wmds-requirement-ok' : 'wmds-requirement-warn'; ?>">
					<?php echo esc_html( WMDS_Admin_Ui::requirement_marker( $sound ? WMDS_Requirements::OK : WMDS_Requirements::WARN ) ); ?>
				</span>
				<?php echo esc_html( WMDS_Smtp::describe( $found, $transport ) ); ?>
			</p>

			<p class="description">
				<?php esc_html_e( 'Enquiries and alerts are sent with wp_mail(). Without an SMTP plugin this means PHP mail(): no authentication, a sender address chosen by the server, and no confirmation that a message was delivered. Enquiries may therefore end up in spam folders unnoticed.', 'wp-mobile-de-sync' ); ?>
			</p>

			<?php if ( $failure ) : ?>
				<table class="widefat striped wmds-table">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Last mail error', 'wp-mobile-de-sync' ); ?></td>
							<td>
								<?php echo esc_html( $failure['message'] ); ?>
								<p class="description">
									<?php WMDS_Admin_Ui::print_time( $failure['time'], 'relative' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="description">
					<?php esc_html_e( 'Recorded for all e-mails sent by this site, not only those of the plugin. The notice is removed automatically once an e-mail has been sent successfully.', 'wp-mobile-de-sync' ); ?>
				</p>
			<?php endif; ?>

			<p>
				<?php WMDS_Admin_Ui::action_button( 'test-mail', __( 'Send a test mail', 'wp-mobile-de-sync' ), 'secondary', '', 'enquiries' ); ?>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: the addresses enquiries are sent to. */
					esc_html__( 'Enquiries: %s', 'wp-mobile-de-sync' ),
					'<strong>' . esc_html( implode( ', ', WMDS_Lead_Mail::recipients() ) ) . '</strong>'
				);
				if ( WMDS_Alerts::enabled() ) {
					echo '<br>';
					printf(
						/* translators: %s: the addresses sync alerts are sent to. */
						esc_html__( 'Sync alerts: %s', 'wp-mobile-de-sync' ),
						'<strong>' . esc_html( implode( ', ', WMDS_Alerts::recipients() ) ) . '</strong>'
					);
				}
				?>
			</p>
		</div>
		<?php
	}
}
