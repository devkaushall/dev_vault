/**
 * Estat.OS — favorites and property comparison.
 * Guest choices live in localStorage; nothing personal is stored.
 */
( function () {
	'use strict';

	var settings = window.estatPublic || { i18n: {} };
	var FAV_KEY = 'estat_favorites';
	var CMP_KEY = 'estat_compare';
	var MAX_COMPARE = 4;

	function read( key ) {
		try {
			var raw = window.localStorage.getItem( key );
			var list = raw ? JSON.parse( raw ) : [];
			return Array.isArray( list ) ? list.filter( function ( id ) {
				return typeof id === 'number' && id > 0;
			} ) : [];
		} catch ( e ) {
			return [];
		}
	}

	function write( key, list ) {
		try {
			window.localStorage.setItem( key, JSON.stringify( list.slice( 0, 100 ) ) );
		} catch ( e ) {
			/* storage may be full or blocked; the page still works */
		}
	}

	function toggle( key, id, limit ) {
		var list = read( key );
		var index = list.indexOf( id );
		if ( index !== -1 ) {
			list.splice( index, 1 );
		} else {
			if ( limit && list.length >= limit ) {
				return { list: list, added: false, full: true };
			}
			list.push( id );
		}
		write( key, list );
		return { list: list, added: index === -1, full: false };
	}

	function syncButtons() {
		var favorites = read( FAV_KEY );
		var compares = read( CMP_KEY );
		document.querySelectorAll( '.estat-fav' ).forEach( function ( button ) {
			var id = parseInt( button.getAttribute( 'data-listing' ), 10 );
			button.setAttribute( 'aria-pressed', favorites.indexOf( id ) !== -1 ? 'true' : 'false' );
		} );
		document.querySelectorAll( '.estat-compare' ).forEach( function ( button ) {
			var id = parseInt( button.getAttribute( 'data-listing' ), 10 );
			button.setAttribute( 'aria-pressed', compares.indexOf( id ) !== -1 ? 'true' : 'false' );
		} );
	}

	function renderCompare() {
		var panel = document.querySelector( '#estat-compare-panel .estat-compare-table' );
		if ( ! panel ) {
			return;
		}
		var ids = read( CMP_KEY );
		if ( ! ids.length ) {
			panel.innerHTML = '';
			return;
		}
		Promise.all( ids.map( function ( id ) {
			return fetch( settings.restUrl + 'properties/' + id ).then( function ( r ) {
				return r.ok ? r.json() : null;
			} ).catch( function () {
				return null;
			} );
		} ) ).then( function ( items ) {
			items = items.filter( Boolean );
			if ( ! items.length ) {
				panel.innerHTML = '';
				return;
			}
			var rows = [
				[ 'Price', 'price_display' ],
				[ 'Bedrooms', 'bedrooms' ],
				[ 'Bathrooms', 'bathrooms' ],
				[ 'Parking', 'parking' ],
				[ 'Floor', 'floor' ],
				[ 'Furnishing', 'furnishing' ],
				[ 'Type', 'property_type' ],
				[ 'Availability', 'availability' ]
			];
			var table = document.createElement( 'table' );
			var head = document.createElement( 'tr' );
			head.appendChild( document.createElement( 'th' ) );
			items.forEach( function ( item ) {
				var th = document.createElement( 'th' );
				var link = document.createElement( 'a' );
				link.href = item.url;
				link.textContent = item.title;
				th.appendChild( link );
				head.appendChild( th );
			} );
			table.appendChild( head );

			rows.forEach( function ( row ) {
				var tr = document.createElement( 'tr' );
				var label = document.createElement( 'th' );
				label.scope = 'row';
				label.textContent = row[ 0 ];
				tr.appendChild( label );
				items.forEach( function ( item ) {
					var td = document.createElement( 'td' );
					var value = item[ row[ 1 ] ];
					td.textContent = ( value === null || value === '' || value === 0 ) ? '—' : String( value );
					tr.appendChild( td );
				} );
				table.appendChild( tr );
			} );

			panel.innerHTML = '';
			panel.appendChild( table );
		} );
	}

	function renderFavorites() {
		var section = document.querySelector( '#estat-favorites .estat-grid' );
		if ( ! section ) {
			return;
		}
		var ids = read( FAV_KEY );
		if ( ! ids.length ) {
			section.innerHTML = '';
			return;
		}
		Promise.all( ids.map( function ( id ) {
			return fetch( settings.restUrl + 'properties/' + id ).then( function ( r ) {
				return r.ok ? r.json() : null;
			} ).catch( function () {
				return null;
			} );
		} ) ).then( function ( items ) {
			section.innerHTML = '';
			items.filter( Boolean ).forEach( function ( item ) {
				var card = document.createElement( 'article' );
				card.className = 'estat-card';
				var body = document.createElement( 'div' );
				body.className = 'estat-card-body';
				var title = document.createElement( 'h3' );
				title.className = 'estat-card-title';
				var link = document.createElement( 'a' );
				link.href = item.url;
				link.textContent = item.title;
				title.appendChild( link );
				var price = document.createElement( 'p' );
				price.className = 'estat-card-price';
				price.textContent = item.price_display || '';
				body.appendChild( title );
				body.appendChild( price );
				card.appendChild( body );
				section.appendChild( card );
			} );
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var fav = event.target.closest ? event.target.closest( '.estat-fav' ) : null;
		if ( fav ) {
			toggle( FAV_KEY, parseInt( fav.getAttribute( 'data-listing' ), 10 ), 0 );
			syncButtons();
			renderFavorites();
			return;
		}
		var cmp = event.target.closest ? event.target.closest( '.estat-compare' ) : null;
		if ( cmp ) {
			var result = toggle( CMP_KEY, parseInt( cmp.getAttribute( 'data-listing' ), 10 ), MAX_COMPARE );
			if ( result.full ) {
				window.alert( ( settings.i18n && settings.i18n.compareFull ) || 'You can compare up to four properties at a time.' );
				return;
			}
			syncButtons();
			renderCompare();
		}
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		syncButtons();
		renderCompare();
		renderFavorites();
	} );
}() );

/* ------------------------------------------------------------------
 * The monthly repayment estimate.
 *
 * Everything happens here in the browser. No request is made, nothing
 * is stored, and no lead is created by someone moving the numbers
 * around — which matters, because a visitor working out affordability
 * is thinking, not enquiring, and should not be treated as a lead for
 * doing so.
 * ------------------------------------------------------------------ */

( function () {
	'use strict';

	var config = window.estatPublic || {};
	var strings = config.i18n || {};
	var symbol = config.currency || '\u20b9';
	var indian = config.priceStyle !== 'plain' && config.priceStyle !== 'short';

	/**
	 * Trim a number the way the server's Format::money() does, so the
	 * calculator and the price above it read as one website.
	 */
	function trim( value ) {
		var rounded = Math.round( value * 100 ) / 100;

		return String( rounded ).replace( /\.0+$/, '' ).replace( /(\.\d*[1-9])0+$/, '$1' );
	}

	function money( amount ) {
		if ( ! isFinite( amount ) || amount < 0 ) {
			return symbol + '0';
		}

		if ( indian ) {
			if ( amount >= 10000000 ) {
				return symbol + trim( amount / 10000000 ) + ' ' + ( strings.crore || 'Cr' );
			}
			if ( amount >= 100000 ) {
				return symbol + trim( amount / 100000 ) + ' ' + ( strings.lakh || 'Lakh' );
			}
			if ( amount >= 1000 ) {
				return symbol + trim( amount / 1000 ) + ' ' + ( strings.thousand || 'K' );
			}
		} else if ( amount >= 1000000 ) {
			return symbol + trim( amount / 1000000 ) + 'M';
		} else if ( amount >= 1000 ) {
			return symbol + trim( amount / 1000 ) + 'K';
		}

		return symbol + Math.round( amount ).toLocaleString();
	}

	/**
	 * The formula every Indian lender publishes:
	 *
	 *   EMI = P x R x (1+R)^N / ((1+R)^N - 1)
	 *
	 * A rate of exactly zero would divide by zero, so that case is the
	 * plain principal split evenly across the months.
	 */
	function monthlyPayment( principal, annualRate, years ) {
		var months = years * 12;

		if ( principal <= 0 || months <= 0 ) {
			return 0;
		}

		if ( annualRate <= 0 ) {
			return principal / months;
		}

		var monthly = annualRate / 12 / 100;
		var growth = Math.pow( 1 + monthly, months );

		return ( principal * monthly * growth ) / ( growth - 1 );
	}

	function setUp( panel ) {
		var loanBox = panel.querySelector( '.estat-emi-loan' );
		var rateBox = panel.querySelector( '.estat-emi-rate' );
		var yearsBox = panel.querySelector( '.estat-emi-years' );
		var value = panel.querySelector( '.estat-emi-value' );
		var total = panel.querySelector( '.estat-emi-total' );

		if ( ! loanBox || ! rateBox || ! yearsBox || ! value ) {
			return;
		}

		function recalculate() {
			var principal = parseFloat( loanBox.value );
			var rate = parseFloat( rateBox.value );
			var years = parseFloat( yearsBox.value );

			// An empty or nonsense box should say so rather than print NaN.
			if ( ! isFinite( principal ) || ! isFinite( rate ) || ! isFinite( years ) || years <= 0 ) {
				value.textContent = '\u2014';
				if ( total ) {
					total.textContent = strings.emiCheck || 'Please check the numbers.';
				}
				return;
			}

			var payment = monthlyPayment( principal, rate, years );

			value.textContent = money( payment );

			if ( total ) {
				total.textContent = ( strings.emiTotal || '%s repaid in total' )
					.replace( '%s', money( payment * years * 12 ) );
			}
		}

		[ loanBox, rateBox, yearsBox ].forEach( function ( box ) {
			box.addEventListener( 'input', recalculate );
			box.addEventListener( 'change', recalculate );
		} );

		recalculate();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.estat-emi' ), setUp );
	} );
}() );
