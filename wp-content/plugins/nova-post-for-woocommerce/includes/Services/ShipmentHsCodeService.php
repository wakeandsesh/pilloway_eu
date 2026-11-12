<?php
/**
 * Shipment HS Code Service.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Factories\NovaPost;

/**
 * Handles logic for retrieving HS codes for products.
 */
class ShipmentHsCodeService {

    /**
     * The length of the HS code.
     */
    private const HS_CODE_LENGTH = 10;

    /**
     * Get HS tariff code for product.
     *
     * @param \WC_Product      $product  The WooCommerce product object.
     * @param NovaPostSettings $settings The NovaPost settings object.
     * @param \WC_Order        $order    The WooCommerce order object.
     * @return string
     */
    public static function get_product_hs_code( $product, $settings, $order ): string {
        $hs_code = $product->get_meta( '_hs_code' );

        if ( empty( $hs_code ) ) {
            $hs_code = $settings->default_hs_tariff_code;
        }

        if ( empty( $hs_code ) ) {
            $hs_code = self::get_hs_code_from_api( $product, $order );
        }

        $add_digits = self::HS_CODE_LENGTH - strlen( $hs_code );

        if ( $add_digits > 0 ) {
            $hs_code = str_pad( $hs_code, self::HS_CODE_LENGTH, '0', STR_PAD_RIGHT );
        }

        return $hs_code;
    }

    /**
     * Get HS code for a product from the API.
     *
     * @param \WC_Product $product The WooCommerce product object.
     * @param \WC_Order   $order   The WooCommerce order object.
     * @return string|null
     */
    public static function get_hs_code_from_api( $product, $order ) {
        $product_categories_taxonomy = wp_get_post_terms( $product->get_id(), 'product_cat' );

        $first_product_category = $product_categories_taxonomy[0]->name ?? null;

        return self::get_first_classifier_hs_code( $first_product_category, $order ) ?? '9999999999';
    }

    /**
     * Get the first classifier's HS code based on a keyword.
     *
     * @param string    $keyword The search keyword, usually a product category name.
     * @param \WC_Order $order The WooCommerce order object.
     * @return string|null
     */
    private static function get_first_classifier_hs_code( $keyword, $order ) {

        $params = array(
            'keyword'      => $keyword,
            'country-code' => $order->get_shipping_country(),
            'fuzzy'        => true,
            'locale'       => substr( get_locale(), 0, 2 ),
        );

        $classifiers = NovaPost::get_client()->dictionary()->cargoClassifiers( $params );

        return $classifiers['items'][0]['hsCode'] ?? null;
    }
}
