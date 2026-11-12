<?php
/**
 * Pickup Controller for handling pickup-related API endpoints.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Api\Controllers
 */

namespace NovaPostForWoocommerce\Api\Controllers;

use NovaPostForWoocommerce\Models\Traits\TrackEvent;
use NovaPostForWoocommerce\Services\PickupErrorFormatter;
use NovaPostForWoocommerce\Validation\Requests\PickupTimeIntervalsValidator;
use NovaPostForWoocommerce\Validation\Requests\PickupShipmentsValidator;
use NovaPostForWoocommerce\Validation\Requests\PickupCreationValidator;
use NovaPostForWoocommerce\Validation\Requests\PickupStatusValidator;
use NovaPostForWoocommerce\Exceptions\ShipmentException;
use NovaPostForWoocommerce\Exceptions\PickupException;
use NovaPostForWoocommerce\Services\PickupApiService;
use NovaPostForWoocommerce\Services\PickupService;
use NovaPostForWoocommerce\Admin\OrderBulkActions;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;

/**
 * Handles pickup-related API endpoints.
 *
 * This controller manages all REST API endpoints related to Nova Post pickups.
 */
class PickupController {

    use TrackEvent;

    /**
     * The pickup service instance.
     *
     * @var PickupService
     */
    private PickupService $pickup_service;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->pickup_service = new PickupService( new PickupApiService() );
    }

    /**
     * Find available time intervals for shipment pickup.
     *
     * @param WP_REST_Request $request The REST API request object containing order IDs.
     * @return WP_REST_Response|WP_Error Response with available time intervals or error object.
     * @throws PickupException If there's an error while finding time intervals or if orders don't have shipments.
     */
    public function find_time_intervals( WP_REST_Request $request ) {
        $request_params = $request->get_json_params();

        try {
            PickupTimeIntervalsValidator::validate_request( $request_params );

            $params = $this->pickup_service->build_time_intervals_params( $request_params );

            PickupTimeIntervalsValidator::validate_params( $params );

            $intervals = $this->pickup_service->get_available_time_intervals( $params );

            $this->track_pickup_created( array(), array(), 'set_time' );

            return new WP_REST_Response( $intervals );
        } catch ( PickupException $e ) {
            $this->track_pickup_failed( $e->getMessage(), array(), '', $request_params['orderIds'] ?? array(), 'set_time' );

            return PickupErrorFormatter::format( $e, PickupException::PICKUP_CREATION_FAILED );
        }
    }

    /**
     * Create a new pickup.
     *
     * @param WP_REST_Request $request The REST API request object containing a pickup time window.
     * @return WP_REST_Response|WP_Error Response with created pickup data or error object.
     */
    public function create_pickup( WP_REST_Request $request ) {
        $request_params = $request->get_json_params();

        try {
            PickupCreationValidator::validate_request( $request_params );

            $params = $this->pickup_service->build_pickup_params( $request_params );

            PickupCreationValidator::validate_params( $params );

            $pickup = $this->pickup_service->create_pickup_request( $params );

            $this->track_pickup_created( $pickup, array(), 'crt_pickup' );

            return new WP_REST_Response( $pickup, 201 );
        } catch ( PickupException $e ) {
            $this->track_pickup_failed( $e->getMessage(), $e->get_data(), '', $request_params['orderIds'] ?? array(), 'crt_pickup' );

            return new WP_Error( PickupException::PICKUP_CREATION_FAILED, $e->getMessage(), $e->get_data() );
        }
    }

    /**
     * Add shipments to an existing pickup.
     *
     * @param WP_REST_Request $request The request object containing pickup ID and order IDs.
     * @return WP_REST_Response|WP_Error Response object or WP_Error on failure.
     */
    public function add_shipments( WP_REST_Request $request ) {
        $request_params = $request->get_params();

        ['pickupId' => $pickup_id, 'orderIds' => $order_ids] = $request_params;

        try {
            PickupShipmentsValidator::validate_request( $request_params );
            $shipments = $this->pickup_service->add_shipments_to_pickup( $pickup_id, $order_ids );

            OrderBulkActions::add_pickup_success_notice( count( $shipments['shipments'] ) );
            $shipments_arr = array(
                'shipments' => $shipments['shipments'] ?? array(),
                'order_ids' => $order_ids ?? array(),
            );
            $pickup_info   = $this->pickup_service->get_pickup_data( $pickup_id );
            $this->track_pickup_created( $pickup_info, $shipments_arr, 'add_shpmnt' );

            return new WP_REST_Response( $shipments, 200 );
        } catch ( PickupException | ShipmentException $e ) {
            $code = $e instanceof ShipmentException
                ? ShipmentException::NOT_FOUND
                : PickupException::PICKUP_UPDATE_FAILED;

            $this->track_pickup_failed( $e->getMessage(), $e->get_data(), $pickup_id, $order_ids, 'add_shpmnt' );

            return new WP_Error( $code, $e->getMessage(), $e->get_data() );
        }
    }

    /**
     * Update the status of an existing pickup.
     *
     * @param WP_REST_Request $request The request object containing pickup ID and params to update.
     * @return WP_REST_Response|WP_Error Response object or WP_Error on failure.
     */
    public function update_status( WP_REST_Request $request ) {
        $request_params = $request->get_params();

        try {
            PickupStatusValidator::validate_request( $request_params );

            ['pickupId' => $pickup_id] = $request_params;
            unset( $request_params['pickupId'] );
            $success = $this->pickup_service->update_pickup_status( $pickup_id, $request_params );

            $pickup_info = $this->pickup_service->get_pickup_data( $pickup_id );
            $this->track_pickup_created( $pickup_info, array(), 'upd_status' );

            return new WP_REST_Response( $success, 200 );
        } catch ( PickupException $e ) {
            $this->track_pickup_failed( $e->getMessage(), $e->get_data(), $pickup_id, $request_params['orderIds'] ?? array(), 'upd_status' );
            return new WP_Error( PickupException::PICKUP_UPDATE_FAILED, $e->getMessage(), $e->get_data() );
        }
    }
}
