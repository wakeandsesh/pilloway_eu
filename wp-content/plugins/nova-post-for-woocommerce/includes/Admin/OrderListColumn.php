<?php
/**
 * NovaPostForWoocommerce Order List Column.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Admin;

use NovaPostForWoocommerce\Services\NovaPostOrderBlock;
use NovaPostForWoocommerce\Services\OrderService;
use NovaPostForWoocommerce\Services\ShipmentService;
use NovaPostForWoocommerce\Services\NovaPostStatusBlock;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * NovaPostForWoocommerce Order List Column Class
 *
 * Adds a custom "Nova Post" column to the WooCommerce orders list table
 */
class OrderListColumn {

    const COLUMN_SHIPMENT = 'npw_shipment';
    const COLUMN_STATUS   = 'npw_shipping_status';

    /**
     * Construct.
     */
    public function __construct() {
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_custom_column' ), 90 );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_custom_column' ), 90 );

        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'display_column_content' ), 90, 2 );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'display_column_content' ), 90, 2 );
    }

    /**
     * Add custom column to orders list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_custom_column( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $label ) {
            if ( 'order_total' === $key && ! isset( $columns[ self::COLUMN_STATUS ] ) ) {
                $new_columns[ self::COLUMN_STATUS ] = __( 'Shipping Status', 'nova-post-for-woocommerce' );
            }

            $new_columns[ $key ] = $label;

            if ( 'shipping_address' === $key && ! isset( $columns[ self::COLUMN_SHIPMENT ] ) ) {
                $new_columns[ self::COLUMN_SHIPMENT ] = __( 'Nova Post Shipment', 'nova-post-for-woocommerce' );
            }
        }

        if ( ! isset( $new_columns[ self::COLUMN_SHIPMENT ] ) ) {
            $new_columns[ self::COLUMN_SHIPMENT ] = __( 'Nova Post Shipment', 'nova-post-for-woocommerce' );
        }

        if ( ! isset( $new_columns[ self::COLUMN_STATUS ] ) ) {
            $new_columns[ self::COLUMN_STATUS ] = __( 'Shipping Status', 'nova-post-for-woocommerce' );
        }

        return $new_columns;
    }

    /**
     * Display content for the custom column.
     *
     * @param string $column Column name.
     * @param mixed  $order Order ID.
     */
    public function display_column_content( $column, $order ) {
        if ( ! $order && in_array( $column, array( self::COLUMN_SHIPMENT, self::COLUMN_STATUS ), true ) ) {
            echo '-';
            return;
        }

        switch ( $column ) {
            case self::COLUMN_SHIPMENT:
                $this->render_nova_post_column( $order );
                break;
            case self::COLUMN_STATUS:
                $this->render_shipping_status_column( $order );
                break;
        }
    }

    /**
     * Method to render Nova Post column.
     *
     * @param WC_Order $order WooCommerce order.
     * @return void
     */
    private function render_nova_post_column( WC_Order $order ) {
        if ( ! OrderService::has_nova_post_shipping( $order ) ) {
            echo '-';
            return;
        }

        NovaPostOrderBlock::render(
            array(
                'order'             => $order,
                'shipment_info'     => ShipmentService::get_order_shipment_info( $order ),
                'container_classes' => 'npw-content',
                'hide_status_block' => true,
            )
        );
    }

    /**
     * Method to render Nova Post status.
     *
     * @param WC_Order $order WooCommerce order.
     * @return void
     */
    private function render_shipping_status_column( WC_Order $order ) {
        echo '<div class="npw-content">';
        NovaPostStatusBlock::render(
            $order,
            ShipmentService::get_order_shipment_info( $order )
        );
        echo '</div>';
    }
}
