<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wmds_args = isset( $args ) && is_array( $args ) ? $args : array();

$wmds_vehicle_id = isset( $wmds_args['vehicle_id'] ) ? (int) $wmds_args['vehicle_id'] : 0;
$wmds_heading    = isset( $wmds_args['heading'] ) && '' !== $wmds_args['heading']
	? (string) $wmds_args['heading']
	: __( 'Enquire about this vehicle', 'wp-mobile-de-sync' );

$wmds_state   = WMDS_Leads::state();
$wmds_errors  = $wmds_state['errors'];
$wmds_values  = $wmds_state['values'];
$wmds_consent = WMDS_Leads::consent_text();

$wmds_value = function ( $field ) use ( $wmds_values ) {
	return isset( $wmds_values[ $field ] ) ? (string) $wmds_values[ $field ] : '';
};
?>
<section class="wmds-enquiry" id="wmds-enquiry">
	<h2 class="wmds-enquiry__heading"><?php echo esc_html( $wmds_heading ); ?></h2>

	<?php if ( $wmds_state['sent'] ) : ?>
		<p class="wmds-enquiry__sent" role="status">
			<?php esc_html_e( 'Thank you — your enquiry is on its way. We will get back to you.', 'wp-mobile-de-sync' ); ?>
		</p>
	<?php else : ?>

		<?php if ( isset( $wmds_errors['form'] ) ) : ?>
			<p class="wmds-warning" role="alert"><?php echo esc_html( $wmds_errors['form'] ); ?></p>
		<?php endif; ?>

		<form class="wmds-enquiry__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( WMDS_Leads::NONCE ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( WMDS_Leads::ACTION ); ?>">
			<input type="hidden" name="wmds_vehicle" value="<?php echo esc_attr( $wmds_vehicle_id ); ?>">
			<input type="hidden" name="wmds_opened" value="<?php echo esc_attr( time() ); ?>">

			<p class="wmds-enquiry__trap" aria-hidden="true">
				<label for="wmds_website"><?php esc_html_e( 'Leave this field empty', 'wp-mobile-de-sync' ); ?></label>
				<input type="text" id="wmds_website" name="wmds_website" value="" tabindex="-1" autocomplete="off">
			</p>

			<div class="wmds-enquiry__fields">
				<p class="wmds-field">
					<label for="wmds_name">
						<?php esc_html_e( 'Name', 'wp-mobile-de-sync' ); ?> <span aria-hidden="true">*</span>
					</label>
					<input type="text" id="wmds_name" name="wmds_name" required autocomplete="name"
						value="<?php echo esc_attr( $wmds_value( 'name' ) ); ?>"
						<?php echo isset( $wmds_errors['name'] ) ? 'aria-invalid="true"' : ''; ?>>
					<?php if ( isset( $wmds_errors['name'] ) ) : ?>
						<span class="wmds-field__error"><?php echo esc_html( $wmds_errors['name'] ); ?></span>
					<?php endif; ?>
				</p>

				<p class="wmds-field">
					<label for="wmds_email">
						<?php esc_html_e( 'E-mail', 'wp-mobile-de-sync' ); ?> <span aria-hidden="true">*</span>
					</label>
					<input type="email" id="wmds_email" name="wmds_email" required autocomplete="email"
						value="<?php echo esc_attr( $wmds_value( 'email' ) ); ?>"
						<?php echo isset( $wmds_errors['email'] ) ? 'aria-invalid="true"' : ''; ?>>
					<?php if ( isset( $wmds_errors['email'] ) ) : ?>
						<span class="wmds-field__error"><?php echo esc_html( $wmds_errors['email'] ); ?></span>
					<?php endif; ?>
				</p>

				<p class="wmds-field">
					<label for="wmds_phone"><?php esc_html_e( 'Phone', 'wp-mobile-de-sync' ); ?></label>
					<input type="tel" id="wmds_phone" name="wmds_phone" autocomplete="tel"
						value="<?php echo esc_attr( $wmds_value( 'phone' ) ); ?>"
						<?php echo isset( $wmds_errors['phone'] ) ? 'aria-invalid="true"' : ''; ?>>
					<?php if ( isset( $wmds_errors['phone'] ) ) : ?>
						<span class="wmds-field__error"><?php echo esc_html( $wmds_errors['phone'] ); ?></span>
					<?php endif; ?>
				</p>
			</div>

			<p class="wmds-field wmds-field--wide">
				<label for="wmds_message">
					<?php esc_html_e( 'Your message', 'wp-mobile-de-sync' ); ?> <span aria-hidden="true">*</span>
				</label>
				<textarea id="wmds_message" name="wmds_message" rows="5" required
					placeholder="<?php esc_attr_e( 'When could I come and see it?', 'wp-mobile-de-sync' ); ?>"
					<?php echo isset( $wmds_errors['message'] ) ? 'aria-invalid="true"' : ''; ?>><?php echo esc_textarea( $wmds_value( 'message' ) ); ?></textarea>
				<?php if ( isset( $wmds_errors['message'] ) ) : ?>
					<span class="wmds-field__error"><?php echo esc_html( $wmds_errors['message'] ); ?></span>
				<?php endif; ?>
			</p>

			<?php if ( '' !== $wmds_consent ) : ?>
				<p class="wmds-field wmds-field--consent">
					<label>
						<input type="checkbox" name="wmds_consent" value="1" required
							<?php echo isset( $wmds_errors['consent'] ) ? 'aria-invalid="true"' : ''; ?>>
						<span><?php echo wp_kses_post( $wmds_consent ); ?></span>
					</label>
					<?php if ( isset( $wmds_errors['consent'] ) ) : ?>
						<span class="wmds-field__error"><?php echo esc_html( $wmds_errors['consent'] ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<p class="wmds-enquiry__submit">
				<button type="submit" class="wmds-button wmds-button--primary">
					<?php esc_html_e( 'Send the enquiry', 'wp-mobile-de-sync' ); ?>
				</button>
				<small><?php esc_html_e( 'Fields marked * are required.', 'wp-mobile-de-sync' ); ?></small>
			</p>
		</form>
	<?php endif; ?>
</section>
