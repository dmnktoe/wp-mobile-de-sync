/*
 * Progressive enhancement for the filter bar.
 *
 * Without this file the bar is a plain GET form: number inputs for the
 * ranges, a submit button, links to remove a filter. Everything here only
 * makes that nicer - the two-handle sliders that mirror into the number
 * inputs, the readout above them, and a sort control that submits on its
 * own because picking a sort order is a decision in itself.
 */
( function () {
	'use strict';

	function toNumber( value, fallback ) {
		var parsed = parseFloat( value );

		return isNaN( parsed ) ? fallback : parsed;
	}

	function format( value ) {
		try {
			return Number( value ).toLocaleString();
		} catch ( error ) {
			return String( value );
		}
	}

	function setUpRange( field ) {
		var low = field.querySelector( '[data-wmds-range-slider="min"]' );
		var high = field.querySelector( '[data-wmds-range-slider="max"]' );
		var lowInput = field.querySelector( '[data-wmds-range-input="min"]' );
		var highInput = field.querySelector( '[data-wmds-range-input="max"]' );
		var readout = field.querySelector( '[data-wmds-range-readout]' );
		var fill = field.querySelector( '[data-wmds-range-fill]' );

		if ( ! low || ! high || ! lowInput || ! highInput ) {
			return;
		}

		var floor = toNumber( field.getAttribute( 'data-min' ), 0 );
		var ceiling = toNumber( field.getAttribute( 'data-max' ), 0 );
		var span = ceiling - floor;

		function paint() {
			var from = toNumber( low.value, floor );
			var to = toNumber( high.value, ceiling );

			if ( readout ) {
				readout.textContent = format( from ) + ' – ' + format( to );
			}

			if ( fill && span > 0 ) {
				fill.style.left = ( ( from - floor ) / span ) * 100 + '%';
				fill.style.right = ( ( ceiling - to ) / span ) * 100 + '%';
			}
		}

		function fromSliders() {
			if ( toNumber( low.value, floor ) > toNumber( high.value, ceiling ) ) {
				var swap = low.value;
				low.value = high.value;
				high.value = swap;
			}

			lowInput.value = ( toNumber( low.value, floor ) > floor ) ? low.value : '';
			highInput.value = ( toNumber( high.value, ceiling ) < ceiling ) ? high.value : '';

			paint();
		}

		function fromInputs() {
			low.value = ( '' === lowInput.value ) ? floor : lowInput.value;
			high.value = ( '' === highInput.value ) ? ceiling : highInput.value;

			paint();
		}

		low.addEventListener( 'input', fromSliders );
		high.addEventListener( 'input', fromSliders );
		lowInput.addEventListener( 'change', fromInputs );
		highInput.addEventListener( 'change', fromInputs );

		field.classList.add( 'wmds-facet--enhanced' );
		paint();
	}

	function setUpForm( form ) {
		var ranges = form.querySelectorAll( '[data-wmds-range]' );
		var index;

		for ( index = 0; index < ranges.length; index++ ) {
			setUpRange( ranges[ index ] );
		}

		var sort = form.querySelector( 'select[name$="_sort"]' );
		if ( sort ) {
			sort.addEventListener( 'change', function () {
				form.submit();
			} );
		}

		form.classList.add( 'wmds-facets--enhanced' );
	}

	function init() {
		var forms = document.querySelectorAll( '[data-wmds-facets]' );
		var index;

		for ( index = 0; index < forms.length; index++ ) {
			setUpForm( forms[ index ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
