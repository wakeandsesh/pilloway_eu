<?php
/**
 * Shipment Invoice Service
 *
 * Handles the preparation of invoice data for international shipments.
 *
 * @package NovaPostForWoocommerce\Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Adapters\ProductAdapter;
use NovaPostForWoocommerce\Helpers\CountriesHelper;
use NovaPostForWoocommerce\Models\NovaPostSettings;

/**
 * Class ShipmentInvoiceService
 *
 * Provides functionality to prepare invoice data for international shipments.
 */
class ShipmentInvoiceService {

    /**
     * Prepare invoice data for international shipments.
     *
     * @param \WC_Order        $order    The WooCommerce order object.
     * @param NovaPostSettings $settings The plugin settings.
     *
     * @return array|null Array of invoice data or null if not required.
     */
    public static function prepare_invoice_data( $order, $settings ) {
        if ( ! self::is_invoice_required( $settings->sender_country, $order->get_shipping_country() ) ) {
            return null;
        }

        $items = array();

        foreach ( $order->get_items() as $item ) {
            $product         = $item->get_product();
            $product_adapter = new ProductAdapter( $product );
            $unit_price      = (float) ( $item->get_total() / $item->get_quantity() );

            $items[] = array(
                'id'                => $item->get_id(),
                'hsCode'            => ShipmentHsCodeService::get_product_hs_code( $product, $settings, $order ),
                'name'              => $product->get_name(),
                'nameEng'           => self::sanitize_product_name( $product ),
                'actualWeight'      => $product_adapter->get_weight_in_grams(),
                'measurementCode'   => 'pieces',
                'amount'            => $item->get_quantity(),
                'cost'              => round( $unit_price, 2 ),
                'material'          => '',
                'materialEng'       => '',
                'madeInCountryCode' => '',
            );
        }

        return array(
            'incoterm'       => 'DAP',
            'exportReason'   => 'Selling',
            'currency'       => get_woocommerce_currency(),
            'customerNumber' => $order->get_order_number(),
            'cost'           => self::items_total_by_type( $items, 'cost' ),
            'items'          => $items,
        );
    }

    /**
     * Determines if an invoice is required for the shipment.
     *
     * @param string $sender_country    The sender's country code.
     * @param string $recipient_country The recipient's country code.
     *
     * @return bool True if an invoice is required, false otherwise.
     */
    public static function is_invoice_required( string $sender_country, string $recipient_country ): bool {
        if ( $sender_country === $recipient_country ) {
            return false; // Domestic shipment.
        }

        if (
            CountriesHelper::is_eu_country( $sender_country ) &&
            CountriesHelper::is_eu_country( $recipient_country )
        ) {
            return false; // Intra-EU shipment.
        }

        return true;
    }

    /**
     * Calculate total item price.
     *
     * @param array  $items      The items.
     * @param string $price_type Price type.
     *
     * @return float
     */
    private static function items_total_by_type( array $items, string $price_type ): float {
        $total = 0.0;

        foreach ( $items as $item ) {
            $total += $item[ $price_type ] * $item['amount'];
        }

        return round( $total, 2 );
    }

    /**
     * Sanitize product name for invoice.
     *
     * @param \WC_Product $product Product object.
     *
     * @return string
     */
    private static function sanitize_product_name( $product ): string {
        $english_name = $product->get_meta( '_english_name' );

        if ( '' !== $english_name ) {
            return sanitize_title( $english_name );
        }

        return sanitize_title( $product->get_name() );
    }
}
