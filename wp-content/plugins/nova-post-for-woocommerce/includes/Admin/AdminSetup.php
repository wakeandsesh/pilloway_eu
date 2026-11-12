<?php
/**
 * Admin Setup.
 *
 * Handles admin page registration for Nova Post settings.
 *
 * @package NovaPostForWoocommerce\Admin
 * @since   1.3.0
 */

namespace NovaPostForWoocommerce\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * NovaPostForWoocommerce Setup Class
 *
 * Registers admin pages and menu items in WooCommerce admin.
 *
 * @since 1.3.0
 */
class AdminSetup {

    /**
     * Constructor.
     *
     * @since 1.3.0
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
    }

    /**
     * Register Nova Post settings page in WooCommerce admin.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function register_settings_page() {
        if ( ! function_exists( 'wc_admin_register_page' ) ) {
            return;
        }

        wc_admin_register_page(
            array(
                'id'     => 'nova-post-settings',
                'title'  => __( 'Nova Post Settings', 'nova-post-for-woocommerce' ),
                'parent' => 'woocommerce',
                'path'   => '/nova-post-settings',
            )
        );
    }
}
