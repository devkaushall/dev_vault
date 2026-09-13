/**
 * Estat.OS - admin behaviour.
 *
 * Plain browser JavaScript, no build step and no framework. Everything here
 * is an enhancement: every screen keeps working with JavaScript switched off.
 */
( function ( window, document ) {
	'use strict';

	var settings = window.estatAdmin || {};
	var strings = settings.i18n || {};

	/**
	 * Shorthand query helpers.
	 */
	function all( selector, context ) {
		return Array.prototype.slice.call( ( context || document ).querySelectorAll( selector ) );
	}

	/**
	 * Announce a short message to screen readers and show it briefly.
	 */
	function announce( message ) {
		var region = document.getElementById( 'estat-live-region' );
		if ( ! region ) {
			region = document.createElement( 'div' );
			region.id = 'estat-live-region';
			region.className = 'screen-reader-text';
			region.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( region );
		}
		region.textContent = message;
	}

	/* ------------------------------------------------------------- Help */

	function initHelp() {
		all( '.estat-help-toggle' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var panel = document.getElementById( button.getAttribute( 'aria-controls' ) || 'estat-help' );
				if ( ! panel ) {
					return;
				}
				var open = button.getAttribute( 'aria-expanded' ) === 'true';
				button.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
				panel.hidden = open;
			} );
		} );
	}

	/* ------------------------------------------------------ Diagnostics */

	function copyText( text ) {
		if ( window.navigator.clipboard && window.navigator.clipboard.writeText ) {
			return window.navigator.clipboard.writeText( text );
		}
		var area = document.createElement( 'textarea' );
		area.value = text;
		area.setAttribute( 'readonly', 'readonly' );
		area.style.position = 'fixed';
		area.style.opacity = '0';
		document.body.appendChild( area );
		area.select();
		try {
			document.execCommand( 'copy' );
		} catch ( e ) {}
		document.body.removeChild( area );
		return Promise.resolve();
	}

	function initCopy() {
		all( '.estat-copy-diagnostics' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var target = document.querySelector( button.getAttribute( 'data-target' ) || '.estat-diagnostics dl' );
				var text = target ? target.innerText.replace( /\n{2,}/g, '\n' ) : '';
				copyText( text ).then( function () {
					button.textContent = strings.copied || 'Copied.';
					announce( strings.copied || 'Copied.' );
				} );
			} );
		} );
	}

	/* ------------------------------------------------------ Media: cover */

	function openFrame( options ) {
		if ( ! window.wp || ! window.wp.media ) {
			return null;
		}
		return window.wp.media( {
			title: options.title || strings.chooseImage || 'Choose an image',
			button: { text: options.button || strings.useImage || 'Use this image' },
			library: { type: 'image' },
			multiple: options.multiple || false
		} );
	}

	function initImagePickers() {
		all( '.estat-pick-image' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				var input = document.querySelector( button.getAttribute( 'data-target' ) );
				if ( ! input ) {
					return;
				}
				var frame = openFrame( {} );
				if ( ! frame ) {
					return;
				}
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					input.value = attachment.id;
					var wrap = button.closest( '.estat-image-picker' );
					var frameBox = wrap ? wrap.querySelector( '.estat-image-preview' ) : null;
					var preview = frameBox ? frameBox.querySelector( 'img' ) : null;
					var url = ( attachment.sizes && attachment.sizes.medium )
						? attachment.sizes.medium.url
						: attachment.url;
					if ( frameBox ) {
						var empty = frameBox.querySelector( '.estat-image-empty' );
						if ( empty && empty.parentNode ) {
							empty.parentNode.removeChild( empty );
						}
						if ( ! preview ) {
							preview = document.createElement( 'img' );
							preview.alt = '';
							frameBox.appendChild( preview );
						}
					}
					if ( preview ) {
						preview.src = url;
					}
				} );
				frame.open();
			} );
		} );

		all( '.estat-clear-image' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				var input = document.querySelector( button.getAttribute( 'data-target' ) );
				if ( input ) {
					input.value = '';
				}
				var wrap = button.closest( '.estat-image-picker' );
				var frameBox = wrap ? wrap.querySelector( '.estat-image-preview' ) : null;
				var preview = frameBox ? frameBox.querySelector( 'img' ) : null;
				if ( preview && preview.parentNode ) {
					preview.parentNode.removeChild( preview );
				}
				if ( frameBox && ! frameBox.querySelector( '.estat-image-empty' ) ) {
					var placeholder = document.createElement( 'span' );
					placeholder.className = 'estat-image-empty';
					placeholder.textContent = strings.tapToChoose || 'Tap to choose a photo';
					frameBox.appendChild( placeholder );
				}
			} );
		} );
	}

	/* ---------------------------------------------------- Media: gallery */

	function galleryIds( list ) {
		return all( 'li', list ).map( function ( item ) {
			return item.getAttribute( 'data-id' );
		} ).filter( Boolean );
	}

	function syncGallery( list ) {
		var input = document.querySelector( list.getAttribute( 'data-target' ) );
		if ( input ) {
			input.value = galleryIds( list ).join( ',' );
		}
	}

	function galleryItem( attachment ) {
		var item = document.createElement( 'li' );
		item.setAttribute( 'data-id', attachment.id );
		item.setAttribute( 'draggable', 'true' );

		var image = document.createElement( 'img' );
		image.src = ( attachment.sizes && attachment.sizes.thumbnail )
			? attachment.sizes.thumbnail.url
			: attachment.url;
		image.alt = '';
		item.appendChild( image );

		var remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'estat-gallery-remove';
		remove.setAttribute( 'aria-label', strings.remove || 'Remove' );
		remove.textContent = '\u00d7';
		item.appendChild( remove );

		return item;
	}

	function initGalleryDrag( list ) {
		var dragging = null;

		list.addEventListener( 'dragstart', function ( event ) {
			var item = event.target.closest( 'li' );
			if ( ! item ) {
				return;
			}
			dragging = item;
			item.classList.add( 'is-dragging' );
			if ( event.dataTransfer ) {
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData( 'text/plain', item.getAttribute( 'data-id' ) || '' );
			}
		} );

		list.addEventListener( 'dragover', function ( event ) {
			event.preventDefault();
			var over = event.target.closest( 'li' );
			if ( ! dragging || ! over || over === dragging ) {
				return;
			}
			var box = over.getBoundingClientRect();
			var after = ( event.clientX - box.left ) > ( box.width / 2 );
			list.insertBefore( dragging, after ? over.nextSibling : over );
		} );

		list.addEventListener( 'dragend', function () {
			if ( dragging ) {
				dragging.classList.remove( 'is-dragging' );
				dragging = null;
			}
			syncGallery( list );
		} );

		list.addEventListener( 'click', function ( event ) {
			var remove = event.target.closest( '.estat-gallery-remove' );
			if ( ! remove ) {
				return;
			}
			event.preventDefault();
			var item = remove.closest( 'li' );
			if ( item && item.parentNode ) {
				item.parentNode.removeChild( item );
			}
			syncGallery( list );
		} );

		// Keyboard alternative to dragging.
		list.addEventListener( 'keydown', function ( event ) {
			var item = event.target.closest( 'li' );
			if ( ! item ) {
				return;
			}
			if ( event.key === 'ArrowLeft' && item.previousElementSibling ) {
				list.insertBefore( item, item.previousElementSibling );
				syncGallery( list );
			} else if ( event.key === 'ArrowRight' && item.nextElementSibling ) {
				list.insertBefore( item.nextElementSibling, item );
				syncGallery( list );
			}
		} );
	}

	function initGalleryPickers() {
		all( '.estat-gallery-list' ).forEach( function ( list ) {
			all( 'li', list ).forEach( function ( item ) {
				item.setAttribute( 'draggable', 'true' );
				item.setAttribute( 'tabindex', '0' );
			} );
			initGalleryDrag( list );
		} );

		all( '.estat-pick-gallery' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				var list = document.querySelector( button.getAttribute( 'data-list' ) );
				if ( ! list ) {
					return;
				}
				var frame = openFrame( { multiple: true } );
				if ( ! frame ) {
					return;
				}
				frame.on( 'select', function () {
					var existing = galleryIds( list );
					frame.state().get( 'selection' ).toJSON().forEach( function ( attachment ) {
						if ( existing.indexOf( String( attachment.id ) ) !== -1 ) {
							return;
						}
						var item = galleryItem( attachment );
						item.setAttribute( 'tabindex', '0' );
						list.appendChild( item );
					} );
					syncGallery( list );
				} );
				frame.open();
			} );
		} );
	}

	/* --------------------------------------------------- Safety prompts */

	/*
	 * Forms may ask for a confirmation with either attribute. The screens use
	 * data-estat-confirm; data-confirm is kept working so a theme or a future
	 * screen using the shorter name is never silently unguarded.
	 */
	function initConfirmations() {
		all( 'form[data-confirm], form[data-estat-confirm]' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				var message = form.getAttribute( 'data-estat-confirm' ) ||
					form.getAttribute( 'data-confirm' ) ||
					strings.confirmDelete;
				if ( ! window.confirm( message ) ) {
					event.preventDefault();
				}
			} );
		} );
	}

	/**
	 * Warn before leaving an edited form, so nobody loses work.
	 */
	function initUnsavedGuard() {
		var dirty = false;
		var forms = all( '.estat-form-admin' );
		if ( ! forms.length ) {
			return;
		}
		forms.forEach( function ( form ) {
			form.addEventListener( 'input', function () {
				dirty = true;
			} );
			form.addEventListener( 'submit', function () {
				dirty = false;
			} );
		} );
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! dirty ) {
				return undefined;
			}
			event.preventDefault();
			event.returnValue = '';
			return '';
		} );
	}

	/**
	 * Copy short snippets such as shortcodes on click.
	 */
	function initSnippets() {
		all( '.estat-copyable' ).forEach( function ( node ) {
			node.setAttribute( 'title', strings.copied ? '' : '' );
			node.addEventListener( 'click', function () {
				copyText( node.textContent || '' ).then( function () {
					announce( strings.copied || 'Copied.' );
				} );
			} );
		} );
	}

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		initHelp();
		initCopy();
		initImagePickers();
		initGalleryPickers();
		initConfirmations();
		initUnsavedGuard();
		initSnippets();
	} );

	// Small shared surface for the form builder.
	window.estatOfficeAdmin = {
		announce: announce,
		copyText: copyText,
		all: all
	};
} )( window, document );

