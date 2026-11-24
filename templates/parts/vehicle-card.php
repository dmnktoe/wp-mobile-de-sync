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
		'type'  => 'positive',
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

$wmds_logo  = $vehicle->logo_url();
$wmds_facts = $vehicle->facts();

unset( $wmds_facts['registration'] );

$wmds_meta = trim( $vehicle->get( 'category' ) . ' · ' . $vehicle->first_registration(), ' ·' );
?>
<li class="wmds-card">
	<a class="wmds-card__link" href="<?php the_permalink(); ?>">
		<div class="wmds-card__image">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?>
			<?php else : ?>
				<span class="wmds-card__placeholder" aria-hidden="true"><?php WMDS_Templates::the_icon( 'images' ); ?></span>
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
		</div>

		<div class="wmds-card__body">
			<div class="wmds-card__head">
				<?php if ( $wmds_logo ) : ?>
					<span class="wmds-card__logo">
						<img src="<?php echo esc_url( $wmds_logo ); ?>" alt="<?php echo esc_attr( $vehicle->get( 'make' ) ); ?>"
							loading="lazy" width="40" height="40">
					</span>
				<?php endif; ?>
				<div class="wmds-card__titles">
					<<?php echo esc_html( $wmds_heading ); ?> class="wmds-card__title"><?php the_title(); ?></<?php echo esc_html( $wmds_heading ); ?>>
					<?php if ( '' !== $wmds_meta ) : ?>
						<p class="wmds-card__meta"><?php echo esc_html( $wmds_meta ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $wmds_facts ) : ?>
				<ul class="wmds-facts wmds-card__facts">
					<?php foreach ( $wmds_facts as $wmds_key => $wmds_fact ) : ?>
						<li class="wmds-fact">
							<?php WMDS_Templates::the_icon( $wmds_key ); ?>
							<span class="screen-reader-text"><?php echo esc_html( $wmds_fact['label'] ); ?>:</span>
							<span class="wmds-fact__value"><?php echo esc_html( $wmds_fact['value'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<div class="wmds-card__footer">
				<?php if ( $vehicle->price() ) : ?>
					<p class="wmds-card__price">
						<span class="wmds-card__amount"><?php echo esc_html( $vehicle->price() ); ?></span>
						<small><?php echo esc_html( $vehicle->vat_note() ); ?></small>
					</p>
				<?php endif; ?>
				<span class="wmds-card__more">
					<span class="screen-reader-text"><?php esc_html_e( 'View vehicle', 'wp-mobile-de-sync' ); ?></span>
					<?php WMDS_Templates::the_icon( 'arrow-right' ); ?>
				</span>
			</div>
		</div>
	</a>
</li>
