<?php
/**
 * DivisionResource.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Resources;

/**
 * Class DivisionResource.
 */
class DivisionResource {

    /**
     * Convert Division to array.
     *
     * @param array $division Division set.
     * @return array
     */
    public static function to_array( $division ) {
        return array(
            'id'                 => $division['id'],
            'name'               => self::modify_division_name( $division ),
            'shortName'          => $division['shortName'],
            'countryCode'        => $division['countryCode'],
            'city'               => $division['settlement']['name'],
            'address'            => $division['address'],
            'status'             => $division['status'],
            'latitude'           => $division['latitude'],
            'longitude'          => $division['longitude'],
            'prohibitedSending'  => $division['prohibitedSending'],
            'prohibitedIssuance' => $division['prohibitedIssuance'],
            'divisionCategory'   => $division['divisionCategory'],
            'maxWeight'          => $division['maxWeightPlaceRecipient'],
        );
    }

    /**
     * Modify division name.
     *
     * @param array $division Division set.
     * @return string
     */
    public static function modify_division_name( $division ) {
        $name = preg_replace( '/\s*\(до \d+ кг[^)]*\)/i', '', $division['name'] );

        $max_weight_kg = round( $division['maxWeightPlaceRecipient'] / 1000 );
        $weight_string = ' (max ' . $max_weight_kg . 'kg)';

        return $name . $weight_string;
    }

    /**
     * Method returns collection.
     *
     * @param array $divisions List of Divisions.
     * @return array
     */
    public static function collection( $divisions ) {
        return array_values( array_map( array( self::class, 'to_array' ), $divisions ) );
    }
}
