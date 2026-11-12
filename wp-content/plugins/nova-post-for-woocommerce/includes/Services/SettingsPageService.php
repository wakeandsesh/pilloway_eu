<?php
/**
 * Settings Page Service
 *
 * @package NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Helpers\CurrencyHelper;
use NovaPostForWoocommerce\Database\Models\WoocommerceShippingZoneMethod;

/**
 * Settings Page Service Class
 *
 * Handles various settings page related functionality.
 */
class SettingsPageService {

    /**
     * Check if any Nova Post shipping methods are enabled in any shipping zones.
     *
     * @return bool True if any Nova Post shipping methods are enabled, false otherwise.
     */
    public static function check_nova_post_methods_in_zones(): bool {

        return ( new WoocommerceShippingZoneMethod() )->check_nova_post_methods();
    }

    /**
     * Check if the given currency is supported.
     *
     * @param string $currency Currency code to check.
     * @return bool True if currency is supported, false otherwise.
     */
    public static function is_supported_currency( string $currency ): bool {
        // @codingStandardsIgnoreStart
        return in_array(
            $currency,
            CurrencyHelper::get_supported_currencies()
        );
        // @codingStandardsIgnoreEnd
    }

    /**
     * Check if phone number is required at checkout.
     *
     * @return bool True if phone number is required, false otherwise.
     */
    public static function is_checkout_phone_required(): bool {
        return get_option( 'woocommerce_checkout_phone_field' ) === 'required';
    }

    /**
     * Check if current page is Nova Post settings page.
     *
     * @return bool True if current page is Nova Post settings, false otherwise.
     */
    public static function is_nova_post_settings_page(): bool {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
        $path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : ''; // phpcs:ignore
        return 'wc-admin' === $page && '/nova-post-settings' === $path;
    }

    /**
     * Check if current page is WooCommerce shipping settings page.
     *
     * @return bool True if current page is WooCommerce shipping settings, false otherwise.
     */
    public static function is_shipping_settings_page(): bool {
        if ( ! is_admin() ) {
            return false;
        }

        $page = filter_input( INPUT_GET, 'page', FILTER_DEFAULT );
        $tab  = filter_input( INPUT_GET, 'tab', FILTER_DEFAULT );

        return 'wc-settings' === $page && 'shipping' === $tab;
    }
}
