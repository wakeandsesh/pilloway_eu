<?php
/**
 * Nova Post Widget Integration.
 *
 * Handles integration with WooCommerce Blocks and enqueuing of frontend widget assets.
 *
 * @package NovaPostForWoocommerce\Widget
 * @since   1.3.0
 */

namespace NovaPostForWoocommerce\Widget;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use NovaPostForWoocommerce\Traits\AssetLoaderTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Class for integrating with WooCommerce Blocks
 *
 * @since 1.3.0
 */
class NovaPostWidgetIntegration implements IntegrationInterface {

    use AssetLoaderTrait;

    /**
     * Returns the name of the integration.
     *
     * @since 1.3.0
     *
     * @return string
     */
    public function get_name() {
        return 'nova-post-for-woocommerce-widget';
    }

    /**
     * Initializes the integration.
     *
     * Registers scripts and styles for both block-based and classic checkouts.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function initialize() {
        // Register Nova Post SDK script.
        wp_register_script(
            'nova-post-sdk',
            'https://integration-widget.novapost.com/sdk.min.js',
            array(),
            '1.0.0',
            false
        );

        // Register block checkout widget script.
        $this->register_script_with_translations(
            'npforwc-widget-script',
            'widget.js',
            array( 'nova-post-sdk' ),
            false // Don't enqueue, let WooCommerce Blocks handle it.
        );

        // Register classic checkout widget script.
        $this->register_script_with_translations(
            'npforwc-widget-classic',
            'widget-classic-checkout.js',
            array( 'nova-post-sdk' ),
            false // Don't enqueue yet.
        );

        // Register widget styles.
        $this->register_style(
            'nova-post-widget-style',
            'widget.css',
            array(),
            false // Don't enqueue yet.
        );

        // Hook to enqueue assets on appropriate pages.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_for_classic_checkout' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_styles' ) );
    }

    /**
     * Enqueue styles for frontend checkout and cart pages.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function enqueue_frontend_styles() {
        if ( is_checkout() || is_cart() ) {
            wp_enqueue_style( 'nova-post-widget-style' );
        }
    }

    /**
     * Enqueue styles in block editor.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function enqueue_editor_styles() {
        wp_enqueue_style( 'nova-post-widget-style' );
    }

    /**
     * Returns script handles for WooCommerce Blocks.
     *
     * @since 1.3.0
     *
     * @return string[]
     */
    public function get_script_handles() {
        return array( 'npforwc-widget-script' );
    }

    /**
     * Returns editor script handles for WooCommerce Blocks.
     *
     * @since 1.3.0
     *
     * @return string[]
     */
    public function get_editor_script_handles() {
        return array( 'npforwc-widget-script' );
    }

    /**
     * Returns script data for WooCommerce Blocks.
     *
     * @since 1.3.0
     *
     * @return array
     */
    public function get_script_data() {
        return array();
    }

    /**
     * Enqueue assets for classic checkout.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function enqueue_for_classic_checkout() {
        if ( is_checkout() && class_exists( 'WooCommerce' ) && WC()->customer ) {
            wp_enqueue_script( 'npforwc-widget-classic' );
            wp_enqueue_style( 'nova-post-widget-style' );
        }
    }
}
