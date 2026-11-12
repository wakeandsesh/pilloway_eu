;( function( $ ) {
	stripe_upe_compatibility = {

		/**
		 * On updated checkout handler.
		 *
		 * @param {eventObject} event
		 * @param {object} data
		 */
		updated_checkout: function( event, data ) {

			if ( typeof wc_stripe_upe_params !== 'undefined' && typeof wc_stripe_upe_params.currency !== 'undefined' && data && data.fragments && data.fragments.wcpbc_stripe_upe && wc_stripe_upe_params.currency !== data.fragments.wcpbc_stripe_upe.currency ) {
				wc_stripe_upe_params.currency = data.fragments.wcpbc_stripe_upe.currency;
				wc_stripe_upe_params.cartTotal = data.fragments.wcpbc_stripe_upe.cartTotal;
				$( '.wc-stripe-upe-element').empty();
			}
		},

		/**
		 * Init.
		 */
		init: function() {
			$( document.body ).on( 'updated_checkout', stripe_upe_compatibility.updated_checkout );
		}

	};
	stripe_upe_compatibility.init();
})( jQuery );