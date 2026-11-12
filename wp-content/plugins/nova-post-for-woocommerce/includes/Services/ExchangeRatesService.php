<?php
/**
 * Exchange Rates Service for Nova Post WooCommerce
 *
 * Handles currency exchange rate conversions using Nova Post API.
 *
 * @package NovaPostForWoocommerce\Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Factories\NovaPost;

/**
 * Class ExchangeRatesService
 *
 * Provides functionality to get exchange rates and convert prices between currencies.
 */
class ExchangeRatesService {


    /**
     * Get main currency data for a specific amount and country.
     *
     * Calls the Nova Post API to retrieve currency information, including
     * the target main currency code and the converted amount.
     *
     * @param float  $amount        Amount to convert.
     * @param string $country_code  Country code (e.g., 'UA', 'PL').
     * @param string $currency_code Source currency code (e.g., 'UAH').
     *
     * @return array|null Returns array with 'mainCurrency' and 'mainPrice', or null on failure.
     */
    public function get_main_currency_data( $amount, $country_code, $currency_code ) {
        $params = array(
            'amount'       => $amount,
            'countryCode'  => $country_code,
            'currencyCode' => $currency_code,
            'date'         => gmdate( 'Y-m-d' ) . 'T00:00:00.000000Z',
        );

        $api_response = NovaPost::get_client()->exchangeRates()->convert( $params );

        if ( ! isset( $api_response['convertedCurrencies'] ) ) {
            return null;
        }

        return array(
            'originalCurrency' => $api_response['mainCurrency']['currencyCode'] ?? null,
            'originalPrice'    => $api_response['mainCurrency']['amount'] ?? null,
        );
    }

    /**
     * Get exchange rates for a given amount and currency.
     *
     * @param float  $amount       The amount to convert.
     * @param string $country_code The country code.
     * @param string $currency_code The source currency code.
     *
     * @return array|null Array with USD and EUR rates or null on failure.
     */
    public function get_exchange_rates( $amount, $country_code, $currency_code ) {
        $params = array(
            'amount'       => $amount,
            'countryCode'  => $country_code,
            'currencyCode' => $currency_code,
            'date'         => gmdate( 'Y-m-d' ) . 'T00:00:00.000000Z',
        );

        $api_response = NovaPost::get_client()->exchangeRates()->convert( $params );

        if ( ! isset( $api_response['convertedCurrencies'] ) ) {
            return null;
        }

        $converted_currencies = array_column( $api_response['convertedCurrencies'], 'amount', 'currencyCode' );

        return array(
            'usdPrice' => $converted_currencies['USD'] ?? null,
            'eurPrice' => $converted_currencies['EUR'] ?? null,
        );
    }

    /**
     * Get exchange price for a specific target currency.
     *
     * @param float  $amount              The amount to convert.
     * @param string $country_code        The country code.
     * @param string $currency_code       The source currency code.
     * @param string $target_currency_code The target currency code.
     *
     * @return float|null The converted amount or null on failure.
     */
    public function get_exchange_price( $amount, $country_code, $currency_code, $target_currency_code ) {
        $params = array(
            'amount'       => $amount,
            'countryCode'  => $country_code,
            'currencyCode' => $currency_code,
            'date'         => gmdate( 'Y-m-d' ) . 'T00:00:00.000000Z',
        );

        $api_response = NovaPost::get_client()->exchangeRates()->convert( $params );

        if ( ! isset( $api_response['convertedCurrencies'] ) ) {
            return null;
        }

        $converted_currencies = array_column( $api_response['convertedCurrencies'], 'amount', 'currencyCode' );

        return $converted_currencies[ $target_currency_code ] ?? null;
    }
}
