<?php
/**
 * Divisions Service.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\DTOs\Address;
use NovaPostForWoocommerce\Factories\NovaPost;

/**
 * Handles logic related to Nova Post divisions (branches).
 */
class DivisionsService {
    /**
     * Division categories for Nova Post API.
     */
    private const DIVISION_CATEGORIES = array(
        'PostBranch',
        'CargoBranch',
        'Postomat',
        'PUDO',
    );

    /**
     * Get a division address by postal code.
     *
     * @param string $country_code The Country code to search for.
     * @param string $city The city to search for.
     * @param string $postcode The postal code to search for.
     * @return Address The address DTO.
     */
    public static function get_address_by_country_city_or_postcode( $country_code, $city, $postcode ): Address {
        $address_data = self::find_division_address( $country_code, $city, $postcode );

        return $address_data ? Address::from_division_address( $address_data ) : Address::empty();
    }

    /**
     * Get a division ID by postal code or city.
     *
     * @param string $country_code The Country code to search for.
     * @param string $city City name to search for.
     * @param string $postcode The postal code to search for.
     * @return int The division ID, or 0 if not found.
     */
    public static function get_by_country_city_or_postcode( $country_code, $city, $postcode ): int {
        return self::find_first_division_id( $country_code, array( $city, $postcode ) );
    }

    /**
     * Get a division ID by postal code or city.
     * Method used for GA Events.
     *
     * @param string $country_code The Country code to search for.
     * @param string $city City name to search for.
     * @param string $postcode The postal code to search for.
     * @return array The division Info.
     */
    public static function get_full_division_by_country_city_or_postcode( $country_code, $city, $postcode ): array {
        return self::find_first_division( $country_code, array( $city, $postcode ) );
    }

    /**
     * Search for divisions.
     *
     * @param array $params The search parameters.
     * @return array The API response items.
     */
    public static function search_divisions( array $params ): array {
        $result = NovaPost::get_client()->divisions()->get( $params );
        return $result['items'] ?? array();
    }

    /**
     * Find a division address by postal code.
     *
     * @param string $country_code The Country code to search for.
     * @param string $city The city to search for in order of priority.
     * @param string $postcode The postcode to search for in order of priority.
     * @return array|null The division address data or null if not found.
     */
    private static function find_division_address( string $country_code, string $city, string $postcode ): ?array {

        $items = self::search_divisions(
            array(
                'countryCode' => $country_code,
                'textSearch'  => trim( $city ),
            )
        );
        if ( ! $items ) {
            $items = self::search_divisions(
                array(
                    'countryCode' => $country_code,
                    'textSearch'  => trim( $postcode ),
                )
            );
        }

        foreach ( $items as $item ) {
            if ( isset( $item['fullAddress']['zipcode'] ) && $item['fullAddress']['zipcode'] === $postcode ) {
                return $item['fullAddress'];
            }
        }

        return null;
    }

    /**
     * Find the first division ID from a list of search terms.
     *
     * @param string $country_code The Country code to search for.
     * @param array  $search_terms The terms to search for in order of priority.
     * @return int The division ID, or 0 if not found.
     */
    private static function find_first_division_id( string $country_code, array $search_terms ): int {
        foreach ( array_filter( $search_terms ) as $term ) {
            $items = self::search_divisions(
                array(
                    'countryCode'        => $country_code,
                    'textSearch'         => trim( $term ),
                    'divisionCategories' => self::DIVISION_CATEGORIES,
                    'prohibitedIssuance' => false,
                    'limit'              => 1,
                )
            );

            if ( ! empty( $items ) && isset( $items[0]['id'] ) ) {
                return (int) $items[0]['id'];
            }
        }

        return 0;
    }

    /**
     * Find the first full division Info from a list of search terms.
     *
     * @param string $country_code The Country code to search for.
     * @param array  $search_terms The terms to search for in order of priority.
     * @return array The division Info or empty array.
     */
    private static function find_first_division( string $country_code, array $search_terms ): array {
        foreach ( array_filter( $search_terms ) as $term ) {
            $items = self::search_divisions(
                array(
                    'countryCode'        => $country_code,
                    'textSearch'         => trim( $term ),
                    'divisionCategories' => self::DIVISION_CATEGORIES,
                    'prohibitedIssuance' => false,
                    'limit'              => 1,
                )
            );

            if ( ! empty( $items ) ) {
                return current( $items );
            }
        }

        return array();
    }
}
