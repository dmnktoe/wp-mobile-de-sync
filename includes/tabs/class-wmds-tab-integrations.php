<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The three plugins a dealer site tends to already run.
 *
 * One form for the whole tab, not one per card: a form that posts only its
 * own checkboxes would read the others as unticked and switch them off.
 */
class WMDS_Tab_Integrations {
	public static function render() {
		self::mail();

		WMDS_Admin_Ui::form_open( 'integrations' );
		self::contact_form_7();
		self::facetwp();
		submit_button( __( 'Save', 'wp-mobile-de-sync' ) );
		echo '</form>';
	}

	/**
	 * @param bool   $active
	 * @param string $version
	 * @param string $name
	 */
	private static function badge( $active, $version, $name ) {
		if ( ! $active ) {
			printf(
				'<p class="wmds-requirement-summary"><span class="wmds-requirement-badge">%s</span> %s</p>',
				esc_html__( 'Not installed', 'wp-mobile-de-sync' ),
				esc_html(
					sprintf(
						/* translators: %s: name of the third-party plugin. */
						__( '%s is not active on this site. Nothing here does anything until it is.', 'wp-mobile-de-sync' ),
						$name
					)
				)
			);

			return;
		}

		printf(
			'<p class="wmds-requirement-summary wmds-requirement-ok"><span class="wmds-requirement-badge wmds-requirement-ok">%s</span> %s</p>',
			esc_html__( 'Active', 'wp-mobile-de-sync' ),
			esc_html( '' === $version ? $name : $name . ' ' . $version )
		);
	}

	private static function contact_form_7() {
		?>
		<div class="wmds-card">
			<h2>Contact Form 7</h2>

			<?php self::badge( WMDS_Cf7::active(), WMDS_Cf7::version(), 'Contact Form 7' ); ?>

			<p class="description">
				<?php esc_html_e( 'A form of your own can ask about the vehicle the visitor is looking at. Put a mail tag into the mail template of the form and it is filled in when the form is sent from a vehicle page — no field in the form needed.', 'wp-mobile-de-sync' ); ?>
			</p>

			<table class="widefat striped wmds-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Mail tag', 'wp-mobile-de-sync' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Value', 'wp-mobile-de-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( WMDS_Cf7::properties() as $property => $label ) : ?>
						<tr>
							<td><code>[<?php echo esc_html( WMDS_Cf7::TAG . $property ); ?>]</code></td>
							<td><?php echo esc_html( $label ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="description">
				<?php
				printf(
					/* translators: 1: an example form tag, 2: the field option, both already marked up. */
					esc_html__( 'The same values are available as a form tag when one should travel with the submission: %1$s, where %2$s takes any of the names above.', 'wp-mobile-de-sync' ),
					'<code>[wmds_vehicle vehicle field:price]</code>',
					'<code>field:</code>'
				);
				?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Keep a record', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_cf7_store" value="yes"
								<?php checked( WMDS_Cf7::stores() ); ?>>
							<?php esc_html_e( 'File a submission sent from a vehicle page under Enquiries', 'wp-mobile-de-sync' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Name, address, phone number and message are read from the form under the names it uses. Submissions from any other page are left alone.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Also to the seller', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_cf7_copy_seller" value="yes"
								<?php checked( WMDS_Cf7::copies_seller() ); ?>>
							<?php esc_html_e( 'Add the address the feed carries for that vehicle as a recipient', 'wp-mobile-de-sync' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Only to the mail that goes to you, never to the confirmation the visitor receives.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the Enquiries tab, already marked up. */
					esc_html__( 'The form the plugin brings itself can be switched off under %s, so a vehicle page carries one form rather than two.', 'wp-mobile-de-sync' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( WMDS_Status::settings_url( 'enquiries' ) ),
						esc_html__( 'Enquiries', 'wp-mobile-de-sync' )
					)
				);
				?>
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
				<?php esc_html_e( 'Enquiries and alerts are sent with wp_mail(). Without an SMTP plugin that means PHP mail(): unauthenticated, sent from whatever address the server decides on, and accepted by the local sendmail whether or not anything is ever delivered. An enquiry that ends up in a spam folder is a lost customer, and nothing on this site would say so.', 'wp-mobile-de-sync' ); ?>
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
					<?php esc_html_e( 'Recorded for every message this site sends, not only ours. It clears itself as soon as one goes out successfully.', 'wp-mobile-de-sync' ); ?>
				</p>
			<?php endif; ?>

			<p>
				<?php WMDS_Admin_Ui::action_button( 'test-alert', __( 'Send a test mail', 'wp-mobile-de-sync' ), 'secondary', '', 'integrations' ); ?>
			</p>
		</div>
		<?php
	}

	private static function facetwp() {
		?>
		<div class="wmds-card">
			<h2>FacetWP</h2>

			<?php self::badge( WMDS_Facetwp::active(), WMDS_Facetwp::version(), 'FacetWP' ); ?>

			<p class="description">
				<?php esc_html_e( 'The plugin filters on its own and needs FacetWP for nothing. A site that was built on FacetWP keeps working all the same: the meta keys are unchanged, they appear in the facet source list under "mobile.de" with readable labels, and the sort orders the filter bar offers are offered to a FacetWP template too.', 'wp-mobile-de-sync' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Re-index', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_facetwp_reindex" value="yes"
								<?php checked( WMDS_Facetwp::reindexes() ); ?>>
							<?php esc_html_e( 'Re-index a vehicle once the sync has finished writing it', 'wp-mobile-de-sync' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'FacetWP indexes when a post is saved. The import saves the post first and writes the meta afterwards, so what FacetWP indexed on its own is the state before the update — which is where stale counts come from. Leave this on unless the index is maintained elsewhere.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}