/**
 * Highlight tick-boxes.
 *
 * Shows, as the user ticks, exactly which tags will end up on the property
 * card, so nobody has to guess or save-and-check.
 */
( function () {
	'use strict';

	function initHighlights( root ) {
		var limit = parseInt( root.getAttribute( 'data-card-limit' ), 10 ) || 3;
		var preview = root.querySelector( '.estat-highlights-preview-chips' );
		var boxes = Array.prototype.slice.call(
			root.querySelectorAll( 'input[type="checkbox"][name="highlights[]"]' )
		);

		if ( ! preview || ! boxes.length ) {
			return;
		}

		function refresh() {
			var chosen = boxes.filter( function ( box ) {
				return box.checked;
			} );

			// Reflect the ticked state on the label, and mark the ones that
			// actually fit on the card.
			boxes.forEach( function ( box ) {
				var label = box.closest( '.estat-highlight' );
				if ( ! label ) {
					return;
				}
				label.classList.toggle( 'is-on', box.checked );
				label.classList.remove( 'is-carded' );
			} );

			chosen.slice( 0, limit ).forEach( function ( box ) {
				var label = box.closest( '.estat-highlight' );
				if ( label ) {
					label.classList.add( 'is-carded' );
				}
			} );

			preview.textContent = '';

			if ( ! chosen.length ) {
				var empty = document.createElement( 'span' );
				empty.className = 'estat-preview-empty';
				empty.textContent = preview.getAttribute( 'data-empty' ) ||
					'Nothing ticked yet, so the card will show bedrooms and size.';
				preview.appendChild( empty );
				return;
			}

			chosen.slice( 0, limit ).forEach( function ( box ) {
				var chip = document.createElement( 'span' );
				chip.className = 'estat-preview-chip';

				var icon = box.getAttribute( 'data-icon' );
				if ( icon ) {
					var iconEl = document.createElement( 'span' );
					iconEl.setAttribute( 'aria-hidden', 'true' );
					iconEl.textContent = icon;
					chip.appendChild( iconEl );
				}

				var text = document.createElement( 'span' );
				text.textContent = box.getAttribute( 'data-label' ) || box.value;
				chip.appendChild( text );

				preview.appendChild( chip );
			} );

			if ( chosen.length > limit ) {
				var more = document.createElement( 'span' );
				more.className = 'estat-preview-empty';
				more.textContent = '+' + ( chosen.length - limit ) + ' more on the property page';
				preview.appendChild( more );
			}
		}

		boxes.forEach( function ( box ) {
			box.addEventListener( 'change', refresh );
		} );

		refresh();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var blocks = document.querySelectorAll( '.estat-highlights' );
		Array.prototype.forEach.call( blocks, initHighlights );
	} );
}() );

