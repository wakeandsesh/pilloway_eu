<?php
/**
 * Shipment status helper for Nova Post WooCommerce plugin.
 *
 * @package NovaPostForWoocommerce\Helpers
 * @since   1.0.0
 */

namespace NovaPostForWoocommerce\Helpers;

use NovaPostForWoocommerce\Logger\Logger;

/**
 * Shipment status helper class.
 */
class ShipmentStatusHelper {

    /**
     * Get grouped custom status label by Nova Post API status code.
     *
     * Maps raw Nova Post API codes to human-readable custom status groups.
     * Logs a warning when an unmapped code is encountered so the table can
     * be extended in future releases.
     *
     * @param int $status_code The Nova Post API status code.
     *
     * @return string The translated custom status label.
     */
    public static function get_text_status( $status_code ) {
        $status_code = (int) $status_code;

        $status_groups = array(
            'not_yet_shipped'     => array( 1, 999 ),
            'in_transit'          => array( 4, 5, 6, 13, 16, 17, 19, 30, 31, 101, 104, 110, 114, 115, 119, 120, 121, 122, 125, 126, 127, 128, 149, 197, 198, 199 ),
            'delivered'           => array( 7, 8 ),
            'received'            => array( 9, 10, 11, 106 ),
            'delivery_failed'     => array( 99, 105, 111, 112, 113, 116, 117, 118, 123, 130, 141, 144, 155 ),
            'refused'             => array( 103 ),
            'returning_to_sender' => array( 102, 131, 132 ),
            'deleted'             => array( 2 ),
        );

        $label_map = array(
            'not_yet_shipped'     => __( 'Not yet shipped', 'nova-post-for-woocommerce' ),
            'in_transit'          => __( 'In transit to recipient', 'nova-post-for-woocommerce' ),
            'delivered'           => __( 'Delivered successfully', 'nova-post-for-woocommerce' ),
            'received'            => __( 'Received by recipient', 'nova-post-for-woocommerce' ),
            'delivery_failed'     => __( 'Delivery failed - action required', 'nova-post-for-woocommerce' ),
            'refused'             => __( 'Refused by recipient', 'nova-post-for-woocommerce' ),
            'returning_to_sender' => __( 'Returning to sender', 'nova-post-for-woocommerce' ),
            'deleted'             => __( 'Shipment deleted', 'nova-post-for-woocommerce' ),
        );

        foreach ( $status_groups as $group => $codes ) {
            if ( in_array( $status_code, $codes, true ) ) {
                return $label_map[ $group ];
            }
        }

        Logger::write_log(
            'warning',
            'Unknown Nova Post shipment status code — add to ShipmentStatusHelper mapping.',
            array( 'status_code' => $status_code )
        );

        return __( 'Unknown status', 'nova-post-for-woocommerce' );
    }
}
