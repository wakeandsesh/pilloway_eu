<?php
/**
 * Order sidebar meta box for Nova Post WooCommerce plugin.
 *
 * Provides a side meta box (classic + HPOS) on the order edit screen with
 * shipment creation, tracking, status, and printing controls similar to the
 * list table column but contextual to a single order.
 *
 * @package NovaPostForWoocommerce\Admin
 * @since   1.3.0
 */

namespace NovaPostForWoocommerce\Admin;

use NovaPostForWoocommerce\Services\NovaPostOrderBlock;
use NovaPostForWoocommerce\Services\OrderService;
use NovaPostForWoocommerce\Services\ShipmentService;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a Nova Post meta box to the WooCommerce order edit sidebar.
 *
 * Handles both classic (posts table) and HPOS order screens, rendering
 * shipment actions and status information.
 *
 * @since 1.3.0
 */
class OrderSidebarMetaBox {

    /**
     * Register hooks for adding the meta box on classic and HPOS order screens.
     *
     * @since 1.3.0
     */
    public function __construct() {
        // Classic Orders screen.
        add_action( 'add_meta_boxes_shop_order', array( $this, 'register_meta_box' ), 10 );

        // HPOS (High-Performance Order Storage) screen.
        add_action( 'add_meta_boxes', array( $this, 'register_meta_box_hpos' ), 10, 2 );
    }

    /**
     * Register meta box for classic (post type) order edit screen.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function register_meta_box() {
        add_meta_box(
            'npw_order_sidebar',
            __( 'Nova Post', 'nova-post-for-woocommerce' ),
            array( $this, 'render_meta_box' ),
            'shop_order',
            'side',
            'high'
        );
    }

    /**
     * Register meta box for HPOS (custom order tables) order edit screen.
     *
     * @param string $post_type Screen post type identifier.
     * @param mixed  $post      Post object / order context (unused).
     *
     * @since  1.3.0
     * @return void
     */
    public function register_meta_box_hpos( $post_type, $post ) {
        // Parameter $post is required by the action hook signature but not used here.
        unset( $post );
        if ( 'shop_order' !== $post_type
            && 'woocommerce_page_wc-orders' !== $post_type
        ) {
            return;
        }

        add_meta_box(
            'npw_order_sidebar',
            __( 'Nova Post', 'nova-post-for-woocommerce' ),
            array( $this, 'render_meta_box' ),
            null,
            'side',
            'high'
        );
    }

    /**
     * Render meta box contents for a specific order.
     *
     * @param \WP_Post|mixed $post WP post object or HPOS order wrapper.
     *
     * @since  1.3.0
     * @return void
     */
    public function render_meta_box( $post ) {
        $order_id = is_object( $post ) && isset( $post->ID )
            ? (int) $post->ID
            : ( isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading order ID only.
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            echo '-';
            return;
        }

        $has_shipping_method = OrderService::has_nova_post_shipping( $order );
        if ( ! $has_shipping_method ) {
            echo '-';
            return;
        }

        $shipment_info = ShipmentService::get_order_shipment_info( $order );

        NovaPostOrderBlock::render(
            array(
                'order'             => $order,
                'shipment_info'     => $shipment_info,
                'container_classes' => 'npw-content --order-meta-box',
            )
        );
    }
}
