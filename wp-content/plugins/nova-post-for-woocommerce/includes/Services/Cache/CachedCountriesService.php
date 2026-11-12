<?php
/**
 * Cached Countries Service for Nova Post WooCommerce
 *
 * Handles fetching and caching of countries dictionary from Nova Post API.
 *
 * @package NovaPostForWoocommerce\Services\Cache
 */

namespace NovaPostForWoocommerce\Services\Cache;

use Exception;
use NovaDigital\NovaPost\NovaPostApi;
use NovaPostForWoocommerce\Logger\Logger;
use NovaPostForWoocommerce\Models\Cache\CountriesCache;

/**
 * Class CachedCountriesService
 */
class CachedCountriesService {

    /**
     * Base endpoint path for countries dictionary.
     */
    private const COUNTRIES_ENDPOINT = '/site/v.1.0/countries';

    /**
     * Cache for countries.
     *
     * @var CountriesCache
     */
    private CountriesCache $countries_cache;

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
        $this->countries_cache = new CountriesCache();
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
     * Gets currency code for a given country code (cached).
     *
     * @param string $country_code ISO country code (e.g., 'US').
     *
     * @return string|null Currency code or null if unavailable.
     */
    public function get_country_currency( string $country_code ): ?string {
        $country_code = strtoupper( trim( $country_code ) );
        if ( empty( $country_code ) ) {
            return null;
        }

        $cache_key   = array( 'country' => $country_code );
        $cached_data = $this->countries_cache->get( $cache_key );

        $currency_code = null;

        if ( false !== $cached_data && is_array( $cached_data ) && isset( $cached_data['currencyCode'] ) ) {
            $currency_code = (string) $cached_data['currencyCode'];
        } else {
            try {
                $country_data = $this->fetch_country_from_api( $country_code );

                if ( ! empty( $country_data ) && isset( $country_data['currencyCode'] ) ) {
                    $this->countries_cache->set( $cache_key, $country_data );
                    $currency_code = (string) $country_data['currencyCode'];
                }
            } catch ( Exception $e ) {
                Logger::write_log(
                    'warning',
                    'Failed to fetch country details from Nova Post API.',
                    array(
                        'country_code' => $country_code,
                        'error'        => $e->getMessage(),
                    )
                );
            }
        }

        return $currency_code;
    }

    /**
     * Fetches country information from Nova Post API.
     *
     * @param string $country_code Country code.
     *
     * @return array|null Country data item or null on failure.
     */
    private function fetch_country_from_api( string $country_code ): ?array {
        $use_sandbox = (bool) get_option( 'npforwc_shipping_debug' );
        $base_url    = $use_sandbox ? NovaPostApi::SANDBOX_BASE_URL : NovaPostApi::PRODUCTION_BASE_URL;
        $base_domain = dirname( rtrim( $base_url, '/' ) );

        $url = $base_domain . self::COUNTRIES_ENDPOINT . '?codes[]=' . rawurlencode( $country_code );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/json',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            Logger::write_log(
                'warning',
                'HTTP error when fetching country from Nova Post API.',
                array(
                    'url'   => $url,
                    'error' => $response->get_error_message(),
                )
            );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            Logger::write_log(
                'warning',
                'Non-200 response when fetching country from Nova Post API.',
                array(
                    'url'  => $url,
                    'code' => $code,
                )
            );
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
            return null;
        }

        foreach ( $data['items'] as $item ) {
            if ( is_array( $item ) && isset( $item['code'] ) && strtoupper( (string) $item['code'] ) === $country_code ) {
                return $item;
            }
        }

        $first_item = reset( $data['items'] );
        return is_array( $first_item ) ? $first_item : null;
    }
}
