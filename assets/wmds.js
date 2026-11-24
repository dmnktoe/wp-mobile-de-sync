( function () {
	'use strict';

	function button( className, label, path ) {
		var element = document.createElement( 'button' );

		element.type = 'button';
		element.className = 'wmds-gallery__nav ' + className;
		element.setAttribute( 'aria-label', label );
		element.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' + path + '"/></svg>';

		return element;
	}

	function setUpGallery( gallery ) {
		var main = gallery.querySelector( '[data-wmds-gallery-main]' );
		var frame = gallery.querySelector( '[data-wmds-gallery-link]' );
		var thumbs = gallery.querySelectorAll( '[data-wmds-gallery-thumb]' );
		var current = 0;
		var counter;

		if ( ! main || ! thumbs.length ) {
			return;
		}

		function show( index ) {
			var thumb;
			var full;
			var position;

			current = ( index + thumbs.length ) % thumbs.length;
			thumb = thumbs[ current ];
			full = thumb.getAttribute( 'data-full' );

			if ( ! full ) {
				return;
			}

			main.src = full;
			if ( frame ) {
				frame.href = full;
			}

			for ( position = 0; position < thumbs.length; position++ ) {
				thumbs[ position ].classList.toggle( 'is-current', position === current );
				thumbs[ position ].setAttribute( 'aria-current', position === current ? 'true' : 'false' );
			}

			if ( counter ) {
				counter.textContent = ( current + 1 ) + ' / ' + thumbs.length;
			}

			if ( thumb.scrollIntoView && gallery.classList.contains( 'wmds-gallery--enhanced' ) ) {
				thumb.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
			}
		}

		function bind( index ) {
			thumbs[ index ].addEventListener( 'click', function ( event ) {
				event.preventDefault();
				show( index );
			} );
		}

		for ( var index = 0; index < thumbs.length; index++ ) {
			bind( index );
		}

		if ( frame && thumbs.length > 1 ) {
			var stage = document.createElement( 'div' );
			var previous = button( 'wmds-gallery__nav--prev', gallery.getAttribute( 'data-label-prev' ) || 'Previous image', 'M15 5l-7 7 7 7' );
			var next = button( 'wmds-gallery__nav--next', gallery.getAttribute( 'data-label-next' ) || 'Next image', 'M9 5l7 7-7 7' );

			counter = document.createElement( 'span' );
			counter.className = 'wmds-gallery__counter';
			counter.setAttribute( 'aria-live', 'polite' );

			stage.className = 'wmds-gallery__stage';
			frame.parentNode.insertBefore( stage, frame );
			stage.appendChild( frame );
			stage.appendChild( previous );
			stage.appendChild( next );
			stage.appendChild( counter );

			previous.addEventListener( 'click', function () {
				show( current - 1 );
			} );
			next.addEventListener( 'click', function () {
				show( current + 1 );
			} );

			stage.addEventListener( 'keydown', function ( event ) {
				if ( 'ArrowLeft' === event.key ) {
					show( current - 1 );
				} else if ( 'ArrowRight' === event.key ) {
					show( current + 1 );
				}
			} );
		}

		show( 0 );
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
