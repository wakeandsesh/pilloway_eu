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
     * Set insurance cost of parcels to match the total COD amount for COD orders.
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

        if ( 'cod' === $order->get_payment_method() && ShipmentApiService::COUNTRY_UA !== $settings->sender_country ) {
            $cod_amount = ShipmentCodService::calculate_order_cod_amount( $order );
            if ( $cod_amount > 0 ) {
                if ( 1 === count( $parcels ) ) {
                    $parcels[0]['insuranceCost'] = $cod_amount;
                } else {
                    $current_insurance = 0.0;
                    foreach ( $parcels as $parcel ) {
                        $current_insurance += (float) ( $parcel['insuranceCost'] ?? 0 );
                    }
                    $diff                        = $cod_amount - $current_insurance;
                    $parcels[0]['insuranceCost'] = max( 1.0, round( ( (float) $parcels[0]['insuranceCost'] ) + $diff, 2 ) );
                }
            }
        }

        return $parcels;
    }
}