/**
 * Gallery drop area.
 *
 * Uploads one file at a time through the plugin's own endpoint. Deliberately
 * plain: a file input, drag-and-drop, and a progress line. No third-party
 * uploader, no hidden iframes — those are what make browser protection
 * suspicious of the stock media library.
 */
( function () {
	'use strict';

	function text( key, fallback ) {
		var strings = ( window.estatAdmin && window.estatAdmin.i18n ) || {};
		return strings[ key ] || fallback;
	}

	function initDropzone( zone ) {
		var input = zone.querySelector( '.estat-dropzone-input' );
		var progress = zone.querySelector( '.estat-dropzone-progress' );
		var bar = zone.querySelector( '.estat-dropzone-bar span' );
		var status = zone.querySelector( '.estat-dropzone-status' );
		var kind = zone.getAttribute( 'data-kind' ) || '';

		if ( ! input || ! window.estatAdmin || ! window.estatAdmin.ajaxUrl ) {
			return;
		}

		function setStatus( message ) {
			if ( status ) {
				status.textContent = message;
			}
		}

		function setBar( done, total ) {
			if ( bar ) {
				bar.style.width = total ? Math.round( ( done / total ) * 100 ) + '%' : '0%';
			}
		}

		function uploadOne( file ) {
			return new Promise( function ( resolve ) {
				var data = new FormData();
				data.append( 'action', 'estat_upload_file' );
				data.append( 'nonce', window.estatAdmin.uploadNonce );
				data.append( 'kind', kind );
				data.append( 'file', file );

				fetch( window.estatAdmin.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: data
				} )
					.then( function ( response ) {
						return response.json().catch( function () {
							return { success: false, data: { message: 'Server did not answer properly.' } };
						} );
					} )
					.then( function ( result ) {
						resolve( {
							ok: !! ( result && result.success ),
							message: ( result && result.data && result.data.message ) || ''
						} );
					} )
					.catch( function ( error ) {
						resolve( { ok: false, message: error.message } );
					} );
			} );
		}

		function handleFiles( files ) {
			var list = Array.prototype.slice.call( files );

			if ( ! list.length ) {
				return;
			}

			if ( progress ) {
				progress.hidden = false;
			}

			var done = 0;
			var failures = [];

			setBar( 0, list.length );
			setStatus( text( 'uploading', 'Adding your files…' ) );

			// One at a time keeps shared hosting and slow connections happy.
			list.reduce( function ( chain, file ) {
				return chain.then( function () {
					return uploadOne( file ).then( function ( result ) {
						done += 1;
						setBar( done, list.length );

						if ( ! result.ok ) {
							failures.push( file.name + ( result.message ? ' — ' + result.message : '' ) );
						}

						setStatus( done + ' / ' + list.length );
					} );
				} );
			}, Promise.resolve() ).then( function () {
				if ( failures.length ) {
					setStatus( text( 'uploadFailed', 'Could not add this file:' ) + ' ' + failures.join( '; ' ) );
					return;
				}

				setStatus( text( 'uploadDone', 'Done. Refreshing…' ) );
				window.location.reload();
			} );
		}

		input.addEventListener( 'change', function () {
			handleFiles( input.files );
		} );

		[ 'dragenter', 'dragover' ].forEach( function ( name ) {
			zone.addEventListener( name, function ( event ) {
				event.preventDefault();
				zone.classList.add( 'is-over' );
			} );
		} );

		[ 'dragleave', 'drop' ].forEach( function ( name ) {
			zone.addEventListener( name, function ( event ) {
				event.preventDefault();
				if ( 'drop' !== name || ! zone.contains( event.relatedTarget ) ) {
					zone.classList.remove( 'is-over' );
				}
			} );
		} );

		zone.addEventListener( 'drop', function ( event ) {
			if ( event.dataTransfer && event.dataTransfer.files ) {
				handleFiles( event.dataTransfer.files );
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.estat-dropzone' ), initDropzone );

		// Anything marked with data-estat-confirm asks first.
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest && event.target.closest( '[data-estat-confirm]' );
			if ( ! trigger ) {
				return;
			}
			if ( ! window.confirm( trigger.getAttribute( 'data-estat-confirm' ) ) ) {
				event.preventDefault();
			}
		} );
	} );
}() );

