<?php
/**
 * Bulk Shipment Controller
 *
 * Handles bulk shipment creation for multiple orders.
 *
 * @package NovaPostForWoocommerce
 * @subpackage Api\Controllers
 */

namespace NovaPostForWoocommerce\Api\Controllers;

use NovaPostForWoocommerce\Models\Traits\TrackEvent;
use NovaPostForWoocommerce\Services\AsyncShipmentApiService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Controller for bulk shipment operations.
 */
class BulkShipmentController {

    use TrackEvent;

    const MAX_ORDERS_PER_REQUEST = 100;

    /**
     * Async shipment service instance.
     *
     * @var AsyncShipmentApiService
     */
    private $async_shipment_service;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->async_shipment_service = new AsyncShipmentApiService();
    }

    /**
     * Create shipments for multiple orders.
     *
     * @param WP_REST_Request $request The REST request object containing order IDs.
     *
     * @return WP_REST_Response|WP_Error The REST response with results or error message.
     */
    public function create_bulk_shipments( WP_REST_Request $request ): WP_REST_Response|WP_Error {

        // Close session to allow parallel requests.
        if ( session_id() || session_status() === PHP_SESSION_ACTIVE ) {
            session_write_close();
        }

        $order_ids = $request->get_param( 'orderIds' );

        $validation_error = $this->validate_order_ids( $order_ids );

        if ( is_wp_error( $validation_error ) ) {
            return $validation_error;
        }

        // Use async processing for better performance.
        $delay_ms = $request->get_param( 'delay_ms' );
        $results  = $this->async_shipment_service->create_bulk_shipments_async( $order_ids, $delay_ms );

        $response_arr = array(
            'results' => $results,
            'summary' => array(
                'total'   => count( $order_ids ),
                'success' => count( $results['success'] ),
                'failed'  => count( $results['failed'] ),
                'skipped' => count( $results['skipped'] ),
            ),
        );
        $this->track_bulk_shipments_items( $response_arr );

        return new WP_REST_Response( $response_arr, 200 );
    }

    /**
     * Validate order IDs.
     *
     * @param mixed $order_ids Order IDs to validate.
     * @return bool|WP_Error True if valid, WP_Error otherwise.
     */
    private function validate_order_ids( $order_ids ) {
        if ( ! is_array( $order_ids ) || empty( $order_ids ) ) {
            return new WP_Error(
                'invalid_order_ids',
                __( 'Order IDs must be a non-empty array', 'nova-post-for-woocommerce' ),
                array( 'status' => 400 )
            );
        }

        if ( count( $order_ids ) > self::MAX_ORDERS_PER_REQUEST ) {
            return new WP_Error(
                'too_many_orders',
                sprintf(
                    /* translators: %d: maximum number of orders allowed */
                    __( 'Maximum %d orders can be processed at once', 'nova-post-for-woocommerce' ),
                    self::MAX_ORDERS_PER_REQUEST
                ),
                array( 'status' => 400 )
            );
        }

        return true;
    }
}
