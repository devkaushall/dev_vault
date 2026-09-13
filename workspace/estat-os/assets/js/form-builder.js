/**
 * Estat.OS - visual form builder.
 *
 * Renders a rows / columns / fields tree, lets the office drag fields
 * between columns, and writes the whole definition back into a hidden
 * input as JSON. The server re-validates everything, so nothing here is
 * trusted; this file only has to be pleasant to use.
 */
( function ( window, document ) {
	'use strict';

	var config = window.estatBuilder || {};
	var types = config.fieldTypes || {};
	var t = config.i18n || {};

	var WIDTHS = [ '100', '75', '66', '50', '33', '25' ];

	// Elementor-style one-click column layouts. Each is a list of widths.
	var LAYOUTS = [
		{ id: 'full', cols: [ 100 ], label: '100' },
		{ id: 'half', cols: [ 50, 50 ], label: '50 / 50' },
		{ id: 'third', cols: [ 33, 33, 33 ], label: '33 / 33 / 33' },
		{ id: 'wide-left', cols: [ 66, 33 ], label: '66 / 33' },
		{ id: 'wide-right', cols: [ 33, 66 ], label: '33 / 66' },
		{ id: 'quarter', cols: [ 25, 25, 25, 25 ], label: '25 x4' }
	];

	var SIZES = [
		{ id: 'sm', label: 'Small' },
		{ id: 'md', label: 'Medium' },
		{ id: 'lg', label: 'Large' }
	];

	// Which device the canvas is previewing at.
	var device = 'desktop';

	var root = null;
	var input = null;
	var model = { rows: [] };
	var selected = null;
	var counter = 0;

	/* ---------------------------------------------------------- Helpers */

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined ) {
			node.textContent = text;
		}
		return node;
	}

	/**
	 * Every matching element, as a real array.
	 *
	 * This file had no such helper; the stage and styling code assumed one
	 * existed in admin.js, which is a different scope.
	 */
	function all( selector, scope ) {
		return Array.prototype.slice.call( ( scope || document ).querySelectorAll( selector ) );
	}

	function uid( prefix ) {
		counter += 1;
		return prefix + '_' + Date.now().toString( 36 ) + counter.toString( 36 );
	}

	function typeLabel( type ) {
		return ( types[ type ] && types[ type ].label ) || type;
	}

	function hasOptions( type ) {
		return !! ( types[ type ] && types[ type ].options );
	}

	function commit() {
		if ( input ) {
			input.value = JSON.stringify( model );
		}
	}

	/* ------------------------------------------------------ Model edits */

	function newField( type ) {
		var field = {
			id: uid( 'f' ),
			type: type,
			label: typeLabel( type ),
			placeholder: '',
			help: '',
			required: false,
			width: '100',
			width_tablet: '100',
			width_mobile: '100',
			size: 'md',
			options: []
		};
		if ( hasOptions( type ) ) {
			field.options = [
				{ value: 'one', label: 'One' },
				{ value: 'two', label: 'Two' }
			];
		}
		return field;
	}

	function newColumn() {
		return { width: '100', width_tablet: '100', width_mobile: '100', fields: [] };
	}

	function newRow() {
		return { heading: '', gap: 16, gap_mobile: 12, align: 'stretch', columns: [ newColumn() ] };
	}

	/**
	 * Does this row already sit at the given preset?
	 */
	function matchesLayout( row, layout ) {
		if ( row.columns.length !== layout.cols.length ) {
			return false;
		}
		return layout.cols.every( function ( width, index ) {
			return Number( row.columns[ index ].width ) === width;
		} );
	}

	/**
	 * Switch a row to a preset column split.
	 *
	 * Fields are never destroyed. If the new layout has fewer columns, the
	 * leftover fields are appended to the last surviving column.
	 */
	function applyLayout( row, layout ) {
		var kept = row.columns.slice( 0, layout.cols.length );
		var spare = row.columns.slice( layout.cols.length );

		while ( kept.length < layout.cols.length ) {
			kept.push( newColumn() );
		}

		if ( spare.length ) {
			var last = kept[ kept.length - 1 ];
			spare.forEach( function ( column ) {
				last.fields = last.fields.concat( column.fields || [] );
			} );
		}

		layout.cols.forEach( function ( width, index ) {
			kept[ index ].width = String( width );
			// On a tablet, anything narrower than a third is unreadable.
			kept[ index ].width_tablet = width < 33 ? '50' : String( width );
			// Phones are always one per line.
			kept[ index ].width_mobile = '100';
		} );

		row.columns = kept;
	}

	/**
	 * Share the width evenly across a row's columns.
	 */
	function evenOut( row ) {
		var each = Math.floor( 100 / row.columns.length );
		row.columns.forEach( function ( column ) {
			column.width = String( each );
			column.width_tablet = each < 33 ? '50' : String( each );
			column.width_mobile = '100';
		} );
	}

	function ensureShape() {
		if ( ! model || ! Array.isArray( model.rows ) || ! model.rows.length ) {
			model = { rows: [ newRow() ] };
		}
		model.rows.forEach( function ( row ) {
			if ( ! Array.isArray( row.columns ) || ! row.columns.length ) {
				row.columns = [ newColumn() ];
			}

			// Older forms stored gap as the word "normal", which the server
			// reads as a number and turns into 0. Repair it on open.
			if ( typeof row.gap !== 'number' || isNaN( row.gap ) ) {
				row.gap = 16;
			}
			if ( typeof row.gap_mobile !== 'number' || isNaN( row.gap_mobile ) ) {
				row.gap_mobile = 12;
			}
			if ( [ 'stretch', 'start', 'center', 'end' ].indexOf( row.align ) === -1 ) {
				row.align = 'stretch';
			}
			row.columns.forEach( function ( column ) {
				if ( ! Array.isArray( column.fields ) ) {
					column.fields = [];
				}
				column.fields.forEach( function ( field ) {
					if ( ! field.id ) {
						field.id = uid( 'f' );
					}
					if ( SIZES.map( function ( s ) { return s.id; } ).indexOf( field.size ) === -1 ) {
						field.size = 'md';
					}
				} );
			} );
		} );
	}

	function findField( id ) {
		var found = null;
		model.rows.forEach( function ( row, r ) {
			row.columns.forEach( function ( column, c ) {
				column.fields.forEach( function ( field, f ) {
					if ( field.id === id ) {
						found = { field: field, row: r, column: c, index: f };
					}
				} );
			} );
		} );
		return found;
	}

	function removeField( id ) {
		var at = findField( id );
		if ( at ) {
			model.rows[ at.row ].columns[ at.column ].fields.splice( at.index, 1 );
		}
	}

	function moveField( id, rowIndex, columnIndex, position ) {
		var at = findField( id );
		if ( ! at ) {
			return;
		}
		var field = at.field;
		model.rows[ at.row ].columns[ at.column ].fields.splice( at.index, 1 );
		var target = model.rows[ rowIndex ].columns[ columnIndex ].fields;
		var index = typeof position === 'number' ? position : target.length;
		target.splice( Math.max( 0, Math.min( index, target.length ) ), 0, field );
	}

	/* ----------------------------------------------------------- Render */

	function widthSelect( value, onChange, label ) {
		var wrap = el( 'span' );
		var caption = el( 'label', null, label );
		var select = el( 'select' );
		WIDTHS.forEach( function ( width ) {
			var option = el( 'option', null, width + '%' );
			option.value = width;
			if ( String( value ) === width ) {
				option.selected = true;
			}
			select.appendChild( option );
		} );
		select.addEventListener( 'change', function () {
			onChange( select.value );
			commit();
		} );
		caption.appendChild( select );
		wrap.appendChild( caption );
		return wrap;
	}

	function textRow( labelText, value, onChange, multiline ) {
		var wrap = el( 'p', 'estat-builder-input' );
		var label = el( 'label', null, labelText );
		var control = multiline ? el( 'textarea' ) : el( 'input' );
		if ( ! multiline ) {
			control.type = 'text';
		} else {
			control.rows = 2;
		}
		control.value = value || '';
		control.style.width = '100%';
		control.addEventListener( 'input', function () {
			onChange( control.value );
			commit();
		} );
		label.appendChild( control );
		wrap.appendChild( label );
		return wrap;
	}

	function renderFieldEditor( field ) {
		var box = el( 'div', 'estat-builder-field-editor' );

		box.appendChild( textRow( t.label || 'Label', field.label, function ( value ) {
			field.label = value;
			var title = document.querySelector( '[data-field="' + field.id + '"] .estat-builder-field-title' );
			if ( title ) {
				title.textContent = value;
			}
		} ) );

		if ( field.type !== 'heading' && field.type !== 'paragraph' && field.type !== 'divider' ) {
			box.appendChild( textRow( t.placeholder || 'Example text inside the box', field.placeholder, function ( value ) {
				field.placeholder = value;
			} ) );
			box.appendChild( textRow( t.help || 'Short explanation', field.help, function ( value ) {
				field.help = value;
			} ) );

			var requiredWrap = el( 'p' );
			var requiredLabel = el( 'label', 'estat-inline-check' );
			var requiredBox = el( 'input' );
			requiredBox.type = 'checkbox';
			requiredBox.checked = !! field.required;
			requiredBox.addEventListener( 'change', function () {
				field.required = requiredBox.checked;
				commit();
			} );
			requiredLabel.appendChild( requiredBox );
			requiredLabel.appendChild( document.createTextNode( ' ' + ( t.required || 'This information is required' ) ) );
			requiredWrap.appendChild( requiredLabel );
			box.appendChild( requiredWrap );
		}

		if ( hasOptions( field.type ) ) {
			var lines = ( field.options || [] ).map( function ( option ) {
				return option.label;
			} ).join( '\n' );
			box.appendChild( textRow( t.options || 'Choices (one per line)', lines, function ( value ) {
				field.options = value.split( '\n' ).map( function ( line ) {
					return line.trim();
				} ).filter( Boolean ).map( function ( line ) {
					return { value: line.toLowerCase().replace( /[^a-z0-9]+/g, '_' ), label: line };
				} );
			}, true ) );
		}

		/* ---- How tall the box is: small / medium / large. */

		var sizeRow = el( 'div', 'estat-builder-input' );
		sizeRow.appendChild( el( 'span', 'estat-builder-sub', t.boxHeight || 'Box height' ) );
		var sizeGroup = el( 'div', 'estat-seg' );
		sizeGroup.setAttribute( 'role', 'group' );
		sizeGroup.setAttribute( 'aria-label', t.boxHeight || 'Box height' );
		SIZES.forEach( function ( size ) {
			var chip = el( 'button', 'estat-seg-item', t[ 'size' + size.id.toUpperCase() ] || size.label );
			chip.type = 'button';
			chip.setAttribute( 'data-size', size.id );
			if ( ( field.size || 'md' ) === size.id ) {
				chip.classList.add( 'is-active' );
				chip.setAttribute( 'aria-pressed', 'true' );
			} else {
				chip.setAttribute( 'aria-pressed', 'false' );
			}
			chip.addEventListener( 'click', function () {
				field.size = size.id;
				commit();
				render();
			} );
			sizeGroup.appendChild( chip );
		} );
		sizeRow.appendChild( sizeGroup );
		box.appendChild( sizeRow );

		/* ---- Taller message box, when this field is a message box. */

		if ( field.type === 'textarea' ) {
			var rowsRow = el( 'div', 'estat-builder-input' );
			rowsRow.appendChild( el( 'span', 'estat-builder-sub', t.lines || 'Lines of space to type in' ) );
			var rowsRange = el( 'input', 'estat-builder-range' );
			rowsRange.type = 'range';
			rowsRange.min = '2';
			rowsRange.max = '20';
			rowsRange.step = '1';
			rowsRange.value = String( field.rows || 4 );
			rowsRange.setAttribute( 'aria-label', t.lines || 'Lines of space to type in' );
			var rowsOut = el( 'output', 'estat-builder-tool-value', String( field.rows || 4 ) );
			rowsRange.addEventListener( 'input', function () {
				field.rows = Number( rowsRange.value );
				rowsOut.textContent = String( field.rows );
				commit();
			} );
			rowsRange.addEventListener( 'change', refreshPreview );
			rowsRow.appendChild( rowsRange );
			rowsRow.appendChild( rowsOut );
			box.appendChild( rowsRow );
		}

		/* ---- How wide the box is, per device, as a segmented control. */

		var widthRow = el( 'div', 'estat-builder-input' );
		widthRow.appendChild( el( 'span', 'estat-builder-sub', t.boxWidth || 'Box width' ) );
		var widthGroup = el( 'div', 'estat-seg' );
		widthGroup.setAttribute( 'role', 'group' );
		widthGroup.setAttribute( 'aria-label', t.boxWidth || 'Box width' );

		var widthKey = 'width';
		if ( device === 'tablet' ) {
			widthKey = 'width_tablet';
		} else if ( device === 'mobile' ) {
			widthKey = 'width_mobile';
		}

		[ '100', '50', '33', '25' ].forEach( function ( width ) {
			var chip = el( 'button', 'estat-seg-item', width + '%' );
			chip.type = 'button';
			chip.setAttribute( 'data-width', width );
			if ( String( field[ widthKey ] || '100' ) === width ) {
				chip.classList.add( 'is-active' );
				chip.setAttribute( 'aria-pressed', 'true' );
			} else {
				chip.setAttribute( 'aria-pressed', 'false' );
			}
			chip.addEventListener( 'click', function () {
				field[ widthKey ] = width;
				if ( widthKey === 'width' && String( field.width_tablet ) === '100' && width !== '100' ) {
					field.width_tablet = width;
				}
				commit();
				render();
			} );
			widthGroup.appendChild( chip );
		} );
		widthRow.appendChild( widthGroup );
		box.appendChild( widthRow );

		/* ---- Every exact percentage, still there, folded away. */

		var fine = el( 'details', 'estat-builder-fine' );
		var fineSummary = el( 'summary', null, t.exactWidths || 'Exact widths' );
		fine.appendChild( fineSummary );

		var widths = el( 'div', 'estat-builder-widths' );
		widths.appendChild( widthSelect( field.width, function ( value ) {
			field.width = value;
			refreshPreview();
		}, t.width || 'Width on a computer' ) );
		widths.appendChild( widthSelect( field.width_tablet, function ( value ) {
			field.width_tablet = value;
			refreshPreview();
		}, t.widthTablet || 'Width on a tablet' ) );
		widths.appendChild( widthSelect( field.width_mobile, function ( value ) {
			field.width_mobile = value;
			refreshPreview();
		}, t.widthMobile || 'Width on a phone' ) );
		fine.appendChild( widths );
		box.appendChild( fine );

		// One simple rule: only show this box when another one has a value.
		box.appendChild( renderConditionEditor( field ) );

		return box;
	}

	/**
	 * "Only show this when ..." — one rule, in plain words.
	 */
	function renderConditionEditor( field ) {
		var wrap = el( 'details', 'estat-builder-condition' );
		var rule = field.condition || { field: '', operator: 'is', value: '' };
		var isOn = !! rule.field;

		var summary = el( 'summary', null, isOn
			? ( t.conditionOn || 'Only shown sometimes' )
			: ( t.conditionOff || 'Always shown' ) );
		wrap.appendChild( summary );

		if ( isOn ) {
			wrap.setAttribute( 'open', 'open' );
		}

		var line = el( 'div', 'estat-condition-line' );
		line.appendChild( el( 'span', null, t.conditionWhen || 'Only show this box when' ) );

		var picker = el( 'select' );
		var none = el( 'option', null, t.conditionAlways || '— always show it —' );
		none.value = '';
		picker.appendChild( none );

		// A field may only depend on one that comes before it, otherwise the
		// rule could never be satisfied.
		everyFieldBefore( field.id ).forEach( function ( other ) {
			var option = el( 'option', null, other.label || other.id );
			option.value = other.id;
			if ( rule.field === other.id ) {
				option.selected = true;
			}
			picker.appendChild( option );
		} );

		var valueBox = el( 'input' );
		valueBox.type = 'text';
		valueBox.value = rule.value || '';
		valueBox.placeholder = t.conditionValue || 'has this answer';
		valueBox.disabled = ! rule.field;

		picker.addEventListener( 'change', function () {
			field.condition = {
				field: picker.value,
				operator: 'is',
				value: picker.value ? valueBox.value : ''
			};
			valueBox.disabled = ! picker.value;
			summary.textContent = picker.value
				? ( t.conditionOn || 'Only shown sometimes' )
				: ( t.conditionOff || 'Always shown' );
			commit();
			refreshPreview();
		} );

		valueBox.addEventListener( 'input', function () {
			field.condition = {
				field: picker.value,
				operator: 'is',
				value: valueBox.value
			};
			commit();
		} );

		line.appendChild( picker );
		line.appendChild( el( 'span', null, t.conditionIs || 'is' ) );
		line.appendChild( valueBox );
		wrap.appendChild( line );

		return wrap;
	}

	/**
	 * Every field that appears before this one, in form order.
	 */
	function everyFieldBefore( id ) {
		var out = [];
		var stop = false;

		model.rows.forEach( function ( row ) {
			row.columns.forEach( function ( column ) {
				column.fields.forEach( function ( field ) {
					if ( field.id === id ) {
						stop = true;
					}
					if ( ! stop && field.type !== 'heading' && field.type !== 'paragraph' && field.type !== 'submit' ) {
						out.push( field );
					}
				} );
			} );
		} );

		return out;
	}

	function renderField( field ) {
		var node = el( 'div', 'estat-builder-field' );
		node.setAttribute( 'data-field', field.id );
		node.setAttribute( 'draggable', 'true' );
		node.setAttribute( 'tabindex', '0' );
		if ( selected === field.id ) {
			node.classList.add( 'is-selected' );
		}

		var head = el( 'div', 'estat-builder-field-head' );
		var title = el( 'strong', 'estat-builder-field-title', field.label || typeLabel( field.type ) );
		var type = el( 'span', 'estat-builder-field-type', typeLabel( field.type ) );
		head.appendChild( title );
		head.appendChild( type );

		// Inside a narrow column the word "Remove" was being clipped to "Re".
		var remove = el( 'button', 'estat-icon-button estat-danger-link', '\u00d7' );
		remove.type = 'button';
		remove.title = t.remove || 'Remove';
		remove.setAttribute( 'aria-label', t.remove || 'Remove' );
		remove.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
			if ( ! window.confirm( t.confirmDelete || 'Remove this field?' ) ) {
				return;
			}
			removeField( field.id );
			commit();
			render();
		} );
		head.appendChild( remove );
		node.appendChild( head );

		node.addEventListener( 'click', function () {
			selected = ( selected === field.id ) ? null : field.id;
			render();
		} );

		if ( selected === field.id ) {
			var editor = renderFieldEditor( field );
			editor.addEventListener( 'click', function ( event ) {
				event.stopPropagation();
			} );
			node.appendChild( editor );
		}

		return node;
	}

	function renderColumn( column, rowIndex, columnIndex ) {
		var node = el( 'div', 'estat-builder-column' );
		node.setAttribute( 'data-row', String( rowIndex ) );
		node.setAttribute( 'data-column', String( columnIndex ) );

		// The canvas must show the REAL proportion. Previously every column
		// was flex:1 1 220px, so a 25/75 split looked like 50/50 and the
		// office could not see what it was building.
		var shown = column.width;
		if ( device === 'tablet' ) {
			shown = column.width_tablet;
		} else if ( device === 'mobile' ) {
			shown = column.width_mobile;
		}
		node.style.setProperty( '--cw', Number( shown || 100 ) + '%' );
		node.setAttribute( 'data-width', String( Number( shown || 100 ) ) );

		// A grab handle that reports, and lets you change, this column's width.
		var bar = el( 'div', 'estat-builder-column-bar' );
		// The dropdown already shows the width. A separate tag beside it just
		// printed "100%" twice, overlapping on narrow columns.
		bar.appendChild( el( 'span', 'estat-builder-column-width', t.columnWidth || 'Width' ) );

		var picker = el( 'select', 'estat-builder-column-pick' );
		picker.setAttribute( 'aria-label', t.columnWidth || 'Width of this column' );
		WIDTHS.forEach( function ( width ) {
			var option = el( 'option', null, width + '%' );
			option.value = width;
			if ( String( shown ) === width ) {
				option.selected = true;
			}
			picker.appendChild( option );
		} );
		picker.addEventListener( 'change', function () {
			if ( device === 'tablet' ) {
				column.width_tablet = picker.value;
			} else if ( device === 'mobile' ) {
				column.width_mobile = picker.value;
			} else {
				column.width = picker.value;
				// Keep tablet in step unless it was deliberately changed.
				if ( String( column.width_tablet ) === String( shown ) ) {
					column.width_tablet = picker.value;
				}
			}
			commit();
			render();
		} );
		bar.appendChild( picker );

		if ( ( model.rows[ rowIndex ] || {} ).columns && model.rows[ rowIndex ].columns.length > 1 ) {
			// An icon, not words: this bar lives inside a column that may be
			// only a quarter of the canvas wide.
			var drop = el( 'button', 'estat-icon-button estat-danger-link', '\u00d7' );
			drop.type = 'button';
			drop.title = t.removeColumn || 'Remove this column';
			drop.setAttribute( 'aria-label', t.removeColumn || 'Remove this column' );
			drop.addEventListener( 'click', function ( event ) {
				event.stopPropagation();
				var row = model.rows[ rowIndex ];
				var dying = row.columns.splice( columnIndex, 1 )[ 0 ];
				// Never silently destroy fields: move them left.
				if ( dying && dying.fields.length ) {
					var target = row.columns[ Math.max( 0, columnIndex - 1 ) ];
					target.fields = target.fields.concat( dying.fields );
				}
				ensureShape();
				commit();
				render();
			} );
			bar.appendChild( drop );
		}

		node.appendChild( bar );

		column.fields.forEach( function ( field ) {
			node.appendChild( renderField( field ) );
		} );

		if ( ! column.fields.length ) {
			node.appendChild( el( 'p', 'estat-muted', t.dragHint || 'Drag a field here' ) );
		}

		node.addEventListener( 'dragover', function ( event ) {
			event.preventDefault();
			node.classList.add( 'is-over' );
		} );
		node.addEventListener( 'dragleave', function () {
			node.classList.remove( 'is-over' );
		} );
		node.addEventListener( 'drop', function ( event ) {
			event.preventDefault();
			node.classList.remove( 'is-over' );
			var id = event.dataTransfer ? event.dataTransfer.getData( 'text/plain' ) : '';
			if ( ! id ) {
				return;
			}
			moveField( id, rowIndex, columnIndex );
			commit();
			render();
		} );

		return node;
	}

	function renderRow( row, rowIndex ) {
		var node = el( 'div', 'estat-builder-row' );

		var head = el( 'div', 'estat-builder-row-head' );
		var heading = el( 'input' );
		heading.type = 'text';
		heading.value = row.heading || '';
		heading.placeholder = t.rowHeading || 'Optional heading for this row';
		heading.addEventListener( 'input', function () {
			row.heading = heading.value;
			commit();
		} );
		head.appendChild( heading );

		var controls = el( 'span', 'estat-builder-row-controls' );

		var addColumn = el( 'button', 'button button-small', t.addColumn || 'Add a column' );
		addColumn.type = 'button';
		addColumn.disabled = row.columns.length >= 4;
		addColumn.addEventListener( 'click', function () {
			if ( row.columns.length >= 4 ) {
				return;
			}
			row.columns.push( newColumn() );
			evenOut( row );
			commit();
			render();
		} );
		controls.appendChild( addColumn );

		var up = el( 'button', 'button button-small', '\u2191' );
		up.type = 'button';
		up.setAttribute( 'aria-label', t.moveUp || 'Move up' );
		up.disabled = rowIndex === 0;
		up.addEventListener( 'click', function () {
			var moved = model.rows.splice( rowIndex, 1 )[ 0 ];
			model.rows.splice( rowIndex - 1, 0, moved );
			commit();
			render();
		} );
		controls.appendChild( up );

		var down = el( 'button', 'button button-small', '\u2193' );
		down.type = 'button';
		down.setAttribute( 'aria-label', t.moveDown || 'Move down' );
		down.disabled = rowIndex === model.rows.length - 1;
		down.addEventListener( 'click', function () {
			var moved = model.rows.splice( rowIndex, 1 )[ 0 ];
			model.rows.splice( rowIndex + 1, 0, moved );
			commit();
			render();
		} );
		controls.appendChild( down );

		var removeRow = el( 'button', 'button button-small estat-danger-button', t.remove || 'Remove' );
		removeRow.type = 'button';
		removeRow.addEventListener( 'click', function () {
			if ( ! window.confirm( t.confirmDeleteRow || t.confirmDelete || 'Remove this row?' ) ) {
				return;
			}
			model.rows.splice( rowIndex, 1 );
			ensureShape();
			commit();
			render();
		} );
		controls.appendChild( removeRow );

		head.appendChild( controls );
		node.appendChild( head );

		/* ---- Elementor-style layout strip: pick a column split in one click. */

		var tools = el( 'div', 'estat-builder-row-tools' );

		var layoutWrap = el( 'div', 'estat-builder-tool' );
		layoutWrap.appendChild( el( 'span', 'estat-builder-tool-label', t.layout || 'Columns' ) );
		var chips = el( 'div', 'estat-builder-layouts' );
		LAYOUTS.forEach( function ( layout ) {
			var chip = el( 'button', 'estat-layout-chip' );
			chip.type = 'button';
			chip.title = layout.label;
			chip.setAttribute( 'aria-label', layout.label );
			chip.setAttribute( 'data-layout', layout.id );

			layout.cols.forEach( function ( width ) {
				var slice = el( 'span', 'estat-layout-slice' );
				slice.style.flexGrow = String( width );
				chip.appendChild( slice );
			} );

			if ( matchesLayout( row, layout ) ) {
				chip.classList.add( 'is-active' );
				chip.setAttribute( 'aria-pressed', 'true' );
			} else {
				chip.setAttribute( 'aria-pressed', 'false' );
			}

			chip.addEventListener( 'click', function () {
				applyLayout( row, layout );
				commit();
				render();
			} );
			chips.appendChild( chip );
		} );
		layoutWrap.appendChild( chips );
		tools.appendChild( layoutWrap );

		/* ---- Space between the boxes. */

		var gapWrap = el( 'div', 'estat-builder-tool' );
		var gapLabel = el( 'span', 'estat-builder-tool-label', t.gap || 'Space between boxes' );
		gapWrap.appendChild( gapLabel );
		var gapRange = el( 'input', 'estat-builder-range' );
		gapRange.type = 'range';
		gapRange.min = '0';
		gapRange.max = '48';
		gapRange.step = '2';
		gapRange.value = String( row.gap );
		gapRange.setAttribute( 'aria-label', t.gap || 'Space between boxes' );
		var gapOut = el( 'output', 'estat-builder-tool-value', row.gap + 'px' );
		gapRange.addEventListener( 'input', function () {
			row.gap = Number( gapRange.value );
			gapOut.textContent = row.gap + 'px';
			var live = node.querySelector( '.estat-builder-columns' );
			if ( live ) {
				live.style.setProperty( '--cgap', row.gap + 'px' );
			}
			commit();
		} );
		gapRange.addEventListener( 'change', refreshPreview );
		gapWrap.appendChild( gapRange );
		gapWrap.appendChild( gapOut );

		// Spacing and alignment share a second line, because all of this on
		// one line needs far more width than the canvas column ever has.
		var secondLine = el( 'div', 'estat-builder-tools-line' );
		secondLine.appendChild( gapWrap );
		tools.appendChild( secondLine );

		/* ---- Vertical alignment of the columns. */

		var alignWrap = el( 'div', 'estat-builder-tool' );
		alignWrap.appendChild( el( 'span', 'estat-builder-tool-label', t.align || 'Line up' ) );
		var alignPick = el( 'select' );
		alignPick.setAttribute( 'aria-label', t.align || 'Line up' );
		[
			{ id: 'stretch', label: t.alignStretch || 'Same height' },
			{ id: 'start', label: t.alignStart || 'Top' },
			{ id: 'center', label: t.alignCenter || 'Middle' },
			{ id: 'end', label: t.alignEnd || 'Bottom' }
		].forEach( function ( choice ) {
			var option = el( 'option', null, choice.label );
			option.value = choice.id;
			if ( row.align === choice.id ) {
				option.selected = true;
			}
			alignPick.appendChild( option );
		} );
		alignPick.addEventListener( 'change', function () {
			row.align = alignPick.value;
			commit();
			render();
		} );
		alignWrap.appendChild( alignPick );
		secondLine.appendChild( alignWrap );

		node.appendChild( tools );

		var columns = el( 'div', 'estat-builder-columns' );
		columns.style.setProperty( '--cgap', row.gap + 'px' );
		columns.setAttribute( 'data-align', row.align );
		row.columns.forEach( function ( column, columnIndex ) {
			columns.appendChild( renderColumn( column, rowIndex, columnIndex ) );
		} );
		node.appendChild( columns );

		return node;
	}

	/**
	 * Desktop / tablet / phone switch. Changing it changes which width the
	 * canvas draws and which width the controls edit -- the same idea as the
	 * device switch in Elementor.
	 */
	function renderDeviceBar() {
		var bar = el( 'div', 'estat-builder-devices' );
		bar.setAttribute( 'role', 'group' );
		bar.setAttribute( 'aria-label', t.deviceBar || 'Show the layout as it appears on' );

		[
			{ id: 'desktop', label: t.deviceDesktop || 'Computer' },
			{ id: 'tablet', label: t.deviceTablet || 'Tablet' },
			{ id: 'mobile', label: t.deviceMobile || 'Phone' }
		].forEach( function ( choice ) {
			var chip = el( 'button', 'estat-seg-item', choice.label );
			chip.type = 'button';
			chip.setAttribute( 'data-device', choice.id );
			if ( device === choice.id ) {
				chip.classList.add( 'is-active' );
				chip.setAttribute( 'aria-pressed', 'true' );
			} else {
				chip.setAttribute( 'aria-pressed', 'false' );
			}
			chip.addEventListener( 'click', function () {
				device = choice.id;
				render();
			} );
			bar.appendChild( chip );
		} );

		var hint = el( 'span', 'estat-builder-device-hint', device === 'desktop'
			? ( t.deviceHintDesktop || 'Widths you set now apply to computers.' )
			: ( device === 'tablet'
				? ( t.deviceHintTablet || 'Widths you set now apply to tablets only.' )
				: ( t.deviceHintMobile || 'Widths you set now apply to phones only.' ) ) );
		bar.appendChild( hint );

		return bar;
	}

	function render() {
		if ( ! root ) {
			return;
		}
		root.textContent = '';
		ensureShape();

		root.appendChild( renderDeviceBar() );

		var canvas = el( 'div', 'estat-builder-canvas' );
		canvas.setAttribute( 'data-device', device );

		model.rows.forEach( function ( row, index ) {
			canvas.appendChild( renderRow( row, index ) );
		} );

		var addRow = el( 'button', 'button estat-builder-addrow', t.addRow || 'Add a row' );
		addRow.type = 'button';
		addRow.addEventListener( 'click', function () {
			model.rows.push( newRow() );
			commit();
			render();
		} );
		canvas.appendChild( addRow );

		root.appendChild( canvas );

		commit();
		refreshPreview();
	}

	/* ------------------------------------------------------------- Drag */

	function initDrag() {
		root.addEventListener( 'dragstart', function ( event ) {
			var field = event.target.closest ? event.target.closest( '.estat-builder-field' ) : null;
			if ( ! field || ! event.dataTransfer ) {
				return;
			}
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData( 'text/plain', field.getAttribute( 'data-field' ) || '' );
		} );
	}

	/* ------------------------------------------------------------ Setup */

	function initPalette() {
		var palette = document.getElementById( 'estat-field-palette' );
		if ( ! palette ) {
			return;
		}
		palette.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.estat-add-field' );
			if ( ! button ) {
				return;
			}
			event.preventDefault();
			ensureShape();
			var lastRow = model.rows[ model.rows.length - 1 ];
			var field = newField( button.getAttribute( 'data-type' ) || 'text' );
			lastRow.columns[ lastRow.columns.length - 1 ].fields.push( field );
			selected = field.id;
			commit();
			render();
			var node = document.querySelector( '[data-field="' + field.id + '"]' );
			if ( node ) {
				node.scrollIntoView( { block: 'center' } );
				node.focus();
			}
		} );
	}

	/* --------------------------------------------------- Live preview */

	/**
	 * Show the form roughly as a visitor will see it.
	 *
	 * This is a picture, not a working form: the boxes are disabled so nobody
	 * can type into the preview and think they have filled the real thing in.
	 */
	function refreshPreview() {
		var host = document.getElementById( 'estat-form-preview' );

		if ( ! host ) {
			return;
		}

		host.textContent = '';

		var visible = 0;

		host.setAttribute( 'data-device', device );

		model.rows.forEach( function ( row ) {
			var rowNode = el( 'div', 'estat-preview-row' );

			// The preview must obey the same spacing and alignment the
			// visitor will get, or it is not a preview.
			rowNode.style.setProperty( '--cgap', ( device === 'mobile' ? row.gap_mobile : row.gap ) + 'px' );
			rowNode.setAttribute( 'data-align', row.align || 'stretch' );

			if ( row.heading ) {
				host.appendChild( el( 'strong', 'estat-preview-section', row.heading ) );
			}

			row.columns.forEach( function ( column ) {
				var colNode = el( 'div', 'estat-preview-col' );
				var colWidth = column.width;
				if ( device === 'tablet' ) {
					colWidth = column.width_tablet;
				} else if ( device === 'mobile' ) {
					colWidth = column.width_mobile;
				}
				colNode.style.setProperty( '--cw', Number( colWidth || 100 ) + '%' );

				column.fields.forEach( function ( field ) {
					colNode.appendChild( previewField( field, column ) );
					visible++;
				} );

				if ( colNode.childNodes.length ) {
					rowNode.appendChild( colNode );
				}
			} );

			if ( rowNode.childNodes.length ) {
				host.appendChild( rowNode );
			}
		} );

		if ( ! visible ) {
			host.appendChild( el( 'p', 'description', t.previewEmpty || 'Add a field and it will appear here.' ) );
			return;
		}

		var button = el( 'span', 'estat-preview-submit', submitLabel() );
		host.appendChild( button );
	}

	function submitLabel() {
		var box = document.querySelector( '[name="settings[submit_label]"]' );

		return ( box && box.value ) || t.previewSend || 'Send enquiry';
	}

	function previewField( field, column ) {
		var wrap = el( 'div', 'estat-preview-field' );

		// The column already carries its own width, so the field only needs
		// its own. Multiplying the two was what made narrow columns collapse.
		var fieldWidth = field.width;
		if ( device === 'tablet' ) {
			fieldWidth = field.width_tablet;
		} else if ( device === 'mobile' ) {
			fieldWidth = field.width_mobile;
		}
		wrap.style.width = Math.min( 100, Number( fieldWidth || 100 ) ) + '%';
		wrap.setAttribute( 'data-size', field.size || 'md' );

		if ( field.visible && field.visible[ device ] === false ) {
			wrap.classList.add( 'is-hidden-here' );
		}

		if ( field.condition && field.condition.field ) {
			wrap.classList.add( 'is-conditional' );
		}

		if ( 'heading' === field.type ) {
			wrap.appendChild( el( 'strong', 'estat-preview-heading', field.label || '' ) );
			return wrap;
		}

		if ( 'paragraph' === field.type ) {
			wrap.appendChild( el( 'p', 'estat-preview-text', field.label || '' ) );
			return wrap;
		}

		if ( 'hidden' === field.type ) {
			wrap.appendChild( el( 'span', 'estat-preview-hidden', ( t.previewHidden || 'Hidden:' ) + ' ' + ( field.label || field.id ) ) );
			return wrap;
		}

		var label = el( 'span', 'estat-preview-label', field.label || typeLabel( field.type ) );

		if ( field.required ) {
			label.appendChild( el( 'span', 'estat-preview-required', ' *' ) );
		}

		wrap.appendChild( label );

		var control;

		if ( 'textarea' === field.type || 'message' === field.type || 'address' === field.type ) {
			control = el( 'textarea' );
			// Show the real number of lines, capped so the preview panel
			// does not become taller than the panel it sits in.
			control.rows = Math.min( 8, Math.max( 2, Number( field.rows || 4 ) ) );
		} else if ( 'checkbox' === field.type || 'consent' === field.type ) {
			control = el( 'input' );
			control.type = 'checkbox';
		} else if ( hasOptions( field.type ) || 'select' === field.type || 'property_type' === field.type || 'locality' === field.type || 'budget' === field.type ) {
			control = el( 'select' );
			var choices = field.options || [];

			if ( ! choices.length ) {
				var placeholderOption = el( 'option', null, t.previewChoices || 'Your choices appear here' );
				control.appendChild( placeholderOption );
			} else {
				choices.slice( 0, 8 ).forEach( function ( choice ) {
					control.appendChild( el( 'option', null, choice.label || choice ) );
				} );
			}
		} else {
			control = el( 'input' );
			control.type = 'text';
		}

		control.disabled = true;
		control.placeholder = field.placeholder || '';
		wrap.appendChild( control );

		if ( field.help ) {
			wrap.appendChild( el( 'span', 'estat-preview-help', field.help ) );
		}

		return wrap;
	}

	/* ------------------------------------------- Searching the palette */

	function initPaletteSearch() {
		var search = document.getElementById( 'estat-palette-search' );
		var more = document.querySelector( '.estat-palette-more' );

		if ( ! search || ! more ) {
			return;
		}

		var empty = more.querySelector( '.estat-palette-empty' );
		var groups = Array.prototype.slice.call( more.querySelectorAll( '.estat-palette-group' ) );

		search.addEventListener( 'input', function () {
			var term = search.value.trim().toLowerCase();
			var anyShown = false;

			groups.forEach( function ( group ) {
				var shownHere = 0;

				Array.prototype.forEach.call( group.querySelectorAll( '.estat-palette-chip' ), function ( chip ) {
					var label = ( chip.getAttribute( 'data-label' ) || chip.textContent || '' ).toLowerCase();
					var match = '' === term || label.indexOf( term ) !== -1;

					chip.hidden = ! match;

					if ( match ) {
						shownHere++;
					}
				} );

				// A group heading with nothing under it is just noise.
				group.hidden = 0 === shownHere;

				if ( shownHere ) {
					anyShown = true;
				}
			} );

			if ( empty ) {
				empty.hidden = anyShown;
			}
		} );
	}

	/* ------------------------------------------------------- The stages */

	/**
	 * Seven tabs across the top. Only one panel is shown at a time, so a
	 * narrow left column never becomes an endless scroll.
	 *
	 * Nothing is unmounted -- every panel stays in the form, just hidden --
	 * so switching tabs can never drop a setting that has been typed.
	 */
	function initStages() {
		var bar = document.querySelector( '.estat-stage-bar' );

		if ( ! bar ) {
			return;
		}

		var tabs = all( '.estat-stage-tab', bar );
		var panels = all( '.estat-stage' );

		function show( key ) {
			tabs.forEach( function ( tab ) {
				var on = tab.getAttribute( 'data-stage' ) === key;
				tab.classList.toggle( 'is-active', on );
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );

			panels.forEach( function ( panel ) {
				if ( panel.getAttribute( 'data-stage' ) === key ) {
					panel.removeAttribute( 'hidden' );
				} else {
					panel.setAttribute( 'hidden', 'hidden' );
				}
			} );

			// The canvas is only relevant while shaping the form.
			var canvas = document.querySelector( '.estat-studio-canvas' );
			if ( canvas ) {
				canvas.classList.toggle( 'is-dimmed', key === 'backend' || key === 'done' );
			}
		}

		bar.addEventListener( 'click', function ( event ) {
			var tab = event.target.closest( '.estat-stage-tab' );
			if ( ! tab ) {
				return;
			}
			event.preventDefault();
			show( tab.getAttribute( 'data-stage' ) || 'container' );
		} );

		// Left and right arrows move between tabs, as a tab strip should.
		bar.addEventListener( 'keydown', function ( event ) {
			if ( event.key !== 'ArrowRight' && event.key !== 'ArrowLeft' ) {
				return;
			}
			var current = tabs.indexOf( document.activeElement );
			if ( current === -1 ) {
				return;
			}
			event.preventDefault();
			var step = event.key === 'ArrowRight' ? 1 : -1;
			var next = tabs[ ( current + step + tabs.length ) % tabs.length ];
			next.focus();
			show( next.getAttribute( 'data-stage' ) || 'container' );
		} );
	}

	/* ------------------------------------------------------ The styling */

	/**
	 * Wire every styling control to the live preview.
	 *
	 * Styling only ever writes CSS custom properties onto the preview, so it
	 * can never touch a field, a label or where a submission goes.
	 */
	function initStyling() {
		var host = document.getElementById( 'estat-form-preview' );

		if ( ! host ) {
			return;
		}

		// Sliders: show the number and restyle as it moves.
		all( 'input[type="range"].estat-style-input' ).forEach( function ( range ) {
			var out = range.parentNode ? range.parentNode.querySelector( '.estat-style-value' ) : null;
			range.addEventListener( 'input', function () {
				if ( out ) {
					out.textContent = range.value + ( range.getAttribute( 'data-unit' ) || '' );
				}
				applyStyle();
			} );
		} );

		all( 'select.estat-style-input' ).forEach( function ( select ) {
			select.addEventListener( 'change', applyStyle );
		} );

		// Colours: a tick box decides whether the colour is used at all, so
		// "no colour" stays a real choice and the theme keeps control.
		all( '.estat-style-toggle' ).forEach( function ( toggle ) {
			var key = toggle.getAttribute( 'data-style' );
			var picker = document.querySelector( 'input.estat-style-colour[data-style="' + key + '"]' );
			var hidden = document.querySelector( 'input.estat-style-input[data-style="' + key + '"]' );

			function sync() {
				if ( ! picker || ! hidden ) {
					return;
				}
				picker.disabled = ! toggle.checked;
				hidden.value = toggle.checked ? picker.value : '';
				applyStyle();
			}

			toggle.addEventListener( 'change', sync );
			if ( picker ) {
				picker.addEventListener( 'input', sync );
			}
		} );

		applyStyle();
	}

	/**
	 * Read every styling control and paint the preview with it.
	 */
	function applyStyle() {
		var host = document.getElementById( 'estat-form-preview' );

		if ( ! host ) {
			return;
		}

		var numbers = {
			label_size: '--estat-f-label-size',
			input_size: '--estat-f-font',
			radius: '--estat-f-radius',
			border_width: '--estat-f-border',
			input_pad_y: '--estat-f-pad-y',
			input_pad_x: '--estat-f-pad-x',
			field_gap: '--estat-f-gap',
			button_radius: '--estat-f-btn-radius',
			button_pad_y: '--estat-f-btn-pad-y',
			button_pad_x: '--estat-f-btn-pad-x',
			button_size: '--estat-f-btn-size'
		};

		var colours = {
			label_colour: '--estat-f-label-ink',
			input_colour: '--estat-f-ink',
			input_bg: '--estat-f-field-bg',
			border_colour: '--estat-f-line',
			focus_colour: '--estat-f-accent',
			help_colour: '--estat-f-muted',
			error_colour: '--estat-f-danger',
			button_bg: '--estat-f-btn-bg',
			button_text: '--estat-f-btn-ink',
			button_hover_bg: '--estat-f-btn-hover'
		};

		Object.keys( numbers ).forEach( function ( key ) {
			var box = document.querySelector( '.estat-style-input[data-style="' + key + '"]' );
			if ( box && box.value !== '' ) {
				host.style.setProperty( numbers[ key ], Number( box.value ) + 'px' );
			}
		} );

		Object.keys( colours ).forEach( function ( key ) {
			var box = document.querySelector( 'input.estat-style-input[data-style="' + key + '"]' );
			if ( ! box ) {
				return;
			}
			if ( box.value ) {
				host.style.setProperty( colours[ key ], box.value );
			} else {
				host.style.removeProperty( colours[ key ] );
			}
		} );

		var weight = document.querySelector( '.estat-style-input[data-style="label_weight"]' );
		if ( weight ) {
			host.style.setProperty( '--estat-f-label-weight', weight.value );
		}

		var font = document.querySelector( '.estat-style-input[data-style="font"]' );
		if ( font ) {
			host.setAttribute( 'data-font', font.value );
		}

		var align = document.querySelector( '.estat-style-input[data-style="button_align"]' );
		var width = document.querySelector( '.estat-style-input[data-style="button_width"]' );
		host.setAttribute( 'data-btn-align', align ? align.value : 'left' );
		host.setAttribute( 'data-btn-width', width ? width.value : 'auto' );
	}

	function start() {
		root = document.getElementById( 'estat-builder' );
		input = document.getElementById( 'estat-definition' );
		if ( ! root ) {
			return;
		}

		try {
			model = JSON.parse( root.getAttribute( 'data-definition' ) || '{}' );
		} catch ( error ) {
			model = { rows: [] };
		}
		if ( ! model || typeof model !== 'object' ) {
			model = { rows: [] };
		}

		ensureShape();
		render();
		initDrag();
		initPalette();
		initPaletteSearch();
		initStages();
		initStyling();
		refreshPreview();

		// The preview shows the button's wording, so follow it as it is typed.
		var submitBox = document.querySelector( '[name="settings[submit_label]"]' );
		if ( submitBox ) {
			submitBox.addEventListener( 'input', refreshPreview );
		}
	}

	if ( document.readyState !== 'loading' ) {
		start();
	} else {
		document.addEventListener( 'DOMContentLoaded', start );
	}
} )( window, document );
