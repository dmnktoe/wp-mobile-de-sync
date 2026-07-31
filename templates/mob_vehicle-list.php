<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wmds_atts = shortcode_atts(
	array(
		'posts_per_page' => 6,
		'make'           => '',
		'model'          => '',
		'condition'      => '',
		'fuel'           => '',
		'gearbox'        => '',
		'marke'          => '',
		'modell'         => '',
		'zustand'        => '',
		'kraftstoffart'  => '',
		'getriebe'       => '',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_key'       => '', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- an attribute name, not a query.
	),
	isset( $atts ) && is_array( $atts ) ? $atts : array(),
	'fahrzeuge-anzeigen'
);

$wmds_query_args = array(
	'post_type'      => WMDS_CPT,
	'post_status'    => 'publish',
	'posts_per_page' => (int) $wmds_atts['posts_per_page'],
	'orderby'        => sanitize_key( $wmds_atts['orderby'] ),
	'order'          => ( 'ASC' === strtoupper( $wmds_atts['order'] ) ) ? 'ASC' : 'DESC',
	'no_found_rows'  => true,
);

if ( '' !== $wmds_atts['meta_key'] ) {
	$wmds_query_args['meta_key'] = sanitize_key( $wmds_atts['meta_key'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- sorting by a meta field is what the attribute is for.
}

$wmds_filters = array(
	'make'      => $wmds_atts['make'] ? $wmds_atts['make'] : $wmds_atts['marke'],
	'model'     => $wmds_atts['model'] ? $wmds_atts['model'] : $wmds_atts['modell'],
	'condition' => $wmds_atts['condition'] ? $wmds_atts['condition'] : $wmds_atts['zustand'],
	'fuel'      => $wmds_atts['fuel'] ? $wmds_atts['fuel'] : $wmds_atts['kraftstoffart'],
	'gearbox'   => $wmds_atts['gearbox'] ? $wmds_atts['gearbox'] : $wmds_atts['getriebe'],
);

$wmds_meta_query = array();
foreach ( $wmds_filters as $wmds_key => $wmds_value ) {
	$wmds_value = is_array( $wmds_value ) ? array_filter( $wmds_value ) : trim( (string) $wmds_value );
	if ( ! $wmds_value ) {
		continue;
	}
	$wmds_meta_query[] = array(
		'key'     => $wmds_key,
		'value'   => $wmds_value,
		'compare' => is_array( $wmds_value ) ? 'IN' : '=',
	);
}

if ( $wmds_meta_query ) {
	$wmds_meta_query['relation']   = 'AND';
	$wmds_query_args['meta_query'] = $wmds_meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- filtering by meta is what the shortcode is for.
}

$wmds_vehicles = new WP_Query( $wmds_query_args );

if ( $wmds_vehicles->have_posts() ) : ?>
	<ul class="wmds-grid wmds-grid--shortcode">
		<?php
		while ( $wmds_vehicles->have_posts() ) :
			$wmds_vehicles->the_post();
			WMDS_Templates::render( 'parts/vehicle-card.php', array( 'heading' => 'h3' ) );
		endwhile;
		?>
	</ul>
<?php else : ?>
	<p class="wmds-empty"><?php esc_html_e( 'No vehicles are available at the moment.', 'wp-mobile-de-sync' ); ?></p>
	<?php
endif;

wp_reset_postdata();
