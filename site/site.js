/*
 * Project site of mobile.de Sync: colour theme, copy buttons, the section
 * highlight in "On this page", Mermaid diagrams, and a check for a release
 * newer than the one the page was built with. Everything here is an
 * enhancement - the pages read and work without it.
 */
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

	/* Theme ------------------------------------------------------------ */

	var toggle = document.querySelector( '.theme-toggle' );
	if ( toggle ) {
		toggle.addEventListener( 'click', function () {
			var next = isDark() ? 'light' : 'dark';
			root.setAttribute( 'data-theme', next );
			store( 'wmds-theme', next );
			renderDiagrams();
		} );
	}

	/* Copy buttons ------------------------------------------------------ */

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

	/* On this page ------------------------------------------------------ */

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

	/* Mermaid diagrams ---------------------------------------------------- */

	var diagrams = [];

	document.querySelectorAll( 'pre > code.language-mermaid' ).forEach( function ( code ) {
		var holder = document.createElement( 'div' );
		holder.className = 'mermaid';
		holder.setAttribute( 'role', 'img' );
		holder.setAttribute( 'aria-label', 'Diagram' );
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
				fontFamily: styles.getPropertyValue( '--font' ),
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
		} ).catch( function () {
			// The source stays on the page as a code block.
		} );
	}

	renderDiagrams();

	/* Newer release ------------------------------------------------------- */

	// The site is rebuilt with every release, so this only matters in the
	// minutes between a release and the deployment that follows it.
	var built = root.getAttribute( 'data-version' );
	var repo = root.getAttribute( 'data-repo' );

	function newer( a, b ) {
		var x = String( a ).split( '.' ).map( Number );
		var y = String( b ).split( '.' ).map( Number );
		for ( var i = 0; i < Math.max( x.length, y.length ); i++ ) {
			if ( ( x[ i ] || 0 ) !== ( y[ i ] || 0 ) ) {
				return ( x[ i ] || 0 ) > ( y[ i ] || 0 );
			}
		}
		return false;
	}

	function apply( release ) {
		var version = String( release.tag_name || '' ).replace( /^v/, '' );
		if ( ! version || ! newer( version, built ) ) {
			return;
		}

		document.querySelectorAll( '[data-latest-version]' ).forEach( function ( node ) {
			node.textContent = version;
		} );

		if ( release.published_at ) {
			var date = new Date( release.published_at );
			document.querySelectorAll( '[data-latest-date]' ).forEach( function ( node ) {
				node.setAttribute( 'datetime', release.published_at.slice( 0, 10 ) );
				node.textContent = date.toLocaleDateString( 'en-GB', { day: 'numeric', month: 'long', year: 'numeric' } );
			} );
		}
	}

	if ( repo && built && window.fetch && document.querySelector( '[data-latest-version]' ) ) {
		var cached = null;
		try {
			cached = JSON.parse( window.sessionStorage.getItem( 'wmds-release' ) || 'null' );
		} catch ( e ) {}

		if ( cached && Date.now() - cached.at < 3600000 ) {
			apply( cached.release );
		} else {
			fetch( 'https://api.github.com/repos/' + repo + '/releases/latest', { headers: { Accept: 'application/vnd.github+json' } } )
				.then( function ( response ) {
					return response.ok ? response.json() : null;
				} )
				.then( function ( release ) {
					if ( ! release ) {
						return;
					}
					var slim = { tag_name: release.tag_name, published_at: release.published_at };
					try {
						window.sessionStorage.setItem( 'wmds-release', JSON.stringify( { at: Date.now(), release: slim } ) );
					} catch ( e ) {}
					apply( slim );
				} )
				.catch( function () {} );
		}
	}
}() );
