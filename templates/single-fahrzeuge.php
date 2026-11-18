<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

WMDS_Templates::header();

while ( have_posts() ) :
	the_post();
	$vehicle  = new WMDS_Vehicle();
	$images   = $vehicle->images();
	$archive  = get_post_type_archive_link( WMDS_CPT );
	$logo     = $vehicle->logo_url();
	$enquiry  = WMDS_Leads::enabled();
	$phone    = $vehicle->seller_phone();
	$email    = $vehicle->seller_email();
	$sticker  = $vehicle->emission_sticker_url();
	$subtitle = array_filter(
		array(
			$vehicle->get( 'category' ),
			$vehicle->get( 'condition' ),
			$vehicle->first_registration(),
		)
	);
	?>

<article id="vehicle-<?php the_ID(); ?>" class="wmds-scope wmds-single<?php echo esc_attr( WMDS_Templates::wrapper_class() ); ?>">

	<?php if ( $archive ) : ?>
		<nav class="wmds-single__back">
			<a href="<?php echo esc_url( $archive ); ?>">
				<?php WMDS_Templates::the_icon( 'arrow-left' ); ?>
				<?php esc_html_e( 'All vehicles', 'wp-mobile-de-sync' ); ?>
			</a>
		</nav>
	<?php endif; ?>

	<header class="wmds-single__header">
		<?php if ( $logo ) : ?>
			<span class="wmds-single__logo">
				<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $vehicle->get( 'make' ) ); ?>" width="56" height="56">
			</span>
		<?php endif; ?>
		<div class="wmds-single__titles">
			<h1 class="wmds-single__title"><?php the_title(); ?></h1>
			<?php if ( $subtitle ) : ?>
				<p class="wmds-single__subtitle"><?php echo esc_html( implode( ' · ', $subtitle ) ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( $vehicle->warnings() ) : ?>
		<p class="wmds-warning"><?php echo esc_html( implode( ' · ', $vehicle->warnings() ) ); ?></p>
	<?php endif; ?>

	<div class="wmds-single__main<?php echo ( $images || has_post_thumbnail() ) ? '' : ' wmds-single__main--no-gallery'; ?>">

		<?php WMDS_Templates::render( 'parts/vehicle-gallery.php', array( 'images' => $images ) ); ?>

		<aside class="wmds-summary">
			<?php if ( $vehicle->price() ) : ?>
				<p class="wmds-price">
					<span class="wmds-price__amount"><?php echo esc_html( $vehicle->price() ); ?></span>
					<small><?php echo esc_html( $vehicle->vat_note() ); ?></small>
				</p>
			<?php endif; ?>

			<?php if ( $vehicle->availability_note() ) : ?>
				<p class="wmds-availability"><?php echo esc_html( $vehicle->availability_note() ); ?></p>
			<?php endif; ?>

			<?php if ( $vehicle->facts() ) : ?>
				<dl class="wmds-highlights">
					<?php foreach ( $vehicle->facts() as $key => $fact ) : ?>
						<div class="wmds-highlight">
							<dt><?php WMDS_Templates::the_icon( $key ); ?><span><?php echo esc_html( $fact['label'] ); ?></span></dt>
							<dd><?php echo esc_html( $fact['value'] ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>

			<?php if ( $vehicle->inspection() || '' !== $sticker ) : ?>
				<div class="wmds-summary__badges">
					<?php if ( '' !== $sticker ) : ?>
						<img class="wmds-sticker" src="<?php echo esc_url( $sticker ); ?>"
							alt="<?php echo esc_attr( $vehicle->emission_sticker_label() ); ?>"
							width="36" height="36" loading="lazy">
					<?php endif; ?>
					<?php if ( $vehicle->inspection() ) : ?>
						<span class="wmds-inspection"><?php WMDS_Templates::the_icon( 'check' ); ?><?php echo esc_html( $vehicle->inspection() ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $enquiry || $phone || $email ) : ?>
				<div class="wmds-summary__actions">
					<?php if ( $enquiry ) : ?>
						<a class="wmds-button wmds-button--primary wmds-button--block" href="#wmds-enquiry">
							<?php WMDS_Templates::the_icon( 'message' ); ?>
							<?php esc_html_e( 'Enquire now', 'wp-mobile-de-sync' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $phone ) : ?>
						<a class="wmds-button wmds-button--block<?php echo $enquiry ? '' : ' wmds-button--primary'; ?>"
							href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ); ?>">
							<?php WMDS_Templates::the_icon( 'phone' ); ?>
							<?php echo esc_html( $phone ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $email && ! $enquiry ) : ?>
						<a class="wmds-button wmds-button--block"
							href="mailto:<?php echo esc_attr( $email ); ?>?subject=<?php /* translators: %s: the vehicle title. */ echo rawurlencode( sprintf( __( 'Enquiry about %s', 'wp-mobile-de-sync' ), get_the_title() ) ); ?>">
							<?php WMDS_Templates::the_icon( 'mail' ); ?>
							<?php esc_html_e( 'Send an e-mail', 'wp-mobile-de-sync' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $vehicle->seller() ) : ?>
				<p class="wmds-summary__seller"><?php echo esc_html( $vehicle->seller() ); ?></p>
			<?php endif; ?>
		</aside>
	</div>

	<?php if ( $vehicle->get( 'enriched_description' ) ) : ?>
		<section class="wmds-section wmds-description">
			<h2 class="wmds-section__title"><?php esc_html_e( 'Description', 'wp-mobile-de-sync' ); ?></h2>
			<div class="wmds-description__body">
				<?php echo wp_kses_post( $vehicle->get( 'enriched_description' ) ); ?>
			</div>
		</section>
	<?php endif; ?>

	<?php $specs = $vehicle->specifications(); ?>
	<?php if ( $specs ) : ?>
		<section class="wmds-section wmds-specs">
			<h2 class="wmds-section__title"><?php esc_html_e( 'Specifications', 'wp-mobile-de-sync' ); ?></h2>
			<div class="wmds-specs__groups">
				<?php foreach ( $specs as $group => $rows ) : ?>
					<div class="wmds-specs__group">
						<h3><?php echo esc_html( $group ); ?></h3>
						<dl>
							<?php foreach ( $rows as $label => $value ) : ?>
								<div class="wmds-specs__row">
									<dt><?php echo esc_html( $label ); ?></dt>
									<dd><?php echo esc_html( $value ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php $features = $vehicle->features(); ?>
	<?php if ( $features ) : ?>
		<section class="wmds-section wmds-features">
			<h2 class="wmds-section__title"><?php esc_html_e( 'Features', 'wp-mobile-de-sync' ); ?></h2>
			<ul>
				<?php foreach ( $features as $feature ) : ?>
					<li><?php WMDS_Templates::the_icon( 'check' ); ?><span><?php echo esc_html( $feature ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php
	if ( $enquiry ) {
		WMDS_Templates::render( 'parts/enquiry-form.php', array( 'vehicle_id' => get_the_ID() ) );
	}
	?>

	<?php
	if ( WMDS_Settings::flag( 'similar_enabled' ) ) {
		WMDS_Templates::render( 'parts/similar-vehicles.php', array( 'vehicle_id' => get_the_ID() ) );
	}
	?>

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

WMDS_Templates::footer();
