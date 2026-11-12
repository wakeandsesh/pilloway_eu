<?php
/**
 * Shipment Controller.
 *
 * Handles shipment-related API endpoints for Nova Post shipping.
 *
 * @package NovaPostForWoocommerce\Api\Controllers
 */

namespace NovaPostForWoocommerce\Api\Controllers;

use Exception;
use NovaPostForWoocommerce\Exceptions\ShipmentException;
use NovaPostForWoocommerce\Models\Traits\TrackEvent;
use NovaPostForWoocommerce\Services\ShipmentApiService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class ShipmentController.
 */
class ShipmentController {

    use TrackEvent;

    /**
     * Get shipment preview data before creation.
     *
     * @param WP_REST_Request $request The REST request object containing order ID.
     *
     * @return WP_REST_Response|WP_Error The REST response with preview data or error message.
     */
    public function get_shipment_preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $order_id = $request->get_param( 'orderId' );

        try {
            $response = ShipmentApiService::prepare_and_validate_shipment_preview( $order_id );
        } catch ( ShipmentException $e ) {
            return new WP_Error( 'shipment_preview_failed', $e->getMessage(), array( 'status' => $e->getCode() ) );
        }

        return new WP_REST_Response( $response );
    }

    /**
     * Create shipment (waybill) for a specific order.
     *
     * @param WP_REST_Request $request The REST request object containing order ID.
     *
     * @return WP_REST_Response|WP_Error The REST response with shipment data or error message.
     */
    public function create_shipment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $order_id = $request->get_param( 'orderId' );
        $note     = $request->get_param( 'note' ) ?? '';

        try {
            $response = ShipmentApiService::create_shipment_from_order( $order_id, $note );
            $this->track_shipment_created( $order_id, $response['track_number'], $response['shipment_cost'] );
        } catch ( ShipmentException $e ) {
            $this->track_shipment_failed( $order_id, $e->get_data() );

            return new WP_Error( 'shipment_creation_failed', $e->getMessage(), $e->get_data() );
        }

        return new WP_REST_Response( $response );
    }

    /**
     * Get shipment (waybill) information for a specific order.
     *
     * @param WP_REST_Request $request The REST request object containing order ID.
     *
     * @return WP_REST_Response The REST response with shipment data or error message.
     */
    public function get_shipment( WP_REST_Request $request ): WP_REST_Response {
        $order_id = $request->get_param( 'orderId' );

        try {
            $response = ShipmentApiService::get_shipment( $order_id );
        } catch ( ShipmentException $e ) {
            return new WP_REST_Response( $e->get_data() ?? array( 'message' => $e->getMessage() ), $e->getCode() );
        }

        return new WP_REST_Response( $response );
    }

    /**
     * Get shipment (waybill) status for a specific order.
     *
     * @param WP_REST_Request $request The REST request object containing order ID.
     *
     * @return WP_REST_Response The REST response with shipment status or error message.
     */
    public function get_shipment_status( WP_REST_Request $request ): WP_REST_Response {
        $order_id = $request->get_param( 'orderId' );

        try {
            $response = ShipmentApiService::get_shipment_status( $order_id );
        } catch ( ShipmentException $e ) {
            return new WP_REST_Response( $e->get_data() ?? array( 'message' => $e->getMessage() ), $e->getCode() );
        }

        return new WP_REST_Response( $response );
    }

    /**
     * Get shipment (waybill) statuses for multiple orders.
     *
     * @deprecated Webhook-based status updates replace polling. Endpoint /orders/get-shipment-statuses is deprecated.
     *
     * @param WP_REST_Request $request The REST request object containing order IDs.
     *
     * @return WP_REST_Response The REST response with shipment statuses or error message.
     */
    public function get_shipment_statuses( WP_REST_Request $request ): WP_REST_Response {

        $order_ids = $request->get_param( 'orderIds' );

        if ( empty( $order_ids ) ) {
            return new WP_REST_Response( array( 'message' => __( 'No order IDs provided', 'nova-post-for-woocommerce' ) ), 422 );
        }

        try {
            $response = ShipmentApiService::get_shipments_statuses( $order_ids );
        } catch ( ShipmentException $e ) {
            return new WP_REST_Response( $e->get_data() ?? array( 'message' => $e->getMessage() ), $e->getCode() );
        }

        return new WP_REST_Response( $response );
    }

    /**
     * Delete shipment by order ID.
     *
     * @param WP_REST_Request $request The REST request object containing order ID.
     *
     * @return WP_REST_Response The REST response with deletion result or error message.
     */
    public function delete_shipment( WP_REST_Request $request ): WP_REST_Response {
        $order_id = $request->get_param( 'orderId' );

        try {
            $response = ShipmentApiService::delete_shipment_by_order_id( $order_id );
        } catch ( ShipmentException $e ) {
            return new WP_REST_Response( $e->get_data() ?? array( 'message' => $e->getMessage() ), $e->getCode() ? $e->getCode() : 400 );
        }

        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Print shipment documents.
     *
     * Returns a WP_REST_Response containing PDF binary and headers. If the REST server attempts
     * to JSON-encode the body, add a `rest_pre_serve_request` filter elsewhere to stream raw output
     * when the `Content-Type` is `application/pdf`.
     *
     * @param WP_REST_Request $request The REST request object containing print parameters.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function print_shipment( WP_REST_Request $request ) {
        try {
            $order_id = $request->get_param( 'orderId' );
            $type     = $request->get_param( 'type' );

            if ( ! $order_id ) {
                return new WP_Error( 'missing_order_id', __( 'Order ID is required', 'nova-post-for-woocommerce' ), array( 'status' => 422 ) );
            }

            if ( ! $type ) {
                return new WP_Error( 'missing_type', __( 'Document type is required', 'nova-post-for-woocommerce' ), array( 'status' => 422 ) );
            }

            // Get shipment information first to retrieve document references.
            $shipment_info = ShipmentApiService::get_shipment( $order_id );

            if ( ! isset( $shipment_info['track_number'] ) ) {
                return new WP_Error( 'no_shipment_found', __( 'No shipment found for this order', 'nova-post-for-woocommerce' ), array( 'status' => 404 ) );
            }

            // Prepare print parameters based on the type and shipment info.
            $print_params = array(
                'type'    => $type,
                'numbers' => array( $shipment_info['track_number'] ),
            );

            if ( 'marking' === $type ) {
                $print_params['printSizeType'] = 'size_100_100';
            } else {
                $print_params['printSizeType'] = 'size_A4';
            }

            $print_response = ShipmentApiService::get_shipment_print( $print_params );

            // Check if we have valid PDF data.
            if ( empty( $print_response ) ) {
                $error_message = __( 'No shipment found for this order', 'nova-post-for-woocommerce' );
                $this->track_print_failed( $print_params, array( $error_message ) );
                return new WP_Error( 'no_pdf_data', $error_message, array( 'status' => 500 ) );
            }

            $this->track_print_created( $print_params );
            return new WP_REST_Response(
                array(
                    'success' => true,
                    'data'    => base64_encode( $print_response ), // phpcs:ignore
                )
            );

        } catch ( ShipmentException $e ) {
            $this->track_print_failed( $print_params ?? array(), $e->get_data() );
            return new WP_Error( 'shipment_exception', $e->get_data() ?? $e->getMessage(), array( 'status' => $e->getCode() ? $e->getCode() : 400 ) );
        } catch ( Exception $e ) {
            $this->track_print_failed( $print_params ?? array(), array( $e->getMessage() ) );
            return new WP_Error(
                'unexpected_error',
                sprintf(
                    // translators: %s: Error message text.
                    __( 'An unexpected error occurred: %s', 'nova-post-for-woocommerce' ),
                    $e->getMessage()
                ),
                array( 'status' => 500 )
            );
        }
    }
}
