<?php
/**
 * Shipment service for handling Nova Post shipments
 *
 * @package NovaPostForWoocommerce
 * @since 1.0.0
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Factories\NovaPost;
use Exception;
use NovaPostForWoocommerce\Helpers\CountriesHelper;
use NovaPostForWoocommerce\Models\NovaPostSettings;

/**
 * Handles shipment-related functionality for Nova Post integration.
 */
class ShipmentService {
    const TRACKING_URL_BASE      = 'https://novapost.com/';
    const STATUS_READY_TO_SHIP   = 'ReadyToShip';
    const VALID_PAYMENT_STATUSES = array( 'Paid', 'ContractAfterPayment' );

    private const PAYMENT_STATUS_PRIORITY = array( 'NeedPay', 'Paid', 'Holded', 'ContractAfterPayment', 'Exempt' );

    /**
     * Get shipment (waybill) information for a specific order.
     *
     * @param \WC_Order $order The order object.
     * @return array|null Shipment information or null if order not found.
     */
    public static function get_order_shipment_info( $order ): ?array {
        if ( ! $order ) {
            return null;
        }

        $shipment_api_response = $order->get_meta( '_nova_post_shipment_api_response' );

        return array(
            'track_number'        => $order->get_meta( '_nova_post_shipment_number' ),
            'tracking_url'        => self::get_tracking_url( $order, 'sender' ),
            'shipment_id'         => $order->get_meta( '_nova_post_shipment_id' ),
            'last_status'         => $order->get_meta( '_nova_post_shipment_status' ),
            'last_status_code'    => $order->get_meta( '_nova_post_shipment_status_code' ),
            'shipment_errors'     => $shipment_api_response['errors'] ?? $order->get_meta( '_nova_post_shipment_errors' ),
            'shipment_created_at' => $shipment_api_response['createdAt'] ?? null,
            'shipment_cost'       => (float) ( $shipment_api_response['cost'] ?? 0 ),
        );
    }

    /**
     * Get shipment information for a specific order by its ID.
     *
     * @param int $order_id The order ID.
     * @return array|null Shipment information or null if order not found.
     */
    public static function get_order_shipment_info_by_order_id( $order_id ): ?array {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return null;
        }

        return self::get_order_shipment_info( $order );
    }

    /**
     * Get the current status of a shipment for a specific order.
     *
     * @param int $order_id The order ID.
     * @return array|null Shipment status information or null if order not found.
     */
    public static function get_order_shipment_status( $order_id ): ?array {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return null;
        }

        $shipment_id = $order->get_meta( '_nova_post_shipment_id' );

        $current_status = null;

        if ( $shipment_id ) {
            try {
                $api_response   = NovaPost::get_client()->shipments()->get( array( 'ids' => array( $shipment_id ) ) );
                $current_status = $api_response['items'][0]['status'] ?? null;
                $order->update_meta_data( '_nova_post_shipment_status', $current_status );
                $order->save_meta_data();
            } catch ( Exception $e ) {
                $current_status = 'unknown';
            }
        }

        $shipment_api_response = $order->get_meta( '_nova_post_shipment_api_response' );

        return array(
            'track_number'        => $order->get_meta( '_nova_post_shipment_number' ),
            'shipment_id'         => $shipment_id,
            'current_status'      => $current_status,
            'last_status'         => $order->get_meta( '_nova_post_shipment_status' ),
            'shipment_errors'     => $shipment_api_response['errors'] ?? null,
            'shipment_created_at' => $shipment_api_response['createdAt'] ?? null,
        );
    }

    /**
     * Get tracking URL for a specific order.
     *
     * @param \WC_Order $order The WooCommerce order object.
     * @param string    $type  Type of tracking URL ('sender' or 'recipient').
     * @return string The tracking URL.
     */
    public static function get_tracking_url( $order, string $type = 'recipient' ): string {
        $track_number = $order->get_meta( '_nova_post_shipment_number' );
        if ( empty( $track_number ) ) {
            return self::TRACKING_URL_BASE;
        }

        // Get settings.
        $settings = NovaPostSettings::get_instance();

        // Determine country based on type.
        $country = ( 'sender' === $type )
            ? $settings->sender_country
            : $order->get_shipping_country();

        // Try to get locale for the primary country.
        $locale = CountriesHelper::get_country_locale( $country );

        // Fallback to sender country locale if recipient country has no locale.
        if ( ! $locale ) {
            $country = $settings->sender_country;
            $locale  = CountriesHelper::get_country_locale( $country );
        }

        // Final fallback if no locale found.
        if ( ! $locale ) {
            return self::TRACKING_URL_BASE;
        }

        return self::TRACKING_URL_BASE . $locale . '/tracking/' . $track_number;
    }

    /**
     * Check if the order has the same shipping country as the sender country.
     *
     * @param \WC_Order $order The WooCommerce order object.
     * @return bool True if shipping country matches sender country, false otherwise.
     */
    public static function is_local_delivery( $order ): bool {
        if ( ! $order ) {
            return false;
        }

        $settings         = NovaPostSettings::get_instance();
        $sender_country   = strtoupper( (string) $settings->sender_country );
        $shipping_country = strtoupper( (string) $order->get_shipping_country() );

        if ( '' === $sender_country || '' === $shipping_country ) {
            return false;
        }

        return $sender_country === $shipping_country;
    }

    /**
     * Check if the delivery is within the European Union between sender and recipient.
     *
     * @param \WC_Order $order The WooCommerce order object.
     * @return bool True when both sender and shipping countries are in the EU, false otherwise.
     */
    public static function is_eu_delivery( $order ): bool {
        if ( ! $order ) {
            return false;
        }

        $settings         = NovaPostSettings::get_instance();
        $sender_country   = strtoupper( (string) $settings->sender_country );
        $shipping_country = strtoupper( (string) $order->get_shipping_country() );

        if ( empty( $sender_country ) || empty( $shipping_country ) ) {
            return false;
        }

        $eu_countries = CountriesHelper::get_eu_countries();

        return in_array( $sender_country, $eu_countries, true ) && in_array( $shipping_country, $eu_countries, true );
    }

    /**
     * Determine shipment payment status based on shipment services.
     *
     * @param array $services Shipment services data.
     * @return string
     */
    public static function get_shipment_payment_status( array $services ): string {
        if ( empty( $services ) ) {
            return 'FreeOfCharge';
        }

        $statuses = wp_list_pluck( $services, 'paymentStatus' );

        foreach ( self::PAYMENT_STATUS_PRIORITY as $status ) {
            if ( in_array( $status, $statuses, true ) ) {
                return ( 'Holded' === $status ) ? 'Paid' : $status;
            }
        }

        return 'FreeOfCharge';
    }

    /**
     * Update order status to completed when shipment is created.
     *
     * @param \WC_Order $order The WooCommerce order object.
     */
    public static function shipment_created( \WC_Order $order ) {
        $settings = NovaPostSettings::get_instance();

        if ( ! $settings->order_workflow_auto_complete_orders ) {
            return;
        }

        if ( $order->has_status( array( 'completed', 'cancelled', 'refunded', 'failed' ) ) ) {
            return;
        }

        $order->update_status(
            'completed',
            __( 'Order completed automatically after shipment creation.', 'nova-post-for-woocommerce' )
        );
    }
}
