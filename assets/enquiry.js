( function ( root ) {
	'use strict';

	var K = [
		0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
		0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
		0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
		0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
		0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
		0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
		0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
		0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
	];

	var W = new Array( 64 );

		function sha256( text ) {
		var length = text.length;
		var blocks = ( ( length + 8 ) >> 6 ) + 1;
		var words = new Array( blocks * 16 );
		var h0 = 0x6a09e667, h1 = 0xbb67ae85, h2 = 0x3c6ef372, h3 = 0xa54ff53a;
		var h4 = 0x510e527f, h5 = 0x9b05688c, h6 = 0x1f83d9ab, h7 = 0x5be0cd19;
		var i, j, a, b, c, d, e, f, g, h, s0, s1, t1, t2, out, hex;

		for ( i = 0; i < words.length; i++ ) {
			words[ i ] = 0;
		}
		for ( i = 0; i < length; i++ ) {
			words[ i >> 2 ] |= ( text.charCodeAt( i ) & 0xff ) << ( 24 - ( ( i & 3 ) << 3 ) );
		}
		words[ length >> 2 ] |= 0x80 << ( 24 - ( ( length & 3 ) << 3 ) );
		words[ words.length - 1 ] = length * 8;

		for ( i = 0; i < words.length; i += 16 ) {
			for ( j = 0; j < 16; j++ ) {
				W[ j ] = words[ i + j ];
			}
			for ( j = 16; j < 64; j++ ) {
				s0 = W[ j - 15 ];
				s1 = W[ j - 2 ];
				W[ j ] = ( ( ( s0 >>> 7 ) | ( s0 << 25 ) ) ^ ( ( s0 >>> 18 ) | ( s0 << 14 ) ) ^ ( s0 >>> 3 ) ) +
					W[ j - 7 ] +
					( ( ( s1 >>> 17 ) | ( s1 << 15 ) ) ^ ( ( s1 >>> 19 ) | ( s1 << 13 ) ) ^ ( s1 >>> 10 ) ) +
					W[ j - 16 ] | 0;
			}

			a = h0; b = h1; c = h2; d = h3; e = h4; f = h5; g = h6; h = h7;

			for ( j = 0; j < 64; j++ ) {
				t1 = h + ( ( ( e >>> 6 ) | ( e << 26 ) ) ^ ( ( e >>> 11 ) | ( e << 21 ) ) ^ ( ( e >>> 25 ) | ( e << 7 ) ) ) +
					( ( e & f ) ^ ( ~e & g ) ) + K[ j ] + W[ j ] | 0;
				t2 = ( ( ( a >>> 2 ) | ( a << 30 ) ) ^ ( ( a >>> 13 ) | ( a << 19 ) ) ^ ( ( a >>> 22 ) | ( a << 10 ) ) ) +
					( ( a & b ) ^ ( a & c ) ^ ( b & c ) ) | 0;
				h = g; g = f; f = e; e = d + t1 | 0;
				d = c; c = b; b = a; a = t1 + t2 | 0;
			}

			h0 = h0 + a | 0; h1 = h1 + b | 0; h2 = h2 + c | 0; h3 = h3 + d | 0;
			h4 = h4 + e | 0; h5 = h5 + f | 0; h6 = h6 + g | 0; h7 = h7 + h | 0;
		}

		out = '';
		hex = [ h0, h1, h2, h3, h4, h5, h6, h7 ];
		for ( i = 0; i < 8; i++ ) {
			out += ( '0000000' + ( hex[ i ] >>> 0 ).toString( 16 ) ).slice( -8 );
		}

		return out;
	}

		function solve( challenge, done ) {
		var number = 0;
		var max = challenge.maxnumber;

		function slice() {
			var stop = Math.min( number + 5000, max + 1 );

			for ( ; number < stop; number++ ) {
				if ( sha256( challenge.salt + number ) === challenge.challenge ) {
					done( number );
					return;
				}
			}

			if ( number > max ) {
				done( -1 );
				return;
			}

			setTimeout( slice, 0 );
		}

		slice();
	}

		function payload( challenge, number ) {
		return btoa( JSON.stringify( {
			algorithm: challenge.algorithm,
			challenge: challenge.challenge,
			number: number,
			salt: challenge.salt,
			signature: challenge.signature
		} ) );
	}

		function setUp( form, url ) {
		var field = form.querySelector( 'input[name="wmds_pow"]' );
		var button = form.querySelector( '[type="submit"]' );
		var state = 'idle';
		var waiting = false;
		var issued = 0;
		var lifetime = 0;

		if ( ! field ) {
			field = document.createElement( 'input' );
			field.type = 'hidden';
			field.name = 'wmds_pow';
			form.appendChild( field );
		}

		function finish() {
			state = 'done';

			if ( ! waiting ) {
				return;
			}

			waiting = false;
			if ( button ) {
				button.disabled = false;
			}

			if ( form.requestSubmit ) {
				form.requestSubmit();
			} else {
				form.submit();
			}
		}

		function start() {
			var request;

			if ( 'idle' !== state ) {
				return;
			}

			state = 'working';
			field.value = '';

			request = new XMLHttpRequest();
			request.open( 'GET', url, true );
			request.onload = function () {
				var challenge;

				try {
					challenge = JSON.parse( request.responseText );
				} catch ( error ) {
					challenge = null;
				}

				if ( ! challenge || ! challenge.salt ) {
					finish();
					return;
				}

				issued = Date.now();
				lifetime = ( challenge.lifetime || 1800 ) * 1000;

				solve( challenge, function ( number ) {
					field.value = number < 0 ? '' : payload( challenge, number );
					finish();
				} );
			};
			request.onerror = finish;
			request.send();
		}

		form.addEventListener( 'focusin', start );
		form.addEventListener( 'input', start );

		form.addEventListener( 'submit', function ( event ) {
			if ( 'done' === state && field.value && Date.now() - issued > lifetime - 60000 ) {
				state = 'idle';
			}

			if ( 'done' === state ) {
				return;
			}

			event.preventDefault();
			waiting = true;
			if ( button ) {
				button.disabled = true;
			}
			start();
		} );
	}

	function init() {
		var settings = root.wmdsEnquiry || {};
		var actions = document.querySelectorAll( 'form input[name="action"][value="wmds_enquiry"]' );
		var index;

		if ( ! settings.challenge ) {
			return;
		}

		for ( index = 0; index < actions.length; index++ ) {
			setUp( actions[ index ].form, settings.challenge );
		}
	}

	if ( 'undefined' !== typeof module && module.exports ) {
		module.exports = { sha256: sha256, solve: solve, payload: payload };
		return;
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}( this ) );
