<?php
/**
 * Exchange Rates Service for Nova Post WooCommerce
 *
 * Handles currency exchange rate conversions using Nova Post API.
 *
 * @package NovaPostForWoocommerce\Services
 */

namespace NovaPostForWoocommerce\Services\Cache;

use NovaPostForWoocommerce\Models\Cache\ExchangeRateCache;
use NovaPostForWoocommerce\Services\ExchangeRatesService;
use NovaPostForWoocommerce\Models\Constants\ExchangeRateConstants;
use RuntimeException;
use InvalidArgumentException;

/**
 * Class CachedExchangeRatesService
 */
class CachedExchangeRatesService {

    /**
     * Exchange rates API service.
     *
     * @var ExchangeRatesService
     */
    private ExchangeRatesService $api;

    /**
     * Cache for exchange rates.
     *
     * @var ExchangeRateCache
     */
    private ExchangeRateCache $rate_cache;

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
     * CachedExchangeRatesService constructor.
     */
    public function __construct() {
        $this->api        = new ExchangeRatesService();
        $this->rate_cache = new ExchangeRateCache();
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
     * Convert amount using exchange rate.
     *
     * @param float  $amount  Amount to convert.
     * @param string $country Country code.
     * @param string $from    Source currency.
     * @param string $to      Target currency.
     *
     * @return float
     *
     * @throws RuntimeException When exchange rate is unavailable.
     */
    public function get_exchange_price(
        float $amount,
        string $country,
        string $from,
        string $to
    ): float {
        if ( $from === $to ) {
            return round( $amount, ExchangeRateConstants::RESULT_DECIMAL_COUNT );
        }

        $rate = $this->get_rate( $country, $from, $to );

        return round(
            $amount * $rate,
            ExchangeRateConstants::RESULT_DECIMAL_COUNT
        );
    }

    /**
     * Returns USD and EUR exchange prices.
     *
     * @param float  $amount   Amount to convert.
     * @param string $country  Country code.
     * @param string $currency Source currency.
     *
     * @return array
     */
    public function get_exchange_rates(
        float $amount,
        string $country,
        string $currency
    ): array {

        $main_currency_rate_info = $this->get_main_rate_info(
            $amount,
            $country,
            $currency
        );

        $main_currency_code = $main_currency_rate_info['originalCurrency'];

        return array(
            'originalCurrency' => $main_currency_code,
            'originalPrice'    => $this->get_exchange_price( $amount, $country, $currency, $main_currency_code ) ?? $main_currency_rate_info['originalPrice'],
            'usdPrice'         => $this->get_exchange_price( $amount, $country, $currency, 'USD' ),
            'eurPrice'         => $this->get_exchange_price( $amount, $country, $currency, 'EUR' ),
        );
    }

    /**
     * Get main currency information with caching.
     *
     * This method attempts to retrieve currency data from the cache first.
     * If not found, it fetches data from the API and stores it in the cache.
     *
     * @param float  $amount   The amount to be converted.
     * @param string $country  The country code (e.g., 'UA').
     * @param string $currency The source currency code (e.g., 'UAH').
     *
     * @return array|null Returns an array with currency data or null.
     *
     * @throws \InvalidArgumentException When the API returns empty or invalid data.
     */
    private function get_main_rate_info(
        float $amount,
        string $country,
        string $currency
    ): ?array {
        $cache_key = $this->build_cache_key( $country, $currency, 'MAIN_INFO' );

        $cached_info = $this->get_cached_rate( $cache_key );
        if ( ! empty( $cached_info ) ) {
            return $cached_info;
        }

        $main_currency_rate_info = $this->api->get_main_currency_data(
            $amount,
            $country,
            $currency
        );

        if ( empty( $main_currency_rate_info ) ) {
            throw new InvalidArgumentException(
                'Exchange rate information unavailable.'
            );
        }

        $this->set_cached_data( $cache_key, $main_currency_rate_info );

        return $main_currency_rate_info;
    }

    /**
     * Get exchange rate from cache or API.
     *
     * @param string $country Country code.
     * @param string $from    Source currency.
     * @param string $to      Target currency.
     *
     * @return float
     *
     * @throws \RuntimeException|\InvalidArgumentException When exchange rate is unavailable or API returns invalid rate.
     */
    private function get_rate(
        string $country,
        string $from,
        string $to
    ): float {
        $cache_key   = $this->build_cache_key( $country, $from, $to );
        $cached_rate = $this->get_cached_rate( $cache_key );

        if ( ! empty( $cached_rate['rate'] ) ) {
            return (float) $cached_rate['rate'];
        }

        $api_rate = $this->get_api_rate( $country, $from, $to );

        $rate = round(
            $api_rate / ExchangeRateConstants::SCALE_INDEX,
            ExchangeRateConstants::RATE_DECIMAL_COUNT
        );

        $this->set_cached_data( $cache_key, array( 'rate' => $rate ) );

        return $rate;
    }

    /**
     * Get cached exchange rate.
     *
     * @param array $key Cache key.
     *
     * @return array|null
     */
    private function get_cached_rate( array $key ): ?array {
        $cached = $this->rate_cache->get( $key );

        return ( false !== $cached ) ? $cached : null;
    }

    /**
     * Retrieve the exchange rate from the API for a given country and currency pair.
     *
     * This function calls the API to get the exchange rate between the source currency ($from)
     * and the target currency ($to) for a specific country. If the API does not return a valid
     * rate (empty or non-positive), an InvalidArgumentException is thrown.
     *
     * @param string $country The country code for which the exchange rate is requested.
     * @param string $from    The source currency code (e.g., 'USD').
     * @param string $to      The target currency code (e.g., 'EUR').
     *
     * @return float          The exchange rate as a float.
     *
     * @throws \InvalidArgumentException If the exchange rate is empty, zero, or negative.
     */
    private function get_api_rate(
        string $country,
        string $from,
        string $to
    ): ?float {

        $rate = $this->api->get_exchange_price(
            ExchangeRateConstants::SCALE_INDEX,
            $country,
            $from,
            $to
        );

        if ( empty( $rate ) || $rate <= 0 ) {
            throw new \InvalidArgumentException(
                'Exchange rate unavailable.'
            );
        }

        return $rate;
    }

    /**
     * Store data in the exchange rate cache.
     *
     * @param array $key  The cache key components (country, currencies, etc.).
     * @param array $data The data to be cached (rate or currency info).
     *
     * @return bool True on success, false otherwise.
     */
    private function set_cached_data( array $key, array $data ): bool {
        $this->rate_cache->set( $key, $data );

        return true;
    }

    /**
     * Build cache key.
     *
     * @param string $country Country code.
     * @param string $from    Source currency.
     * @param string $to      Target currency.
     *
     * @return array
     */
    private function build_cache_key(
        string $country,
        string $from,
        string $to
    ): array {

        return array(
            'country' => $country,
            'from'    => $from,
            'to'      => $to,
        );
    }
}
