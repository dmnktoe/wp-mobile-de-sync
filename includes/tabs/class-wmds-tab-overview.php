<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Tab_Overview {
	const RECENT = 5;

	/**
	 * @param array $status
	 */
	public static function render( array $status ) {
		WMDS_Admin_Ui::render_status_card( $status );
		WMDS_Admin_Ui::render_checklist( $status );

		if ( $status['configured'] ) {
			self::sync_card();
		}
		?>
		<div class="wmds-columns">
			<?php
			self::enquiries_card();
			self::log_card();
			?>
		</div>
		<?php
	}

	private static function sync_card() {
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Synchronisation', 'wp-mobile-de-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Runs the sync in batches of 20 vehicles until all are processed. Leaving this page interrupts the process; the schedule processes the remaining vehicles automatically.', 'wp-mobile-de-sync' ); ?>
			</p>
			<p>
				<button type="button" class="button button-primary" id="wmds-sync">
					<?php esc_html_e( 'Sync now', 'wp-mobile-de-sync' ); ?>
				</button>
			</p>
			<div class="wmds-progress" id="wmds-progress" hidden>
				<div class="wmds-progress-bar"><span id="wmds-progress-fill"></span></div>
				<p class="wmds-progress-text" id="wmds-progress-text" role="status" aria-live="polite"></p>
			</div>
			<div class="wmds-result" id="wmds-sync-result" role="status" aria-live="polite"></div>
			<noscript>
				<?php WMDS_Admin_Ui::action_button( 'sync', __( 'Sync now', 'wp-mobile-de-sync' ), 'primary' ); ?>
			</noscript>
			<?php WMDS_Admin_Ui::render_lock_notice(); ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: the WP-CLI command, already marked up. */
					esc_html__( 'For the initial import of a large inventory, the command line is recommended because it has no time limit: %s', 'wp-mobile-de-sync' ),
					'<code>wp wmds sync --full --all</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	private static function enquiries_card() {
		$leads = get_posts(
			array(
				'post_type'      => WMDS_Leads::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => self::RECENT,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$new   = WMDS_Leads::count_new();
		?>
		<div class="wmds-card">
			<div class="wmds-card-head">
				<h2><?php esc_html_e( 'Recent enquiries', 'wp-mobile-de-sync' ); ?></h2>
				<?php if ( $new ) : ?>
					<span class="wmds-pill-count">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of enquiries not yet answered. */
								_n( '%d new', '%d new', $new, 'wp-mobile-de-sync' ),
								$new
							)
						);
						?>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( ! $leads ) : ?>
				<p><em><?php esc_html_e( 'No enquiries yet', 'wp-mobile-de-sync' ); ?></em></p>
			<?php else : ?>
				<ul class="wmds-recent">
					<?php foreach ( $leads as $lead ) : ?>
						<li class="<?php echo WMDS_Leads::is_new( $lead->ID ) ? 'wmds-recent-new' : ''; ?>">
							<a href="<?php echo esc_url( (string) get_edit_post_link( $lead->ID ) ); ?>"><?php echo esc_html( get_the_title( $lead ) ); ?></a>
							<span class="wmds-recent-meta">
								<?php WMDS_Admin_Ui::print_time( (int) get_post_time( 'U', true, $lead ), 'relative' ); ?>
								· <?php echo esc_html( WMDS_Leads::status_label( $lead->ID ) ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . WMDS_Leads::CPT ) ); ?>">
					<?php esc_html_e( 'All enquiries', 'wp-mobile-de-sync' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	private static function log_card() {
		$log = WMDS_Log::entries( self::RECENT );
		?>
		<div class="wmds-card">
			<h2><?php esc_html_e( 'Recent log entries', 'wp-mobile-de-sync' ); ?></h2>

			<?php if ( ! $log ) : ?>
				<p><em><?php esc_html_e( 'The log is empty.', 'wp-mobile-de-sync' ); ?></em></p>
			<?php else : ?>
				<ul class="wmds-recent">
					<?php foreach ( $log as $entry ) : ?>
						<li class="<?php echo WMDS_Log::is_problem( $entry ) ? 'wmds-recent-problem' : ''; ?>">
							<span><?php echo esc_html( $entry['message'] ); ?></span>
							<span class="wmds-recent-meta">
								<?php WMDS_Admin_Ui::print_time( WMDS_Status::timestamp( $entry['time'] ), 'relative', $entry['time'] ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p>
				<a href="<?php echo esc_url( WMDS_Status::settings_url( 'tools' ) . '#wmds-log' ); ?>">
					<?php esc_html_e( 'Full log', 'wp-mobile-de-sync' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
