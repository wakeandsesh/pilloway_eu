/* global wcpbc_admin_params, woocommerce_admin */
jQuery( function( $ ) {
	'use strict';

	/**
	 * Metaboxes actions
	 */
	var wcpbc_meta_boxes = {

		/**
		 * Initialize metabox actions
		 */
		init: function() {
			$(document).ready( this.show_and_hide_panels );
			$( 'body' ).on( 'click', '.wcpbc_price_method', this.price_method_change );
			$( 'body' ).on( 'click', '.wcpbc_sale_price_dates[type="radio"]', this.sale_dates_change );
			$( 'body' ).on( 'keyup', '.wcpbc_sale_price[type=text]', this.sale_price_keyup );
			$( 'body' ).on( 'change', '.wcpbc_sale_price[type=text]', this.sale_price_change );
			$( document.body ).on( 'woocommerce_variations_added', this.show_and_hide_panels );
			$( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded', this.show_and_hide_panels );
			$( document.body ).on( 'wc_price_based_country_show_and_hide_panels', this.show_and_hide_panels );

			$(document).ready( this.product_type_not_supported_alert )
			$(document.body).on( 'woocommerce-product-type-change', this.product_type_not_supported_alert );
		},

		/**
		 * Show/Hide elements if manual
		 */
		show_and_hide_panels: function() {
			$( '.wcpbc_price_method[type="radio"][value="manual"]' ).each( function(){
				var $wrapper = $( this ).closest( '.wcpbc_pricing' );
				var show     = $(this).prop( 'checked' );

				wcpbc_meta_boxes.show_hide_price_controls( $wrapper, show );
			});
		},

		/**
		 * Show or hide the pricing zone controls.
		 *
		 * @param {*} $wrapper
		 * @param {*} show
		 */
		show_hide_price_controls: function( $wrapper, show ) {
			var	 hide_sale_dates = show && ! $wrapper.find( '.wcpbc_sale_price_dates[type="radio"][value="default"]').first().prop( 'checked' );

			$wrapper.find( '.wcpbc_show_if_manual' ).toggle( show );
			$wrapper.find( '.wcpbc_hide_if_sale_dates_default').toggle( hide_sale_dates );

			$( document.body ).trigger( 'wc_price_based_country_manual_price_' + ( show ? 'show' : 'hide' ), [$wrapper] );
		},

		/**
		 * Check if price method is manual and show/hide elements
		 */
		price_method_change: function() {
			var $wrapper = $( this ).closest( '.wcpbc_pricing' );
			var show     = $( this ).val() == 'manual';

			wcpbc_meta_boxes.show_hide_price_controls( $wrapper, show );
		},

		/**
		 * Check if sale dates is default and show/hide elements
		 */
		sale_dates_change: function() {
			$( this ).closest( '.wcpbc_pricing' ).find( '.wcpbc_hide_if_sale_dates_default' ).toggle( 'default' !== $(this).val() );
		},

		/**
		 * Is sale price bigger than regular price
		 */
		is_sale_bigger_than_regular: function( sale_price_field ) {
			var regular_price_field = $('#' + sale_price_field.attr('id').replace('_sale','_regular') ) ;

			var sale_price    = parseFloat( accounting.unformat( sale_price_field.val(), woocommerce_admin.mon_decimal_point ) );
			var regular_price = parseFloat( accounting.unformat( regular_price_field.val(), woocommerce_admin.mon_decimal_point ) );

			return sale_price >= regular_price;
		},

		/**
		 * Trigger on sale price change
		 */
		sale_price_keyup: function() {
			if ( wcpbc_meta_boxes.is_sale_bigger_than_regular( $(this) ) ) {
				$( document.body ).triggerHandler( 'wc_add_error_tip', [ $(this), 'i18n_sale_less_than_regular_error' ] );
			} else {
				$( document.body ).triggerHandler( 'wc_remove_error_tip', [ $(this), 'i18n_sale_less_than_regular_error' ] );
			}
		},

		/**
		 * Trigger on sale price change
		 */
		sale_price_change: function() {
			if ( wcpbc_meta_boxes.is_sale_bigger_than_regular( $(this) ) ) {
				$(this).val( '' );
			}
		},

		/**
		 * Display or hide the product type not supported alert.
		 */
		product_type_not_supported_alert: function() {
			var select_val = $( 'select#product-type' ).val();
			$( '#general_product_data .wc-price-based-country-upgrade-notice.wc-pbc-show-if-not-supported').hide();
			$( '#general_product_data .wc-price-based-country-upgrade-notice.wc-pbc-show-if-not-supported.product-type-' + select_val ).toggle(
				0 > $.inArray( select_val, wcpbc_admin_params.product_type_supported )
			);
			$( '#general_product_data .wc-price-based-country-upgrade-notice.wc-pbc-show-if-third-party').hide();
			$( '#general_product_data .wc-price-based-country-upgrade-notice.wc-pbc-show-if-third-party.product-type-' + select_val ).toggle(
				0 <= $.inArray( select_val, wcpbc_admin_params.product_type_third_party )
			);
		}
	};

	/**
	 * Coupon Metabox actions
	 */
	var wcpbc_coupon_metaboxes = {

		init: function() {
			this.discount_type_change();
			$('#general_coupon_data #discount_type').on('change', this.discount_type_change );
		},

		discount_type_change: function() {
			var show = $( '#discount_type' ).val()=='fixed_cart' || $('#discount_type').val()=='fixed_product';
			$('#general_coupon_data #zone_pricing_type').closest('p').toggle( show );
		}
	};

	/**
	 * Ads actions.
	 */
	var wcpbc_ads = {

		init: function() {
			$( 'select.variation_actions' ).on('wcpbc_variable_bulk_edit_popup', this.upgrade_pro_popup );
			$(document).ready( this.show_and_hide_pro_notice );
			$(document).ready( this.move_integration_upgrade_notices );
			$(document.body).on( 'woocommerce-product-type-change', this.show_and_hide_pro_notice );
			$(document.body).on( 'woocommerce_variations_added', this.show_and_hide_pro_notice );
			$( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded', this.show_and_hide_pro_notice );
			$( 'input#_nyp' ).on( 'change', this.show_hide_nyp_notice );
			$( '#woocommerce-product-data' ).on( 'change', '.checkbox.variation_is_nyp', this.show_hide_nyp_notice );
		},

		upgrade_pro_popup: function(e) {
			e.preventDefault();

			// Remove need update class.
			var need_update = $( '#variable_product_options' ).find( '.woocommerce_variations .variation-needs-update' );
			if ( need_update.length > 0 ) {
				need_update.removeClass('variation-needs-update');
			}

			// Show the popup.
			$( document.body ).trigger( 'wcpbc_show_upgrade_pro_popup' );

		},

		move_integration_upgrade_notices: function() {
			if ( $('.wc-price-based-country-upgrade-germanized-integration').length > 0 ) {
				$('#general_product_data .wc-price-based-country-upgrade-germanized-integration').insertAfter('#general_product_data p._unit_price_sale_field');

				$( document.body ).on( 'woocommerce_variations_added', wcpbc_ads.move_germanized_variation_divs );
				$( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded', wcpbc_ads.move_germanized_variation_divs );
			}
			if ( $('.wc-price-based-country-upgrade-wc-smart-coupons-integration').length > 0 ) {
				$('#general_product_data .wc-price-based-country-upgrade-wc-smart-coupons-integration').insertAfter('#sc-field');
			}
		},

		move_germanized_variation_divs:  function() {
			$('.woocommerce_variation.wc-metabox .variable_gzd_ts_labels').each( function() {
				var $div = $(this).closest('.woocommerce_variation.wc-metabox');
				$div.find('.wcpbc_pricing').insertAfter( $(this) );

				var $variable_pricing_unit = $div.find('.variable_pricing_unit');
				$div.find('.wc-price-based-country-upgrade-germanized-integration').insertAfter( $variable_pricing_unit );
			} );

		},

		show_and_hide_pro_notice: function(){
			var select_val = $( 'select#product-type' ).val();
			$('.wc-price-based-country-upgrade-notice:not(.show)').hide();
			$('.wc-price-based-country-upgrade-notice.wc-pbc-show-if-'+select_val).show();
			$('#general_product_data .wc-price-based-country-upgrade-notice.wc-price-based-country-upgrade-product-variable-subscription').hide();
			$('#general_product_data .wc-price-based-country-upgrade-notice.wc-pbc-show-if-booking').hide();
			wcpbc_ads.show_hide_nyp_notice();
		},

		show_hide_nyp_notice: function() {
			if ( $( 'input#_nyp' ).length < 1 ) {
				return;
			}
			var is_nyp     = $( 'input#_nyp' ).prop( 'checked' );
			var select_val = $( 'select#product-type' ).val();

			$('.wcpbc_pricing.hide_if_nyp-wcpbc').toggle( ! is_nyp );
			$( '.wc-price-based-country-upgrade-notice.wc-pbc-show-if-nyp-wcpbc').toggle( is_nyp );

			$('.checkbox.variation_is_nyp').each( function() {
				var is_nyp = $( this ).prop( 'checked' );
				$( this ).closest( '.woocommerce_variable_attributes.wc-metabox-content' ).find( '.wc-price-based-country-upgrade-notice.wc-pbc-show-if-nyp-wcpbc' ).toggle( is_nyp );
				$( this ).closest( '.woocommerce_variable_attributes.wc-metabox-content' ).find( '.wcpbc_pricing.hide_if_nyp-wcpbc' ).toggle( ! is_nyp );
			} );

			if ( ! is_nyp ) {
				$('.wcpbc_pricing.hide_if_' + select_val ).hide();
			}
		}
	};

	if ( '1' !== wcpbc_admin_params.is_pro ) {
		wcpbc_ads.init();
	}
	wcpbc_meta_boxes.init();
	wcpbc_coupon_metaboxes.init();
});
