<?php
/**
 * Currency Helper
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Helpers
 */

namespace NovaPostForWoocommerce\Helpers;

/**
 * CurrencyHelper class.
 *
 * @class CurrencyHelper
 */
class CurrencyHelper {
    /**
     * Get supported currencies
     *
     * @return array
     */
    public static function get_supported_currencies() {
        return array(
            'USD',
            'UAH',
            'THB',
            'HKD',
            'IDR',
            'TRY',
            'RON',
            'ILS',
            'CHF',
            'HUF',
            'MXN',
            'RUB',
            'CAD',
            'GEL',
            'MDL',
            'ZAR',
            'NZD',
            'SAR',
            'IRR',
            'VND',
            'MYR',
            'DZD',
            'SEK',
            'BRL',
            'UZS',
            'AED',
            'LBP',
            'EGP',
            'TJS',
            'RSD',
            'TMT',
            'KGS',
            'AZN',
            'CNY',
            'NOK',
            'AUD',
            'BDT',
            'INR',
            'MAD',
            'AMD',
            'DOP',
            'KRW',
            'BGN',
            'TWD',
            'EUR',
            'LYD',
            'PLN',
            'BYN',
            'KZT',
            'PKR',
            'SGD',
            'JPY',
            'IQD',
            'GBP',
            'TND',
            'CZK',
            'DKK',
        );
    }
}
