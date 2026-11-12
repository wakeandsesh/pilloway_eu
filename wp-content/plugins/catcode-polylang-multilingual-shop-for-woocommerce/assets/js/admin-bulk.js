/**
 * "Translate the catalogue": runs the server steps one after another
 * (assign → terms → products) and prints what each step did.
 */
( function () {
	'use strict';

	const cfg = window.catcodePlmsBulk || {};
	const i18n = cfg.i18n || {};

	function el( id ) {
		return document.getElementById( id );
	}

	function log( line ) {
		const box = el( 'catcode-plms-bulk-log' );
		if ( ! box ) {
			return;
		}
		box.hidden = false;
		box.textContent += line + '\n';
		box.scrollTop = box.scrollHeight;
	}

	function post( body ) {
		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( body ).toString()
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	function run( button, lang, status, stage, offset, created ) {
		post( {
			action: 'catcode_plms_bulk',
			nonce: cfg.nonce,
			// Not "lang": Polylang would switch the whole AJAX request (and its messages) to that language.
			target: lang,
			status: status,
			stage: stage,
			offset: String( offset )
		} )
			.then( function ( res ) {
				if ( ! res || ! res.success ) {
					log( ( res && res.data && res.data.message ) ? res.data.message : i18n.failed );
					button.disabled = false;
					return;
				}
				const data = res.data || {};
				const total = created + ( data.created || 0 );
				if ( data.message ) {
					log( data.message );
				}
				( data.errors || [] ).forEach( function ( line ) {
					log( '! ' + line );
				} );
				if ( data.done ) {
					log( ( i18n.done || '' ).replace( '%d', String( total ) ) );
					button.disabled = false;
					return;
				}
				run( button, lang, status, data.stage, data.offset || 0, total );
			} )
			.catch( function () {
				log( i18n.failed );
				button.disabled = false;
			} );
	}

	document.addEventListener( 'click', function ( e ) {
		if ( e.target.id !== 'catcode-plms-bulk-start' ) {
			return;
		}
		e.preventDefault();
		const lang = el( 'catcode-plms-bulk-lang' );
		const status = el( 'catcode-plms-bulk-status' );
		if ( ! lang || ! lang.value ) {
			return;
		}
		if ( ! window.confirm( i18n.confirm ) ) {
			return;
		}
		e.target.disabled = true;
		const box = el( 'catcode-plms-bulk-log' );
		if ( box ) {
			box.textContent = '';
		}
		run( e.target, lang.value, status ? status.value : 'draft', 'assign', 0, 0 );
	} );
}() );
