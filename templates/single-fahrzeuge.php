<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$vehicle = new WMDS_Vehicle();
	$images  = $vehicle->images();
	?>

<article id="fahrzeug-<?php the_ID(); ?>" class="wmds-scope wmds-single" itemscope itemtype="https://schema.org/Car">

	<header class="wmds-single__header">
		<?php $logo = $vehicle->logo_url(); ?>
		<?php if ( $logo ) : ?>
			<img class="wmds-single__logo" src="<?php echo esc_url( $logo ); ?>"
				alt="<?php echo esc_attr( $vehicle->get( 'make' ) ); ?>" loading="lazy">
		<?php endif; ?>
		<div>
			<h1 class="wmds-single__title" itemprop="name"><?php the_title(); ?></h1>
			<?php if ( $vehicle->has( 'category' ) || $vehicle->has( 'condition' ) ) : ?>
				<p class="wmds-single__subtitle">
					<?php echo esc_html( trim( $vehicle->get( 'category' ) . ' · ' . $vehicle->get( 'condition' ), ' ·' ) ); ?>
				</p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( $vehicle->warnings() ) : ?>
		<p class="wmds-warning"><?php echo esc_html( implode( ' · ', $vehicle->warnings() ) ); ?></p>
	<?php endif; ?>

	<div class="wmds-single__main">

		<?php WMDS_Templates::render( 'parts/vehicle-gallery.php', array( 'images' => $images ) ); ?>

		<aside class="wmds-summary" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
			<?php if ( $vehicle->price() ) : ?>
				<p class="wmds-price">
					<span itemprop="price" content="<?php echo esc_attr( $vehicle->get( 'price_raw' ) ); ?>">
						<?php echo esc_html( $vehicle->price() ); ?>
					</span>
					<meta itemprop="priceCurrency" content="<?php echo esc_attr( $vehicle->get( 'currency', 'EUR' ) ); ?>">
					<small><?php echo esc_html( $vehicle->vat_note() ); ?></small>
				</p>
			<?php endif; ?>

			<?php if ( $vehicle->availability_note() ) : ?>
				<p class="wmds-availability"><?php echo esc_html( $vehicle->availability_note() ); ?></p>
			<?php endif; ?>

			<?php if ( $vehicle->highlights() ) : ?>
				<dl class="wmds-highlights">
					<?php foreach ( $vehicle->highlights() as $label => $value ) : ?>
						<dt><?php echo esc_html( $label ); ?></dt>
						<dd><?php echo esc_html( $value ); ?></dd>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>

			<?php if ( $vehicle->inspection() ) : ?>
				<p class="wmds-inspection"><?php echo esc_html( $vehicle->inspection() ); ?></p>
			<?php endif; ?>

			<?php
			$wmds_sticker = $vehicle->emission_sticker_url();
			if ( '' !== $wmds_sticker ) :
				?>
				<p class="wmds-sticker">
					<img src="<?php echo esc_url( $wmds_sticker ); ?>"
						alt="<?php echo esc_attr( $vehicle->emission_sticker_label() ); ?>"
						width="40" height="40" loading="lazy">
				</p>
			<?php endif; ?>

			<?php if ( $vehicle->seller_email() || $vehicle->seller_phone() ) : ?>
				<div class="wmds-contact">
					<h2><?php esc_html_e( 'Enquiry', 'wp-mobile-de-sync' ); ?></h2>
					<?php if ( $vehicle->seller() ) : ?>
						<p class="wmds-contact__name"><?php echo esc_html( $vehicle->seller() ); ?></p>
					<?php endif; ?>
					<?php if ( $vehicle->seller_phone() ) : ?>
						<p>
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $vehicle->seller_phone() ) ); ?>">
								<?php echo esc_html( $vehicle->seller_phone() ); ?>
							</a>
						</p>
					<?php endif; ?>
					<?php if ( $vehicle->seller_email() ) : ?>
						<p>
							<a class="wmds-button"
								href="mailto:<?php echo esc_attr( $vehicle->seller_email() ); ?>?subject=
								<?php
									/* translators: %s: the vehicle title. */
									echo rawurlencode( sprintf( __( 'Enquiry about %s', 'wp-mobile-de-sync' ), get_the_title() ) );
								?>
								">
								<?php esc_html_e( 'Send an e-mail', 'wp-mobile-de-sync' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</aside>
	</div>

	<?php
	if ( WMDS_Leads::enabled() ) {
		WMDS_Templates::render( 'parts/enquiry-form.php', array( 'vehicle_id' => get_the_ID() ) );
	}
	?>

	<?php if ( $vehicle->get( 'enriched_description' ) ) : ?>
		<section class="wmds-description" itemprop="description">
			<h2><?php esc_html_e( 'Description', 'wp-mobile-de-sync' ); ?></h2>
			<?php echo wp_kses_post( $vehicle->get( 'enriched_description' ) ); ?>
		</section>
	<?php endif; ?>

	<?php $specs = $vehicle->specifications(); ?>
	<?php if ( $specs ) : ?>
		<section class="wmds-specs">
			<h2><?php esc_html_e( 'Specifications', 'wp-mobile-de-sync' ); ?></h2>
			<div class="wmds-specs__groups">
				<?php foreach ( $specs as $group => $rows ) : ?>
					<div class="wmds-specs__group">
						<h3><?php echo esc_html( $group ); ?></h3>
						<dl>
							<?php foreach ( $rows as $label => $value ) : ?>
								<dt><?php echo esc_html( $label ); ?></dt>
								<dd><?php echo esc_html( $value ); ?></dd>
							<?php endforeach; ?>
						</dl>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php $features = $vehicle->features(); ?>
	<?php if ( $features ) : ?>
		<section class="wmds-features">
			<h2><?php esc_html_e( 'Features', 'wp-mobile-de-sync' ); ?></h2>
			<ul>
				<?php foreach ( $features as $feature ) : ?>
					<li><?php echo esc_html( $feature ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $vehicle->has( 'emissionFuelConsumption_Combined' ) || $vehicle->has( 'emissionFuelConsumption_CO2' ) ) : ?>
		<footer class="wmds-legal">
			<p>
				<?php
				printf(
					/* translators: %s: link to the guide, already marked up as an anchor. */
					esc_html__( 'Further information on the official fuel consumption and the official specific CO₂ emissions of new passenger cars can be found in the guide on fuel consumption, CO₂ emissions and electricity consumption of new passenger cars, available free of charge at all points of sale and at %s.', 'wp-mobile-de-sync' ),
					'<a href="https://www.dat.de/" rel="noopener" target="_blank">www.dat.de</a>'
				);
				?>
			</p>
		</footer>
	<?php endif; ?>

</article>

	<?php
endwhile;

get_footer();
