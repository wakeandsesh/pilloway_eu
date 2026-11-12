<?php
/**
 * Order repository service.
 *
 * @package NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Database\Repositories;

use NovaPostForWoocommerce\Models\Constants\OrderMetaConstants;
use WC_Order;
use NovaPostForWoocommerce\Exceptions\ShipmentException;

/**
 * Handles data access operations for WooCommerce orders.
 *
 * phpcs:disable WordPress.Security.EscapeOutput
 */
class OrderRepository {
    /**
     * Return order IDs that have a Nova Post shipment attached.
     *
     * @param int[] $order_ids The order IDs to check.
     * @return int[] The order IDs that have a Nova Post shipment attached.
     */
    public static function get_order_ids_with_shipment( array $order_ids ): array {
        $result = array();

        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order && $order->get_meta( OrderMetaConstants::NP_SHIPMENT_ID ) ) {
                $result[] = $order_id;
            }
        }

        return $result;
    }

    /**
     * Return a WooCommerce order object by ID or throw an exception.
     *
     * @param int $order_id The order ID to retrieve.
     * @return WC_Order The WooCommerce order object.
     * @throws ShipmentException If the order is not found.
     */
    public static function get_order_or_fail( int $order_id ): WC_Order {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            throw new ShipmentException( __( 'Order not found', 'nova-post-for-woocommerce' ), 404 );
        }
        return $order;
    }

    /**
     * Get multiple orders by their IDs.
     *
     * @param int[] $order_ids Array of order IDs.
     * @return WC_Order[] Array of WC_Order objects.
     */
    public static function get_orders_by_ids( array $order_ids ): array {
        return wc_get_orders(
            array(
                'post__in' => $order_ids,
                'limit'    => -1,
            )
        );
    }

    /**
     * Assign pickup details to given orders.
     *
     * @param WC_Order[] $orders Array of WooCommerce orders.
     * @param object     $pickup The pickup object.
     * @return void
     */
    public static function assign_pickup( array $orders, object $pickup ): void {
        foreach ( $orders as $order ) {
            $order->update_meta_data( OrderMetaConstants::NP_PICKUP_ID, $pickup->id );
            $order->update_meta_data( OrderMetaConstants::NP_PICKUP_NUMBER, $pickup->number );
            $order->save_meta_data();
        }
    }
}
