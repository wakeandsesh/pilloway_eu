<?php
/**
 * Pickup Shipments Validator
 *
 * @package NovaPostWoocommerce\Validation\Requests
 */

namespace NovaPostForWoocommerce\Validation\Requests;

use NovaPostForWoocommerce\Database\Repositories\OrderRepository;
use NovaPostForWoocommerce\Exceptions\ShipmentException;
use NovaPostForWoocommerce\Exceptions\PickupException;
use NovaPostForWoocommerce\Validation\Validator;

/**
 * Validates pickup shipments API requests.
 *
 * phpcs:disable WordPress.Security.EscapeOutput
 */
class PickupShipmentsValidator {
    /**
     * Get validation rules for the request parameters.
     */
    private static function request_rules(): array {
        return array(
            'pickupId'   => 'required|integer|exists_in_custom_table:np_pickups',
            'orderIds'   => 'required|array|min:1|max:15',
            'orderIds.*' => 'required|integer|existing_woo_order',
        );
    }

    /**
     * Validate the request data.
     *
     * @param array $request_data Request data to validate.
     * @throws PickupException If validation fails.
     * @throws ShipmentException If the order does not have a shipment.
     */
    public static function validate_request( array $request_data ): void {
        $validator = Validator::validate( $request_data, self::request_rules() );

        if ( $validator->fails() ) {
            throw new PickupException(
                $validator->errors()->firstMessage(),
                422,
                array( 'errors' => $validator->errors()->allMessages() )
            );
        }

        $order_ids            = $request_data['orderIds'];
        $ids_with_shipment    = OrderRepository::get_order_ids_with_shipment( $order_ids );
        $ids_without_shipment = array_diff( $order_ids, $ids_with_shipment );
        if ( ! empty( $ids_without_shipment ) ) {
            throw ShipmentException::notFound(
                array( 'orderIds' => array_values( $ids_without_shipment ) )
            );
        }
    }
}
