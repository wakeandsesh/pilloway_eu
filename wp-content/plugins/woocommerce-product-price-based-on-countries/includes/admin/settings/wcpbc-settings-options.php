<?php
/**
 * Plugin options.
 *
 * Returns an array of options.
 *
 * @package WC_BOGOF
 */

defined( 'ABSPATH' ) || exit;

return array(

	/**
	 * General
	 */
	array(
		'id'        => 'general',
		'title'     => __( 'General', 'woocommerce-product-price-based-on-countries' ),
		'paragrahs' => array(
			__( 'Configure the general plugin settings.', 'woocommerce-product-price-based-on-countries' ),
			// translators: HTML tags.
			sprintf( __( '%1$sView plugin docs%2$s', 'woocommerce-product-price-based-on-countries' ), '<a target="_blank" rel="external noreferrer noopener" class="wcpbc-external-link" href="' . esc_url( wcpbc_home_url( 'settings', 'docs/getting-started/settings-options' ) ) . '">', '</a>' ),
			// translators: HTML tags.
			sprintf( __( '%1$sGet support%2$s', 'woocommerce-product-price-based-on-countries' ), '<a target="_blank" rel="external noreferrer noopener" class="wcpbc-external-link" href="' . esc_url( wcpbc_home_url( 'settings', 'support' ) ) . '">', '</a>' ),
		),
		'fields'    => array_merge(
			array(

				array(
					'id'      => 'wc_price_based_country_based_on',
					'label'   => __( 'Price based on', 'woocommerce-product-price-based-on-countries' ),
					'desc'    => __( "This controls which address is used to determine the customer's pricing zone. Geolocation will be used when the customer's address is unknown.", 'woocommerce-product-price-based-on-countries' ),
					'default' => 'billing',
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'options' => array(
						'billing'  => __( 'Customer billing country', 'woocommerce-product-price-based-on-countries' ),
						'shipping' => __( 'Customer shipping country', 'woocommerce-product-price-based-on-countries' ),
					),
				),

				array(
					'id'      => 'wc_price_based_country_exchange_rate_api',
					'label'   => __( 'Exchange rates source', 'woocommerce-product-price-based-on-countries' ),
					'desc'    => __( 'Select the service that will be used to update the exchange rates.', 'woocommerce-product-price-based-on-countries' ),
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'options' => wcpbc_is_pro() && is_callable( [ 'WCPBC_Update_Exchange_Rates', 'get_providers' ] ) ? wc_list_pluck( WCPBC_Update_Exchange_Rates::get_providers(), 'get_name' ) : array(
						'FloatRates',
						'Open Exchange Rates',
						'X-Rates',
					),
					'is_pro'  => true,
				),
			),
			( wcpbc_is_pro() && is_callable( [ 'WCPBC_Update_Exchange_Rates', 'get_providers_fields' ] ) ? WCPBC_Update_Exchange_Rates::get_providers_fields() : array() )
		),
	),

	/**
	 * Cache support
	 */
	array(
		'id'        => 'cache',
		'title'     => __( 'Cache support', 'woocommerce-product-price-based-on-countries' ),
		'paragrahs' => array(
			__( 'Settings related to geolocation and cache.', 'woocommerce-product-price-based-on-countries' ),
			// translators: HTML tags.
			sprintf( __( '%1$sGeolocation with cache support%2$s', 'woocommerce-product-price-based-on-countries' ), '<a target="_blank" rel="external noreferrer noopener" class="wcpbc-external-link" href="' . esc_url( wcpbc_home_url( 'settings', 'docs/getting-started/geolocation-cache-support' ) ) . '">', '</a>' ),
		),
		'fields'    => array(

			array(
				'id'      => 'wc_price_based_country_caching_support',
				'default' => 'no',
				'type'    => 'true-false',
				'label'   => __( 'Load product prices in the background', 'woocommerce-product-price-based-on-countries' ),
				// translators: HTML tags.
				'desc'    => __( "Enable this option to refresh the prices via AJAX. It'll run an AJAX request per page.", 'woocommerce-product-price-based-on-countries' ),
			),
		),
	),

	/**
	 * Test mode
	 */
	array(
		'id'        => 'test',
		'title'     => __( 'Test mode', 'woocommerce-product-price-based-on-countries' ),
		'paragrahs' => array(
			// translators: HTML tags.
			sprintf( __( '%1$sHow to do a test%2$s', 'woocommerce-product-price-based-on-countries' ), '<a target="_blank" rel="external noreferrer noopener" class="wcpbc-external-link" href="' . esc_url( wcpbc_home_url( 'settings', 'docs/getting-started/testing' ) ) . '">', '</a>' ),
		),
		'fields'    => array(

			array(
				'label'   => __( 'Enable', 'woocommerce-product-price-based-on-countries' ),
				// translators: HTML tags.
				'desc'    => __( 'Enable test mode to show pricing for a specific country.', 'woocommerce-product-price-based-on-countries' ),
				'id'      => 'wc_price_based_country_test_mode',
				'default' => 'no',
				'type'    => 'true-false',
			),

			array(
				'label'   => __( 'Test country', 'woocommerce-product-price-based-on-countries' ),
				'id'      => 'wc_price_based_country_test_country',
				'type'    => 'country-select',
				'show-if' => array(
					array(
						'field'    => 'wc_price_based_country_test_mode',
						'operator' => '=',
						'value'    => 'yes',
					),
				),
			),
		),
	),

	/**
	 * Show advanced
	 */
	array(
		'id'     => 'show-advanced-options',
		'fields' => array(
			/**
			 * Collapse next options.
			 */
			array(
				'id'                => 'show_advanced_options_btn',
				'label'             => __( 'Advanced', 'woocommerce-product-price-based-on-countries' ),
				'custom_attributes' => array(
					'data-toggle'   => 'collapse',
					'data-target'   => '.wcpbc-settings-section-container.-advanced',
					'role'          => 'button',
					'aria-expanded' => 'false',
				),
				'type'              => 'link',
				'href'              => '#advanced-section',
			),
		),
	),

	/**
	 * Advanced
	 */
	array(
		'id'     => 'advanced',
		'fields' => array_merge(
			array(

				/**
				 * Shipping cost by exchange rate.
				 */
				array(
					'id'      => 'wc_price_based_country_shipping_exchange_rate',
					'label'   => __( 'Apply the exchange rate to the shipping cost', 'woocommerce-product-price-based-on-countries' ),
					'desc'    => __( 'Enable this option if you want to use the exchange rate of the pricing zones to convert shipping costs.', 'woocommerce-product-price-based-on-countries' ),
					'default' => 'yes',
					'type'    => 'true-false',
				),

				/**
				 * Run setup wizard.
				 */
				array(
					'id'                => 'run_setup_wizard',
					'label'             => __( 'Run the geolocation setup wizard', 'woocommerce-product-price-based-on-countries' ),
					'custom_attributes' => array(
						'role' => 'button',
					),
					'type'              => 'link',
					'class'             => 'button',
					'href'              => admin_url( 'admin.php?page=wcpbc-setup' ),
				),

			),
			( class_exists( 'WCPBC_Google_Listing_And_Ads' ) ? WCPBC_Google_Listing_And_Ads::get_setting_options() : array() )
		),
	),

	/**
	 * Submit section.
	 */
	array(
		'id'     => 'submit',
		'fields' => array(

			/**
			 * Save changes
			 */
			array(
				'id'    => 'save_changes',
				'label' => __( 'Save changes', 'woocommerce-product-price-based-on-countries' ),
				'type'  => 'submit',
			),
		),
	),

);
