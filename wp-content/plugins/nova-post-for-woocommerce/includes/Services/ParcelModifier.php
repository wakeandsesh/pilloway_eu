<?php
/**
 * Parcel Modifier Service
 *
 * @package NovaPostForWoocommerce\Services
 */

namespace NovaPostForWoocommerce\Services;

use WC_Order;
use NovaPostForWoocommerce\Models\NovaPostSettings;

/**
 * Class ParcelModifier
 *
 * Modifies parcels payload with additional business rules before sending to Nova Post API.
 */
class ParcelModifier {

    /**
     * Apply all necessary modifications to the packed parcels.
     *
     * @param array            $parcels  Array of API-ready parcels.
     * @param WC_Order         $order    WooCommerce order.
     * @param NovaPostSettings $settings Plugin settings.
     * @return array Modified parcels.
     */
    public static function apply( array $parcels, WC_Order $order, NovaPostSettings $settings ): array {
        $parcels = self::apply_cod_shipping_insurance( $parcels, $order, $settings );

        return $parcels;
    }

    /**
     * Add shipping total to the insurance cost of the first parcel for COD orders with flat rate.
     *
     * @param array            $parcels  Array of API-ready parcels.
     * @param WC_Order         $order    WooCommerce order.
     * @param NovaPostSettings $settings Plugin settings.
     * @return array Modified parcels.
     */
    private static function apply_cod_shipping_insurance( array $parcels, WC_Order $order, NovaPostSettings $settings ): array {
        if ( empty( $parcels ) ) {
            return $parcels;
        }

        if ( 'cod' === $order->get_payment_method() && ShipmentCodService::is_flat_rate_order( $order, $settings ) ) {
            $shipping_total = (float) $order->get_shipping_total();
            if ( $shipping_total > 0 ) {
                $parcels[0]['insuranceCost'] += $shipping_total;
            }
        }

        return $parcels;
    }
}
