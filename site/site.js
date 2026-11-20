( function () {
	'use strict';

	var root = document.documentElement;

	function store( key, value ) {
		try {
			if ( value === undefined ) {
				return window.localStorage.getItem( key );
			}
			window.localStorage.setItem( key, value );
		} catch ( e ) {}
		return null;
	}

	function isDark() {
		var chosen = root.getAttribute( 'data-theme' );
		if ( chosen ) {
			return chosen === 'dark';
		}
		return window.matchMedia( '(prefers-color-scheme: dark)' ).matches;
	}


	var toggle = document.querySelector( '.theme-toggle' );
	if ( toggle ) {
		toggle.addEventListener( 'click', function () {
			var next = isDark() ? 'light' : 'dark';
			root.setAttribute( 'data-theme', next );
			store( 'wmds-theme', next );
			renderDiagrams();
		} );
	}


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

	var mermaid = null;

	function renderDiagrams() {
		if ( ! diagrams.length ) {
			return;
		}

		var load = mermaid
			? Promise.resolve( mermaid )
			: import( 'https://cdn.jsdelivr.net/npm/mermaid@11.4.1/dist/mermaid.esm.min.mjs' ).then( function ( module ) {
				mermaid = module.default;
				return mermaid;
			} );

		load.then( function ( m ) {
			var styles = getComputedStyle( root );
			var dark = isDark();

			m.initialize( {
				startOnLoad: false,
				securityLevel: 'strict',
				theme: 'base',
				fontFamily: styles.getPropertyValue( '--font' ).trim(),
				themeVariables: {
					darkMode: dark,
					background: styles.getPropertyValue( '--surface' ).trim(),
					primaryColor: dark ? '#2a2033' : '#f3ecf7',
					primaryTextColor: styles.getPropertyValue( '--text' ).trim(),
					primaryBorderColor: dark ? '#7a4d99' : '#380050',
					lineColor: dark ? '#a79fb2' : '#625a6c',
					secondaryColor: dark ? '#3a1d12' : '#fff1eb',
					tertiaryColor: styles.getPropertyValue( '--surface' ).trim()
				}
			} );

			diagrams.forEach( function ( diagram, index ) {
				m.render( 'wmds-diagram-' + index + '-' + Date.now(), diagram.source ).then( function ( result ) {
					diagram.holder.innerHTML = result.svg;
					if ( diagram.pre.parentNode ) {
						diagram.pre.parentNode.replaceChild( diagram.holder, diagram.pre );
					}
				} );
			} );
		} ).catch( function () {} );
	}

	renderDiagrams();
}() );
