;( function( $ ) {
	ppec_compatibility = {

		/**
		 * Changes the currency of the PPEC sdk.
		 *
		 * @param {string} currency
		 */
		replace_pp_sdk_currency: function( currency ) {

			if ( 'undefined' !== typeof PayPalCommerceGateway ) {
				PayPalCommerceGateway.currency = currency;
			}

			['script[data-namespace="paypal_sdk"]', 'script[data-partner-attribution-id="Woo_PPCP"]'].forEach( function(selector){
				if ( $(selector).length > 0 ) {
					var pp_script = $(selector);
					var src_url   = ppec_compatibility.get_pp_sdk_src( pp_script.attr('src'), currency );

					if ( src_url !== pp_script.attr('src') ) {

						['#ppc-button-ppcp-gateway', '#woo_pp_ec_button_checkout', '#ppc-button'].forEach(function(selector){
							$(selector).empty();
						});
						pp_script.attr('src', src_url);
					}
				}
			});
		},

		/**
		 * Return the PPCE SDK url for the new currency.
		 *
		 * @param {sting} old_src
		 * @param {string} currency
		 */
		get_pp_sdk_src: function( old_src, currency ) {
			var src_string = old_src;
			try{
				var src = new URL( old_src );
				if ( src.searchParams.get('currency') !== currency ) {
					src.searchParams.set('currency', currency);
					src_string = src.toString();
				}
			} catch(error) {
				// IE does not support URL object.
				var index        = old_src.indexOf('&currency=');
				var old_currency = '';
				if ( index > 0 ) {
					old_currency = old_src.substr( index, 13);
					src_string   = old_src.replace(old_currency, '&currency=' + currency );
				}
			}
			return src_string;
		},

		/**
		 * On updated checkout handler.
		 *
		 * @param {eventObject} event
		 * @param {object} data
		 */
		updated_checkout: function( event, data ) {
			if ( data && data.fragments && data.fragments.wcpbc_currency ) {
				ppec_compatibility.replace_pp_sdk_currency( data.fragments.wcpbc_currency)
			}
		},

		/**
		 * Init.
		 */
		init: function() {
			$( document.body ).on( 'updated_checkout', ppec_compatibility.updated_checkout )
		}
	};
	ppec_compatibility.init();
})( jQuery );