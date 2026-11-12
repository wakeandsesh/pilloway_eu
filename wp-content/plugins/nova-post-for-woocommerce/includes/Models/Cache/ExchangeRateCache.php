<?php
/**
 * NovaPostShippingMethod class
 *
 * @package NovaPostForWoocommerce\Shipping
 */

namespace NovaPostForWoocommerce\Models\Cache;

/**
 * Cache for currency exchange rate coefficients
 */
class ExchangeRateCache extends AbstractCache {

    /**
     * Cache exchange rates for 1 hour
     */
    protected const CACHE_LIFETIME = 60 * 60;

    /**
     * Separate namespace from shipping & division cache
     */
    protected const CACHE_KEY_SUFFIX = 'exchg_rate_';
}
