<?php
/**
 * Pickup Resource
 *
 * Handles the transformation of pickup data between different representations.
 *
 * @package NovaPostForWoocommerce\Resources
 */

namespace NovaPostForWoocommerce\Resources;

/**
 * Class PickupResource
 *
 * Responsible for transforming pickup data between API responses, database storage,
 * and internal application usage.
 */
class PickupResource {
    /**
     * The raw pickup data.
     *
     * @var array
     */
    private array $pickup;

    /**
     * Constructor.
     *
     * @param array $pickup The raw pickup data to be transformed.
     */
    public function __construct( array $pickup ) {
        $this->pickup = $pickup;
    }

    /**
     * Convert the pickup data to an API-friendly array format.
     *
     * @return array The formatted pickup data for API responses.
     */
    public function to_array(): array {
        return array(
            'id'          => $this->pickup['id'],
            'number'      => $this->pickup['number'],
            'status'      => $this->pickup['status'],
            'windowStart' => $this->pickup['pickedTimeFrom'] ?? null,
            'windowEnd'   => $this->pickup['pickedTimeTo'] ?? null,
            'createdAt'   => $this->pickup['createdAt'] ?? null,
            'updatedAt'   => $this->pickup['updatedAt'] ?? null,
        );
    }

    /**
     * Convert the pickup data to a format suitable for database storage.
     *
     * @return array The formatted pickup data for database storage.
     */
    public function for_storage(): array {
        return array(
            'id'               => $this->pickup['id'],
            'number'           => $this->pickup['number'],
            'status'           => $this->pickup['status'],
            'window_start'     => $this->pickup['pickedTimeFrom'] ?? null,
            'window_end'       => $this->pickup['pickedTimeTo'] ?? null,
            'contact_name'     => $this->pickup['fullName'] ?? null,
            'contact_phone'    => $this->pickup['phone'] ?? null,
            'contact_email'    => $this->pickup['email'] ?? null,
            'country_code'     => $this->pickup['countryCode'] ?? null,
            'address_parts'    => wp_json_encode( $this->pickup['addressParts'] ?? array() ),
            'request_payload'  => wp_json_encode( $this->pickup['request_payload'] ?? array() ),
            'response_payload' => wp_json_encode( $this->pickup['response_payload'] ?? array() ),
        );
    }
}
