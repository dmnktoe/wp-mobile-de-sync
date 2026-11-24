( function () {
	'use strict';

	var root = document.documentElement;

	if ( navigator.clipboard ) {
		document.querySelectorAll( '.prose pre > code' ).forEach( function ( code ) {
			if ( code.classList.contains( 'language-mermaid' ) ) {
				return;
			}

			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'copy';
			button.textContent = 'Copy';

			button.addEventListener( 'click', function () {
				navigator.clipboard.writeText( code.innerText.replace( /\n$/, '' ) ).then( function () {
					button.textContent = 'Copied';
					button.setAttribute( 'data-done', '' );
					setTimeout( function () {
						button.textContent = 'Copy';
						button.removeAttribute( 'data-done' );
					}, 1600 );
				} );
			} );

			code.parentNode.appendChild( button );
		} );
	}

	var tocLinks = document.querySelectorAll( '.toc a[href^="#"]' );
	if ( tocLinks.length && 'IntersectionObserver' in window ) {
		var byId = {};
		var targets = [];

		tocLinks.forEach( function ( link ) {
			var target = document.getElementById( decodeURIComponent( link.hash.slice( 1 ) ) );
			if ( target ) {
				byId[ target.id ] = link;
				targets.push( target );
			}
		} );

		var visible = {};
		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				visible[ entry.target.id ] = entry.isIntersecting;
			} );

			var current = null;
			for ( var i = 0; i < targets.length; i++ ) {
				if ( visible[ targets[ i ].id ] ) {
					current = targets[ i ].id;
					break;
				}
			}

			if ( current ) {
				tocLinks.forEach( function ( link ) {
					link.removeAttribute( 'aria-current' );
				} );
				byId[ current ].setAttribute( 'aria-current', 'true' );
			}
		}, { rootMargin: '-70px 0px -60% 0px' } );

		targets.forEach( function ( target ) {
			observer.observe( target );
		} );
	}

	var diagrams = [];

	document.querySelectorAll( 'pre > code.language-mermaid' ).forEach( function ( code ) {
		var holder = document.createElement( 'div' );
		holder.className = 'mermaid';
		holder.textContent = code.textContent;

		diagrams.push( { holder: holder, source: code.textContent, pre: code.parentNode } );
	} );

	if ( diagrams.length ) {
		var dark = window.matchMedia( '(prefers-color-scheme: dark)' ).matches;

		import( 'https://cdn.jsdelivr.net/npm/mermaid@11.4.1/dist/mermaid.esm.min.mjs' ).then( function ( module ) {
			var mermaid = module.default;

			mermaid.initialize( {
				startOnLoad: false,
				securityLevel: 'strict',
				theme: dark ? 'dark' : 'neutral',
				fontFamily: getComputedStyle( root ).getPropertyValue( '--font' ).trim()
			} );

			diagrams.forEach( function ( diagram, index ) {
				mermaid.render( 'wmds-diagram-' + index, diagram.source ).then( function ( result ) {
					diagram.holder.innerHTML = result.svg;
					diagram.pre.parentNode.replaceChild( diagram.holder, diagram.pre );
				} );
			} );
		} ).catch( function () {} );
	}
}() );
