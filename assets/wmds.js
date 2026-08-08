/*
 * Behaviour for the bundled templates.
 *
 * The gallery works without this file: every thumbnail is a link to the
 * full image, which is what a browser without JavaScript should get. Here
 * the click swaps the main image instead of leaving the page.
 */
( function () {
	'use strict';

	function setUpGallery( gallery ) {
		var main = gallery.querySelector( '[data-wmds-gallery-main]' );
		var frame = gallery.querySelector( '[data-wmds-gallery-link]' );
		var thumbs = gallery.querySelectorAll( '[data-wmds-gallery-thumb]' );

		if ( ! main || ! thumbs.length ) {
			return;
		}

		function show( thumb ) {
			var full = thumb.getAttribute( 'data-full' );
			var index;

			if ( ! full ) {
				return;
			}

			main.src = full;
			if ( frame ) {
				frame.href = full;
			}

			for ( index = 0; index < thumbs.length; index++ ) {
				thumbs[ index ].classList.toggle( 'is-current', thumbs[ index ] === thumb );
				thumbs[ index ].setAttribute( 'aria-current', thumbs[ index ] === thumb ? 'true' : 'false' );
			}
		}

		function bind( thumb ) {
			thumb.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				show( thumb );
			} );
		}

		for ( var index = 0; index < thumbs.length; index++ ) {
			bind( thumbs[ index ] );
		}

		gallery.classList.add( 'wmds-gallery--enhanced' );
	}

	function init() {
		var galleries = document.querySelectorAll( '[data-wmds-gallery]' );
		var index;

		for ( index = 0; index < galleries.length; index++ ) {
			setUpGallery( galleries[ index ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
