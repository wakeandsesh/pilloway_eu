<?php
/**
 * Shipment Exception.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Exceptions;

/**
 * Custom exception class for shipment-related errors.
 */
class ShipmentException extends NovaPostException {
    const NOT_FOUND = 'shipment_not_found';

    /**
     * Create a ShipmentException indicating a missing shipment.
     *
     * @param array $data Optional additional contextual data.
     * @return self
     */
    public static function notFound( array $data = array() ): self {
        return new self( __( 'Shipment not found for the order', 'nova-post-for-woocommerce' ), 404, $data );
    }
}
