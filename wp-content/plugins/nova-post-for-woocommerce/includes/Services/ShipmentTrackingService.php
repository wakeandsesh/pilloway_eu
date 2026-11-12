<?php
/**
 * Shipment Tracking Service.
 *
 * @package NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Factories\NovaPost;
use Exception;

/**
 * Handles all logic for retrieving shipment tracking information.
 */
class ShipmentTrackingService {

    /**
     * Get shipment tracking details for a given order.
     *
     * @param int $order_id The ID of the order.
     * @return array|null An array of tracking details or null if not found.
     */
    public static function get_order_shipment_tracking( int $order_id ): array|null {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return null;
        }

        $shipment_id = $order->get_meta( '_nova_post_shipment_id' );

        if ( ! $shipment_id ) {
            return null;
        }

        try {
            $api_response = NovaPost::get_client()->shipments()->tracking( array( 'ids' => array( $shipment_id ) ) );
            return $api_response['items'][0] ?? null;
        } catch ( Exception $e ) {
            return null;
        }
    }

    /**
     * Get shipment tracking history for a given order.
     *
     * @param int $order_id The ID of the order.
     * @return array|null An array of tracking history or null if not found.
     */
    public static function get_order_shipment_tracking_history( int $order_id ): array|null {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return null;
        }

        $shipment_id = $order->get_meta( '_nova_post_shipment_id' );

        if ( ! $shipment_id ) {
            return null;
        }

        try {
            $api_response = NovaPost::get_client()->shipments()->trackingHistory( array( 'ids' => array( $shipment_id ) ) );
            return $api_response['items'][0] ?? null;
        } catch ( Exception $e ) {
            return null;
        }
    }
}
