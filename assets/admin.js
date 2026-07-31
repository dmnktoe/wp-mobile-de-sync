( function () {
	'use strict';

	var config = window.wmdsAdmin || {};
	var text = config.i18n || {};

	var MAX_STEPS = 200;

	function format( template, values ) {
		return String( template || '' ).replace( /%(\d+)\$d/g, function ( match, index ) {
			var value = values[ parseInt( index, 10 ) - 1 ];

			return ( typeof value === 'undefined' ) ? match : String( value );
		} );
	}

	function post( action, data, done ) {
		var body = new window.FormData();

		body.append( 'action', action );
		body.append( 'nonce', config.nonce );

		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( payload ) {
			done( !! payload.success, payload.data || {} );
		} ).catch( function () {
			done( false, { message: text.failed } );
		} );
	}

	function result( id, message, state ) {
		var box = document.getElementById( id );

		if ( ! box ) {
			return;
		}

		box.textContent = message;
		box.className = 'wmds-result' + ( state ? ' wmds-result-' + state : '' );
	}

	function bindConfirmations() {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-wmds-confirm]' );

			if ( button && ! window.confirm( button.getAttribute( 'data-wmds-confirm' ) ) ) {
				event.preventDefault();
			}
		} );
	}

	function bindPasswordToggle() {
		var toggle = document.querySelector( '.wmds-toggle-password' );

		if ( ! toggle ) {
			return;
		}

		toggle.addEventListener( 'click', function () {
			var field = document.getElementById( toggle.getAttribute( 'data-target' ) );
			var icon = toggle.querySelector( '.dashicons' );

			if ( ! field ) {
				return;
			}

			var hidden = field.type === 'password';

			field.type = hidden ? 'text' : 'password';

			if ( icon ) {
				icon.classList.toggle( 'dashicons-visibility', ! hidden );
				icon.classList.toggle( 'dashicons-hidden', hidden );
			}
		} );
	}

	function bindSellerMode() {
		var radios = document.querySelectorAll( 'input[name="wmds_seller_mode"]' );

		if ( ! radios.length ) {
			return;
		}

		function apply() {
			var selected = document.querySelector( 'input[name="wmds_seller_mode"]:checked' );
			var mode = selected ? selected.value : 'id';

			Array.prototype.forEach.call(
				document.querySelectorAll( '.wmds-mode-body' ),
				function ( body ) {
					body.hidden = body.getAttribute( 'data-wmds-mode' ) !== mode;
				}
			);
		}

		Array.prototype.forEach.call( radios, function ( radio ) {
			radio.addEventListener( 'change', apply );
		} );

		apply();
	}

	function bindTest() {
		var button = document.getElementById( 'wmds-test' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;
			result( 'wmds-test-result', text.testing, '' );

			post( 'wmds_test', {}, function ( ok, payload ) {
				button.disabled = false;

				var passed = ok && payload.ok;

				result(
					'wmds-test-result',
					payload.message || text.failed,
					passed ? 'ok' : 'error'
				);
			} );
		} );
	}

	function bindSync() {
		var button = document.getElementById( 'wmds-sync' );

		if ( ! button ) {
			return;
		}

		var wrapper = document.getElementById( 'wmds-progress' );
		var fill = document.getElementById( 'wmds-progress-fill' );
		var label = document.getElementById( 'wmds-progress-text' );

		function paint( done, total ) {
			var share = total > 0 ? Math.min( 100, Math.round( ( done / total ) * 100 ) ) : 100;

			if ( fill ) {
				fill.style.width = share + '%';
			}
			if ( label ) {
				label.textContent = format( text.progress, [ done, total ] );
			}
		}

		function step( index, total ) {
			post( 'wmds_sync', { step: index }, function ( ok, payload ) {
				if ( ! ok ) {
					button.disabled = false;
					result( 'wmds-sync-result', payload.message || text.failed, 'error' );
					return;
				}

				if ( index === 1 ) {
					total = payload.created + payload.updated + payload.pending;
				}

				paint( Math.max( 0, total - payload.pending ), total );

				if ( payload.done || index >= MAX_STEPS ) {
					button.disabled = false;
					result( 'wmds-sync-result', text.done + ' ' + payload.summary, 'ok' );
					return;
				}

				step( index + 1, total );
			} );
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;
			result( 'wmds-sync-result', '', '' );

			if ( wrapper ) {
				wrapper.hidden = false;
			}
			if ( fill ) {
				fill.style.width = '0';
			}
			if ( label ) {
				label.textContent = text.starting;
			}

			step( 1, 0 );
		} );
	}

	function bindLogFilter() {
		var checkbox = document.getElementById( 'wmds-log-errors-only' );

		if ( ! checkbox ) {
			return;
		}

		checkbox.addEventListener( 'change', function () {
			Array.prototype.forEach.call(
				document.querySelectorAll( '.wmds-log tbody tr' ),
				function ( row ) {
					row.hidden = checkbox.checked && ! row.classList.contains( 'wmds-log-problem' );
				}
			);
		} );
	}

	function bindNoticeDismiss() {
		var notice = document.querySelector( '.wmds-global-notice' );

		if ( ! notice ) {
			return;
		}

		notice.addEventListener( 'click', function ( event ) {
			if ( ! event.target.classList.contains( 'notice-dismiss' ) ) {
				return;
			}

			post( 'wmds_dismiss', { level: notice.getAttribute( 'data-wmds-level' ) }, function () {} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		bindConfirmations();
		bindPasswordToggle();
		bindSellerMode();
		bindTest();
		bindSync();
		bindLogFilter();
		bindNoticeDismiss();
	} );
}() );
