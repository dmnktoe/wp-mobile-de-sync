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

$wmds_primary   = array();
$wmds_secondary = array();
foreach ( $wmds_facets as $wmds_key => $wmds_facet ) {
	$wmds_type = isset( $wmds_facet['type'] ) ? $wmds_facet['type'] : 'select';
	if ( in_array( $wmds_type, array( 'search', 'select' ), true ) ) {
		$wmds_primary[ $wmds_key ] = $wmds_facet;
	} else {
		$wmds_secondary[ $wmds_key ] = $wmds_facet;
	}
}

$wmds_open = 0;
foreach ( array_keys( $wmds_secondary ) as $wmds_key ) {
	if ( isset( $wmds_selection[ $wmds_key ] ) ) {
		++$wmds_open;
	}
}

$wmds_chips = WMDS_Facets::chips( $wmds_selection );

/**
 * @param string $key
 * @param array $facet
 * @param array $selection
 * @param bool $counts
 */
$wmds_render_facet = static function ( $key, array $facet, array $selection, $counts ) {
	$type  = isset( $facet['type'] ) ? $facet['type'] : 'select';
	$label = isset( $facet['label'] ) ? $facet['label'] : $key;
	$id    = 'wmds-facet-' . sanitize_html_class( $key );
	$name  = WMDS_Facets::PREFIX . $key;
	$value = isset( $selection[ $key ] ) ? $selection[ $key ] : null;

	if ( 'search' === $type ) :
		?>
		<div class="wmds-facet wmds-facet--search">
			<label class="wmds-facet__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<span class="wmds-input-icon">
				<?php WMDS_Templates::the_icon( 'search' ); ?>
				<input type="search" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( is_string( $value ) ? $value : '' ); ?>"
					placeholder="<?php esc_attr_e( 'Make, model, keyword', 'wp-mobile-de-sync' ); ?>">
			</span>
		</div>
		<?php
		return;
	endif;

	if ( 'range' === $type ) :
		$bounds = WMDS_Facets::bounds( $key, $selection );
		if ( ! $bounds ) {
			return;
		}

		$step     = isset( $facet['step'] ) ? (float) $facet['step'] : 1;
		$unit     = isset( $facet['unit'] ) ? (string) $facet['unit'] : '';
		$grouping = ! isset( $facet['grouping'] ) || false !== $facet['grouping'];
		$min      = isset( $value['min'] ) ? (float) $value['min'] : $bounds['min'];
		$max      = isset( $value['max'] ) ? (float) $value['max'] : $bounds['max'];
		?>
		<fieldset class="wmds-facet wmds-facet--range" data-wmds-range
			data-min="<?php echo esc_attr( $bounds['min'] ); ?>"
			data-max="<?php echo esc_attr( $bounds['max'] ); ?>"
			data-step="<?php echo esc_attr( $step ); ?>"
			data-unit="<?php echo esc_attr( $unit ); ?>"
			<?php echo $grouping ? '' : 'data-plain'; ?>>
			<legend class="wmds-facet__label">
				<?php echo esc_html( $label ); ?>
				<span class="wmds-range__readout" data-wmds-range-readout>
					<?php
					echo esc_html(
						WMDS_Facets::range_label(
							array(
								'min' => $min,
								'max' => $max,
							),
							$unit,
							$grouping
						)
					);
					?>
				</span>
			</legend>

			<div class="wmds-range" aria-hidden="true">
				<span class="wmds-range__track"><span class="wmds-range__fill" data-wmds-range-fill></span></span>
				<input type="range" class="wmds-range__slider" data-wmds-range-slider="min" tabindex="-1"
					min="<?php echo esc_attr( $bounds['min'] ); ?>"
					max="<?php echo esc_attr( $bounds['max'] ); ?>"
					step="<?php echo esc_attr( $step ); ?>"
					value="<?php echo esc_attr( $min ); ?>">
				<input type="range" class="wmds-range__slider" data-wmds-range-slider="max" tabindex="-1"
					min="<?php echo esc_attr( $bounds['min'] ); ?>"
					max="<?php echo esc_attr( $bounds['max'] ); ?>"
					step="<?php echo esc_attr( $step ); ?>"
					value="<?php echo esc_attr( $max ); ?>">
			</div>

			<div class="wmds-range__inputs">
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'from', 'wp-mobile-de-sync' ); ?></span>
					<input type="number" inputmode="numeric" data-wmds-range-input="min"
						name="<?php echo esc_attr( $name . '_min' ); ?>"
						step="any"
						placeholder="<?php echo esc_attr( WMDS_Facets::number( $bounds['min'], $grouping ) ); ?>"
						value="<?php echo esc_attr( isset( $value['min'] ) ? $value['min'] : '' ); ?>">
				</label>
				<span class="wmds-range__dash" aria-hidden="true">–</span>
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'up to', 'wp-mobile-de-sync' ); ?></span>
					<input type="number" inputmode="numeric" data-wmds-range-input="max"
						name="<?php echo esc_attr( $name . '_max' ); ?>"
						step="any"
						placeholder="<?php echo esc_attr( WMDS_Facets::number( $bounds['max'], $grouping ) ); ?>"
						value="<?php echo esc_attr( isset( $value['max'] ) ? $value['max'] : '' ); ?>">
				</label>
				<?php if ( '' !== $unit ) : ?>
					<span class="wmds-range__unit"><?php echo esc_html( $unit ); ?></span>
				<?php endif; ?>
			</div>
		</fieldset>
		<?php
		return;
	endif;

	$choices = WMDS_Facets::choices( $key, $selection );
	if ( count( $choices ) < 2 && ! $value ) {
		return;
	}

	$chosen = array_map( 'strval', (array) $value );

	if ( 'select' === $type ) :
		?>
		<div class="wmds-facet wmds-facet--select">
			<label class="wmds-facet__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<span class="wmds-select">
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<option value=""><?php esc_html_e( 'Any', 'wp-mobile-de-sync' ); ?></option>
					<?php foreach ( $choices as $choice => $total ) : ?>
						<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( in_array( (string) $choice, $chosen, true ) ); ?>>
							<?php echo esc_html( $counts ? sprintf( '%s (%s)', $choice, number_format_i18n( $total ) ) : $choice ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</span>
		</div>
		<?php
		return;
	endif;

	$radio = ( 'radio' === $type );
	?>
	<fieldset class="wmds-facet wmds-facet--<?php echo esc_attr( $type ); ?>">
		<legend class="wmds-facet__label"><?php echo esc_html( $label ); ?></legend>
		<div class="wmds-pills">
			<?php if ( $radio ) : ?>
				<label class="wmds-pill">
					<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="" <?php checked( null === $value ); ?>>
					<span class="wmds-pill__text"><?php esc_html_e( 'Any', 'wp-mobile-de-sync' ); ?></span>
				</label>
			<?php endif; ?>

			<?php foreach ( $choices as $choice => $total ) : ?>
				<label class="wmds-pill">
					<input type="<?php echo $radio ? 'radio' : 'checkbox'; ?>"
						name="<?php echo esc_attr( $radio ? $name : $name . '[]' ); ?>"
						value="<?php echo esc_attr( $choice ); ?>"
						<?php checked( in_array( (string) $choice, $chosen, true ) ); ?>>
					<span class="wmds-pill__text">
						<?php echo esc_html( $choice ); ?>
						<?php if ( $counts ) : ?>
							<span class="wmds-pill__count"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
						<?php endif; ?>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
	</fieldset>
	<?php
};
?>
<form class="wmds-scope wmds-facets wmds-facets--<?php echo esc_attr( $wmds_layout ); ?>" method="get"
	action="<?php echo esc_url( WMDS_Facets::base_url() ); ?>" data-wmds-facets>

	<?php
	$wmds_base_args = array();
	wp_parse_str( (string) wp_parse_url( WMDS_Facets::base_url(), PHP_URL_QUERY ), $wmds_base_args );
	foreach ( $wmds_base_args as $wmds_arg => $wmds_arg_value ) :
		if ( ! is_scalar( $wmds_arg_value ) || 0 === strpos( (string) $wmds_arg, WMDS_Facets::PREFIX ) ) {
			continue;
		}
		?>
		<input type="hidden" name="<?php echo esc_attr( $wmds_arg ); ?>" value="<?php echo esc_attr( $wmds_arg_value ); ?>">
	<?php endforeach; ?>

	<?php if ( '' !== $wmds_heading ) : ?>
		<h2 class="wmds-facets__heading"><?php echo esc_html( $wmds_heading ); ?></h2>
	<?php endif; ?>

	<?php if ( $wmds_primary ) : ?>
		<div class="wmds-facets__fields wmds-facets__primary">
			<?php
			foreach ( $wmds_primary as $wmds_key => $wmds_facet ) {
				$wmds_render_facet( $wmds_key, $wmds_facet, $wmds_selection, $wmds_counts );
			}
			?>
		</div>
	<?php endif; ?>

	<?php if ( $wmds_secondary ) : ?>
		<details class="wmds-facets__more" <?php echo ( $wmds_open || 'sidebar' === $wmds_layout || ! $wmds_primary ) ? 'open' : ''; ?>>
			<summary class="wmds-facets__toggle">
				<?php WMDS_Templates::the_icon( 'sliders' ); ?>
				<span><?php esc_html_e( 'More filters', 'wp-mobile-de-sync' ); ?></span>
				<?php if ( $wmds_open ) : ?>
					<span class="wmds-facets__badge"><?php echo esc_html( number_format_i18n( $wmds_open ) ); ?></span>
				<?php endif; ?>
				<?php WMDS_Templates::the_icon( 'chevron-down' ); ?>
			</summary>
			<div class="wmds-facets__fields wmds-facets__secondary">
				<?php
				foreach ( $wmds_secondary as $wmds_key => $wmds_facet ) {
					$wmds_render_facet( $wmds_key, $wmds_facet, $wmds_selection, $wmds_counts );
				}
				?>
			</div>
		</details>
	<?php endif; ?>

	<div class="wmds-facets__actions">
		<label class="wmds-facet wmds-facet--sort">
			<span class="wmds-facet__label"><?php esc_html_e( 'Sort by', 'wp-mobile-de-sync' ); ?></span>
			<span class="wmds-select">
				<select name="<?php echo esc_attr( WMDS_Facets::PREFIX . 'sort' ); ?>">
					<?php foreach ( WMDS_Facets::sorts() as $wmds_sort_key => $wmds_sort ) : ?>
						<option value="<?php echo esc_attr( $wmds_sort_key ); ?>"
							<?php selected( isset( $wmds_selection['sort'] ) ? $wmds_selection['sort'] : 'newest', $wmds_sort_key ); ?>>
							<?php echo esc_html( $wmds_sort['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</span>
		</label>

		<div class="wmds-facets__buttons">
			<?php if ( $wmds_chips ) : ?>
				<a class="wmds-button wmds-button--quiet" href="<?php echo esc_url( WMDS_Facets::base_url() ); ?>">
					<?php esc_html_e( 'Reset', 'wp-mobile-de-sync' ); ?>
				</a>
			<?php endif; ?>

			<button type="submit" class="wmds-button wmds-button--primary">
				<?php esc_html_e( 'Show vehicles', 'wp-mobile-de-sync' ); ?>
			</button>
		</div>
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
