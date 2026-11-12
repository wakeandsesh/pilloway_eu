<?php
/**
 * Pickup API Service for handling Nova Post pickup operations.
 *
 * @package    NovaPostWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Exceptions\PickupException;
use NovaPostForWoocommerce\Factories\NovaPost;
use Psr\Http\Client\ClientExceptionInterface;
use Exception;

/**
 * Handles all API interactions with Nova Post pickup service.
 *
 * phpcs:disable WordPress.Security.EscapeOutput
 */
class PickupApiService {
    /**
     * Fetch time intervals from Nova Post API.
     *
     * @param array $params Request parameters.
     * @return array Time intervals.
     * @throws PickupException If there are validation errors or the API call fails.
     * @throws ClientExceptionInterface If the HTTP request fails.
     */
    public function fetch_time_intervals( array $params ): array {
        try {
            $response = NovaPost::get_client()->pickups()->findTimeIntervals( $params );

            if ( ! empty( $response['errors'] ) ) {
                throw new PickupException( __( 'Validation error', 'nova-post-for-woocommerce' ), 422, $response );
            }

            return $response;
        } catch ( Exception $e ) {
            if ( $e instanceof PickupException ) {
                throw $e;
            }

            throw new PickupException( $e->getMessage(), $e->getCode() ?: 500, array(), $e ); // phpcs:ignore
        }
    }

    /**
     * Create a new pickup request.
     *
     * @param array $params The pickup request parameters.
     * @return array The pickup data.
     * @throws PickupException If there are validation errors or the API call fails.
     * @throws ClientExceptionInterface If the HTTP request fails.
     */
    public function create_pickup( array $params ): array {
        try {
            $response = NovaPost::get_client()->pickups()->create( $params );

            if ( ! empty( $response['errors'] ) ) {
                throw new PickupException( __( 'Validation error', 'nova-post-for-woocommerce' ), 422, $response );
            }

            return $response;
        } catch ( Exception $e ) {
            if ( $e instanceof PickupException ) {
                throw $e;
            }

            throw new PickupException( $e->getMessage(), $e->getCode() ?: 500, array(), $e ); // phpcs:ignore
        }
    }

    /**
     * Add shipments to an existing pickup.
     *
     * @param string $pickup_id The ID of the pickup to add shipments to.
     * @param array  $shipments The shipment IDs to add.
     * @return array The API response data.
     * @throws PickupException If there are validation errors or the API call fails.
     * @throws ClientExceptionInterface If the HTTP request fails.
     */
    public function add_shipments_to_pickup( string $pickup_id, array $shipments ): array {
        try {
            $response = NovaPost::get_client()->pickups()->addShipments( $pickup_id, $shipments );

            if ( ! empty( $response['errors'] ) ) {
                throw new PickupException( __( 'Validation error', 'nova-post-for-woocommerce' ), 422, $response );
            }

            return $response;
        } catch ( Exception $e ) {
            if ( $e instanceof PickupException ) {
                throw $e;
            }

            throw new PickupException( $e->getMessage(), $e->getCode() ?: 500, array(), $e ); // phpcs:ignore
        }
    }
    /**
     * Update the status of an existing pickup.
     *
     * @param string $pickup_id The ID of the pickup.
     * @param array  $params The params to update.
     * @return array The API response data.
     * @throws PickupException If there are validation errors or the API call fails.
     * @throws ClientExceptionInterface If the HTTP request fails.
     */
    public function update_pickup_status( string $pickup_id, array $params ): array {
        try {
            $response = NovaPost::get_client()->pickups()->updateStatus( $pickup_id, $params );

            if ( ! empty( $response['errors'] ) ) {
                throw new PickupException( __( 'Validation error', 'nova-post-for-woocommerce' ), 422, $response );
            }

            return $response;
        } catch ( Exception $e ) {
            if ( $e instanceof PickupException ) {
                throw $e;
            }

            throw new PickupException( $e->getMessage(), $e->getCode() ?: 500, array(), $e ); // phpcs:ignore
        }
    }
}
