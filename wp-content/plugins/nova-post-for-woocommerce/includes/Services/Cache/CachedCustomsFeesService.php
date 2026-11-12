<?php
/**
 * Cached Customs Fees Service for Nova Post WooCommerce
 *
 * Handles fetching and caching of customs fees dictionary from Nova Post API.
 *
 * @package NovaPostForWoocommerce\Services\Cache
 */

namespace NovaPostForWoocommerce\Services\Cache;

use Exception;
use NovaPostForWoocommerce\Factories\NovaPost;
use NovaPostForWoocommerce\Models\Cache\CustomsFeesCache;

/**
 * Class CachedCustomsFeesService
 */
class CachedCustomsFeesService {

    /**
     * Cache for customs fees.
     *
     * @var CustomsFeesCache
     */
    private CustomsFeesCache $fees_cache;

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function get_instance(): self {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * FOR TESTS
     *
     * @param self|null $instance Instance to set.
     *
     * @return void
     */
    public static function set_instance( ?self $instance ): void {
        self::$instance = $instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        $this->fees_cache = new CustomsFeesCache();
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     */
    private function __wakeup() {}

    /**
     * Gets customs fees settings for the destination country (cached).
     *
     * @param string $country_code ISO 3166-1 alpha-2 country code.
     *
     * @return array|null Dictionary data or null if not found.
     *
     * @throws Exception If API fetch fails.
     */
    public function get_customs_fees( string $country_code ): ?array {
        $cache_key   = array( 'country' => $country_code );
        $cached_data = $this->fees_cache->get( $cache_key );

        if ( false !== $cached_data ) {
            return $cached_data;
        }

        $response = NovaPost::get_client()->dictionary()->customsFees( $country_code );

        if ( ! is_array( $response ) ) {
            return null;
        }

        $this->fees_cache->set( $cache_key, $response );

        return $response;
    }
}
