/**
 * Licence box: activate a key, release it, or start the 7-day trial from the
 * modal. Plain fetch against admin-ajax.
 */
( function () {
	'use strict';

	const cfg = window.catcodePlmsLicense || {};
	const i18n = cfg.i18n || {};

	function el( id ) {
		return document.getElementById( id );
	}

	function say( box, text, isError ) {
		if ( ! box ) {
			return;
		}
		box.textContent = text;
		box.className = 'catcode-plms-msg ' + ( isError ? 'catcode-plms-msg--error' : 'catcode-plms-msg--ok' );
	}

	function post( body, done ) {
		fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( body ).toString()
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( done )
			.catch( function () {
				done( { success: false, data: { message: i18n.network } } );
			} );
	}

	function reload() {
		window.setTimeout( function () {
			window.location.href = cfg.settingsUrl;
		}, 1200 );
	}

	document.addEventListener( 'click', function ( e ) {
		const target = e.target;

		if ( target.id === 'catcode-plms-activate' ) {
			e.preventDefault();
			const field = el( 'catcode-plms-license' );
			target.disabled = true;
			post( { action: 'catcode_plms_license', nonce: cfg.nonce, key: field ? field.value : '' }, function ( res ) {
				target.disabled = false;
				say( el( 'catcode-plms-license-msg' ), res.data && res.data.message ? res.data.message : '', ! res.success );
				if ( res.success ) {
					reload();
				}
			} );
			return;
		}

		if ( target.id === 'catcode-plms-deactivate' ) {
			e.preventDefault();
			if ( ! window.confirm( i18n.confirmDeactivate ) ) {
				return;
			}
			target.disabled = true;
			post( { action: 'catcode_plms_license', nonce: cfg.nonce, mode: 'deactivate' }, function ( res ) {
				target.disabled = false;
				say( el( 'catcode-plms-license-msg' ), res.data && res.data.message ? res.data.message : '', ! res.success );
				reload();
			} );
			return;
		}

		if ( target.id === 'catcode-plms-trial-open' ) {
			e.preventDefault();
			const modal = el( 'catcode-plms-modal' );
			if ( modal ) {
				modal.hidden = false;
				const mail = el( 'catcode-plms-trial-email' );
				if ( mail ) {
					mail.focus();
				}
			}
			return;
		}

		if ( target.classList && target.classList.contains( 'catcode-plms-modal-close' ) ) {
			e.preventDefault();
			const box = el( 'catcode-plms-modal' );
			if ( box ) {
				box.hidden = true;
			}
			return;
		}

		// The only place a trial is ever born.
		if ( target.id === 'catcode-plms-trial-start' ) {
			e.preventDefault();
			const email = el( 'catcode-plms-trial-email' );
			target.disabled = true;
			post( { action: 'catcode_plms_trial', nonce: cfg.nonce, email: email ? email.value : '' }, function ( res ) {
				target.disabled = false;
				say( el( 'catcode-plms-trial-msg' ), res.data && res.data.message ? res.data.message : '', ! res.success );
				if ( res.success ) {
					const keyBox = el( 'catcode-plms-trial-key' );
					if ( keyBox && res.data.key ) {
						keyBox.hidden = false;
						keyBox.querySelector( 'code' ).textContent = res.data.key;
					}
					reload();
				}
			} );
		}
	} );
}() );
