<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Tab_About {
	public static function render() {
		$state  = WMDS_Updater::state();
		$newer  = '' !== $state['version'] && version_compare( $state['version'], WMDS_VERSION, '>' );
		$counts = wp_count_posts( WMDS_CPT );
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'mobile.de Sync', 'wp-mobile-de-sync' ); ?></h2>

			<p>
				<?php esc_html_e( 'Keeps a dealer\'s inventory on mobile.de and the vehicles on this site in step. The mobile.de Search API is read on a schedule and every ad becomes a regular WordPress post that themes, FacetWP and the block editor can work with like any other.', 'wp-mobile-de-sync' ); ?>
			</p>

			<table class="widefat striped wmds-table">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Installed version', 'wp-mobile-de-sync' ); ?></td>
						<td>
							<?php echo esc_html( WMDS_VERSION ); ?>
							<?php if ( $newer ) : ?>
								<strong>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: version number of the available release. */
											__( '- version %s is available', 'wp-mobile-de-sync' ),
											$state['version']
										)
									);
									?>
								</strong>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Vehicles published', 'wp-mobile-de-sync' ); ?></td>
						<td><?php echo esc_html( isset( $counts->publish ) ? (string) (int) $counts->publish : '0' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Post type', 'wp-mobile-de-sync' ); ?></td>
						<td><code><?php echo esc_html( WMDS_CPT ); ?></code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Shortcodes', 'wp-mobile-de-sync' ); ?></td>
						<td><code>[vehicles]</code> <code>[vehicle-logo]</code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Author', 'wp-mobile-de-sync' ); ?></td>
						<td>
							<a href="https://github.com/dmnktoe" target="_blank" rel="noopener noreferrer">Domenik Töfflinger</a>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Licence', 'wp-mobile-de-sync' ); ?></td>
						<td>GPL-2.0-or-later</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="wmds-card">
			<h2><?php esc_html_e( 'Help and source', 'wp-mobile-de-sync' ); ?></h2>
			<ul class="wmds-links">
				<li>
					<a href="https://github.com/dmnktoe/wp-mobile-de-sync" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Source code on GitHub', 'wp-mobile-de-sync' ); ?>
					</a>
				</li>
				<li>
					<a href="https://github.com/dmnktoe/wp-mobile-de-sync/issues" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Report a problem', 'wp-mobile-de-sync' ); ?>
					</a>
				</li>
				<li>
					<a href="https://github.com/dmnktoe/wp-mobile-de-sync/releases" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Release notes', 'wp-mobile-de-sync' ); ?>
					</a>
				</li>
				<li>
					<a href="https://services.mobile.de/manual/search-api.html" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'mobile.de Search API documentation', 'wp-mobile-de-sync' ); ?>
					</a>
				</li>
			</ul>

			<p class="description">
				<?php esc_html_e( 'Not affiliated with mobile.de. Access to the Search API is arranged with mobile.de directly.', 'wp-mobile-de-sync' ); ?>
			</p>
		</div>
		<?php
	}
}
