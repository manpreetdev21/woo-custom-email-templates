/**
 * The visual builder, for templates and reusable components alike.
 *
 * The canvas shows a schematic, reorderable list of blocks (icon, label,
 * short summary) rather than a live WYSIWYG render — duplicating the PHP
 * table-based email renderer in JS would double the rendering logic to
 * maintain. The real, pixel-accurate render is always one click away via
 * the Preview button, which asks the server (TemplateRenderer, the same
 * code that builds the real outgoing email) for the HTML.
 *
 * @package Woo_Custom_Email_Templates
 */
( function ( $ ) {
	'use strict';

	var boot = JSON.parse( $( '#wcem-editor' ).attr( 'data-boot' ) );
	var i18n = wcem.i18n;

	var state = {
		id: boot.id,
		name: boot.name,
		description: boot.description,
		status: boot.status,
		subject: boot.subject,
		preview_text: boot.preview_text,
		blocks: boot.blocks,
		styles: boot.styles,
	};

	var selectedId = state.blocks.length ? state.blocks[ 0 ].id : null;
	var dirty      = false;

	/** Field schema per block type — mirrors Blocks::defaults(). */
	var FIELDS = {
		header: [
			{ key: 'logo_id', label: 'Logo', type: 'media' },
			{ key: 'logo_width', label: 'Logo Width (px)', type: 'number' },
			{ key: 'align', label: 'Alignment', type: 'select', options: [ 'left', 'center', 'right' ] },
			{ key: 'show_name', label: 'Show store name when there is no logo', type: 'checkbox' },
			{ key: 'bg_color', label: 'Background (blank = default)', type: 'color' },
		],
		columns: [
			{ key: 'count', label: 'Number of Columns', type: 'select', options: [ '2', '3' ] },
			{ key: 'col1', label: 'Column 1', type: 'textarea' },
			{ key: 'col2', label: 'Column 2', type: 'textarea' },
			{ key: 'col3', label: 'Column 3 (used when 3 columns)', type: 'textarea' },
			{ key: 'gap', label: 'Gap (px)', type: 'number' },
			{ key: 'align', label: 'Alignment', type: 'select', options: [ 'left', 'center', 'right' ] },
		],
		heading: [
			{ key: 'text', label: 'Text', type: 'text' },
			{ key: 'tag', label: 'HTML Tag', type: 'select', options: [ 'h1', 'h2', 'h3' ] },
			{ key: 'align', label: 'Alignment', type: 'select', options: [ 'left', 'center', 'right' ] },
			{ key: 'size', label: 'Font Size (px, blank = default)', type: 'number' },
			{ key: 'color', label: 'Color (blank = default)', type: 'color' },
		],
		text: [
			{ key: 'content', label: 'Content', type: 'textarea' },
			{ key: 'align', label: 'Alignment', type: 'select', options: [ 'left', 'center', 'right' ] },
		],
		image: [
			{ key: 'media_id', label: 'Image', type: 'media' },
			{ key: 'url', label: 'Or image URL', type: 'text' },
			{ key: 'width', label: 'Width (px)', type: 'number' },
			{ key: 'align', label: 'Alignment', type: 'select', options: [ 'left', 'center', 'right' ] },
			{ key: 'link', label: 'Link URL', type: 'text' },
			{ key: 'alt', label: 'Alt Text', type: 'text' },
		],
		button: [
			{ key: 'text', label: 'Button Text', type: 'text' },
			{ key: 'url', label: 'URL', type: 'text' },
			{ key: 'align', label: 'Alignment', type: 'select', options: [ 'left', 'center', 'right' ] },
			{ key: 'full_width', label: 'Full Width', type: 'checkbox' },
			{ key: 'bg_color', label: 'Background (blank = default)', type: 'color' },
			{ key: 'text_color', label: 'Text Color (blank = default)', type: 'color' },
		],
		divider: [
			{ key: 'color', label: 'Color', type: 'color' },
			{ key: 'thickness', label: 'Thickness (px)', type: 'number' },
		],
		spacer: [
			{ key: 'height', label: 'Height (px)', type: 'number' },
		],
		html: [
			{ key: 'content', label: 'HTML', type: 'textarea', note: 'Custom HTML can break rendering in some email clients. Table-based markup with inline styles is safest.' },
		],
		order_details: [
			{ key: 'title', label: 'Title', type: 'text' },
			{ key: 'show_sku', label: 'Show SKU', type: 'checkbox' },
		],
		order_totals: [
			{ key: 'title', label: 'Title', type: 'text' },
		],
		customer_details: [
			{ key: 'title', label: 'Title', type: 'text' },
			{ key: 'show_billing', label: 'Show Billing Address', type: 'checkbox' },
			{ key: 'show_shipping', label: 'Show Shipping Address', type: 'checkbox' },
		],
		downloads: [
			{ key: 'title', label: 'Title', type: 'text' },
			{ key: 'link_text', label: 'Link Text', type: 'text' },
			{ key: 'show_expiry', label: 'Show expiry date', type: 'checkbox', note: 'Renders nothing when the order has no downloadable products, so it is safe to leave in every template.' },
		],
		store_info: [
			{ key: 'title', label: 'Title (optional)', type: 'text' },
			{ key: 'show_address', label: 'Show store address', type: 'checkbox', note: 'Taken from WooCommerce → Settings → General.' },
			{ key: 'phone', label: 'Phone', type: 'text' },
			{ key: 'email', label: 'Email', type: 'text' },
			{ key: 'align', label: 'Alignment', type: 'select', options: [ 'left', 'center', 'right' ] },
		],
		footer: [
			{ key: 'text', label: 'Footer Text', type: 'textarea' },
			{ key: 'show_social', label: 'Show Social Icons', type: 'checkbox' },
			{ key: 'facebook', label: 'Facebook URL', type: 'text' },
			{ key: 'instagram', label: 'Instagram URL', type: 'text' },
			{ key: 'twitter', label: 'X / Twitter URL', type: 'text' },
			{ key: 'linkedin', label: 'LinkedIn URL', type: 'text' },
			{ key: 'youtube', label: 'YouTube URL', type: 'text' },
			{ key: 'pinterest', label: 'Pinterest URL', type: 'text' },
			{ key: 'bg_color', label: 'Background (blank = default)', type: 'color' },
			{ key: 'text_color', label: 'Text Color (blank = default)', type: 'color' },
		],
	};

	var STYLE_FIELDS = [
		{ key: 'width', label: 'Email Width (px)', type: 'number' },
		{ key: 'padding', label: 'Inner Padding (px)', type: 'number' },
		{ key: 'radius', label: 'Corner Radius (px)', type: 'number' },
		{ key: 'bg_color', label: 'Page Background', type: 'color' },
		{ key: 'content_bg', label: 'Content Background', type: 'color' },
		{ key: 'text_color', label: 'Text Color', type: 'color' },
		{ key: 'heading_color', label: 'Heading Color', type: 'color' },
		{ key: 'link_color', label: 'Link Color', type: 'color' },
		{ key: 'button_bg', label: 'Button Background', type: 'color' },
		{ key: 'button_text', label: 'Button Text Color', type: 'color' },
		{ key: 'footer_bg', label: 'Footer Background', type: 'color' },
		{ key: 'footer_text', label: 'Footer Text Color', type: 'color' },
		{ key: 'font_family', label: 'Font Family', type: 'text' },
		{ key: 'body_size', label: 'Body Font Size (px)', type: 'number' },
		{ key: 'heading_size', label: 'Heading Font Size (px)', type: 'number' },
		{ key: 'button_size', label: 'Button Font Size (px)', type: 'number' },
		{ key: 'line_height', label: 'Line Height', type: 'number', step: '0.1' },
	];

	function esc( text ) {
		return String( text == null ? '' : text )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	/* ---------------------------------------------------------------------
	 * Undo / redo
	 *
	 * Each entry is a JSON snapshot of the whole editable state rather than
	 * an inverse operation. The state is a few KB, so a bounded stack of
	 * snapshots costs nothing and removes the need to write — and keep
	 * correct — an undo for every kind of mutation.
	 * ------------------------------------------------------------------ */

	var history = { stack: [], index: -1, max: 50 };
	var historyTimer = null;

	function snapshot() {
		return JSON.stringify( {
			blocks: state.blocks,
			styles: state.styles,
			name: state.name,
			description: state.description,
			subject: state.subject,
			preview_text: state.preview_text,
			status: state.status,
		} );
	}

	function pushHistory() {
		var snap = snapshot();

		if ( history.stack[ history.index ] === snap ) {
			return; // Nothing actually changed.
		}

		// Any redo branch is discarded once a new edit lands on top of it.
		history.stack = history.stack.slice( 0, history.index + 1 );
		history.stack.push( snap );

		if ( history.stack.length > history.max ) {
			history.stack.shift();
		}

		history.index = history.stack.length - 1;
		paintHistoryButtons();
	}

	// Typing fires markDirty per keystroke; debouncing keeps a typed word as
	// one undo step instead of one per character.
	function scheduleHistory() {
		clearTimeout( historyTimer );
		historyTimer = setTimeout( pushHistory, 400 );
	}

	function flushHistory() {
		clearTimeout( historyTimer );
		pushHistory();
	}

	function applySnapshot( snap ) {
		var s = JSON.parse( snap );

		state.blocks       = s.blocks;
		state.styles       = s.styles;
		state.name         = s.name;
		state.description  = s.description;
		state.subject      = s.subject;
		state.preview_text = s.preview_text;
		state.status       = s.status;

		// Toolbar and Email-tab inputs are plain DOM, so push the restored
		// values back into them; the two settings panels re-render from state.
		$( '#wcem-f-name' ).val( state.name );
		$( '#wcem-f-status' ).val( state.status );
		$( '#wcem-f-description' ).val( state.description );
		$( '#wcem-f-subject' ).val( state.subject );
		$( '#wcem-f-preview-text' ).val( state.preview_text );

		if ( ! findBlock( selectedId ) ) {
			selectedId = state.blocks.length ? state.blocks[ 0 ].id : null;
		}

		renderCanvas();
		renderStylesPanel();
		renderBlockSettings();
		paintHistoryButtons();
	}

	function undo() {
		flushHistory();

		if ( history.index <= 0 ) {
			return;
		}

		history.index--;
		dirty = true;
		applySnapshot( history.stack[ history.index ] );
	}

	function redo() {
		if ( history.index >= history.stack.length - 1 ) {
			return;
		}

		history.index++;
		dirty = true;
		applySnapshot( history.stack[ history.index ] );
	}

	function paintHistoryButtons() {
		$( '#wcem-btn-undo' ).prop( 'disabled', history.index <= 0 );
		$( '#wcem-btn-redo' ).prop( 'disabled', history.index >= history.stack.length - 1 );
	}

	$( document ).on( 'click', '#wcem-btn-undo', undo );
	$( document ).on( 'click', '#wcem-btn-redo', redo );

	$( document ).on( 'keydown', function ( e ) {
		if ( ! ( e.ctrlKey || e.metaKey ) || 'z' !== e.key.toLowerCase() ) {
			return;
		}

		/*
		 * Ours always wins, including inside a text field: a snapshot carries
		 * the field values too, so letting the browser's native text undo run
		 * here would drift the DOM out of sync with state.
		 */
		e.preventDefault();

		if ( e.shiftKey ) {
			redo();
		} else {
			undo();
		}
	} );

	function markDirty() {
		dirty = true;
		scheduleHistory();
	}

	function newId() {
		return 'b' + Math.random().toString( 36 ).slice( 2, 10 );
	}

	function findBlock( id ) {
		return state.blocks.find( function ( b ) {
			return b.id === id;
		} );
	}

	function blockSummary( block ) {
		var s = block.settings || {};

		switch ( block.type ) {
			case 'heading':
			case 'button':
				return s.text || '';
			case 'text':
			case 'footer':
				return $( '<div>' ).html( s.text || s.content || '' ).text().slice( 0, 60 );
			case 'columns':
				return s.count + ' × ' + $( '<div>' ).html( s.col1 || '' ).text().slice( 0, 40 );
			case 'image':
				return s.alt || s.url || ( s.media_id ? '#' + s.media_id : '' );
			default:
				return '';
		}
	}

	/* ---------------------------------------------------------------------
	 * Palette
	 * ------------------------------------------------------------------ */

	function renderPalette() {
		var groups  = { layout: i18n.layout, content: i18n.content, woocommerce: i18n.woocommerce };
		var byGroup = {};

		Object.keys( boot.registry ).forEach( function ( type ) {
			var g = boot.registry[ type ][ 2 ];
			byGroup[ g ] = byGroup[ g ] || [];
			byGroup[ g ].push( type );
		} );

		var html = '';

		Object.keys( groups ).forEach( function ( g ) {
			if ( ! byGroup[ g ] ) {
				return;
			}

			html += '<div class="wcem-palette__group-label">' + esc( groups[ g ] ) + '</div>';

			byGroup[ g ].forEach( function ( type ) {
				var def = boot.registry[ type ];
				html += '<button type="button" class="wcem-palette__item" data-type="' + esc( type ) + '">' +
					'<span class="dashicons ' + esc( def[ 1 ] ) + '"></span> ' + esc( def[ 0 ] ) +
				'</button>';
			} );
		} );

		$( '#wcem-palette' ).html( html );
	}

	function renderComponents() {
		var $wrap = $( '#wcem-components' );

		if ( ! $wrap.length ) {
			return;
		}

		if ( ! boot.components.length ) {
			$wrap.html( '<p class="wcem-muted">' + esc( i18n.noComponents ) + '</p>' );
			return;
		}

		$wrap.html(
			boot.components.map( function ( component ) {
				return '<button type="button" class="wcem-palette__item" data-component="' + component.id + '">' +
					'<span class="dashicons dashicons-screenoptions"></span> ' + esc( component.name ) +
				'</button>';
			} ).join( '' )
		);
	}

	/**
	 * Expands one component into fresh blocks, each tagged with the
	 * component it came from so re-sync can find them again.
	 *
	 * @param {Object} component { id, name, blocks }.
	 * @return {Array} Blocks ready to splice into state.
	 */
	function expandComponent( component ) {
		return component.blocks.map( function ( block ) {
			var copy  = JSON.parse( JSON.stringify( block ) );
			copy.id     = newId();
			copy.origin = component.id;
			return copy;
		} );
	}

	$( '#wcem-components' ).on( 'click', '[data-component]', function () {
		var id        = $( this ).data( 'component' );
		var component = boot.components.find( function ( c ) {
			return c.id === id;
		} );

		if ( ! component ) {
			return;
		}

		var expanded = expandComponent( component );

		state.blocks = state.blocks.concat( expanded );
		selectedId   = expanded.length ? expanded[ 0 ].id : selectedId;

		markDirty();
		renderCanvas();
		renderBlockSettings();
	} );

	/*
	 * Re-sync replaces every block carrying a component's origin id with
	 * that component's current blocks, at the position of the first one.
	 *
	 * ponytail: instances of the same component are assumed contiguous —
	 * true for anything inserted through the palette. Two separate
	 * insertions of one component merge into a single run on re-sync; give
	 * each instance its own instance id if that ever matters.
	 */
	$( '#wcem-btn-sync' ).on( 'click', function () {
		var origins = state.blocks
			.map( function ( b ) { return b.origin || 0; } )
			.filter( function ( id, index, all ) { return id && all.indexOf( id ) === index; } );

		if ( ! origins.length ) {
			WCEM.toast( i18n.nothingToSync, 'error' );
			return;
		}

		WCEM.confirm( {
			title: i18n.syncTitle,
			body: i18n.syncBody,
			confirmLabel: i18n.sync,
			danger: true,
		} ).then( function ( ok ) {
			if ( ! ok ) {
				return;
			}

			origins.forEach( function ( originId ) {
				var component = boot.components.find( function ( c ) {
					return c.id === originId;
				} );

				if ( ! component ) {
					return;
				}

				var at   = state.blocks.findIndex( function ( b ) { return b.origin === originId; } );
				var rest = state.blocks.filter( function ( b ) { return b.origin !== originId; } );

				rest.splice( at, 0, ...expandComponent( component ) );
				state.blocks = rest;
			} );

			selectedId = state.blocks.length ? state.blocks[ 0 ].id : null;

			markDirty();
			renderCanvas();
			renderBlockSettings();
			WCEM.toast( i18n.synced, 'success' );
		} );
	} );

	/* ---------------------------------------------------------------------
	 * Tag picker
	 * ------------------------------------------------------------------ */

	var lastFocused = null;

	function renderTags() {
		var html = '';

		Object.keys( boot.tagGroups ).forEach( function ( group ) {
			html += '<div class="wcem-tags__group-label">' + esc( group ) + '</div>';

			var tags = boot.tagGroups[ group ];

			Object.keys( tags ).forEach( function ( tag ) {
				html += '<button type="button" class="wcem-tags__item" data-tag="' + esc( tag ) + '">' + esc( tags[ tag ] ) + '</button>';
			} );
		} );

		$( '#wcem-tags' ).html( html );
	}

	$( document ).on( 'focus', '.wcem-settings-panel input[type="text"], .wcem-settings-panel textarea', function () {
		lastFocused = this;
	} );

	$( '#wcem-tags' ).on( 'click', '.wcem-tags__item', function () {
		var tag = '{' + $( this ).data( 'tag' ) + '}';

		if ( ! lastFocused ) {
			WCEM.toast( i18n.copied, 'success' );

			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( tag );
			}

			return;
		}

		var el    = lastFocused;
		var start = el.selectionStart || el.value.length;
		var end   = el.selectionEnd || el.value.length;

		el.value          = el.value.slice( 0, start ) + tag + el.value.slice( end );
		el.selectionStart = start + tag.length;
		el.selectionEnd   = start + tag.length;
		el.focus();

		$( el ).trigger( 'input' );
	} );

	/* ---------------------------------------------------------------------
	 * Canvas
	 * ------------------------------------------------------------------ */

	function renderCanvas() {
		var $canvas = $( '#wcem-canvas' );

		if ( ! state.blocks.length ) {
			$canvas.html( '<div class="wcem-canvas__empty">' + esc( i18n.emptyCanvas ) + '</div>' );
			return;
		}

		var html = '';

		state.blocks.forEach( function ( block ) {
			var def     = boot.registry[ block.type ] || [ block.type, 'dashicons-block-default' ];
			var summary = blockSummary( block );

			html += '<div class="wcem-block' + ( block.id === selectedId ? ' is-selected' : '' ) + '" data-id="' + esc( block.id ) + '" tabindex="0">' +
				'<span class="wcem-block__handle dashicons dashicons-menu"></span>' +
				'<span class="dashicons ' + esc( def[ 1 ] ) + '"></span>' +
				'<span class="wcem-block__label">' + esc( def[ 0 ] ) + '</span>' +
				( block.origin ? '<span class="wcem-block__origin dashicons dashicons-screenoptions" title="' + esc( i18n.components ) + '"></span>' : '' ) +
				'<span class="wcem-block__summary">' + esc( summary ) + '</span>' +
				'<span class="wcem-block__actions">' +
					'<button type="button" class="wcem-js-dup" aria-label="' + esc( i18n.duplicate ) + '" title="' + esc( i18n.duplicate ) + '"><span class="dashicons dashicons-admin-page"></span></button>' +
					'<button type="button" class="wcem-js-del" aria-label="' + esc( i18n.delete ) + '" title="' + esc( i18n.delete ) + '"><span class="dashicons dashicons-trash"></span></button>' +
				'</span>' +
			'</div>';
		} );

		$canvas.html( html );

		if ( $canvas.hasClass( 'ui-sortable' ) ) {
			$canvas.sortable( 'destroy' );
		}

		$canvas.sortable( {
			handle: '.wcem-block__handle',
			axis: 'y',
			update: function () {
				var order = $canvas.find( '.wcem-block' ).map( function () {
					return $( this ).data( 'id' ).toString();
				} ).get();

				state.blocks.sort( function ( a, b ) {
					return order.indexOf( a.id ) - order.indexOf( b.id );
				} );

				markDirty();
			},
		} );
	}

	function selectBlock( id ) {
		selectedId = id;
		$( '#wcem-canvas .wcem-block' ).removeClass( 'is-selected' );
		$( '#wcem-canvas .wcem-block[data-id="' + id + '"]' ).addClass( 'is-selected' );
		setActiveTab( 'block' );
		renderBlockSettings();
	}

	function moveBlock( id, offset ) {
		var from = state.blocks.findIndex( function ( b ) { return b.id === id; } );
		var to   = from + offset;

		if ( from < 0 || to < 0 || to >= state.blocks.length ) {
			return;
		}

		state.blocks.splice( to, 0, state.blocks.splice( from, 1 )[ 0 ] );

		markDirty();
		renderCanvas();
		$( '#wcem-canvas .wcem-block[data-id="' + id + '"]' ).trigger( 'focus' );
	}

	function deleteBlock( id ) {
		state.blocks = state.blocks.filter( function ( b ) {
			return b.id !== id;
		} );

		if ( selectedId === id ) {
			selectedId = state.blocks.length ? state.blocks[ 0 ].id : null;
		}

		markDirty();
		renderCanvas();
		renderBlockSettings();
	}

	function duplicateBlock( id ) {
		var block = findBlock( id );

		if ( ! block ) {
			return;
		}

		var copy = JSON.parse( JSON.stringify( block ) );
		copy.id  = newId();

		state.blocks.splice( state.blocks.findIndex( function ( b ) { return b.id === id; } ) + 1, 0, copy );

		markDirty();
		selectedId = copy.id;
		renderCanvas();
		renderBlockSettings();
	}

	$( '#wcem-canvas' ).on( 'click', '.wcem-block', function ( e ) {
		if ( $( e.target ).closest( '.wcem-block__actions' ).length ) {
			return;
		}

		selectBlock( $( this ).data( 'id' ).toString() );
	} );

	// Keyboard equivalents for the drag handle, so the canvas is usable
	// without a mouse: Alt+Up/Down reorders, Delete removes.
	$( '#wcem-canvas' ).on( 'keydown', '.wcem-block', function ( e ) {
		var id = $( this ).data( 'id' ).toString();

		if ( 'Enter' === e.key || ' ' === e.key ) {
			e.preventDefault();
			selectBlock( id );
		} else if ( e.altKey && 'ArrowUp' === e.key ) {
			e.preventDefault();
			moveBlock( id, -1 );
		} else if ( e.altKey && 'ArrowDown' === e.key ) {
			e.preventDefault();
			moveBlock( id, 1 );
		} else if ( 'Delete' === e.key ) {
			e.preventDefault();
			deleteBlock( id );
		}
	} );

	$( '#wcem-canvas' ).on( 'click', '.wcem-js-del', function ( e ) {
		e.stopPropagation();
		deleteBlock( $( this ).closest( '.wcem-block' ).data( 'id' ).toString() );
	} );

	$( '#wcem-canvas' ).on( 'click', '.wcem-js-dup', function ( e ) {
		e.stopPropagation();
		duplicateBlock( $( this ).closest( '.wcem-block' ).data( 'id' ).toString() );
	} );

	function addBlock( type ) {
		var block = {
			id: newId(),
			type: type,
			origin: 0,
			settings: JSON.parse( JSON.stringify( boot.defaults[ type ] || {} ) ),
		};

		state.blocks.push( block );
		selectedId = block.id;

		markDirty();
		renderCanvas();
		renderBlockSettings();

		var $card = $( '#wcem-canvas .wcem-block[data-id="' + block.id + '"]' );

		if ( $card.length ) {
			$card[ 0 ].scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}
	}

	$( '#wcem-palette' ).on( 'click', '.wcem-palette__item', function () {
		addBlock( $( this ).data( 'type' ) );
	} );

	/* ---------------------------------------------------------------------
	 * Settings panel — block tab
	 * ------------------------------------------------------------------ */

	function fieldControl( field, value ) {
		switch ( field.type ) {
			case 'textarea':
				return '<textarea data-key="' + esc( field.key ) + '" rows="4">' + esc( value ) + '</textarea>';
			case 'select':
				return '<select data-key="' + esc( field.key ) + '">' + field.options.map( function ( opt ) {
					return '<option value="' + esc( opt ) + '"' + ( String( opt ) === String( value ) ? ' selected' : '' ) + '>' + esc( opt ) + '</option>';
				} ).join( '' ) + '</select>';
			case 'checkbox':
				return '<input type="checkbox" data-key="' + esc( field.key ) + '"' + ( value ? ' checked' : '' ) + ' />';
			case 'color':
				return '<div class="wcem-color-field"><input type="color" data-key="' + esc( field.key ) + '" value="' + esc( value || '#ffffff' ) + '" />' +
					'<input type="text" data-key="' + esc( field.key ) + '" data-color-text="1" value="' + esc( value ) + '" placeholder="inherit" /></div>';
			case 'number':
				return '<input type="number" data-key="' + esc( field.key ) + '" value="' + esc( value ) + '"' + ( field.step ? ' step="' + esc( field.step ) + '"' : '' ) + ' />';
			case 'media':
				return '<div class="wcem-media-field" data-key="' + esc( field.key ) + '">' +
					( value ? '<img class="wcem-media-field__preview" src="" alt="" data-attachment="' + esc( value ) + '" />' : '' ) +
					'<button type="button" class="button wcem-js-pick-media">' + esc( value ? i18n.replaceImage : i18n.selectImage ) + '</button>' +
					( value ? ' <button type="button" class="button-link wcem-js-remove-media">' + esc( i18n.removeImage ) + '</button>' : '' ) +
				'</div>';
			default:
				return '<input type="text" data-key="' + esc( field.key ) + '" value="' + esc( value ) + '" />';
		}
	}

	function renderBlockSettings() {
		var $panel = $( '#wcem-tab-block' );
		var block  = selectedId ? findBlock( selectedId ) : null;

		if ( ! block ) {
			$panel.html( '<p class="wcem-muted">' + esc( i18n.selectBlock ) + '</p>' );
			return;
		}

		var fields = FIELDS[ block.type ] || [];
		var html   = '<h4>' + esc( ( boot.registry[ block.type ] || [ block.type ] )[ 0 ] ) + ' ' + esc( i18n.settingsSuffix ) + '</h4>';

		fields.forEach( function ( field ) {
			html += '<label class="wcem-field wcem-field--' + esc( field.type ) + '">' + esc( field.label ) +
				fieldControl( field, block.settings[ field.key ] ) +
				( field.note ? '<span class="wcem-field__note">' + esc( field.note ) + '</span>' : '' ) +
			'</label>';
		} );

		$panel.html( html );

		$panel.find( '.wcem-media-field__preview[data-attachment]' ).each( function () {
			resolveAttachmentThumb( $( this ) );
		} );
	}

	function resolveAttachmentThumb( $img ) {
		var id = $img.data( 'attachment' );

		if ( ! id || ! window.wp || ! wp.media ) {
			return;
		}

		var attachment = wp.media.attachment( id );

		attachment.fetch().then( function () {
			var sizes = attachment.get( 'sizes' );
			$img.attr( 'src', sizes && sizes.thumbnail ? sizes.thumbnail.url : attachment.get( 'url' ) );
		} );
	}

	$( '#wcem-tab-block' ).on( 'input change', '[data-key]', function () {
		var block = findBlock( selectedId );

		if ( ! block ) {
			return;
		}

		var $el = $( this );
		var key = $el.data( 'key' );
		var val = 'checkbox' === $el.attr( 'type' ) ? ( $el.is( ':checked' ) ? 1 : 0 ) : $el.val();

		block.settings[ key ] = val;

		// A color field's text twin mirrors the swatch, and vice versa.
		if ( $el.is( '[type="color"]' ) ) {
			$el.siblings( '[data-color-text]' ).val( val );
		} else if ( $el.is( '[data-color-text]' ) && val ) {
			$el.siblings( '[type="color"]' ).val( val );
		}

		markDirty();

		var $summary = $( '#wcem-canvas .wcem-block[data-id="' + selectedId + '"] .wcem-block__summary' );

		if ( $summary.length ) {
			$summary.text( blockSummary( block ) );
		}
	} );

	$( '#wcem-tab-block' ).on( 'click', '.wcem-js-pick-media', function () {
		var key   = $( this ).closest( '[data-key]' ).data( 'key' );
		var block = findBlock( selectedId );
		var frame = wp.media( { title: i18n.selectImage, multiple: false } );

		frame.on( 'select', function () {
			block.settings[ key ] = frame.state().get( 'selection' ).first().toJSON().id;
			markDirty();
			renderBlockSettings();
		} );

		frame.open();
	} );

	$( '#wcem-tab-block' ).on( 'click', '.wcem-js-remove-media', function () {
		var key   = $( this ).closest( '[data-key]' ).data( 'key' );
		var block = findBlock( selectedId );

		block.settings[ key ] = 0;

		markDirty();
		renderBlockSettings();
	} );

	/* ---------------------------------------------------------------------
	 * Settings panel — design (global styles) tab
	 * ------------------------------------------------------------------ */

	function renderStylesPanel() {
		var html = '<h4>' + esc( i18n.globalDesign ) + '</h4>';

		STYLE_FIELDS.forEach( function ( field ) {
			html += '<label class="wcem-field wcem-field--' + esc( field.type ) + '">' + esc( field.label ) +
				fieldControl( Object.assign( {}, field, { key: 'style_' + field.key } ), state.styles[ field.key ] ) +
			'</label>';
		} );

		$( '#wcem-tab-styles' ).html( html );
	}

	$( '#wcem-tab-styles' ).on( 'input change', '[data-key]', function () {
		var $el = $( this );
		var key = $el.data( 'key' ).replace( /^style_/, '' );

		state.styles[ key ] = $el.val();

		if ( $el.is( '[type="color"]' ) ) {
			$el.siblings( '[data-color-text]' ).val( $el.val() );
		} else if ( $el.is( '[data-color-text]' ) && $el.val() ) {
			$el.siblings( '[type="color"]' ).val( $el.val() );
		}

		markDirty();
	} );

	/* ---------------------------------------------------------------------
	 * Settings tabs
	 * ------------------------------------------------------------------ */

	function setActiveTab( tab ) {
		$( '.wcem-settings-tabs button' ).removeClass( 'active' ).attr( 'aria-selected', 'false' );
		$( '.wcem-settings-tabs button[data-tab="' + tab + '"]' ).addClass( 'active' ).attr( 'aria-selected', 'true' );
		$( '.wcem-settings-panel' ).removeClass( 'wcem-settings-panel--active' );
		$( '#wcem-tab-' + tab ).addClass( 'wcem-settings-panel--active' );
	}

	$( '.wcem-settings-tabs' ).on( 'click', 'button', function () {
		setActiveTab( $( this ).data( 'tab' ) );
	} );

	/* ---------------------------------------------------------------------
	 * Toolbar: meta fields, device toggle
	 * ------------------------------------------------------------------ */

	$( '#wcem-f-name' ).on( 'input', function () {
		state.name = $( this ).val();
		markDirty();
	} );

	$( '#wcem-f-status' ).on( 'change', function () {
		state.status = $( this ).val();
		markDirty();
	} );

	$( '#wcem-f-description, #wcem-f-subject, #wcem-f-preview-text' ).on( 'input', function () {
		var map = {
			'wcem-f-description': 'description',
			'wcem-f-subject': 'subject',
			'wcem-f-preview-text': 'preview_text',
		};

		state[ map[ this.id ] ] = this.value;
		markDirty();
	} );

	$( '.wcem-device' ).on( 'click', function () {
		var device = $( this ).data( 'device' );

		$( '.wcem-device' ).removeClass( 'active' ).attr( 'aria-pressed', 'false' );
		$( this ).addClass( 'active' ).attr( 'aria-pressed', 'true' );

		$( '#wcem-preview-frame' )
			.removeClass( 'wcem-preview--tablet wcem-preview--mobile' )
			.addClass( 'desktop' === device ? '' : 'wcem-preview--' + device );
	} );

	/* ---------------------------------------------------------------------
	 * Preview + test email
	 * ------------------------------------------------------------------ */

	function currentPayload() {
		return {
			kind: boot.kind,
			subject: state.subject,
			preview_text: state.preview_text,
			blocks: JSON.stringify( state.blocks ),
			styles: JSON.stringify( state.styles ),
		};
	}

	function runPreview() {
		WCEM.post( 'preview_template', Object.assign( currentPayload(), {
			order_id: $( '#wcem-preview-order' ).val() || 0,
		} ) ).done( function ( res ) {
			if ( res.success ) {
				$( '#wcem-preview-frame' ).attr( 'srcdoc', res.data.html );
			} else {
				WCEM.report( res );
			}
		} );
	}

	function openModal( id ) {
		$( '#' + id ).removeAttr( 'hidden' ).addClass( 'is-visible' );
	}

	$( '#wcem-btn-preview' ).on( 'click', function () {
		openModal( 'wcem-modal-preview' );
		runPreview();
	} );

	$( '#wcem-preview-order' ).on( 'change', runPreview );

	$( '#wcem-btn-test' ).on( 'click', function () {
		openModal( 'wcem-modal-test' );
	} );

	$( '.wcem-modal' ).on( 'click', '[data-close]', function () {
		$( this ).closest( '.wcem-modal' ).removeClass( 'is-visible' ).attr( 'hidden', true );
	} );

	$( document ).on( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			$( '.wcem-modal.is-visible' ).removeClass( 'is-visible' ).attr( 'hidden', true );
		}
	} );

	$( '#wcem-btn-send-test' ).on( 'click', function () {
		var $btn = $( this ).prop( 'disabled', true );

		WCEM.post( 'send_test_email', Object.assign( currentPayload(), {
			recipient: $( '#wcem-test-email' ).val(),
			order_id: $( '#wcem-preview-order' ).val() || 0,
		} ) ).done( function ( res ) {
			WCEM.report( res, i18n.testSent );
		} ).always( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	/* ---------------------------------------------------------------------
	 * Versions
	 * ------------------------------------------------------------------ */

	$( '#wcem-tab-versions' ).on( 'click', '.wcem-js-restore', function () {
		var revisionId = $( this ).data( 'revision' );

		WCEM.confirm( {
			title: i18n.restoreTitle,
			body: i18n.restoreBody,
			confirmLabel: i18n.restore,
		} ).then( function ( ok ) {
			if ( ! ok ) {
				return;
			}

			WCEM.post( 'restore_version', { revision_id: revisionId } ).done( function ( res ) {
				if ( WCEM.report( res ) ) {
					dirty = false;
					window.location = res.data.editUrl;
				}
			} );
		} );
	} );

	/* ---------------------------------------------------------------------
	 * Save
	 * ------------------------------------------------------------------ */

	$( '#wcem-btn-save' ).on( 'click', function () {
		var $btn = $( this ).prop( 'disabled', true ).text( i18n.saving );

		WCEM.post( 'save_template', {
			kind: boot.kind,
			id: state.id,
			name: state.name,
			description: state.description,
			status: state.status,
			subject: state.subject,
			preview_text: state.preview_text,
			blocks: JSON.stringify( state.blocks ),
			styles: JSON.stringify( state.styles ),
		} ).done( function ( res ) {
			if ( WCEM.report( res ) ) {
				dirty = false;

				if ( ! state.id ) {
					state.id = res.data.id;
					window.history.replaceState( {}, '', res.data.editUrl );
				}
			}
		} ).always( function () {
			$btn.prop( 'disabled', false ).text( i18n.save );
		} );
	} );

	window.addEventListener( 'beforeunload', function ( e ) {
		if ( dirty ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );

	/* ---------------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------------ */

	renderPalette();
	renderComponents();
	renderTags();
	renderCanvas();
	renderStylesPanel();
	renderBlockSettings();

	// Seed the history with the state as loaded, so the first undo returns
	// here rather than to an empty stack.
	pushHistory();
} )( jQuery );
