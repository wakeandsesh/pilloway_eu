<?php
/**
 * Handle compatiblity with WooCommerce EU VAT Assistant plugin.
 *
 * @since 2.0.18
 * @see https://wordpress.org/plugins/woocommerce-eu-vat-assistant/
 * @package WCPBC/Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCPBC_EU_VAT_Assistant' ) ) :

	/**
	 * WCPBC_EU_VAT_Assistant class.
	 */
	class WCPBC_EU_VAT_Assistant {

		/**
		 * Hook actions and filters
		 */
		public static function init() {
			// Reset the option.
			$eu_vat_plugin   = isset( $GLOBALS['wc-aelia-eu-vat-assistant'] ) ? $GLOBALS['wc-aelia-eu-vat-assistant'] : false;
			$eu_vat_settings = $eu_vat_plugin && is_callable( array( $eu_vat_plugin, 'settings_controller' ) ) ? $eu_vat_plugin->settings_controller() : false;
			if ( $eu_vat_settings && is_callable( array( $eu_vat_settings, 'save' ) ) ) {
				$eu_vat_settings->save();
			}
			// Add the filters.
			add_filter( 'wc_aelia_euva_enabled_currencies', array( __CLASS__, 'euva_enabled_currencies' ) );
			add_filter( 'option_wc_aelia_eu_vat_assistant', array( __CLASS__, 'option_eu_vat_assistant' ) );
		}


		/**
		 * Add the pricing zone currencies.
		 *
		 * @param array $currencies Currencies.
		 */
		public static function euva_enabled_currencies( $currencies ) {
			$currencies = is_array( $currencies ) ? $currencies : array();
			foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
				$currencies[] = $zone->get_currency();
			}
			return $currencies;
		}

		/**
		 * Add the exchange rates to the eu vat assistant setting option.
		 *
		 * @param array $settings Option value.
		 * @return array
		 */
		public static function option_eu_vat_assistant( $settings ) {
			$vat_currency = empty( $settings['vat_currency'] ) ? wcpbc_get_base_currency() : $settings['vat_currency'];
			if ( wcpbc_get_base_currency() === $vat_currency ) {
				foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {

					$currency = $zone->get_currency();
					$rate     = $zone->get_base_currency_amount( 1 );

					if ( $vat_currency !== $currency && (
						empty( $settings['exchange_rates'][ $currency ] ) ||
						! isset( $settings['exchange_rates'][ $currency ]['set_manually'] ) ||
						$settings['exchange_rates'][ $currency ]['set_manually']
					) ) {

						$settings['exchange_rates'][ $currency ] = array(
							'rate'         => $rate,
							'set_manually' => 1,
						);
					}
				}
			}
			return $settings;
		}
	}

	WCPBC_EU_VAT_Assistant::init();

endif;
