<?php
/**
 * CustomsFeesCache class
 *
 * @package NovaPostForWoocommerce\Models\Cache
 */

namespace NovaPostForWoocommerce\Models\Cache;

/**
 * Cache for Customs Fees dictionary responses.
 */
class CustomsFeesCache extends AbstractCache {

    /**
     * Cache Key suffix.
     */
    protected const CACHE_KEY_SUFFIX = '_customs_fees_';

    /**
     * Cache custom fees for 1 hour
     */
    protected const CACHE_LIFETIME = 60 * 60;
}