/**
 * Add a Home, one step at a time.
 *
 * The form is a single form and still posts in one go — the steps only change
 * what is on screen. If this script never runs, every panel is simply visible
 * and the form still works, so nobody is locked out by a script error.
 */
( function () {
	'use strict';

	function initWizard( form ) {
		var panels = Array.prototype.slice.call( form.querySelectorAll( '.estat-wizard-panel' ) );
		var markers = Array.prototype.slice.call( form.querySelectorAll( '.estat-step' ) );
		var back = form.querySelector( '.estat-wizard-back' );
		var next = form.querySelector( '.estat-wizard-next' );
		var nav = form.querySelector( '.estat-wizard-nav' );
		var finish = form.querySelector( '.estat-wizard-finish' );

		if ( panels.length < 2 || ! next || ! finish ) {
			return;
		}

		var current = 0;

		function firstInvalid( panel ) {
			var fields = panel.querySelectorAll( 'input, select, textarea' );

			for ( var i = 0; i < fields.length; i++ ) {
				if ( ! fields[ i ].checkValidity() ) {
					return fields[ i ];
				}
			}

			return null;
		}

		function show( index ) {
			current = Math.max( 0, Math.min( panels.length - 1, index ) );

			panels.forEach( function ( panel, i ) {
				var isCurrent = i === current;
				panel.hidden = ! isCurrent;
				panel.classList.toggle( 'is-current', isCurrent );
			} );

			markers.forEach( function ( marker, i ) {
				marker.classList.toggle( 'is-current', i === current );
				marker.classList.toggle( 'is-done', i < current );
			} );

			var onLast = current === panels.length - 1;

			if ( back ) {
				back.hidden = 0 === current;
			}

			// On the last step the Next button gives way to Save / Publish.
			next.hidden = onLast;
			finish.hidden = ! onLast;

			if ( nav ) {
				nav.hidden = onLast && ! back;
			}

			var heading = panels[ current ].querySelector( 'label, legend, h3' );
			if ( heading && heading.scrollIntoView ) {
				heading.scrollIntoView( { block: 'nearest' } );
			}
		}

		next.addEventListener( 'click', function () {
			var invalid = firstInvalid( panels[ current ] );

			// Do not let someone walk past a required box and get an error at
			// the very end; point at it here instead.
			if ( invalid ) {
				invalid.reportValidity();
				return;
			}

			show( current + 1 );
		} );

		if ( back ) {
			back.addEventListener( 'click', function () {
				show( current - 1 );
			} );
		}

		// Let a step marker be clicked to go back to a finished step.
		markers.forEach( function ( marker, index ) {
			marker.addEventListener( 'click', function () {
				if ( index < current ) {
					show( index );
				}
			} );
		} );

		// Enter should move on, not submit half a form.
		form.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' !== event.key || 'TEXTAREA' === event.target.tagName ) {
				return;
			}
			if ( current < panels.length - 1 ) {
				event.preventDefault();
				next.click();
			}
		} );

		// If the browser refuses the form, jump to the panel holding the problem.
		form.addEventListener( 'invalid', function ( event ) {
			for ( var i = 0; i < panels.length; i++ ) {
				if ( panels[ i ].contains( event.target ) && i !== current ) {
					show( i );
					break;
				}
			}
		}, true );

		show( 0 );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.estat-wizard' ), initWizard );
	} );
}() );

