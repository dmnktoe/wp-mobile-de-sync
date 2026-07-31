<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="wmds-archive">

	<header class="wmds-archive__header">
		<h1><?php post_type_archive_title(); ?></h1>
		<?php
		$total = wp_count_posts( WMDS_CPT );
		if ( isset( $total->publish ) && $total->publish ) :
			?>
			<p class="wmds-archive__count">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of vehicles in the inventory. */
						_n( '%d vehicle', '%d vehicles', $total->publish, 'wp-mobile-de-sync' ),
						$total->publish
					)
				);
				?>
			</p>
		<?php endif; ?>
	</header>

	<?php if ( have_posts() ) : ?>

		<ul class="wmds-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				$vehicle = new WMDS_Vehicle();
				?>
				<li class="wmds-card">
					<a class="wmds-card__link" href="<?php the_permalink(); ?>">
						<div class="wmds-card__image">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?>
							<?php else : ?>
								<span class="wmds-card__placeholder" aria-hidden="true"></span>
							<?php endif; ?>
						</div>

						<div class="wmds-card__body">
							<h2 class="wmds-card__title"><?php the_title(); ?></h2>

							<?php if ( $vehicle->has( 'category' ) || $vehicle->has( 'condition' ) ) : ?>
								<p class="wmds-card__meta">
									<?php echo esc_html( trim( $vehicle->get( 'category' ) . ' · ' . $vehicle->get( 'condition' ), ' ·' ) ); ?>
								</p>
							<?php endif; ?>

							<?php if ( $vehicle->highlights() ) : ?>
								<ul class="wmds-card__facts">
									<?php foreach ( $vehicle->highlights() as $label => $value ) : ?>
										<li>
											<span class="wmds-card__fact-label"><?php echo esc_html( $label ); ?></span>
											<span class="wmds-card__fact-value"><?php echo esc_html( $value ); ?></span>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<?php if ( $vehicle->warnings() ) : ?>
								<p class="wmds-warning"><?php echo esc_html( implode( ' · ', $vehicle->warnings() ) ); ?></p>
							<?php endif; ?>

							<?php if ( $vehicle->price() ) : ?>
								<p class="wmds-card__price">
									<?php echo esc_html( $vehicle->price() ); ?>
									<small><?php echo esc_html( $vehicle->vat_note() ); ?></small>
								</p>
							<?php endif; ?>
						</div>
					</a>
				</li>
			<?php endwhile; ?>
		</ul>

		<nav class="wmds-pagination">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'prev_text' => __( '&laquo; Previous', 'wp-mobile-de-sync' ),
						'next_text' => __( 'Next &raquo;', 'wp-mobile-de-sync' ),
					)
				)
			);
			?>
		</nav>

	<?php else : ?>
		<p class="wmds-empty"><?php esc_html_e( 'No vehicles are available at the moment.', 'wp-mobile-de-sync' ); ?></p>
	<?php endif; ?>

</div>

<?php
get_footer();
