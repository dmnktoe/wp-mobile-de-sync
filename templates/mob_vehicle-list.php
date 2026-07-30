<?php
/**
 * Output of the [fahrzeuge-anzeigen] shortcode.
 *
 * Fallback template. A theme's own mob_vehicle-list.php takes precedence, so
 * established sites keep the presentation they already had.
 *
 * Attributes:
 *   posts_per_page  how many (default 6, -1 for all)
 *   make            manufacturer, e.g. "Audi"
 *   model           model
 *   condition       used / new
 *   fuel            diesel / petrol / …
 *   gearbox         manual / automatic
 *   orderby / order sorting, default date descending
 *   meta_key        meta field to sort by, e.g. price_raw
 *
 * The German attribute names marke, modell, zustand, kraftstoffart and
 * getriebe still work as aliases: they appear in the page content of existing
 * sites, and silently dropping them would change what those pages show.
 *
 * $atts and $content come from the calling shortcode.
 *
 * @package wp-mobile-de-sync
 */

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
		// Deprecated aliases, see the file header.
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

// The attributes filter on the same meta fields the facets use. Important:
// they have to be translated into a meta_query - handed to WP_Query as
// unknown keys they simply do nothing.
//
// The English name wins; the alias only applies when it is empty.
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
						<h3 class="wmds-card__title"><?php the_title(); ?></h3>

						<?php if ( $vehicle->has( 'category' ) || $vehicle->has( 'condition' ) ) : ?>
							<p class="wmds-card__meta">
								<?php echo esc_html( trim( $vehicle->get( 'category' ) . ' · ' . $vehicle->get( 'condition' ), ' ·' ) ); ?>
							</p>
						<?php endif; ?>

						<?php if ( $vehicle->highlights() ) : ?>
							<ul class="wmds-card__facts">
								<?php foreach ( $vehicle->highlights() as $wmds_label => $wmds_value ) : ?>
									<li>
										<span class="wmds-card__fact-label"><?php echo esc_html( $wmds_label ); ?></span>
										<span class="wmds-card__fact-value"><?php echo esc_html( $wmds_value ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
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
<?php else : ?>
	<p class="wmds-empty"><?php esc_html_e( 'No vehicles are available at the moment.', 'wp-mobile-de-sync' ); ?></p>
	<?php
endif;

wp_reset_postdata();
