<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wmds_args = isset( $args ) && is_array( $args ) ? $args : array();

$wmds_selection = isset( $wmds_args['selection'] ) && is_array( $wmds_args['selection'] ) ? $wmds_args['selection'] : array();
$wmds_only      = isset( $wmds_args['only'] ) && is_array( $wmds_args['only'] ) ? $wmds_args['only'] : array();
$wmds_layout    = ( isset( $wmds_args['layout'] ) && 'sidebar' === $wmds_args['layout'] ) ? 'sidebar' : 'bar';
$wmds_counts    = ! isset( $wmds_args['counts'] ) || (bool) $wmds_args['counts'];
$wmds_heading   = isset( $wmds_args['heading'] ) ? (string) $wmds_args['heading'] : '';

$wmds_facets = WMDS_Facets::definitions();
if ( $wmds_only ) {
	$wmds_facets = array_intersect_key( $wmds_facets, array_flip( $wmds_only ) );
}

$wmds_chips = WMDS_Facets::chips( $wmds_selection );
?>
<form class="wmds-facets wmds-facets--<?php echo esc_attr( $wmds_layout ); ?>" method="get"
	action="<?php echo esc_url( WMDS_Facets::base_url() ); ?>" data-wmds-facets>

	<?php if ( '' !== $wmds_heading ) : ?>
		<h2 class="wmds-facets__heading"><?php echo esc_html( $wmds_heading ); ?></h2>
	<?php endif; ?>

	<div class="wmds-facets__fields">
		<?php
		foreach ( $wmds_facets as $wmds_key => $wmds_facet ) :
			$wmds_type  = isset( $wmds_facet['type'] ) ? $wmds_facet['type'] : 'select';
			$wmds_label = isset( $wmds_facet['label'] ) ? $wmds_facet['label'] : $wmds_key;
			$wmds_id    = 'wmds-facet-' . sanitize_html_class( $wmds_key );
			$wmds_name  = WMDS_Facets::PREFIX . $wmds_key;
			$wmds_value = isset( $wmds_selection[ $wmds_key ] ) ? $wmds_selection[ $wmds_key ] : null;

			if ( 'search' === $wmds_type ) :
				?>
				<div class="wmds-facet wmds-facet--search">
					<label class="wmds-facet__label" for="<?php echo esc_attr( $wmds_id ); ?>"><?php echo esc_html( $wmds_label ); ?></label>
					<input type="search" id="<?php echo esc_attr( $wmds_id ); ?>" name="<?php echo esc_attr( $wmds_name ); ?>"
						value="<?php echo esc_attr( is_string( $wmds_value ) ? $wmds_value : '' ); ?>"
						placeholder="<?php esc_attr_e( 'Make, model, keyword', 'wp-mobile-de-sync' ); ?>">
				</div>
				<?php
				continue;
			endif;

			if ( 'range' === $wmds_type ) :
				$wmds_bounds = WMDS_Facets::bounds( $wmds_key, $wmds_selection );
				if ( ! $wmds_bounds ) {
					continue;
				}

				$wmds_step = isset( $wmds_facet['step'] ) ? (float) $wmds_facet['step'] : 1;
				$wmds_unit = isset( $wmds_facet['unit'] ) ? (string) $wmds_facet['unit'] : '';
				$wmds_min  = isset( $wmds_value['min'] ) ? (float) $wmds_value['min'] : $wmds_bounds['min'];
				$wmds_max  = isset( $wmds_value['max'] ) ? (float) $wmds_value['max'] : $wmds_bounds['max'];
				?>
				<fieldset class="wmds-facet wmds-facet--range" data-wmds-range
					data-min="<?php echo esc_attr( $wmds_bounds['min'] ); ?>"
					data-max="<?php echo esc_attr( $wmds_bounds['max'] ); ?>"
					data-step="<?php echo esc_attr( $wmds_step ); ?>">
					<legend class="wmds-facet__label">
						<?php echo esc_html( $wmds_label ); ?>
						<span class="wmds-range__readout" data-wmds-range-readout>
							<?php
							echo esc_html(
								WMDS_Facets::range_label(
									array(
										'min' => $wmds_min,
										'max' => $wmds_max,
									),
									$wmds_unit
								)
							);
							?>
						</span>
					</legend>

					<div class="wmds-range" aria-hidden="true">
						<span class="wmds-range__track"><span class="wmds-range__fill" data-wmds-range-fill></span></span>
						<input type="range" class="wmds-range__slider" data-wmds-range-slider="min" tabindex="-1"
							min="<?php echo esc_attr( $wmds_bounds['min'] ); ?>"
							max="<?php echo esc_attr( $wmds_bounds['max'] ); ?>"
							step="<?php echo esc_attr( $wmds_step ); ?>"
							value="<?php echo esc_attr( $wmds_min ); ?>">
						<input type="range" class="wmds-range__slider" data-wmds-range-slider="max" tabindex="-1"
							min="<?php echo esc_attr( $wmds_bounds['min'] ); ?>"
							max="<?php echo esc_attr( $wmds_bounds['max'] ); ?>"
							step="<?php echo esc_attr( $wmds_step ); ?>"
							value="<?php echo esc_attr( $wmds_max ); ?>">
					</div>

					<div class="wmds-range__inputs">
						<label>
							<span class="screen-reader-text"><?php esc_html_e( 'from', 'wp-mobile-de-sync' ); ?></span>
							<input type="number" inputmode="numeric" data-wmds-range-input="min"
								name="<?php echo esc_attr( $wmds_name . '_min' ); ?>"
								min="<?php echo esc_attr( $wmds_bounds['min'] ); ?>"
								max="<?php echo esc_attr( $wmds_bounds['max'] ); ?>"
								step="<?php echo esc_attr( $wmds_step ); ?>"
								placeholder="<?php echo esc_attr( $wmds_bounds['min'] ); ?>"
								value="<?php echo esc_attr( isset( $wmds_value['min'] ) ? $wmds_value['min'] : '' ); ?>">
						</label>
						<span class="wmds-range__dash" aria-hidden="true">–</span>
						<label>
							<span class="screen-reader-text"><?php esc_html_e( 'up to', 'wp-mobile-de-sync' ); ?></span>
							<input type="number" inputmode="numeric" data-wmds-range-input="max"
								name="<?php echo esc_attr( $wmds_name . '_max' ); ?>"
								min="<?php echo esc_attr( $wmds_bounds['min'] ); ?>"
								max="<?php echo esc_attr( $wmds_bounds['max'] ); ?>"
								step="<?php echo esc_attr( $wmds_step ); ?>"
								placeholder="<?php echo esc_attr( $wmds_bounds['max'] ); ?>"
								value="<?php echo esc_attr( isset( $wmds_value['max'] ) ? $wmds_value['max'] : '' ); ?>">
						</label>
						<?php if ( '' !== $wmds_unit ) : ?>
							<span class="wmds-range__unit"><?php echo esc_html( $wmds_unit ); ?></span>
						<?php endif; ?>
					</div>
				</fieldset>
				<?php
				continue;
			endif;

			$wmds_choices = WMDS_Facets::choices( $wmds_key, $wmds_selection );
			if ( count( $wmds_choices ) < 2 && ! $wmds_value ) {
				continue;
			}

			if ( 'select' === $wmds_type ) :
				?>
				<div class="wmds-facet wmds-facet--select">
					<label class="wmds-facet__label" for="<?php echo esc_attr( $wmds_id ); ?>"><?php echo esc_html( $wmds_label ); ?></label>
					<select id="<?php echo esc_attr( $wmds_id ); ?>" name="<?php echo esc_attr( $wmds_name ); ?>">
						<option value=""><?php esc_html_e( 'Any', 'wp-mobile-de-sync' ); ?></option>
						<?php foreach ( $wmds_choices as $wmds_choice => $wmds_total ) : ?>
							<option value="<?php echo esc_attr( $wmds_choice ); ?>"
								<?php selected( in_array( (string) $wmds_choice, array_map( 'strval', (array) $wmds_value ), true ) ); ?>>
								<?php
								echo esc_html(
									$wmds_counts
										? sprintf( '%s (%s)', $wmds_choice, number_format_i18n( $wmds_total ) )
										: $wmds_choice
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php
				continue;
			endif;
			?>

			<fieldset class="wmds-facet wmds-facet--<?php echo esc_attr( $wmds_type ); ?>">
				<legend class="wmds-facet__label"><?php echo esc_html( $wmds_label ); ?></legend>
				<ul class="wmds-facet__options">
					<?php if ( 'radio' === $wmds_type ) : ?>
						<li>
							<label>
								<input type="radio" name="<?php echo esc_attr( $wmds_name ); ?>" value=""
									<?php checked( null === $wmds_value ); ?>>
								<span class="wmds-facet__option-label"><?php esc_html_e( 'Any', 'wp-mobile-de-sync' ); ?></span>
							</label>
						</li>
					<?php endif; ?>

					<?php foreach ( $wmds_choices as $wmds_choice => $wmds_total ) : ?>
						<li>
							<label>
								<input type="<?php echo ( 'radio' === $wmds_type ) ? 'radio' : 'checkbox'; ?>"
									name="<?php echo esc_attr( ( 'radio' === $wmds_type ) ? $wmds_name : $wmds_name . '[]' ); ?>"
									value="<?php echo esc_attr( $wmds_choice ); ?>"
									<?php checked( in_array( (string) $wmds_choice, array_map( 'strval', (array) $wmds_value ), true ) ); ?>>
								<span class="wmds-facet__option-label"><?php echo esc_html( $wmds_choice ); ?></span>
								<?php if ( $wmds_counts ) : ?>
									<span class="wmds-facet__count"><?php echo esc_html( number_format_i18n( $wmds_total ) ); ?></span>
								<?php endif; ?>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</fieldset>
			<?php
		endforeach;
		?>
	</div>

	<div class="wmds-facets__actions">
		<label class="wmds-facet wmds-facet--sort">
			<span class="wmds-facet__label"><?php esc_html_e( 'Sort by', 'wp-mobile-de-sync' ); ?></span>
			<select name="<?php echo esc_attr( WMDS_Facets::PREFIX . 'sort' ); ?>">
				<?php foreach ( WMDS_Facets::sorts() as $wmds_sort_key => $wmds_sort ) : ?>
					<option value="<?php echo esc_attr( $wmds_sort_key ); ?>"
						<?php selected( isset( $wmds_selection['sort'] ) ? $wmds_selection['sort'] : 'newest', $wmds_sort_key ); ?>>
						<?php echo esc_html( $wmds_sort['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>

		<button type="submit" class="wmds-button wmds-button--primary">
			<?php esc_html_e( 'Show vehicles', 'wp-mobile-de-sync' ); ?>
		</button>

		<?php if ( $wmds_chips ) : ?>
			<a class="wmds-button wmds-button--quiet" href="<?php echo esc_url( WMDS_Facets::base_url() ); ?>">
				<?php esc_html_e( 'Reset', 'wp-mobile-de-sync' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( $wmds_chips ) : ?>
		<ul class="wmds-facets__chips">
			<?php foreach ( $wmds_chips as $wmds_chip ) : ?>
				<li>
					<a class="wmds-chip" href="<?php echo esc_url( $wmds_chip['url'] ); ?>">
						<span class="wmds-chip__label"><?php echo esc_html( $wmds_chip['label'] ); ?>:</span>
						<span class="wmds-chip__value"><?php echo esc_html( $wmds_chip['value'] ); ?></span>
						<span class="wmds-chip__remove" aria-hidden="true">×</span>
						<span class="screen-reader-text"><?php esc_html_e( 'Remove this filter', 'wp-mobile-de-sync' ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</form>
