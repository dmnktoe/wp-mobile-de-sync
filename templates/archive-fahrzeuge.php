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
				WMDS_Templates::render(
					'parts/vehicle-card.php',
					array(
						'heading'  => 'h2',
						'warnings' => true,
					)
				);
			endwhile;
			?>
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
