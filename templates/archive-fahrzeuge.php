<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

WMDS_Templates::header();

$wmds_selection = WMDS_Facets::selection();
$wmds_found     = (int) $GLOBALS['wp_query']->found_posts;
?>

<div class="wmds-scope wmds-archive<?php echo esc_attr( WMDS_Templates::wrapper_class() ); ?>">

	<header class="wmds-archive__header">
		<h1 class="wmds-archive__title"><?php post_type_archive_title(); ?></h1>
		<p class="wmds-archive__lead">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of vehicles the current filters leave. */
					_n( '%d vehicle in stock', '%d vehicles in stock', $wmds_found, 'wp-mobile-de-sync' ),
					$wmds_found
				)
			);
			?>
		</p>
	</header>

	<?php if ( WMDS_Removed::arrived() ) : ?>
		<p class="wmds-notice" role="status">
			<?php esc_html_e( 'The vehicle you were looking for is no longer available. Here is our current inventory.', 'wp-mobile-de-sync' ); ?>
		</p>
	<?php endif; ?>

	<?php
	WMDS_Facets::enqueue();
	WMDS_Templates::render(
		'parts/filter-bar.php',
		array(
			'selection' => $wmds_selection,
			'layout'    => 'bar',
			'counts'    => true,
			'heading'   => __( 'Find your vehicle', 'wp-mobile-de-sync' ),
		)
	);
	?>

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

		<?php
		$wmds_pages = (string) paginate_links(
			array(
				'prev_text' => WMDS_Templates::icon( 'chevron-left' ) . '<span class="screen-reader-text">' . esc_html__( 'Previous', 'wp-mobile-de-sync' ) . '</span>',
				'next_text' => '<span class="screen-reader-text">' . esc_html__( 'Next', 'wp-mobile-de-sync' ) . '</span>' . WMDS_Templates::icon( 'chevron-right' ),
				'add_args'  => WMDS_Facets::to_query( $wmds_selection ),
			)
		);
		?>
		<?php if ( '' !== $wmds_pages ) : ?>
			<nav class="wmds-pagination" aria-label="<?php esc_attr_e( 'Vehicle pages', 'wp-mobile-de-sync' ); ?>">
				<?php echo $wmds_pages; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by paginate_links() from escaped parts and the fixed icons. ?>
			</nav>
		<?php endif; ?>

	<?php elseif ( WMDS_Facets::is_filtered( $wmds_selection ) ) : ?>
		<div class="wmds-empty">
			<p><?php esc_html_e( 'No vehicle matches these filters.', 'wp-mobile-de-sync' ); ?></p>
			<a class="wmds-button" href="<?php echo esc_url( WMDS_Facets::base_url() ); ?>"><?php esc_html_e( 'Reset the filters', 'wp-mobile-de-sync' ); ?></a>
		</div>
	<?php else : ?>
		<div class="wmds-empty">
			<p><?php esc_html_e( 'No vehicles are available at the moment.', 'wp-mobile-de-sync' ); ?></p>
		</div>
	<?php endif; ?>

</div>

<?php
WMDS_Templates::footer();
