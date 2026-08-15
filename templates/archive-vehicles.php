<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$wmds_selection = WMDS_Facets::selection();
$wmds_found     = (int) $GLOBALS['wp_query']->found_posts;
?>

<div class="wmds-scope wmds-archive">

	<header class="wmds-archive__header">
		<h1><?php post_type_archive_title(); ?></h1>
	</header>

	<?php
	WMDS_Facets::enqueue();
	WMDS_Templates::render(
		'parts/filter-bar.php',
		array(
			'selection' => $wmds_selection,
			'layout'    => 'bar',
			'counts'    => true,
		)
	);
	?>

	<div class="wmds-toolbar">
		<p class="wmds-toolbar__count">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of vehicles the current filters leave. */
					_n( '%d vehicle', '%d vehicles', $wmds_found, 'wp-mobile-de-sync' ),
					$wmds_found
				)
			);
			?>
		</p>
	</div>

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
						'add_args'  => WMDS_Facets::to_query( $wmds_selection ),
					)
				)
			);
			?>
		</nav>

	<?php elseif ( WMDS_Facets::is_filtered( $wmds_selection ) ) : ?>
		<p class="wmds-empty">
			<?php esc_html_e( 'No vehicle matches these filters.', 'wp-mobile-de-sync' ); ?>
			<a href="<?php echo esc_url( WMDS_Facets::base_url() ); ?>"><?php esc_html_e( 'Reset the filters', 'wp-mobile-de-sync' ); ?></a>
		</p>
	<?php else : ?>
		<p class="wmds-empty"><?php esc_html_e( 'No vehicles are available at the moment.', 'wp-mobile-de-sync' ); ?></p>
	<?php endif; ?>

</div>

<?php
get_footer();
