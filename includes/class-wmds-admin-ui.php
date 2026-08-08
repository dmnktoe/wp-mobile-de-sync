<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Admin_Ui {
	/**
	 * @param string $tab Tab to return to after saving.
	 */
	public static function form_open( $tab ) {
		printf( '<form method="post" action="%s" class="wmds-form">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( WMDS_Admin::NONCE );
		echo '<input type="hidden" name="action" value="wmds">';
		echo '<input type="hidden" name="wmds_action" value="save">';
		printf( '<input type="hidden" name="wmds_tab" value="%s">', esc_attr( $tab ) );
	}

	/**
	 * @param string $action
	 * @param string $label
	 * @param string $type
	 * @param string $confirm Optional confirmation prompt.
	 */
	public static function action_button( $action, $label, $type, $confirm = '' ) {
		printf( '<form method="post" action="%s" class="wmds-inline-form">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( WMDS_Admin::NONCE );
		echo '<input type="hidden" name="action" value="wmds">';
		printf( '<input type="hidden" name="wmds_action" value="%s">', esc_attr( $action ) );

		printf(
			'<button type="submit" class="button button-%s"%s>%s</button>',
			esc_attr( $type ),
			$confirm ? ' data-wmds-confirm="' . esc_attr( $confirm ) . '"' : '',
			esc_html( $label )
		);

		echo '</form>';
	}

	/**
	 * @param int    $timestamp
	 * @param string $mode  'absolute' for the date, 'relative' for "5 mins ago".
	 * @param string $title Tooltip, when the other reading is not the useful one.
	 */
	public static function print_time( $timestamp, $mode = 'absolute', $title = '' ) {
		$timestamp = (int) $timestamp;
		if ( ! $timestamp ) {
			echo '—';
			return;
		}

		$absolute = WMDS_Status::local_time( $timestamp );
		$relative = WMDS_Status::relative_time( $timestamp );

		if ( '' === $title ) {
			$title = ( 'relative' === $mode ) ? $absolute : $relative;
		}

		printf(
			'<time datetime="%s" title="%s">%s</time>',
			esc_attr( WMDS_Status::iso_time( $timestamp ) ),
			esc_attr( $title ),
			esc_html( 'relative' === $mode ? $relative : $absolute )
		);
	}

	/**
	 * @param string $status
	 * @return string
	 */
	public static function requirement_marker( $status ) {
		if ( WMDS_Requirements::FAIL === $status ) {
			return _x( 'Problem', 'requirement check', 'wp-mobile-de-sync' );
		}
		if ( WMDS_Requirements::WARN === $status ) {
			return _x( 'Note', 'requirement check', 'wp-mobile-de-sync' );
		}

		return _x( 'OK', 'requirement check', 'wp-mobile-de-sync' );
	}

	/**
	 * @param array $status
	 */
	public static function render_status_card( array $status ) {
		?>
		<div class="wmds-card wmds-status-card wmds-sev-bg-<?php echo esc_attr( $status['severity'] ); ?>">
			<div class="wmds-status-head">
				<span class="wmds-bar-dot wmds-sev-<?php echo esc_attr( $status['severity'] ); ?>" aria-hidden="true"></span>
				<h2><?php echo esc_html( $status['label'] ); ?></h2>
			</div>
			<p class="wmds-status-explanation"><?php echo esc_html( $status['explanation'] ); ?></p>

			<ul class="wmds-metrics">
				<li>
					<span class="wmds-metric-value"><?php echo esc_html( number_format_i18n( $status['count'] ) ); ?></span>
					<span class="wmds-metric-label"><?php esc_html_e( 'Vehicles published', 'wp-mobile-de-sync' ); ?></span>
				</li>
				<li>
					<span class="wmds-metric-value">
						<?php self::print_time( $status['last_ts'], 'relative' ); ?>
					</span>
					<span class="wmds-metric-label"><?php esc_html_e( 'Last run', 'wp-mobile-de-sync' ); ?></span>
					<?php if ( $status['last_ts'] ) : ?>
						<span class="wmds-metric-note"><?php echo esc_html( WMDS_Status::local_time( $status['last_ts'] ) ); ?></span>
					<?php endif; ?>
				</li>
				<li>
					<span class="wmds-metric-value">
						<?php
						echo esc_html(
							$status['next']
								? WMDS_Status::relative_time( $status['next'] )
								: __( 'not scheduled', 'wp-mobile-de-sync' )
						);
						?>
					</span>
					<span class="wmds-metric-label"><?php esc_html_e( 'Next run', 'wp-mobile-de-sync' ); ?></span>
					<?php if ( $status['next'] ) : ?>
						<span class="wmds-metric-note"><?php echo esc_html( WMDS_Status::local_time( $status['next'] ) ); ?></span>
					<?php endif; ?>
				</li>
				<li>
					<span class="wmds-metric-value">
						<?php
						echo esc_html(
							$status['incremental']
								? __( 'incremental', 'wp-mobile-de-sync' )
								: __( 'full', 'wp-mobile-de-sync' )
						);
						?>
					</span>
					<span class="wmds-metric-label"><?php esc_html_e( 'Next run type', 'wp-mobile-de-sync' ); ?></span>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * @param array $status
	 */
	public static function render_checklist( array $status ) {
		if ( WMDS_Status::OK === $status['level'] && $status['count'] > 0 ) {
			return;
		}

		$steps = array(
			array(
				'done'  => $status['configured'],
				'label' => __( 'Store the mobile.de credentials', 'wp-mobile-de-sync' ),
				'url'   => WMDS_Status::settings_url( 'connection' ),
				'cta'   => __( 'Enter now', 'wp-mobile-de-sync' ),
			),
			array(
				'done'  => ! empty( $status['tested'] ),
				'label' => __( 'Test the connection', 'wp-mobile-de-sync' ),
				'url'   => WMDS_Status::settings_url( 'tools' ),
				'cta'   => __( 'Run the test', 'wp-mobile-de-sync' ),
			),
			array(
				'done'  => $status['count'] > 0,
				'label' => __( 'Import the inventory once', 'wp-mobile-de-sync' ),
				'url'   => WMDS_Status::settings_url( 'tools' ),
				'cta'   => __( 'Start the import', 'wp-mobile-de-sync' ),
			),
			array(
				'done'  => $status['count'] > 0 && $status['next'],
				'label' => __( 'Confirm the schedule is running', 'wp-mobile-de-sync' ),
				'url'   => WMDS_Status::settings_url( 'schedule' ),
				'cta'   => __( 'Check', 'wp-mobile-de-sync' ),
			),
		);
		?>
		<div class="wmds-card wmds-checklist-card">
			<h2><?php esc_html_e( 'Getting started', 'wp-mobile-de-sync' ); ?></h2>
			<ol class="wmds-checklist">
				<?php foreach ( $steps as $step ) : ?>
					<li class="<?php echo $step['done'] ? 'wmds-step-done' : 'wmds-step-open'; ?>">
						<span class="dashicons <?php echo $step['done'] ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span>
						<span class="wmds-step-label"><?php echo esc_html( $step['label'] ); ?></span>
						<?php if ( ! $step['done'] ) : ?>
							<a href="<?php echo esc_url( $step['url'] ); ?>"><?php echo esc_html( $step['cta'] ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}
}
