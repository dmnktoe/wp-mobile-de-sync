<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How vehicles get onto the site - blocks, shortcodes, widgets - and the two
 * plugins a dealer site tends to run already.
 */
class WMDS_Tab_Embed {
	/** @return string[] The settings this tab's form holds. */
	public static function fields() {
		return array( 'cf7_store', 'cf7_copy_seller', 'facetwp_reindex' );
	}

	public static function render() {
		self::usage();

		WMDS_Admin_Ui::form_open( 'embed' );
		self::contact_form_7();
		self::facetwp();
		submit_button( __( 'Save', 'wp-mobile-de-sync' ) );
		echo '</form>';
	}

	private static function usage() {
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'How to place vehicles on the site', 'wp-mobile-de-sync' ); ?></h2>
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: 1: post type name, 2: archive URL. */
						esc_html__( 'Blocks in the editor, shortcodes in content or templates, and a dashboard widget in wp-admin. The post type is %1$s; the archive is %2$s.', 'wp-mobile-de-sync' ),
						'<code>' . esc_html( WMDS_CPT ) . '</code>',
						'<a href="' . esc_url( home_url( '/' . WMDS_Settings::slug() . '/' ) ) . '"><code>/' . esc_html( WMDS_Settings::slug() ) . '/</code></a>'
					)
				);
				?>
			</p>
		</div>


		<div class="wmds-card">
			<h2><?php esc_html_e( 'Blocks', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Inserter → mobile.de Sync. Each block renders the matching shortcode.', 'wp-mobile-de-sync' ); ?>
			</p>
			<table class="widefat striped wmds-table wmds-usage-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Block', 'wp-mobile-de-sync' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Name', 'wp-mobile-de-sync' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What it does', 'wp-mobile-de-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( WMDS_Catalog::blocks() as $block ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $block['title'] ); ?></strong></td>
							<td><code><?php echo esc_html( $block['name'] ); ?></code></td>
							<td><?php echo esc_html( $block['description'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'Shortcodes', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Same attributes as the block sidebar controls.', 'wp-mobile-de-sync' ); ?>
			</p>
			<table class="widefat striped wmds-table wmds-usage-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Shortcode', 'wp-mobile-de-sync' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What it does', 'wp-mobile-de-sync' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Example', 'wp-mobile-de-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( WMDS_Catalog::shortcodes() as $shortcode ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $shortcode['title'] ); ?></strong><br>
								<code>[<?php echo esc_html( $shortcode['tag'] ); ?>]</code>
							</td>
							<td><?php echo esc_html( $shortcode['description'] ); ?></td>
							<td><code class="wmds-usage-example"><?php echo esc_html( $shortcode['example'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Vehicle list attributes', 'wp-mobile-de-sync' ); ?></h3>
			<table class="widefat striped wmds-table wmds-usage-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Attribute', 'wp-mobile-de-sync' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Values', 'wp-mobile-de-sync' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Default', 'wp-mobile-de-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>posts_per_page</code></td>
						<td><?php esc_html_e( 'Number of vehicles', 'wp-mobile-de-sync' ); ?></td>
						<td><code>6</code></td>
					</tr>
					<tr>
						<td><code>layout</code></td>
						<td><code>grid</code>, <code>list</code></td>
						<td><code>grid</code></td>
					</tr>
					<tr>
						<td><code>columns</code></td>
						<td><code>2</code>, <code>3</code>, <code>4</code></td>
						<td><?php esc_html_e( 'Theme default', 'wp-mobile-de-sync' ); ?></td>
					</tr>
					<tr>
						<td><code>filters</code></td>
						<td><code>yes</code>, <code>no</code></td>
						<td><code>no</code></td>
					</tr>
					<tr>
						<td><code>pagination</code></td>
						<td><code>yes</code>, <code>no</code></td>
						<td><code>no</code></td>
					</tr>
					<tr>
						<td><code>heading</code></td>
						<td><code>h2</code>, <code>h3</code>, <code>h4</code></td>
						<td><code>h3</code></td>
					</tr>
					<tr>
						<td><code>orderby</code> / <code>order</code></td>
						<td><?php esc_html_e( 'WordPress orderby; ASC or DESC', 'wp-mobile-de-sync' ); ?></td>
						<td><code>date</code> / <code>DESC</code></td>
					</tr>
					<tr>
						<td><code>make</code>, <code>model</code>, <code>fuel</code>, …</td>
						<td><?php esc_html_e( 'Any vehicle meta key filters the list', 'wp-mobile-de-sync' ); ?></td>
						<td>—</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'Widgets', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Admin only. For the front end use a block or shortcode.', 'wp-mobile-de-sync' ); ?>
			</p>
			<table class="widefat striped wmds-table wmds-usage-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Widget', 'wp-mobile-de-sync' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Where', 'wp-mobile-de-sync' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What it does', 'wp-mobile-de-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( WMDS_Catalog::widgets() as $widget ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $widget['title'] ); ?></strong></td>
							<td><?php echo esc_html( $widget['where'] ); ?></td>
							<td><?php echo esc_html( $widget['description'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
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
						__( '%s is not active on this site. The following settings therefore have no effect.', 'wp-mobile-de-sync' ),
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
				<?php esc_html_e( 'A custom form can refer to the vehicle currently being viewed. Mail tags in the form\'s mail template are filled in automatically when the form is submitted from a vehicle page; no additional form field is required.', 'wp-mobile-de-sync' ); ?>
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
					esc_html__( 'The same values are available as a form tag if they should be submitted with the form: %1$s, where %2$s accepts any of the names above.', 'wp-mobile-de-sync' ),
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
							<?php esc_html_e( 'Store submissions from vehicle pages under Enquiries', 'wp-mobile-de-sync' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Name, e-mail address, phone number and message are taken from the form\'s field names. Submissions from other pages are not recorded.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Also to the seller', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_cf7_copy_seller" value="yes"
								<?php checked( WMDS_Cf7::copies_seller() ); ?>>
							<?php esc_html_e( 'Add the address stored in the feed for this vehicle as a recipient', 'wp-mobile-de-sync' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Applies only to the e-mail sent to you, not to the confirmation sent to the visitor.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the Enquiries tab, already marked up. */
					esc_html__( 'The plugin\'s built-in form can be disabled under %s so that only one form appears on a vehicle page.', 'wp-mobile-de-sync' ),
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

	private static function facetwp() {
		?>
		<div class="wmds-card">
			<h2>FacetWP</h2>

			<?php self::badge( WMDS_Facetwp::active(), WMDS_Facetwp::version(), 'FacetWP' ); ?>

			<p class="description">
				<?php esc_html_e( 'The plugin includes its own filters and does not require FacetWP. Existing FacetWP setups continue to work: the meta keys are unchanged, appear in the facet source list under "mobile.de" with readable labels, and the filter bar\'s sort orders are also available to FacetWP templates.', 'wp-mobile-de-sync' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Re-index', 'wp-mobile-de-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wmds_facetwp_reindex" value="yes"
								<?php checked( WMDS_Facetwp::reindexes() ); ?>>
							<?php esc_html_e( 'Re-index a vehicle once the sync has finished saving it', 'wp-mobile-de-sync' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'FacetWP indexes when a post is saved. The import saves the post first and the meta data afterwards, so FacetWP\'s automatic index reflects the state before the update, which leads to outdated counts. Keep this enabled unless the index is maintained elsewhere.', 'wp-mobile-de-sync' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}
