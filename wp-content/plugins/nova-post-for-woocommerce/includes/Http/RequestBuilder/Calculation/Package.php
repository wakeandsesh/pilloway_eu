<?php
/**
 * Package object.
 *
 * Object for shipping cost calculation request.
 *
 * @package NovaPostForWoocommerce\Http
 */

namespace NovaPostForWoocommerce\Http\RequestBuilder\Calculation;

/**
 * Package class.
 */
class Package {
    /**
     * Package data array.
     *
     * @var array
     */
    private $package;

    /**
     * Package constructor.
     *
     * @param array $package Package data.
     */
    public function __construct( array $package ) {
        $this->package = $package;
    }

    /**
     * Get the destination country.
     *
     * @return string
     */
    public function get_destination_country(): string {
        return (string) ( $this->package['destination']['country'] ?? '' );
    }

    /**
     * Get the destination city.
     *
     * @return string
     */
    public function get_destination_city(): string {
        return (string) ( $this->package['destination']['city'] ?? '' );
    }

    /**
     * Get the destination address line 1.
     *
     * @return string
     */
    public function get_destination_address1(): string {
        return (string) ( $this->package['destination']['address_1'] ?? '' );
    }

    /**
     * Get the destination address line 2.
     *
     * @return string
     */
    public function get_destination_address2(): string {
        return (string) ( $this->package['destination']['address_2'] ?? '' );
    }

    /**
     * Get the destination postcode.
     *
     * @return string
     */
    public function get_destination_postcode(): string {
        return (string) ( $this->package['destination']['postcode'] ?? '' );
    }

    /**
     * Get the destination type (e.g., 'division' or 'address').
     *
     * @return string
     */
    public function get_destination_type(): string {
        return (string) ( $this->package['destination_type'] ?? '' );
    }

    /**
     * Get the package contents.
     *
     * @return array
     */
    public function get_contents(): array {
        return $this->package['contents'] ?? array();
    }

    /**
     * Get the package boxes
     *
     * @return array
     */
    public function get_boxes(): array {
        return $this->package['boxes'] ?? array();
    }

    /**
     * Get the package contents cost.
     *
     * @return float
     */
    public function get_contents_cost(): float {
        return (float) ( $this->package['contents_cost'] ?? 0.0 );
    }
}
