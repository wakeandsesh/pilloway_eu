<?php
/**
 * NovaPostShippingMethod class
 *
 * @package NovaPostForWoocommerce\Shipping
 */

namespace NovaPostForWoocommerce\Models\Cache;

/**
 * Abstract Cache class.
 */
abstract class AbstractCache {
    /**
     * Cache lifetime in seconds (15 minutes).
     */
    protected const CACHE_LIFETIME = 15 * 60;

    protected const CACHE_KEY_SUFFIX = '';

    /**
     * Cache Key prefix.
     */
    private const CACHE_KEY_PREFIX = 'npforwc_shipping_';

    /**
     * Method reads from cache.
     *
     * @param array $request_data to generate cache key.
     * @return array|false
     */
    public function get( array $request_data ): array|false {
        return get_transient( $this->generate_cache_key( $request_data ) );
    }

    /**
     * Method set cache to storage.
     *
     * @param array $request_data to generate cache key.
     * @param array $data to write cache data.
     * @return void
     */
    public function set( array $request_data, array $data ): void {
        set_transient( $this->generate_cache_key( $request_data ), $data, static::CACHE_LIFETIME );
    }

    /**
     * Method generates cache key based in calculation request params.
     *
     * @param array $request_data Calculation request data.
     * @return string
     */
    protected function generate_cache_key( array $request_data ): string {
        $this->deep_sort( $request_data );
        return self::CACHE_KEY_PREFIX . static::CACHE_KEY_SUFFIX . md5( wp_json_encode( $request_data ) );
    }

    /**
     * Recursively sorts an array by keys.
     *
     * @param array &$arr The array to sort.
     */
    private function deep_sort( array &$arr ) {
        ksort( $arr );
        foreach ( $arr as &$value ) {
            if ( is_array( $value ) ) {
                $this->deep_sort( $value );
            }
        }
    }
}
