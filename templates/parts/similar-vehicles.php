<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wmds_args    = isset( $args ) && is_array( $args ) ? $args : array();
$wmds_vehicle = isset( $wmds_args['vehicle_id'] ) ? (int) $wmds_args['vehicle_id'] : 0;
$wmds_similar = $wmds_vehicle ? WMDS_Similar::ids( $wmds_vehicle ) : array();

if ( ! $wmds_similar ) {
	return;
}

$wmds_query = new WP_Query(
	array(
		'post_type'      => WMDS_CPT,
		'post__in'       => $wmds_similar,
		'orderby'        => 'post__in',
		'posts_per_page' => count( $wmds_similar ),
		'no_found_rows'  => true,
	)
);
?>
<section class="wmds-section wmds-similar">
	<h2 class="wmds-section__title"><?php esc_html_e( 'Similar vehicles', 'wp-mobile-de-sync' ); ?></h2>
	<ul class="wmds-grid">
		<?php
		while ( $wmds_query->have_posts() ) :
			$wmds_query->the_post();
			WMDS_Templates::render( 'parts/vehicle-card.php', array( 'heading' => 'h3' ) );
		endwhile;
		?>
	</ul>
</section>
<?php
wp_reset_postdata();
