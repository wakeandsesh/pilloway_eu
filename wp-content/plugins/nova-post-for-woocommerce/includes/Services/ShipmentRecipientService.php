<?php
/**
 * Shipment Recipient Service for preparing recipient data.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodAddress;
use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodDivision;

/**
 * Class ShipmentRecipientService.
 */
class ShipmentRecipientService {

    /**
     * Prepare recipient data.
     *
     * @param WC_Order $order WooCommerce order.
     * @return array
     */
    public static function prepare_recipient_data( $order ): array {
        $recipient_data = array(
            'companyTin'  => '',
            'companyName' => '',
            'phone'       => ! empty( $order->get_shipping_phone() ) ? $order->get_shipping_phone() : $order->get_billing_phone(),
            'email'       => $order->get_billing_email(),
            'name'        => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'countryCode' => $order->get_shipping_country(),
            'ioss'        => null,
        );

        $shipping_method_id = OrderService::get_order_shipping_method_id( $order );

        if ( NovaPostShippingMethodDivision::METHOD_ID === $shipping_method_id ) {
            $recipient_data['divisionId'] = $order->get_meta( '_nova_post_division_id' );
        }

        if ( NovaPostShippingMethodAddress::METHOD_ID === $shipping_method_id ) {
            $recipient_data['addressParts'] = self::fill_address_from_order( $order );
        }

        $recipient_data['registrationAddressRecipient'] = self::fill_address_from_order( $order );

        return $recipient_data;
    }

    /**
     * Fill address from order.
     *
     * @param WC_Order $order WooCommerce order.
     * @return array
     */
    private static function fill_address_from_order( $order ) {
        return array(
            'street'   => self::format_address_line( $order->get_shipping_address_1(), 100 ),
            'building' => '-',
            'city'     => $order->get_shipping_city(),
            'region'   => $order->get_shipping_state(),
            'postCode' => $order->get_shipping_postcode(),
            'flat'     => self::format_address_line( $order->get_shipping_address_2(), 10 ),
            'block'    => '',
            'note'     => '',
        );
    }

    /**
     * Format address line.
     *
     * @param string $address_line Address line.
     * @param int    $max_length   Max length.
     * @return string
     */
    private static function format_address_line( $address_line, $max_length ) {

        $address_line = str_replace( ',', ' ', $address_line );

        $address_line = preg_replace( '/\s+/', ' ', $address_line );

        $address_line = substr( $address_line, 0, $max_length );

        return $address_line;
    }
}
