<?php
/**
 * Order Controller
 *
 * Handles order-related API endpoints for Nova Post shipping.
 *
 * @package NovaPostForWoocommerce\Api\Controllers
 */

namespace NovaPostForWoocommerce\Api\Controllers;

use NovaPostForWoocommerce\Models\Traits\TrackEvent;
use NovaPostForWoocommerce\Validation\Validator;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OrderController
 *
 * @package NovaPostForWoocommerce\Api\Controllers
 */
class OrderController {

    use TrackEvent;

    /**
     * Updates the Nova Post division for an order.
     *
     * @param WP_REST_Request $request The REST request object containing order and division data.
     *
     * @return WP_REST_Response The REST response with success or error message.
     */
    public function update_division( WP_REST_Request $request ): WP_REST_Response {
        $validator = Validator::validate(
            $request->get_params(),
            array(
                'orderId'       => 'required|numeric',
                'division'      => 'nullable',
                'division_id'   => 'required|numeric',
                'division_type' => 'required|string',
            )
        );

        if ( $validator->fails() ) {
            return new WP_REST_Response(
                array(
                    'errors'  => $validator->errors()->allMessages(),
                    'message' => $validator->errors()->firstMessage(),
                ),
                422
            );
        }

        $data = $validator->getValidatedData();

        $order = wc_get_order( $data['orderId'] );

        if ( ! $order ) {
            return new WP_REST_Response( array( 'message' => __( 'Order not found.', 'nova-post-for-woocommerce' ) ), 404 );
        }

        try {
            $order->update_meta_data( '_nova_post_division', $data['division'] ?? null );
            $order->update_meta_data( '_nova_post_division_id', (int) $data['division_id'] );
            $order->update_meta_data( '_nova_post_division_type', $data['division_type'] );
            $order->save_meta_data();

            $this->track_branch_replaced( $data );
        } catch ( \Exception $e ) {
            return new WP_REST_Response( array( 'message' => __( 'An error occurred while updating the order division.', 'nova-post-for-woocommerce' ) ), 500 );
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Gets the Nova Post division for an order.
     *
     * @param WP_REST_Request $request The REST request object containing the order ID.
     *
     * @return WP_REST_Response The REST response with division data or error message.
     */
    public function get_division( WP_REST_Request $request ): WP_REST_Response {
        $order_id = $request->get_param( 'orderId' );

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_REST_Response( array( 'message' => __( 'Order not found.', 'nova-post-for-woocommerce' ) ), 404 );
        }

        return new WP_REST_Response(
            array(
                'division'         => $order->get_meta( '_nova_post_division' ),
                'division_id'      => $order->get_meta( '_nova_post_division_id' ),
                'division_type'    => $order->get_meta( '_nova_post_division_type' ),
                'shipping_address' => array(
                    'country' => $order->get_shipping_country(),
                    'city'    => $order->get_shipping_city(),
                ),
            ),
            200
        );
    }
}
