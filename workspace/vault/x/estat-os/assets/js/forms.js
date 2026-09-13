/**
 * Estat.OS — form submission and conditional logic.
 * Vanilla JavaScript, no dependencies, progressive: the form still posts
 * through the REST endpoint if JavaScript is unavailable the page simply
 * reloads with a normal submission handled by the browser.
 */
( function () {
	'use strict';

	var settings = window.estatForms || { i18n: {} };

	function text( key, fallback ) {
		return ( settings.i18n && settings.i18n[ key ] ) || fallback;
	}

	function clearErrors( form ) {
		form.querySelectorAll( '.estat-error' ).forEach( function ( node ) {
			node.textContent = '';
		} );
		form.querySelectorAll( '[aria-invalid]' ).forEach( function ( node ) {
			node.removeAttribute( 'aria-invalid' );
		} );
	}

	function showFieldErrors( form, errors ) {
		var first = null;
		Object.keys( errors ).forEach( function ( fieldId ) {
			var input = form.querySelector( '[name="fields[' + fieldId + ']"], [name="fields[' + fieldId + '][]"], [name="' + fieldId + '"]' );
			if ( ! input ) {
				return;
			}
			input.setAttribute( 'aria-invalid', 'true' );
			var wrap = input.closest( '.estat-field' );
			var slot = wrap ? wrap.querySelector( '.estat-error' ) : null;
			if ( slot ) {
				slot.textContent = errors[ fieldId ];
			}
			if ( ! first ) {
				first = input;
			}
		} );
		if ( first ) {
			first.focus();
		}
	}

	function message( form, kind, msg ) {
		var box = form.querySelector( '.estat-form-messages' );
		if ( ! box ) {
			return;
		}
		box.className = 'estat-form-messages is-' + kind;
		box.textContent = msg;
	}

	function applyConditions( form ) {
		form.querySelectorAll( '[data-condition]' ).forEach( function ( wrap ) {
			var rule;
			try {
				rule = JSON.parse( wrap.getAttribute( 'data-condition' ) );
			} catch ( e ) {
				return;
			}
			if ( ! rule || ! rule.field ) {
				return;
			}
			var source = form.querySelector( '[name="fields[' + rule.field + ']"]' );
			if ( ! source ) {
				return;
			}
			var value = source.type === 'checkbox' ? ( source.checked ? '1' : '' ) : source.value;
			var show;
			switch ( rule.operator ) {
				case 'is_not':
					show = value !== rule.value;
					break;
				case 'contains':
					show = value.indexOf( rule.value ) !== -1;
					break;
				case 'filled':
					show = value !== '';
					break;
				case 'empty':
					show = value === '';
					break;
				default:
					show = value === rule.value;
			}
			wrap.hidden = ! show;
			wrap.querySelectorAll( 'input,select,textarea' ).forEach( function ( input ) {
				input.disabled = ! show;
			} );
		} );
	}

	function serialize( form ) {
		var data = new FormData( form );
		var out = { fields: {} };
		data.forEach( function ( value, key ) {
			var match = key.match( /^fields\[(.+?)\](\[\])?$/ );
			if ( match ) {
				var id = match[ 1 ];
				if ( match[ 2 ] ) {
					out.fields[ id ] = out.fields[ id ] || [];
					out.fields[ id ].push( value );
				} else {
					out.fields[ id ] = value;
				}
				return;
			}
			out[ key ] = value;
		} );
		return out;
	}

	function submit( event ) {
		var form = event.target;
		if ( ! form.classList.contains( 'estat-form' ) ) {
			return;
		}
		event.preventDefault();

		if ( form.dataset.busy === '1' ) {
			return;
		}
		form.dataset.busy = '1';

		clearErrors( form );
		var button = form.querySelector( 'button[type="submit"]' );
		var spinner = form.querySelector( '.estat-form-spinner' );
		if ( button ) {
			button.disabled = true;
		}
		if ( spinner ) {
			spinner.hidden = false;
		}
		message( form, 'info', text( 'sending', 'Sending…' ) );

		fetch( form.getAttribute( 'data-endpoint' ), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
			body: JSON.stringify( serialize( form ) )
		} )
			.then( function ( response ) {
				return response.json().then( function ( body ) {
					return { ok: response.ok, body: body };
				} );
			} )
			.then( function ( result ) {
				if ( result.ok && result.body && result.body.ok ) {
					message( form, 'success', result.body.message );
					form.reset();
					if ( result.body.redirect ) {
						window.location.href = result.body.redirect;
					}
					return;
				}
				var body = result.body || {};
				var data = body.data || {};
				if ( data.fields ) {
					showFieldErrors( form, data.fields );
				}
				message( form, 'error', body.message || text( 'genericError', 'Something went wrong.' ) );
			} )
			.catch( function () {
				message( form, 'error', text( 'genericError', 'Something went wrong.' ) );
			} )
			.finally( function () {
				form.dataset.busy = '0';
				if ( button ) {
					button.disabled = false;
				}
				if ( spinner ) {
					spinner.hidden = true;
				}
				// A fresh key so a genuine second enquiry is never blocked.
				var key = form.querySelector( '[name="idempotency_key"]' );
				if ( key && window.crypto && window.crypto.randomUUID ) {
					key.value = window.crypto.randomUUID();
				}
			} );
	}

	document.addEventListener( 'submit', submit );
	document.addEventListener( 'change', function ( event ) {
		var form = event.target.closest ? event.target.closest( '.estat-form' ) : null;
		if ( form ) {
			applyConditions( form );
		}
	} );
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.estat-form' ).forEach( applyConditions );
	} );
}() );