/**
 * Small conveniences: tell people something is happening, and stop them
 * losing work by accident.
 *
 * None of this changes what gets saved — if this file fails to load, every
 * form still submits exactly as before.
 */
( function () {
	'use strict';

	/*
	 * PHP localises these under 'i18n'. This block read 'strings', so text()
	 * would always have fallen through to English however the office set its
	 * language. Nothing called text() yet, which is the only reason nobody
	 * noticed.
	 */
	var strings = ( window.estatAdmin && window.estatAdmin.i18n ) || {};

	function text( key, fallback ) {
		return strings[ key ] || fallback;
	}

	/**
	 * A button that has been pressed should look pressed, so nobody clicks
	 * Save three times and wonders why.
	 */
	function markBusy( form ) {
		var buttons = form.querySelectorAll( 'button[type="submit"], input[type="submit"]' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.classList.add( 'estat-is-busy' );

			// Keep the value submitting: disabling a button drops its name,
			// so only block a second press instead.
			button.setAttribute( 'aria-busy', 'true' );
		} );

		form.classList.add( 'estat-form-busy' );
	}

	function initBusyForms() {
		Array.prototype.forEach.call( document.querySelectorAll( '.estat-screen form' ), function ( form ) {
			var submitted = false;

			form.addEventListener( 'submit', function ( event ) {
				// A second press would create two properties, two leads, two
				// of whatever this form makes.
				if ( submitted ) {
					event.preventDefault();
					return;
				}

				if ( form.checkValidity && ! form.checkValidity() ) {
					return;
				}

				submitted = true;
				markBusy( form );
			} );
		} );
	}

	/**
	 * Warn before leaving a half-filled form.
	 */
	function initUnsavedGuard() {
		var forms = document.querySelectorAll( '.estat-form-admin, .estat-wizard' );

		Array.prototype.forEach.call( forms, function ( form ) {
			var dirty = false;

			form.addEventListener( 'input', function () {
				dirty = true;
			} );

			form.addEventListener( 'submit', function () {
				dirty = false;
			} );

			window.addEventListener( 'beforeunload', function ( event ) {
				if ( ! dirty ) {
					return undefined;
				}

				// Browsers show their own wording; returning a string is what
				// asks them to.
				event.preventDefault();
				event.returnValue = '';
				return '';
			} );
		} );
	}

	/**
	 * Let a wide table be swiped sideways rather than stretching the page.
	 */
	function initScrollableTables() {
		Array.prototype.forEach.call( document.querySelectorAll( '.estat-screen .estat-table' ), function ( table ) {
			if ( table.parentNode && table.parentNode.classList.contains( 'estat-scroll-x' ) ) {
				return;
			}

			var wrap = document.createElement( 'div' );
			wrap.className = 'estat-scroll-x';
			table.parentNode.insertBefore( wrap, table );
			wrap.appendChild( table );
		} );
	}

	/**
	 * Keyboard shortcuts for the things done all day long.
	 */
	function initShortcuts() {
		document.addEventListener( 'keydown', function ( event ) {
			var tag = ( event.target && event.target.tagName ) || '';
			var typing = 'INPUT' === tag || 'TEXTAREA' === tag || 'SELECT' === tag || event.target.isContentEditable;

			// "/" jumps to the search box, the way most tools do it.
			if ( '/' === event.key && ! typing && ! event.metaKey && ! event.ctrlKey ) {
				var search = document.querySelector( '.estat-screen .estat-filters input[type="search"]' );

				if ( search ) {
					event.preventDefault();
					search.focus();
					search.select();
				}
				return;
			}

			// Ctrl/Cmd+S saves the form being worked on.
			if ( 's' === event.key.toLowerCase() && ( event.metaKey || event.ctrlKey ) ) {
				var form = document.querySelector( '.estat-screen .estat-form-admin, .estat-screen .estat-wizard' );

				if ( ! form ) {
					return;
				}

				var save = form.querySelector( 'button[type="submit"], input[type="submit"]' );

				if ( save ) {
					event.preventDefault();
					save.click();
				}
				return;
			}

			// Escape closes an open confirm drawer instead of trapping people.
			if ( 'Escape' === event.key ) {
				Array.prototype.forEach.call( document.querySelectorAll( '.estat-screen details[open]' ), function ( item ) {
					item.removeAttribute( 'open' );
				} );
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! document.body.classList.contains( 'estat-screen' ) ) {
			return;
		}

		initBusyForms();
		initUnsavedGuard();
		initScrollableTables();
		initShortcuts();
	} );

	// Exposed so the shortcut hint can be translated later.
	window.estatConveniences = { text: text };
}() );

