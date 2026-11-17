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
				<?php esc_html_e( 'Blocks in the editor, shortcodes in content or templates, and a dashboard widget in wp-admin. The post type is vehicles; the archive is /vehicles/.', 'wp-mobile-de-sync' ); ?>
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
}
