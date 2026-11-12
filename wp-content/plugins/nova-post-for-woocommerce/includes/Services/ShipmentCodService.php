<?php
/**
 * Shipment COD Service.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Models\NovaPostSettings;

/**
 * Class ShipmentCodService.
 */
class ShipmentCodService {

    /**
     * Method to prepare COD data.
     *
     * @param mixed            $order WooCommerce Order.
     * @param NovaPostSettings $settings Nova Post settings.
     * @return array
     */
    public static function prepare_cod_data( $order, NovaPostSettings $settings ) {
        $is_flat_rate = self::is_flat_rate_order( $order, $settings );

        $amount = $is_flat_rate
            ? floatval( round( $order->get_total(), 2 ) )
            : floatval( round( $order->get_total() - $order->get_shipping_total(), 2 ) );

        $result = array(
            'shipmentParcelRowNumber' => null,
            'serviceCode'             => 'COD',
            'amount'                  => $amount,
            'contractNumber'          => null,
            'payerType'               => 'Recipient',
            'additionalParameters'    => array(
                'cod' => array(
                    'cash'        => null,
                    'card'        => null,
                    'bankAccount' => array(
                        'amount'          => $amount,
                        'currencyCode'    => $order->get_currency(),
                        'bankAccountId'   => $settings->cod_bank_account_id,
                        'bankAccountName' => $settings->cod_bank_account_name,
                        'description'     => 'Order Id: ' . $order->get_order_number(),
                        'commissionPayer' => $settings->cod_fee_paid_seller ? 'Sender' : 'Recipient',
                    ),
                ),
            ),
        );

        return $result;
    }

    /**
     * Determines if the order uses a flat rate shipping method.
     *
     * @param mixed            $order WooCommerce Order.
     * @param NovaPostSettings $settings Nova Post settings.
     * @return bool
     */
    public static function is_flat_rate_order( $order, NovaPostSettings $settings ): bool {
        $shipping_methods = $order->get_shipping_methods();
        foreach ( $shipping_methods as $shipping_method ) {
            $method_id = $shipping_method->get_method_id();

            if ( ( 'nova_post_address' === $method_id ) && $settings->shipping_address_flat_rate ) {
                return true;
            } elseif ( ( 'nova_post_division' === $method_id ) && $settings->shipping_division_flat_rate ) {
                return true;
            }
        }

        return false;
    }
}
