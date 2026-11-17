<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Tab_Usage {
	public static function render() {
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

		<?php WMDS_Admin_Ui::form_open( 'usage' ); ?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Address', 'wp-mobile-de-sync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wmds_slug"><?php esc_html_e( 'Slug', 'wp-mobile-de-sync' ); ?></label></th>
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
			</table>
			<?php submit_button( __( 'Save', 'wp-mobile-de-sync' ) ); ?>
		</div>
		</form>

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
}
