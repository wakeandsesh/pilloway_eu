<?php
/**
 * Nova Post Settings Model.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Models\Traits;

trait MerchantIdentifier {
    /**
     * Get merchant domain with http/https value.
     */
    public function get_merchant_id(): string {
        return wp_parse_url( home_url(), PHP_URL_HOST );
    }
}
