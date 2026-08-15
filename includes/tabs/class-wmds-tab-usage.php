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
				<?php esc_html_e( 'The inventory is the vehicles post type. Use the blocks in the editor, the shortcodes anywhere content accepts them, or the dashboard widget for a quick overview in wp-admin. Everything below is English — the same names in the block inserter, the shortcode and the post type.', 'wp-mobile-de-sync' ); ?>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: 1: post type name, 2: archive path. */
					esc_html__( 'Post type: %1$s. Archive URL: %2$s.', 'wp-mobile-de-sync' ),
					'<code>' . esc_html( WMDS_CPT ) . '</code>',
					'<code>/' . esc_html( WMDS_CPT ) . '/</code>'
				);
				?>
			</p>
		</div>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'Blocks', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'In the block editor open the inserter and look under mobile.de Sync. Each block renders through the shortcode it stands for, so the markup stays identical.', 'wp-mobile-de-sync' ); ?>
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
				<?php esc_html_e( 'Paste into a Shortcode block, a classic editor, a text widget or a template. Attributes match the block sidebar controls.', 'wp-mobile-de-sync' ); ?>
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
						<td><?php esc_html_e( 'Any WordPress orderby value; ASC or DESC', 'wp-mobile-de-sync' ); ?></td>
						<td><code>date</code> / <code>DESC</code></td>
					</tr>
					<tr>
						<td><code>make</code>, <code>model</code>, <code>fuel</code>, …</td>
						<td><?php esc_html_e( 'Any vehicle meta key filters the list to that value', 'wp-mobile-de-sync' ); ?></td>
						<td>—</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'Widgets', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Admin-only overview. Front-end placement uses the blocks or shortcodes above — there is no classic sidebar widget.', 'wp-mobile-de-sync' ); ?>
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
