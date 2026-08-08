<?php

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

$wmds_badges = array();
if ( $vehicle->has( 'condition' ) ) {
	$wmds_badges[] = array(
		'label' => $vehicle->get( 'condition' ),
		'type'  => 'neutral',
	);
}
if ( 'true' === $vehicle->get( 'available_now' ) ) {
	$wmds_badges[] = array(
		'label' => __( 'Available now', 'wp-mobile-de-sync' ),
		'type'  => 'neutral',
	);
}
if ( $wmds_show_warnings ) {
	foreach ( $vehicle->warnings() as $wmds_warning ) {
		$wmds_badges[] = array(
			'label' => $wmds_warning,
			'type'  => 'warning',
		);
	}
}

$wmds_logo = $vehicle->logo_url();
?>
<li class="wmds-card">
	<a class="wmds-card__link" href="<?php the_permalink(); ?>">
		<div class="wmds-card__image">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?>
			<?php else : ?>
				<span class="wmds-card__placeholder" aria-hidden="true"></span>
			<?php endif; ?>

			<?php if ( $wmds_badges ) : ?>
				<ul class="wmds-card__badges">
					<?php foreach ( $wmds_badges as $wmds_badge ) : ?>
						<li class="wmds-badge wmds-badge--<?php echo esc_attr( $wmds_badge['type'] ); ?>">
							<?php echo esc_html( $wmds_badge['label'] ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $wmds_logo ) : ?>
				<img class="wmds-card__logo" src="<?php echo esc_url( $wmds_logo ); ?>"
					alt="<?php echo esc_attr( $vehicle->get( 'make' ) ); ?>" loading="lazy" width="32" height="32">
			<?php endif; ?>
		</div>

		<div class="wmds-card__body">
			<<?php echo esc_html( $wmds_heading ); ?> class="wmds-card__title"><?php the_title(); ?></<?php echo esc_html( $wmds_heading ); ?>>

			<?php if ( $vehicle->has( 'category' ) || $vehicle->has( 'firstRegistration' ) ) : ?>
				<p class="wmds-card__meta">
					<?php echo esc_html( trim( $vehicle->get( 'category' ) . ' · ' . $vehicle->first_registration(), ' ·' ) ); ?>
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