/* ------------------------------------------------------------------
 * Acting on several records at once.
 *
 * The bar stays hidden until something is ticked, so a list that nobody
 * is selecting from looks exactly as it did before.
 * ------------------------------------------------------------------ */

( function () {
	'use strict';

	var forms = document.querySelectorAll( '.estat-bulk-form' );

	Array.prototype.forEach.call( forms, function ( form ) {
		var all = form.querySelector( '.estat-bulk-all' );
		var bar = form.querySelector( '.estat-bulk-bar' );
		var count = form.querySelector( '.estat-bulk-count' );
		var clear = form.querySelector( '.estat-bulk-clear' );
		var choice = form.querySelector( '[name="bulk_action"]' );
		var strings = ( window.estatAdmin && window.estatAdmin.i18n ) || {};

		if ( ! bar ) {
			return;
		}

		function ticks() {
			return form.querySelectorAll( '.estat-bulk-tick' );
		}

		function chosen() {
			return form.querySelectorAll( '.estat-bulk-tick:checked' );
		}

		function refresh() {
			var picked = chosen().length;
			var total = ticks().length;

			if ( picked > 0 ) {
				bar.removeAttribute( 'hidden' );
			} else {
				bar.setAttribute( 'hidden', 'hidden' );
			}

			if ( count ) {
				count.textContent = picked === 1
					? ( strings.bulkOne || '1 ticked' )
					: ( ( strings.bulkMany || '%d ticked' ).replace( '%d', picked ) );
			}

			if ( all ) {
				all.checked = total > 0 && picked === total;
				// Partly selected reads as neither on nor off, which is the
				// honest state when some rows are ticked.
				all.indeterminate = picked > 0 && picked < total;
			}
		}

		form.addEventListener( 'change', function ( event ) {
			if ( event.target === all ) {
				Array.prototype.forEach.call( ticks(), function ( tick ) {
					tick.checked = all.checked;
				} );
			}

			if ( event.target === all || event.target.classList.contains( 'estat-bulk-tick' ) ) {
				refresh();
			}
		} );

		if ( clear ) {
			clear.addEventListener( 'click', function () {
				Array.prototype.forEach.call( ticks(), function ( tick ) {
					tick.checked = false;
				} );
				refresh();
			} );
		}

		/*
		 * Two guards before anything is submitted: something must be chosen,
		 * and moving records to the bin is confirmed. The server checks
		 * permission on every record regardless; this is only about not
		 * doing it by accident.
		 */
		form.addEventListener( 'submit', function ( event ) {
			var picked = chosen().length;

			if ( ! picked ) {
				event.preventDefault();
				return;
			}

			if ( choice && ! choice.value ) {
				event.preventDefault();
				choice.focus();
				return;
			}

			if ( choice && choice.value === 'trash' ) {
				var ask = ( strings.bulkConfirmTrash || 'Move %d records to the bin?' ).replace( '%d', picked );

				if ( ! window.confirm( ask ) ) {
					event.preventDefault();
				}
			}
		} );

		refresh();
	} );
}() );

