<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Tab_System {
	public static function render() {
		$checks = WMDS_Requirements::all();
		$tally  = WMDS_Requirements::tally();

		$grouped = array();
		foreach ( $checks as $check ) {
			$grouped[ $check['group'] ][] = $check;
		}
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Requirements', 'wp-mobile-de-sync' ); ?></h2>

			<?php if ( $tally['fail'] ) : ?>
				<p class="wmds-requirement-summary wmds-requirement-fail">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of failed requirement checks. */
							_n(
								'%d requirement is not met. Reliable synchronisation is not possible until it is resolved.',
								'%d requirements are not met. Reliable synchronisation is not possible until they are resolved.',
								$tally['fail'],
								'wp-mobile-de-sync'
							),
							$tally['fail']
						)
					);
					?>
				</p>
			<?php elseif ( $tally['warn'] ) : ?>
				<p class="wmds-requirement-summary wmds-requirement-warn">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of requirement checks worth a look. */
							_n(
								'All requirements are met. %d item should be reviewed.',
								'All requirements are met. %d items should be reviewed.',
								$tally['warn'],
								'wp-mobile-de-sync'
							),
							$tally['warn']
						)
					);
					?>
				</p>
			<?php else : ?>
				<p class="wmds-requirement-summary wmds-requirement-ok">
					<?php esc_html_e( 'Every check passed.', 'wp-mobile-de-sync' ); ?>
				</p>
			<?php endif; ?>

			<?php foreach ( $grouped as $group => $rows ) : ?>
				<h3><?php echo esc_html( $group ); ?></h3>
				<table class="widefat striped wmds-table wmds-requirements">
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr class="wmds-requirement-row-<?php echo esc_attr( $row['status'] ); ?>">
								<td class="wmds-requirement-label"><?php echo esc_html( $row['label'] ); ?></td>
								<td class="wmds-requirement-value">
									<span class="wmds-requirement-badge wmds-requirement-<?php echo esc_attr( $row['status'] ); ?>">
										<?php echo esc_html( WMDS_Admin_Ui::requirement_marker( $row['status'] ) ); ?>
									</span>
									<?php echo esc_html( $row['value'] ); ?>
									<?php if ( '' !== $row['hint'] ) : ?>
										<p class="description"><?php echo esc_html( $row['hint'] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the WordPress site health screen, already marked up. */
					esc_html__( 'WordPress provides further server information under %s.', 'wp-mobile-de-sync' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'site-health.php?tab=debug' ) ),
						esc_html__( 'Tools → Site Health → Info', 'wp-mobile-de-sync' )
					)
				);
				?>
			</p>
		</div>
		<?php
	}
}
