<?php
/**
 * Shipment COD Service.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Services\Cache\CachedExchangeRatesService;

/**
 * Class ShipmentCodService.
 */
class ShipmentCodService {

    /**
     * Calculate base COD amount from WooCommerce order total.
     *
     * @param mixed $order WooCommerce Order.
     * @return float
     */
    public static function calculate_order_cod_amount( $order ): float {
        return floatval( round( (float) $order->get_total(), 2 ) );
    }

    /**
     * Method to prepare COD data.
     *
     * @param mixed            $order WooCommerce Order.
     * @param NovaPostSettings $settings Nova Post settings.
     * @return array
     */
    public static function prepare_cod_data( $order, NovaPostSettings $settings ): array {
        $base_amount = self::calculate_order_cod_amount( $order );
        if ( $base_amount <= 0 ) {
            return array();
        }

        $order_currency = $order->get_currency();
        $cod_currency   = ! empty( $settings->cod_bank_account_currency )
            ? (string) $settings->cod_bank_account_currency
            : $order_currency;

        $amount              = $base_amount;
        $exchange_rate_error = false;

        if ( $cod_currency !== $order_currency ) {
            try {
                $rate = CachedExchangeRatesService::get_instance()->get_rate(
                    (string) $settings->sender_country,
                    $order_currency,
                    $cod_currency
                );

                if ( empty( $rate ) || $rate <= 0 ) {
                    $exchange_rate_error = true;
                } else {
                    // BR-16: Round down to two decimals.
                    $amount = floor( round( $base_amount * $rate * 100, 8 ) ) / 100;

                    if ( $amount <= 0 ) {
                        return array();
                    }
                }
            } catch ( \Throwable $e ) {
                $exchange_rate_error = true;
            }
        }

        // BR-25: Remove whitespace from bank credentials.
        $bank_account_id   = preg_replace( '/\s+/', '', (string) ( $settings->cod_bank_account_id ?? '' ) );
        $bank_account_name = preg_replace( '/\s+/', '', (string) ( $settings->cod_bank_account_name ?? '' ) );

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
                        'currencyCode'    => $cod_currency,
                        'bankAccountId'   => $bank_account_id,
                        'bankAccountName' => $bank_account_name,
                        'description'     => 'Order Id: ' . $order->get_order_number(),
                        'commissionPayer' => $settings->cod_fee_paid_seller ? 'Sender' : 'Recipient',
                    ),
                ),
            ),
        );

        if ( $exchange_rate_error ) {
            $result['exchange_rate_error'] = true;
        }

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