/* ------------------------------------------------------------------
 * Keyboard shortcuts.
 *
 * For the person who lives in these screens all day. Every one of them
 * has a visible control too, so nothing is reachable ONLY by keyboard,
 * and nothing fires while you are typing.
 * ------------------------------------------------------------------ */

( function () {
	'use strict';

	var strings = ( window.estatAdmin && window.estatAdmin.i18n ) || {};
	var urls = ( window.estatAdmin && window.estatAdmin.urls ) || {};

	function typing( element ) {
		if ( ! element ) {
			return false;
		}

		var tag = ( element.tagName || '' ).toLowerCase();

		return tag === 'input' || tag === 'textarea' || tag === 'select' ||
			element.isContentEditable === true;
	}

	function focusSearch() {
		var box = document.querySelector( '.estat-filters input[type="search"]' ) ||
			document.querySelector( '.estat-screen input[type="search"]' );

		if ( box ) {
			box.focus();
			box.select();
			return true;
		}

		return false;
	}

	function saveCurrentForm() {
		var form = document.querySelector( '.estat-form-admin' );

		if ( ! form ) {
			return false;
		}

		var submit = form.querySelector( '[type="submit"]' );

		if ( submit ) {
			submit.click();
			return true;
		}

		return false;
	}

	var help = null;

	function toggleHelp() {
		if ( help && help.parentNode ) {
			help.parentNode.removeChild( help );
			help = null;
			return;
		}

		help = document.createElement( 'div' );
		help.className = 'estat-shortcuts';
		help.setAttribute( 'role', 'dialog' );
		help.setAttribute( 'aria-modal', 'false' );
		help.setAttribute( 'aria-label', strings.shortcutsTitle || 'Keyboard shortcuts' );

		var heading = document.createElement( 'h2' );
		heading.textContent = strings.shortcutsTitle || 'Keyboard shortcuts';
		help.appendChild( heading );

		var list = document.createElement( 'dl' );

		[
			[ '/', strings.shortcutSearch || 'Jump to the search box' ],
			[ 'n', strings.shortcutNew || 'Add a Home' ],
			[ 'g then t', strings.shortcutToday || 'Go to Today' ],
			[ 'g then l', strings.shortcutListings || 'Go to Listings' ],
			[ 'g then e', strings.shortcutEnquiries || 'Go to Enquiries' ],
			[ 'Ctrl or Cmd + S', strings.shortcutSave || 'Save what you are editing' ],
			[ '?', strings.shortcutHelp || 'Show this list' ],
			[ 'Esc', strings.shortcutClose || 'Close this list' ]
		].forEach( function ( pair ) {
			var key = document.createElement( 'dt' );
			key.textContent = pair[ 0 ];
			var what = document.createElement( 'dd' );
			what.textContent = pair[ 1 ];
			list.appendChild( key );
			list.appendChild( what );
		} );

		help.appendChild( list );

		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'button';
		close.textContent = strings.shortcutCloseButton || 'Close';
		close.addEventListener( 'click', toggleHelp );
		help.appendChild( close );

		document.body.appendChild( help );
		close.focus();
	}

	// "g" then a letter, the way Gmail and GitHub do it.
	var pending = false;
	var pendingTimer = null;

	function go( where ) {
		if ( urls[ where ] ) {
			window.location.href = urls[ where ];
		}
	}

	document.addEventListener( 'keydown', function ( event ) {
		// Never steal a key from someone who is typing, and never from a
		// browser or screen-reader combination.
		if ( event.altKey || event.metaKey && event.key !== 's' ) {
			return;
		}

		if ( ( event.ctrlKey || event.metaKey ) && event.key === 's' ) {
			if ( saveCurrentForm() ) {
				event.preventDefault();
			}
			return;
		}

		if ( event.ctrlKey ) {
			return;
		}

		if ( event.key === 'Escape' && help ) {
			toggleHelp();
			return;
		}

		if ( typing( document.activeElement ) ) {
			return;
		}

		if ( pending ) {
			pending = false;
			window.clearTimeout( pendingTimer );

			if ( event.key === 't' ) {
				event.preventDefault();
				go( 'today' );
				return;
			}
			if ( event.key === 'l' ) {
				event.preventDefault();
				go( 'listings' );
				return;
			}
			if ( event.key === 'e' ) {
				event.preventDefault();
				go( 'enquiries' );
				return;
			}
		}

		if ( event.key === 'g' ) {
			pending = true;
			// A stray "g" must not arm the shortcut forever.
			pendingTimer = window.setTimeout( function () {
				pending = false;
			}, 1200 );
			return;
		}

		if ( event.key === '/' ) {
			if ( focusSearch() ) {
				event.preventDefault();
			}
			return;
		}

		if ( event.key === 'n' ) {
			if ( urls.addHome ) {
				event.preventDefault();
				window.location.href = urls.addHome;
			}
			return;
		}

		if ( event.key === '?' ) {
			event.preventDefault();
			toggleHelp();
		}
	} );
}() );
