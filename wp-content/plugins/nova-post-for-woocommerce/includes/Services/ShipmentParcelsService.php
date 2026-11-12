<?php
/**
 * Shipment Parcels Service
 *
 * @package NovaPostForWoocommerce\Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Services\BoxPackerService;

/**
 * Shipment Parcels Service
 */
class ShipmentParcelsService {
    /**
     * Prepare parcels data for the shipment.
     *
     * @param WC_Order         $order The WooCommerce order object.
     * @param NovaPostSettings $settings The Nova Post settings object.
     * @return array Prepared parcels data.
     */
    public static function prepare_parcels_data( $order, $settings ) {
        $packer  = new BoxPackerService( $settings );
        $parcels = $packer->pack_and_map_from_shipment_items( $order->get_items(), $order->get_currency(), true );
        $parcels = ParcelModifier::apply( $parcels, $order, $settings );

        return $parcels;
    }
}
