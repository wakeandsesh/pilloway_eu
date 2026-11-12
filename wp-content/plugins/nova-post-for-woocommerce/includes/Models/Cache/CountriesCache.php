<?php
/**
 * CountriesCache class
 *
 * @package NovaPostForWoocommerce\Models\Cache
 */

namespace NovaPostForWoocommerce\Models\Cache;

/**
 * Cache for Countries dictionary responses.
 */
class CountriesCache extends AbstractCache {

    /**
     * Cache Key suffix.
     */
    protected const CACHE_KEY_SUFFIX = '_countries_';

    /**
     * Cache countries for 30 days.
     */
    protected const CACHE_LIFETIME = 30 * 24 * 60 * 60;
}
