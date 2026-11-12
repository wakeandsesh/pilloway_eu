<?php
/**
 * Order service for handling Nova Post order-related functionality.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

use WC_Order;
use NovaPostForWoocommerce\Models\UserStorage;
use Exception;
use NovaPostForWoocommerce\Logger\Logger;
use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodAddress;
use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodDivision;

/**
 * Class OrderService
 *
 * Handles order-related functionality for Nova Post shipping.
 */
class OrderService {

    /**
     * Add division information to order meta data.
     *
     * @param \WC_Order $order The WooCommerce order object.
     * @return void
     */
    public static function add_division_to_order( WC_Order $order ) {
        try {
            $division      = UserStorage::get_instance()->get( 'division' );
            $division_id   = UserStorage::get_instance()->get( 'division_id' );
            $division_type = UserStorage::get_instance()->get( 'division_type' );

            $order->update_meta_data( '_nova_post_division', $division );
            $order->update_meta_data( '_nova_post_division_id', $division_id );
            $order->update_meta_data( '_nova_post_division_type', $division_type );

            $order->save_meta_data();
        } catch ( Exception $e ) {
            Logger::write_log( \WC_Log_Levels::ERROR, $e->getMessage(), $order->get_id() );
        }
    }

    /**
     * Get the shipping method ID for an order.
     *
     * @param \WC_Order $order The WooCommerce order object.
     * @return string|null The shipping method ID or null if not found.
     */
    public static function get_order_shipping_method_id( $order ) {
        $shipping_methods = $order->get_shipping_methods();

        if ( $shipping_methods ) {
            $shipping_item = reset( $shipping_methods );
            return $shipping_item->get_method_id();
        }

        return null;
    }

    /**
     * Check if order has Nova Post shipping method.
     *
     * @param \WC_Order $order Order object.
     * @return bool True if order uses Nova Post shipping.
     */
    public static function has_nova_post_shipping( $order ) {
        $method_id = self::get_order_shipping_method_id( $order );

        $np_shipping_methods = array(
            NovaPostShippingMethodAddress::METHOD_ID,
            NovaPostShippingMethodDivision::METHOD_ID,
        );

        return in_array( $method_id, $np_shipping_methods, true );
    }

    /**
     * Check if order has Nova Post address-based shipping method.
     *
     * @param \WC_Order $order Order object.
     * @return bool True if order uses Nova Post address-based shipping.
     */
    public static function has_nova_post_division_shipping( $order ) {
        $method_id = self::get_order_shipping_method_id( $order );
        return NovaPostShippingMethodDivision::METHOD_ID === $method_id;
    }

    /**
     * Get the shipping phone number from the order.
     *
     * @param \WC_Order $order The WooCommerce order object.
     * @return string The billing phone number.
     */
    public static function get_order_shipping_phone( $order ) {
        return $order->get_billing_phone();
    }

    /**
     * Get the division ID from the order meta.
     *
     * @param \WC_Order $order The WooCommerce order object.
     * @return string The division ID.
     */
    public static function get_order_division_id( $order ) {
        return $order->get_meta( '_nova_post_division_id' );
    }

    /**
     * Get all division information from the order meta.
     *
     * @param \WC_Order $order The WooCommerce order object.
     * @return array An array containing division information.
     */
    public static function get_order_division_info( $order ) {
        return array(
            'division'      => $order->get_meta( '_nova_post_division' ),
            'division_id'   => $order->get_meta( '_nova_post_division_id' ),
            'division_type' => $order->get_meta( '_nova_post_division_type' ),
        );
    }
}
