<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wmds_atts = isset( $atts ) && is_array( $atts ) ? $atts : array();

$wmds_controls = array(
	'posts_per_page' => 6,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'meta_key'       => '', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- an attribute name, not a query.
	'columns'        => '',
	'layout'         => 'grid',
	'filters'        => 'no',
	'pagination'     => 'no',
	'heading'        => 'h3',
);

$wmds_settings = $wmds_controls;
$wmds_filters  = array();

foreach ( $wmds_atts as $wmds_key => $wmds_value ) {
	if ( array_key_exists( $wmds_key, $wmds_controls ) ) {
		$wmds_settings[ $wmds_key ] = $wmds_value;
		continue;
	}

	$wmds_value = is_array( $wmds_value ) ? array_filter( $wmds_value ) : trim( (string) $wmds_value );
	if ( $wmds_value ) {
		$wmds_filters[ $wmds_key ] = $wmds_value;
	}
}

// An attribute that names a facet is a filter the visitor starts with, not
// one hidden from them. As a bare meta clause the filter bar knew nothing of
// it: [vehicles make="BMW" filters="yes"] offered every make with the counts
// of the whole inventory, and choosing any of them found nothing.
$wmds_pinned = array();
foreach ( WMDS_Facets::definitions() as $wmds_key => $wmds_facet ) {
	$wmds_type = isset( $wmds_facet['type'] ) ? $wmds_facet['type'] : 'select';
	if ( 'range' === $wmds_type || 'search' === $wmds_type ) {
		continue;
	}

	foreach ( array_unique( array( $wmds_key, isset( $wmds_facet['meta'] ) ? $wmds_facet['meta'] : $wmds_key ) ) as $wmds_name ) {
		if ( isset( $wmds_filters[ $wmds_name ] ) ) {
			$wmds_pinned[ WMDS_Facets::PREFIX . $wmds_key ] = is_array( $wmds_filters[ $wmds_name ] )
				? $wmds_filters[ $wmds_name ]
				: array_filter( array_map( 'trim', explode( ',', $wmds_filters[ $wmds_name ] ) ) );

			unset( $wmds_filters[ $wmds_name ] );
		}
	}
}

$wmds_selection = WMDS_Facets::selection() + WMDS_Facets::parse( $wmds_pinned );
$wmds_paginated = ( 'no' !== $wmds_settings['pagination'] );
$wmds_layout    = ( 'list' === $wmds_settings['layout'] ) ? 'list' : 'grid';
$wmds_heading   = in_array( $wmds_settings['heading'], array( 'h2', 'h3', 'h4' ), true ) ? $wmds_settings['heading'] : 'h3';

$wmds_paged = 1;
if ( $wmds_paginated ) {
	$wmds_paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
}

$wmds_query_args = WMDS_Facets::query_args(
	$wmds_selection,
	array(
		'posts_per_page' => (int) $wmds_settings['posts_per_page'],
		'paged'          => $wmds_paged,
		'no_found_rows'  => ! $wmds_paginated,
	)
);

if ( ! isset( $wmds_selection['sort'] ) ) {
	$wmds_query_args['orderby'] = sanitize_key( $wmds_settings['orderby'] );
	$wmds_query_args['order']   = ( 'ASC' === strtoupper( $wmds_settings['order'] ) ) ? 'ASC' : 'DESC';

	if ( '' !== $wmds_settings['meta_key'] ) {
		$wmds_query_args['meta_key'] = sanitize_key( $wmds_settings['meta_key'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- sorting by a meta field is what the attribute is for.
	}
}

$wmds_meta_query = array();
foreach ( $wmds_filters as $wmds_key => $wmds_value ) {
	$wmds_meta_query[] = array(
		'key'     => $wmds_key,
		'value'   => $wmds_value,
		'compare' => is_array( $wmds_value ) ? 'IN' : '=',
	);
}

if ( $wmds_meta_query ) {
	$wmds_meta_query['relation'] = 'AND';

	$wmds_query_args['meta_query'] = isset( $wmds_query_args['meta_query'] ) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- filtering by meta is what the shortcode is for.
		? array(
			'relation' => 'AND',
			$wmds_query_args['meta_query'],
			$wmds_meta_query,
		)
		: $wmds_meta_query;
}

$wmds_vehicles = new WP_Query( $wmds_query_args );

$wmds_classes = array( 'wmds-grid', 'wmds-grid--shortcode' );
if ( 'list' === $wmds_layout ) {
	$wmds_classes[] = 'wmds-grid--list';
} elseif ( in_array( (string) $wmds_settings['columns'], array( '2', '3', '4' ), true ) ) {
	$wmds_classes[] = 'wmds-grid--columns-' . (int) $wmds_settings['columns'];
}
?>
<div class="wmds-scope wmds-list">
	<?php
	if ( 'no' !== $wmds_settings['filters'] ) {
		WMDS_Facets::enqueue();
		WMDS_Templates::render(
			'parts/filter-bar.php',
			array(
				'selection' => $wmds_selection,
				'layout'    => ( 'sidebar' === $wmds_settings['filters'] ) ? 'sidebar' : 'bar',
				'counts'    => true,
			)
		);
	}
	?>

	<?php if ( $wmds_vehicles->have_posts() ) : ?>
		<ul class="<?php echo esc_attr( implode( ' ', $wmds_classes ) ); ?>">
			<?php
			while ( $wmds_vehicles->have_posts() ) :
				$wmds_vehicles->the_post();
				WMDS_Templates::render(
					'parts/vehicle-card.php',
					array(
						'heading'  => $wmds_heading,
						'warnings' => true,
					)
				);
			endwhile;
			?>
		</ul>

		<?php if ( $wmds_paginated && $wmds_vehicles->max_num_pages > 1 ) : ?>
			<nav class="wmds-pagination" aria-label="<?php esc_attr_e( 'Vehicle pages', 'wp-mobile-de-sync' ); ?>">
				<?php
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- built by paginate_links() from escaped parts and the fixed icons.
				echo (string) paginate_links(
					array(
						'total'     => (int) $wmds_vehicles->max_num_pages,
						'current'   => $wmds_paged,
						'prev_text' => WMDS_Templates::icon( 'chevron-left' ) . '<span class="screen-reader-text">' . esc_html__( 'Previous', 'wp-mobile-de-sync' ) . '</span>',
						'next_text' => '<span class="screen-reader-text">' . esc_html__( 'Next', 'wp-mobile-de-sync' ) . '</span>' . WMDS_Templates::icon( 'chevron-right' ),
						'add_args'  => WMDS_Facets::to_query( $wmds_selection ),
					)
				);
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</nav>
		<?php endif; ?>

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

wp_reset_postdata();
