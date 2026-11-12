( function () {
	'use strict';

	const settings = ( window.wc && window.wc.wcSettings )
		? window.wc.wcSettings.getSetting( 'wayforpay_data', {} )
		: {};

	const label = ( window.wp && window.wp.htmlEntities )
		? window.wp.htmlEntities.decodeEntities( settings.title || 'WayForPay' )
		: ( settings.title || 'WayForPay' );

	const el = window.wp.element.createElement;

	const Label = function () {
		if ( settings.icon ) {
			return el(
				'span',
				{ style: { display: 'flex', alignItems: 'center', gap: '8px' } },
				el( 'span', null, label ),
				el( 'img', { src: settings.icon, alt: label, style: { height: '20px' } } )
			);
		}
		return el( 'span', null, label );
	};

	const Content = function () {
		return el( 'div', null, settings.description || '' );
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( {
		name: 'wayforpay',
		label: el( Label, null ),
		content: el( Content, null ),
		edit: el( Content, null ),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: label,
		supports: {
			features: ( settings.supports || [ 'products' ] ),
		},
	} );
} )();
