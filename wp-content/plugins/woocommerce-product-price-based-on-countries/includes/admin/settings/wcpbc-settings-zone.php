<?php
/**
 * Pricing Zone settings fields.
 *
 * Returns an array of fields for the zone.
 *
 * @var WCPBC_Pricing_Zone $zone The current zone.
 * @package WC_BOGOF
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $zone ) ) {
	return array();
}

return array(

	/**
	 * Heading
	 */
	array(
		'id'    => 'heading',
		'title' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=price-based-country' ) . '">' . __( 'Pricing zones', 'woocommerce-product-price-based-on-countries' ) . '</a> &gt; <span class="wcpbc-zone-name">' . ( empty( $zone->get_name() ) ? __( 'Zone', 'woocommerce-product-price-based-on-countries' ) : $zone->get_name() ) . '</span>',
	),

	/**
	 * General
	 */
	array(
		'id'        => 'general',
		'title'     => __( 'General', 'woocommerce-product-price-based-on-countries' ),
		'paragrahs' => array(
			__( 'Enter a name, the countries included in the zone, and the currency.', 'woocommerce-product-price-based-on-countries' ),
			// translators: HTML tags.
			sprintf( __( '%1$sView plugin docs%2$s', 'woocommerce-product-price-based-on-countries' ), '<a target="_blank" rel="external noreferrer noopener" class="wcpbc-external-link" href="' . esc_url( wcpbc_home_url( 'settings', 'docs/getting-started/setting-pricing-zones' ) ) . '">', '</a>' ),
			// translators: HTML tags.
			sprintf( __( '%1$sGet support%2$s', 'woocommerce-product-price-based-on-countries' ), '<a target="_blank" rel="external noreferrer noopener" class="wcpbc-external-link" href="' . esc_url( wcpbc_home_url( 'settings', 'support' ) ) . '">', '</a>' ),
		),
		'fields'    => array(

			/**
			 * Enabled.
			 */
			array(
				'id'    => 'enabled',
				'label' => __( 'Enabled', 'woocommerce-product-price-based-on-countries' ),
				'desc'  => __( 'Enable/Disable the pricing zone on the frontend.', 'woocommerce-product-price-based-on-countries' ),
				'type'  => 'true-false',
				'value' => $zone->get_enabled(),
			),

			/**
			 * Name
			 */
			array(
				'id'                => 'name',
				'label'             => __( 'Zone name', 'woocommerce-product-price-based-on-countries' ),
				'type'              => 'text',
				'desc'              => __( 'This is the name of the zone for your reference.', 'woocommerce-product-price-based-on-countries' ),
				'value'             => $zone->get_name(),
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),

			/**
			 * Countries
			 */
			array(
				'id'       => 'countries',
				'label'    => __( 'Countries', 'woocommerce-product-price-based-on-countries' ),
				'type'     => 'country-select',
				'multiple' => true,
				'options'  => WCPBC_Pricing_Zones::get_allowed_countries( $zone ),
				'desc'     => __( 'These are countries inside this zone. Customers will be matched against these countries.', 'woocommerce-product-price-based-on-countries' ),
				'value'    => $zone->get_countries(),
			),

			/**
			 * Name
			 */
			array(
				'id'    => 'currency',
				'label' => __( 'Currency', 'woocommerce-product-price-based-on-countries' ),
				'type'  => 'currency-select',
				'desc'  => __( 'Choose the currency of this zone. Customers from the countries of this zone will see the prices and pay in this currency.', 'woocommerce-product-price-based-on-countries' ),
				'value' => $zone->get_currency(),
			),
		),
	),

	/**
	 * Exchange rate
	 */
	array(
		'id'        => 'exchange_rate',
		'title'     => __( 'Exchange rate', 'woocommerce-product-price-based-on-countries' ),
		'paragrahs' => array(
			__( 'The following options affect the prices calculated using the exchange rate.', 'woocommerce-product-price-based-on-countries' ),
			// translators: HTML tags.
			sprintf( __( '%1$sHow to set the product price per zone?%2$s', 'woocommerce-product-price-based-on-countries' ), '<a target="_blank" rel="external noreferrer noopener" class="wcpbc-external-link" href="' . esc_url( wcpbc_home_url( 'settings', 'docs/getting-started/managing-country-product-pricing' ) ) . '">', '</a>' ),
		),
		'fields'    => array(

			/**
			 * Automatic update of the exchange rate.
			 */
			array(
				'id'     => 'auto_exchange_rate',
				'label'  => __( 'Automatic update of the exchange rate', 'woocommerce-product-price-based-on-countries' ),
				'desc'   => __( 'Enable this option to get the exchange rate from a service instead of entering it manually.', 'woocommerce-product-price-based-on-countries' ),
				'type'   => 'true-false',
				'value'  => is_callable( array( $zone, 'get_auto_exchange_rate' ) ) ? $zone->get_auto_exchange_rate() : false,
				'is_pro' => true,
			),

			/**
			 * Exchange Rate
			 */
			array(
				'id'                => 'exchange_rate',
				'label'             => __( 'Exchange rate', 'woocommerce-product-price-based-on-countries' ),
				'type'              => 'text',
				'class'             => 'wc_input_decimal',
				'value'             => wcpbc_float_to_string( $zone->get_exchange_rate(), true ),
				'prepend'           => sprintf( '1&nbsp;%s&nbsp;=', wcpbc_get_base_currency() ),
				'append'            => $zone->get_currency(),
				'desc'              => __( 'Enter the exchange rate manually.', 'woocommerce-product-price-based-on-countries' ),
				'show-if'           => array(
					array(
						'field'    => 'auto_exchange_rate',
						'operator' => '=',
						'value'    => 'no',
					),
				),
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),

			/**
			 * Exchange Rate Fee
			 */
			array(
				'id'                => 'exchange_rate_fee',
				'label'             => __( 'Exchange rate fee (%)', 'woocommerce-product-price-based-on-countries' ),
				'type'              => 'number',
				'value'             => is_callable( array( $zone, 'get_exchange_rate_fee' ) ) ? $zone->get_exchange_rate_fee() : '',
				'custom_attributes' => array(
					'min'  => -100,
					'max'  => 100,
					'step' => apply_filters( 'wc_price_based_country_exchange_rate_fee_step', '0.1' ),
				),
				'desc'              => __( 'Enter a fee (percentage) to increment the auto exchange rate.', 'woocommerce-product-price-based-on-countries' ),
				'append'            => '%',
				'show-if'           => array(
					array(
						'field'    => 'auto_exchange_rate',
						'operator' => '=',
						'value'    => 'yes',
					),
				),
				'is_pro'            => true,
			),

			/**
			 * Round to Nearest
			 */
			array(
				'id'      => 'round_nearest',
				'label'   => __( 'Round to nearest', 'woocommerce-product-price-based-on-countries' ),
				'type'    => 'select',
				'value'   => is_callable( array( $zone, 'get_round_nearest' ) ) ? $zone->get_round_nearest() : '',
				'options' => array(
					''     => __( 'Deactivate', 'woocommerce-product-price-based-on-countries' ),
					'0.05' => __( '0.05 ( 1785.42 to 1785.45 )', 'woocommerce-product-price-based-on-countries' ),
					'0.5'  => __( '0.50 ( 1785.42 to 1785.50 )', 'woocommerce-product-price-based-on-countries' ),
					'1'    => __( 'Next integer ( 1785.42 to 1786 )', 'woocommerce-product-price-based-on-countries' ),
					'5'    => __( '5 ( 1782.42 to 1785 )', 'woocommerce-product-price-based-on-countries' ),
					'10'   => __( '10 ( 1785.42 to 1790 )', 'woocommerce-product-price-based-on-countries' ),
					'50'   => __( '50 ( 1785.42 to 1800 )', 'woocommerce-product-price-based-on-countries' ),
					'500'  => __( '500 ( 1785.42 to 2000 )', 'woocommerce-product-price-based-on-countries' ),
				),
				'desc'    => __( 'Round up the prices calculated by exchange rate. The result will be a multiple of the value that you select.', 'woocommerce-product-price-based-on-countries' ),
				'is_pro'  => true,
			),

			/**
			 * Price charm
			 */
			array(
				'id'      => 'price_charm',
				'label'   => __( 'Reduce the converted price', 'woocommerce-product-price-based-on-countries' ),
				'type'    => 'select',
				'value'   => is_callable( array( $zone, 'get_price_charm' ) ) ? $zone->get_price_charm() : '',
				'options' => array(
					''     => __( 'Deactivate', 'woocommerce-product-price-based-on-countries' ),
					'0.01' => __( '-0.01', 'woocommerce-product-price-based-on-countries' ),
					'0.10' => __( '-0.10', 'woocommerce-product-price-based-on-countries' ),
					'0.5'  => __( '-0.50', 'woocommerce-product-price-based-on-countries' ),
					'1'    => __( '-1', 'woocommerce-product-price-based-on-countries' ),
				),
				'desc'    => __( 'Reduces the converted price by a specific amount. This option allows you to display prices slightly less than a round number (e.g., $1.99 instead of $2).', 'woocommerce-product-price-based-on-countries' ),
				'show-if' => array(
					array(
						'field'    => 'round_nearest',
						'operator' => '!=',
						'value'    => '',
					),
				),
				'is_pro'  => true,
			),

			/**
			 * Round the price after WooCommerce adds the taxes.
			 */
			array(
				'id'      => 'round_after_taxes',
				'label'   => __( 'Round the price after WooCommerce adds the taxes', 'woocommerce-product-price-based-on-countries' ),
				'type'    => 'true-false',
				'value'   => is_callable( array( $zone, 'get_round_after_taxes' ) ) ? $zone->get_round_after_taxes() : false,
				// Translators: 1,2 HTML tags.
				'desc'    => sprintf( __( 'Check this box if you want to display rounded prices with tax included in the shop. Note the WooCommerce %1$stax rounding functions could modify to the final price%2$s.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>' ),
				'show-if' => array(
					array(
						'field'    => 'round_nearest',
						'operator' => '!=',
						'value'    => '',
					),
					array(
						'field'    => '__wc_tax_display_shop',
						'operator' => '=',
						'value'    => 'yes',
					),
				),
				'is_pro'  => true,
			),
		),
	),

	/**
	 * Currency options
	 */
	array(
		'id'        => 'currency_options',
		'title'     => __( 'Currency options', 'woocommerce-product-price-based-on-countries' ),
		'paragrahs' => array(
			__( 'The following options affect how prices are displayed on the frontend.', 'woocommerce-product-price-based-on-countries' ),
		),
		'fields'    => array(

			/**
			 * Currency format
			 */
			array(
				'id'                => 'currency_format',
				'label'             => __( 'Currency format', 'woocommerce-product-price-based-on-countries' ),
				'type'              => 'text',
				'value'             => is_callable( array( $zone, 'get_currency_format' ) ) ? $zone->get_currency_format() : false,
				'placeholder'       => wcpbc_is_pro() ? get_option( 'wc_price_based_currency_format', '[symbol-alt][price]' ) : '[symbol-alt][price]',
				'desc'              => __( 'Supports the following placeholders: [code] = currency code, [symbol] = currency symbol, [symbol-alt] = alternative currency symbol (US$, CA$, ...), [price] = product price.', 'woocommerce-product-price-based-on-countries' ),
				'append'            => 'US$99.00',
				'custom_attributes' => array(
					'data-preview-currency'     => '#currency',
					'data-preview-num-decimals' => '#price_num_decimals',
					'data-preview-decimal-sep'  => '#price_decimal_sep',
					'data-preview-trim-zeros'   => '#trim_zeros',
					'data-price-preview'        => '.wcpbc-input-append.-append-currency_format',

				),
				'is_pro'            => true,
			),

			/**
			 * Collapse next options.
			 */
			array(
				'id'                => 'show_advanced_currency_options',
				'label'             => __( 'Advanced', 'woocommerce-product-price-based-on-countries' ),
				'custom_attributes' => array(
					'data-toggle'   => 'collapse',
					'data-target'   => '.show-if-advanced-currency-options',
					'role'          => 'button',
					'aria-expanded' => 'false',
				),
				'type'              => 'link',
				'href'              => '#',
			),

			/**
			 * Thousand Separator
			 */
			array(
				'id'              => 'price_thousand_sep',
				'label'           => __( 'Thousand separator', 'woocommerce-product-price-based-on-countries' ),
				'type'            => 'text',
				'value'           => is_callable( array( $zone, 'get_price_thousand_sep' ) ) ? $zone->get_price_thousand_sep() : '',
				'placeholder'     => get_option( 'woocommerce_price_thousand_sep' ),
				'desc'            => __( 'This sets the thousand separator of displayed prices.', 'woocommerce-product-price-based-on-countries' ),
				'container_class' => 'show-if-advanced-currency-options',
				'is_pro'          => true,
			),

			/**
			 * Decimal Separator
			 */
			array(
				'id'              => 'price_decimal_sep',
				'label'           => __( 'Decimal separator', 'woocommerce-product-price-based-on-countries' ),
				'type'            => 'text',
				'value'           => is_callable( array( $zone, 'get_price_decimal_sep' ) ) ? $zone->get_price_decimal_sep() : '',
				'placeholder'     => get_option( 'woocommerce_price_decimal_sep' ),
				'desc'            => __( 'This sets the decimal separator of displayed prices.', 'woocommerce-product-price-based-on-countries' ),
				'container_class' => 'show-if-advanced-currency-options',
				'is_pro'          => true,
			),

			/**
			 * Hide trailing zeros on prices
			 */
			array(
				'id'              => 'trim_zeros',
				'label'           => __( 'Hide trailing zeros on prices', 'woocommerce-product-price-based-on-countries' ),
				'type'            => 'true-false',
				'value'           => is_callable( array( $zone, 'get_trim_zeros' ) ) ? $zone->get_trim_zeros() : false,
				'container_class' => 'show-if-advanced-currency-options',
				'is_pro'          => true,
			),

			/**
			 * Number of Decimals
			 */
			array(
				'id'                => 'price_num_decimals',
				'label'             => __( 'Number of decimals', 'woocommerce-product-price-based-on-countries' ),
				'type'              => 'number',
				'desc'              => __( 'This sets the number of decimal points shown in displayed prices.', 'woocommerce-product-price-based-on-countries' ),
				'value'             => is_callable( array( $zone, 'get_price_num_decimals' ) ) ? $zone->get_price_num_decimals() : '',
				'placeholder'       => get_option( 'woocommerce_price_num_decimals' ),
				'custom_attributes' => array(
					'min'  => 0,
					'max'  => 100,
					'step' => 1,
				),
				'container_class'   => 'show-if-advanced-currency-options',
				'is_pro'            => true,
			),
		),
	),


	/**
	 * Tax options
	 */
	array(
		'id'     => 'tax_options',
		'title'  => __( 'Tax options', 'woocommerce-product-price-based-on-countries' ),
		'class'  => wc_tax_enabled() && wc_prices_include_tax() ? '' : '-hidden',
		'fields' => array(

			/**
			 * Do not adjust taxes based on location.
			 */
			array(
				'id'    => 'disable_tax_adjustment',
				'label' => __( 'Do not adjust taxes based on location', 'woocommerce-product-price-based-on-countries' ),
				'type'  => 'true-false',
				'value' => $zone->get_disable_tax_adjustment(),
				// Translators: 1,2 Link to doc.
				'desc'  => sprintf( __( 'Check this to disable tax adjustment. e.g., If a product costs 10 including tax, all users will pay 10 regardless of country taxes. %1$sRead more%2$s', 'woocommerce-product-price-based-on-countries' ), '<a target="_blank" rel="noopener noreferrer" class="wcpbc-external-link" href="https://www.pricebasedcountry.com/docs/getting-started/prices-entered-with-tax-show-a-wrong-value/?utm_source=settings&utm_medium=banner&utm_campaign=Docs">', '</a>' ),
			),
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

			/**
			 * Delete zone
			 */
			array(
				'id'                => 'delete-zone',
				'label'             => __( 'Delete', 'woocommerce-product-price-based-on-countries' ),
				'class'             => 'button -remove',
				'type'              => 'link',
				'custom_attributes' => array(
					'style'       => ( $zone->get_id() ? '' : 'display:none;' ),
					'data-toggle' => 'tooltip-confirm',
					// translators: HTML tags.
					'data-title'  => sprintf( esc_html__( 'Are you sure? %1$sDelete%2$s %3$sCancel%2$s', 'woocommerce-product-price-based-on-countries' ), '<a href="#" data-event="confirm">', '</a>', '<a data-event="cancel" href="#">' ),
				),
				'href'              => wp_nonce_url(
					add_query_arg(
						'delete_zone',
						$zone->get_id(),
						admin_url( 'admin.php?page=wc-settings&tab=price-based-country' )
					),
					'wc-price-based-country-delete-zone'
				),
			),

			/**
			 * Hidden fields.
			 */
			array(
				'id'    => '__wc_tax_display_shop',
				'type'  => 'hidden',
				'value' => wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_shop' ) && ! wc_prices_include_tax() ? 'yes' : 'no',
			),

		),
	),
);

