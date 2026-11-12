<?php
/**
 * Admin Alerts
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Admin
 */

namespace NovaPostForWoocommerce\Admin;

use NovaPostForWoocommerce\Services\SettingsPageService;
use NovaPostForWoocommerce\Helpers\View;
use NovaPostForWoocommerce\Helpers\CurrencyHelper;
use NovaPostForWoocommerce\Models\NovaPostSettings;

defined( 'ABSPATH' ) || exit;

/**
 * AdminAlerts class.
 *
 * @class AdminAlerts
 */
class AdminAlerts {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_tooltip_assets' ) );
    }

    /**
     * Enqueue assets for tooltips.
     *
     * @return void
     */
    public function enqueue_tooltip_assets() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $show_unsupported_currency_warning = ! SettingsPageService::is_supported_currency( get_woocommerce_currency() );
        if ( $show_unsupported_currency_warning ) {
            wp_enqueue_script( 'jquery-tiptip' );
            wp_enqueue_style( 'woocommerce_admin_styles' );

            // Enqueue the tooltip script.
            wp_enqueue_script(
                'nova-post-tooltip',
                plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/tooltip-script.js',
                array( 'jquery', 'jquery-tiptip' ),
                filemtime( plugin_dir_path( dirname( __DIR__ ) ) . 'assets/js/tooltip-script.js' ),
                true
            );
        }
    }

    /**
     * Display admin notices.
     *
     * @return void
     */
    public function display_admin_notices() {

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $show_unsupported_currency_warning = ! SettingsPageService::is_supported_currency( get_woocommerce_currency() );
        $show_no_shipping_zone_warning     = ! SettingsPageService::check_nova_post_methods_in_zones();
        $show_phone_required_warning       = ! SettingsPageService::is_checkout_phone_required();

        $is_settings_page = SettingsPageService::is_nova_post_settings_page();
        $is_shipping_page = SettingsPageService::is_shipping_settings_page();

        if ( $show_unsupported_currency_warning ) {
            $currency_url         = admin_url( 'admin.php?page=wc-settings&tab=general' );
            $supported_currencies = implode( ', ', CurrencyHelper::get_supported_currencies() );
            $tooltip              = sprintf(
                '<span class="woocommerce-help-tip" data-tip="%s"></span>',
                // translators: %s: list of supported currencies.
                esc_attr( __( 'Supported currencies: ', 'nova-post-for-woocommerce' ) . $supported_currencies )
            );
            View::render(
                'alerts/admin-alert',
                array(
                    /* translators: 1: currency symbol, 2: tooltip with supported currencies, 3: link to WooCommerce settings */
                    'message'    => __( 'Attention! The current currency of your store (%1$s) is not supported by the Nova Post API. Please change it for the plugin to work correctly. <a href="%3$s">Set up currency</a>. %2$s', 'nova-post-for-woocommerce' ),
                    'args'       => array( get_woocommerce_currency(), $tooltip, $currency_url ),
                    'alert_type' => 'error',
                )
            );
        }

        if ( $show_no_shipping_zone_warning ) {
            $shipping_url = admin_url( 'admin.php?page=wc-settings&tab=shipping' );
            View::render(
                'alerts/admin-alert',
                array(
                    /* translators: %s: link to WooCommerce shipping settings */
                    'message'    => __( 'Please note: None of the Nova Post shipping methods have been added to your shipping zones. The plugin will not appear on the Checkout page until you configure the region. <a href="%s">Set up shipping zone</a>.', 'nova-post-for-woocommerce' ),
                    'args'       => array( $shipping_url ),
                    'alert_type' => 'error',
                )
            );
        }

        if ( $is_settings_page && $show_phone_required_warning ) {
            $checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
            $url              = $checkout_page_id ? admin_url( 'post.php?post=' . $checkout_page_id . '&action=edit' ) : admin_url( 'edit.php?post_type=page' );
            View::render(
                'alerts/admin-alert',
                array(
                    /* translators: %s: link to checkout page editor */
                    'message'    => __( 'To work correctly with the NovaPost API please set the "Phone" field as required on the checkout page for shipping address block. <a href="%s">Edit checkout page</a>.', 'nova-post-for-woocommerce' ),
                    'args'       => array( $url ),
                    'alert_type' => 'warning',
                )
            );
        }

        $is_api_key_available = NovaPostSettings::get_instance()->is_available();

        if ( $is_settings_page && ! $is_api_key_available ) {
            View::render(
                'alerts/admin-alert',
                array(
                    /* translators: %s: link to Help Center */
                    'message'    => __( 'You can get the Nova Post API key according to the  <a href="%s" target="_blank">instruction.</a>', 'nova-post-for-woocommerce' ),
                    'args'       => array( 'https://api-portal.novapost.com/en/help-center' ),
                    'alert_type' => 'error',
                    'class'      => 'nova-post-alert-api-key-missing',
                )
            );
        }

        if ( $is_shipping_page && ! $is_api_key_available ) {
            View::render(
                'alerts/admin-alert',
                array(
                    'message'    => __( 'Nova Post shipping methods cannot be added until at least one API key is configured', 'nova-post-for-woocommerce' ),
                    'args'       => array(),
                    'alert_type' => 'error',
                    'class'      => 'nova-post-alert-api-key-missing',
                )
            );
        }
    }
}
