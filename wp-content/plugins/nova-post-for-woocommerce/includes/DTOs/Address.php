<?php
/**
 * Address Data Object for Nova Post.
 *
 * @package NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\DTOs;

/**
 * Class Address.
 */
class Address {

    /**
     * Country Code.
     *
     * @var string
     */
    public $country_code;

    /**
     * Country Name.
     *
     * @var string
     */
    public $country_name;

    /**
     * City.
     *
     * @var string
     */
    public $city;

    /**
     * Region.
     *
     * @var string
     */
    public $region;

    /**
     * Street.
     *
     * @var string
     */
    public $street;

    /**
     * Post code.
     *
     * @var string
     */
    public $post_code;

    /**
     * Building.
     *
     * @var string
     */
    public $building;

    /**
     * Flat.
     *
     * @var string
     */
    public $flat;

    /**
     * Block.
     *
     * @var string
     */
    public $block;

    /**
     * Note.
     *
     * @var string
     */
    public $note;

    /**
     * Construct method.
     *
     * @param string $country_code Country Code.
     * @param string $country_name Country Name.
     * @param string $city City.
     * @param string $region Region.
     * @param string $street Street.
     * @param string $post_code Postal Code.
     * @param string $building Building Number.
     * @param string $flat Flat.
     * @param string $block Block.
     * @param string $note Notes.
     */
    public function __construct( $country_code, $country_name, $city, $region, $street, $post_code, $building, $flat, $block, $note ) {
        $this->country_code = $country_code;
        $this->country_name = $country_name;
        $this->city         = $city;
        $this->region       = $region;
        $this->street       = $street;
        $this->post_code    = $post_code;
        $this->building     = $building;
        $this->flat         = $flat;
        $this->block        = $block;
        $this->note         = $note;
    }

    /**
     * Convert from array.
     *
     * @param array $data Data.
     * @return self
     */
    public static function from_array( $data ) {
        return new self(
            $data['countryCode'] ?? null,
            $data['countryName'] ?? null,
            $data['city'] ?? null,
            $data['region'] ?? null,
            $data['street'] ?? null,
            $data['postCode'] ?? null,
            $data['building'] ?? null,
            $data['flat'] ?? null,
            $data['block'] ?? null,
            $data['note'] ?? null,
        );
    }

    /**
     * Convert from Division Address.
     *
     * @param array $data Data.
     * @return self
     */
    public static function from_division_address( $data ) {
        return new self(
            country_code: null,
            country_name: $data['country'] ?? null,
            city: $data['settlement'] ?? null,
            region: null,
            street: $data['street'] ?? null,
            post_code: $data['zipcode'] ?? null,
            building: $data['building'] ?? null,
            flat: null,
            block: null,
            note: $data['note'] ?? null,
        );
    }

    /**
     * Generate empty.
     *
     * @return self
     */
    public static function empty() {
        return new self(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );
    }
}
