<?php
/**
 * Pickup Time Intervals Validator
 *
 * @package NovaPostForWoocommerce\Validation\Requests
 */

namespace NovaPostForWoocommerce\Validation\Requests;

use NovaPostForWoocommerce\Database\Repositories\OrderRepository;
use NovaPostForWoocommerce\Validation\Rules\ExistingWooOrderRule;
use NovaPostForWoocommerce\Exceptions\PickupException;
use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Validation\Validator;
use WP_REST_Request;

/**
 * Validates pickup time intervals API requests.
 *
 * phpcs:disable WordPress.Security.EscapeOutput
 */
class PickupTimeIntervalsValidator {
    /**
     * Get validation rules for the initial request.
     */
    protected static function request_rules(): array {
        return array(
            'orderIds'   => 'required|array|min:1|max:15',
            'orderIds.*' => 'required|integer|existing_woo_order',
        );
    }

    /**
     * Get validation rules for the built parameters.
     *
     * @return array
     */
    protected static function params_rules(): array {
        $settings = NovaPostSettings::get_instance();

        $rules = array(
            'type'                    => 'required|string|in:PickupNextDay',
            'countryCode'             => 'required|string|size:2|regex:/^[A-Z]{2}$/',
            'maxWeightPlaceRecipient' => 'nullable|numeric|min:0',
        );

        if ( 'division' === $settings->address_type ) {
            $rules['latitude']   = 'required|numeric|between:-90,90';
            $rules['longitude']  = 'required|numeric|between:-180,180';
            $rules['divisionId'] = 'nullable|integer|min:1';
        } elseif ( 'address' === $settings->address_type ) {
            $rules['addressParts']          = 'required|array';
            $rules['addressParts.postCode'] = 'required|string|max:10';
            $rules['addressParts.region']   = 'required|string|max:100';
            $rules['addressParts.city']     = 'required|string|max:100';
            $rules['addressParts.street']   = 'required|string|max:100';
            $rules['addressParts.building'] = 'required|string|max:100';
            $rules['addressParts.flat']     = 'nullable|string|max:10';
            $rules['addressParts.note']     = 'nullable|string|max:100';
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    protected static function params_messages(): array {
        return array(
            'type.in'               => __( 'Type must be `PickupNextDay`', 'nova-post-for-woocommerce' ),
            'latitude.required'     => __( 'Latitude is required when departure method is division', 'nova-post-for-woocommerce' ),
            'longitude.required'    => __( 'Longitude is required when departure method is division', 'nova-post-for-woocommerce' ),
            'addressParts.required' => __( 'addressParts is required when departure method is address', 'nova-post-for-woocommerce' ),
        );
    }

    /**
     * Validate the initial request.
     *
     * @param array $request_data Request data containing order IDs.
     * @throws PickupException If validation fails or orders don't have shipments.
     */
    public static function validate_request( array $request_data ): void {
        $validator = Validator::validate( $request_data, static::request_rules() );

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
            $order_list = implode( ', #', array_values( $ids_without_shipment ) );
            $message    = sprintf(
                /* translators: %s: comma-separated order IDs */
                __( 'Orders #%s do not have shipments created. Please create shipments first', 'nova-post-for-woocommerce' ),
                $order_list
            );
            throw new PickupException( $message, 400, array( 'orderIds' => array_values( $ids_without_shipment ) ) );
        }
    }

    /**
     * Validate the built parameters.
     *
     * @param array $params Request parameters to validate.
     * @throws PickupException If validation fails.
     */
    public static function validate_params( array $params ): void {
        $validator = Validator::validate( $params, static::params_rules(), static::params_messages() );

        if ( $validator->fails() ) {
            throw new PickupException(
                $validator->errors()->firstMessage(),
                422,
                array( 'errors' => $validator->errors()->allMessages() )
            );
        }
    }
}
