/**
 * Shared admin helpers (toasts, confirm modal, AJAX) plus the dashboard,
 * templates list, components list and assignments page interactions.
 *
 * @package Woo_Custom_Email_Templates
 */
( function ( $ ) {
	'use strict';

	/**
	 * POSTs to admin-ajax with the plugin nonce attached.
	 *
	 * @param {string} action wp_ajax_wcem_{action} suffix.
	 * @param {Object} data   Extra fields.
	 * @return {jQuery.Deferred}
	 */
	function post( action, data ) {
		return $.post( wcem.ajaxUrl, Object.assign( { action: 'wcem_' + action, nonce: wcem.nonce }, data ) );
	}

	/**
	 * Shows a floating toast notification.
	 *
	 * @param {string} message Text to show.
	 * @param {string} type    'success' | 'error'.
	 */
	function toast( message, type ) {
		var $wrap = $( '#wcem-toasts' );

		if ( ! $wrap.length ) {
			$wrap = $( '<div id="wcem-toasts" class="wcem-toasts" role="status" aria-live="polite"></div>' ).appendTo( 'body' );
		}

		var $toast = $( '<div class="wcem-toast wcem-toast--' + ( type || 'success' ) + '"></div>' ).text( message );
		$wrap.append( $toast );

		requestAnimationFrame( function () {
			$toast.addClass( 'is-visible' );
		} );

		setTimeout( function () {
			$toast.removeClass( 'is-visible' );
			setTimeout( function () {
				$toast.remove();
			}, 250 );
		}, 3500 );
	}

	/**
	 * Reports an AJAX response as a toast, success or failure.
	 *
	 * @param {Object} res      jQuery response payload.
	 * @param {string} fallback Message to show when the server sent none.
	 * @return {boolean} Whether the call succeeded.
	 */
	function report( res, fallback ) {
		var message = ( res.data && res.data.message ) || fallback || wcem.i18n.error;
		toast( res.success ? message : ( ( res.data && res.data.message ) || wcem.i18n.error ), res.success ? 'success' : 'error' );
		return !! res.success;
	}

	/**
	 * A promise-based confirm dialog styled to match the plugin, used for
	 * every destructive action instead of the browser's native confirm().
	 *
	 * @param {Object} opts { title, body, confirmLabel, danger }.
	 * @return {Promise<boolean>}
	 */
	function confirmModal( opts ) {
		return new Promise( function ( resolve ) {
			var $overlay = $(
				'<div class="wcem-modal wcem-modal--confirm">' +
					'<div class="wcem-modal__panel wcem-modal__panel--small" role="dialog" aria-modal="true">' +
						'<div class="wcem-modal__head"><strong></strong></div>' +
						'<div class="wcem-modal__body"><p></p></div>' +
						'<div class="wcem-modal__foot">' +
							'<button type="button" class="button" data-cancel></button>' +
							'<button type="button" class="button button-primary" data-confirm></button>' +
						'</div>' +
					'</div>' +
				'</div>'
			);

			$overlay.find( '.wcem-modal__head strong' ).text( opts.title || '' );
			$overlay.find( '.wcem-modal__body p' ).text( opts.body || '' );
			$overlay.find( '[data-cancel]' ).text( wcem.i18n.cancel );
			$overlay.find( '[data-confirm]' ).text( opts.confirmLabel || wcem.i18n.confirmDelete );

			if ( opts.danger ) {
				$overlay.find( '[data-confirm]' ).addClass( 'wcem-button-danger' );
			}

			$( 'body' ).append( $overlay );
			requestAnimationFrame( function () {
				$overlay.addClass( 'is-visible' );
				$overlay.find( '[data-confirm]' ).trigger( 'focus' );
			} );

			function close( result ) {
				$overlay.removeClass( 'is-visible' );
				setTimeout( function () {
					$overlay.remove();
				}, 200 );
				resolve( result );
			}

			$overlay.on( 'click', '[data-cancel]', function () {
				close( false );
			} );
			$overlay.on( 'click', '[data-confirm]', function () {
				close( true );
			} );
			$overlay.on( 'click', function ( e ) {
				if ( e.target === $overlay[ 0 ] ) {
					close( false );
				}
			} );
			$overlay.on( 'keydown', function ( e ) {
				if ( 'Escape' === e.key ) {
					close( false );
				}
			} );
		} );
	}

	window.WCEM = { post: post, toast: toast, report: report, confirm: confirmModal };

	$( function () {
		/* ---------------------------------------------------------------
		 * Templates / components list: duplicate, delete
		 * ------------------------------------------------------------ */
		$( '.wcem-js-duplicate' ).on( 'click', function () {
			var $btn = $( this );

			post( 'duplicate_template', {
				id: $btn.data( 'id' ),
				kind: $btn.data( 'kind' ) || 'template',
			} ).done( function ( res ) {
				if ( report( res ) ) {
					window.location = res.data.editUrl;
				}
			} );
		} );

		$( '.wcem-js-delete' ).on( 'click', function () {
			var $btn = $( this );
			var $row = $btn.closest( 'tr' );

			confirmModal( {
				title: wcem.i18n.deleteTitle,
				body: wcem.i18n.deleteBody,
				confirmLabel: wcem.i18n.confirmDelete,
				danger: true,
			} ).then( function ( ok ) {
				if ( ! ok ) {
					return;
				}

				post( 'delete_template', {
					id: $btn.data( 'id' ),
					kind: $btn.data( 'kind' ) || 'template',
				} ).done( function ( res ) {
					if ( report( res ) ) {
						$row.fadeOut( 200, function () {
							$row.remove();
						} );
					}
				} );
			} );
		} );

		/* ---------------------------------------------------------------
		 * Assignments page
		 * ------------------------------------------------------------ */
		$( '.wcem-assign-row' ).each( function () {
			var $row      = $( this );
			var emailId   = $row.data( 'email-id' );
			var $template = $row.find( '.wcem-js-assign-template' );
			var $enabled  = $row.find( '.wcem-js-assign-enabled' );
			var $badge    = $row.find( '.wcem-badge' );

			function paintBadge() {
				var on = $enabled.is( ':checked' );
				$badge
					.toggleClass( 'wcem-badge--publish', on )
					.toggleClass( 'wcem-badge--default', ! on );
			}

			function save() {
				post( 'assign_template', {
					email_id: emailId,
					template_id: $template.val(),
					enabled: $enabled.is( ':checked' ) ? 1 : 0,
				} ).done( function ( res ) {
					report( res );
					paintBadge();
				} );
			}

			$template.on( 'change', function () {
				var none = ! $template.val() || '0' === $template.val();

				$enabled.prop( 'disabled', none );

				if ( none ) {
					$enabled.prop( 'checked', false );
				}

				save();
			} );

			$enabled.on( 'change', save );

			$row.find( '.wcem-js-reset-assignment' ).on( 'click', function () {
				confirmModal( {
					title: wcem.i18n.resetTitle,
					body: wcem.i18n.resetBody,
					confirmLabel: wcem.i18n.confirmReset,
					danger: true,
				} ).then( function ( ok ) {
					if ( ! ok ) {
						return;
					}

					post( 'reset_assignment', { email_id: emailId } ).done( function ( res ) {
						if ( report( res ) ) {
							$template.val( '0' );
							$enabled.prop( 'checked', false ).prop( 'disabled', true );
							paintBadge();
						}
					} );
				} );
			} );
		} );
	} );
} )( jQuery );
