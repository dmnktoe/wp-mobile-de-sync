<?php
/**
 * One vehicle in a grid. Rendered inside the loop.
 *
 * $args['heading']  Heading level for the title, h2 to h4. Default h3.
 * $args['warnings'] Whether to show the vehicle's warnings. Default false.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wmds_args = isset( $args ) && is_array( $args ) ? $args : array();

$wmds_heading = isset( $wmds_args['heading'] ) ? $wmds_args['heading'] : 'h3';
if ( ! in_array( $wmds_heading, array( 'h2', 'h3', 'h4' ), true ) ) {
	$wmds_heading = 'h3';
}

$wmds_show_warnings = ! empty( $wmds_args['warnings'] );

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
			<<?php echo esc_html( $wmds_heading ); ?> class="wmds-card__title"><?php the_title(); ?></<?php echo esc_html( $wmds_heading ); ?>>

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

			<?php if ( $wmds_show_warnings && $vehicle->warnings() ) : ?>
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
