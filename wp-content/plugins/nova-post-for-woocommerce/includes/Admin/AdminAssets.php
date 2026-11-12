<?php
/**
 * Admin Assets Management
 *
 * Handles enqueuing of all admin scripts and styles for Nova Post WooCommerce plugin.
 *
 * @package NovaPostForWoocommerce\Admin
 * @since   1.3.0
 */

namespace NovaPostForWoocommerce\Admin;

use NovaPostForWoocommerce\Traits\AssetLoaderTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Assets Management
 *
 * Centralized management of all admin assets including scripts, styles, and translations.
 *
 * @since 1.3.0
 */
class AdminAssets {

    use AssetLoaderTrait;

    /**
     * Constructor.
     *
     * @since 1.3.0
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Enqueue admin assets based on current admin page.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function enqueue_admin_assets() {
        // Enqueue settings page assets.
        if ( $this->is_settings_page() ) {
            $this->enqueue_settings_assets();
        }

        // Enqueue order list assets.
        if ( $this->is_orders_list_page() ) {
            $this->enqueue_orders_list_assets();

            // Add custom scripts for list page.
            $this->enqueue_order_page_app_script();
        }

        // Enqueue order edit assets.
        if ( $this->is_order_page() ) {
            $this->enqueue_order_assets();
        }
    }

    /**
     * Enqueue assets for settings page.
     *
     * @since 1.3.0
     *
     * @return void
     */
    private function enqueue_settings_assets() {
        $this->register_script_with_translations( 'nova-post-settings', 'admin.js' );
        $this->register_style( 'nova-post-admin-styles', 'admin.css' );
    }

    /**
     * Enqueue assets for orders list page.
     *
     * @since 1.3.0
     *
     * @return void
     */
    private function enqueue_orders_list_assets() {

        $this->register_script_with_translations( 'nova-post-shipment', 'shipment.js' );
        $this->register_style( 'nova-post-shipment', 'shipment.css' );
        $this->register_style( 'nova-post-admin-styles', 'admin.css' );
    }

    /**
     * Enqueue assets for order edit page.
     *
     * @since 1.3.0
     *
     * @return void
     */
    private function enqueue_order_assets() {
        $this->register_script_with_translations( 'npforwc-order-change-division', 'widget-order-edit.js' );
        $this->register_style( 'npforwc-order-change-division', 'widget-order-edit.css' );
        $this->register_script_with_translations( 'nova-post-shipment', 'shipment.js' );
        $this->register_style( 'nova-post-shipment', 'shipment.css' );
        $this->register_style( 'nova-post-admin-styles', 'admin.css' );
    }

    /**
     * Check if current page is Nova Post settings page.
     *
     * @since 1.3.0
     *
     * @return bool True if settings page, false otherwise.
     */
    private function is_settings_page() {
        if ( ! method_exists( 'Automattic\WooCommerce\Admin\PageController', 'is_admin_or_embed_page' ) ||
            ! \Automattic\WooCommerce\Admin\PageController::is_admin_or_embed_page()
        ) {
            return false;
        }

        // Check if we're on the Nova Post settings page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset( $_GET['page'] ) && 'wc-admin' === $_GET['page']
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
            && isset( $_GET['path'] ) && '/nova-post-settings' === $_GET['path'];
    }

    /**
     * Check if current page is orders list page (HPOS).
     *
     * @since 1.3.0
     *
     * @return bool True if orders list page, false otherwise.
     */
    private function is_orders_list_page() {
        $screen = get_current_screen();

        // Classic order list page.
        if ( 'shop_order' === $screen->id ) {
            return true;
        }

        // HPOS order list page.
        if ( $screen && 'woocommerce_page_wc-orders' === $screen->id ) {
            return true;
        }

        return false;
    }

    /**
     * Check if current page is order edit page.
     *
     * @since 1.3.0
     *
     * @return bool True if order edit page, false otherwise.
     */
    private function is_order_page() {
        $screen = get_current_screen();

        if ( ! $screen ) {
            return false;
        }

        // Classic admin: shop_order post type edit screen.
        if ( 'shop_order' === $screen->id ) {
            return true;
        }

        // HPOS: wc-orders admin page with order parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'woocommerce_page_wc-orders' === $screen->id && isset( $_GET['id'] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Enqueue order page app script for orders list page
     */
    private function enqueue_order_page_app_script() {
        $asset_file = plugin_dir_path( NPFORWC_PLUGIN_FILE_PATH ) . 'build/orderPageApp.asset.php';
        $asset      = file_exists( $asset_file ) ? include $asset_file : array(
            'dependencies' => array(),
            'version'      => NPFORWC_PLUGIN_VERSION,
        );

        wp_enqueue_script(
            'nova-post-order-page-app',
            plugin_dir_url( NPFORWC_PLUGIN_FILE_PATH ) . 'build/orderPageApp.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_set_script_translations(
            'nova-post-order-page-app',
            'nova-post-for-woocommerce',
            NPFORWC_PLUGIN_PATH . 'languages/'
        );

        wp_enqueue_style(
            'nova-post-order-page-app-style',
            plugin_dir_url( NPFORWC_PLUGIN_FILE_PATH ) . 'build/style-orderPageApp.css',
            array(),
            $asset['version']
        );
    }
}
