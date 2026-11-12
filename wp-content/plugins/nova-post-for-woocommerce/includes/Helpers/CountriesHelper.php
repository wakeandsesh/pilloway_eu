<?php
/**
 * Countries Helper
 *
 * Provides helper methods for country-related functionality including EU countries,
 * countries of presence, and locale information.
 *
 * @package NovaPostForWoocommerce\Helpers
 */

namespace NovaPostForWoocommerce\Helpers;

/**
 * Class CountriesHelper
 *
 * Handles country-related operations including retrieving EU countries,
 * countries of presence, and locale information.
 */
class CountriesHelper {

    /**
     * Get the list of European Union country codes.
     *
     * @return array Array of EU country codes in ISO 3166-1 alpha-2 format.
     */
    public static function get_eu_countries() {
        return array(
            'AT',
            'BE',
            'BG',
            'HR',
            'CY',
            'CZ',
            'DK',
            'EE',
            'FI',
            'FR',
            'DE',
            'GR',
            'HU',
            'IE',
            'IT',
            'LV',
            'LT',
            'LU',
            'MT',
            'NL',
            'PL',
            'PT',
            'RO',
            'SK',
            'SI',
            'ES',
            'SE',
        );
    }

    /**
     * Get the list of countries where the service is available.
     *
     * @return array Array of country codes in ISO 3166-1 alpha-2 format
     *               where the service operates.
     */
    public static function get_countries_of_presence() {
        return array(
            'CZ',
            'DE',
            'EE',
            'ES',
            'FR',
            'GB',
            'HU',
            'IT',
            'LT',
            'LV',
            'MD',
            'NL',
            'PL',
            'RO',
            'SK',
            'UA',
        );
    }

    /**
     * Get the locale for a given country code.
     *
     * @param string $country Country code in ISO 3166-1 alpha-2 format.
     * @return string|null The locale string (e.g., 'en-US') or null if not found.
     */
    public static function get_country_locale( $country ) {
        $country = strtolower( $country );

        $countries = array(
            'at' => 'de-at',
            'ca' => 'en-ca',
            'cz' => 'cs-cz',
            'de' => 'de-de',
            'ee' => 'et-ee',
            'es' => 'es-es',
            'fr' => 'fr-fr',
            'gb' => 'en-gb',
            'hu' => 'hu-hu',
            'it' => 'it-it',
            'lt' => 'lt-lt',
            'lv' => 'lv-lv',
            'md' => 'ro-md',
            'nl' => 'nl-nl',
            'pl' => 'pl-pl',
            'ro' => 'ro-ro',
            'sk' => 'sk-sk',
            'ua' => 'uk-ua',
            'us' => 'en-us',
        );

        return $countries[ $country ] ?? null;
    }

    /**
     * Method checks if country is from EU.
     *
     * @param string $country_code Country Code.
     * @return bool
     */
    public static function is_eu_country( $country_code ) {
        return in_array( $country_code, self::get_eu_countries(), true );
    }
}
