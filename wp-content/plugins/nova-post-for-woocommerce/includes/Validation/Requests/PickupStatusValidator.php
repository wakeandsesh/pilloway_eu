<?php
/**
 * Pickup Status Validator
 *
 * @package NovaPostForWoocommerce\Validation\Requests
 */

namespace NovaPostForWoocommerce\Validation\Requests;

use NovaPostForWoocommerce\Exceptions\PickupException;
use NovaPostForWoocommerce\Validation\Validator;

/**
 * Validates pickup status update API requests.
 *
 * phpcs:disable WordPress.Security.EscapeOutput
 */
class PickupStatusValidator {
    /**
     * Get validation rules for the request parameters.
     *
     * @return array
     */
    protected static function request_rules(): array {
        return array(
            'pickupId' => 'required|integer|min:1|exists_in_custom_table:np_pickups',
            'status'   => 'required|in:Created',
            'note'     => 'required|max:255',
        );
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    protected static function request_messages(): array {
        return array(
            'status.in'     => __( 'Status must be `Created`', 'nova-post-for-woocommerce' ),
            'note.required' => __( 'Please provide the reason for the status update.', 'nova-post-for-woocommerce' ),
        );
    }

    /**
     * Validate the request data.
     *
     * @param array $request_data Request data to validate.
     * @throws PickupException If validation fails.
     */
    public static function validate_request( array $request_data ): void {
        $validator = Validator::validate( $request_data, static::request_rules(), static::request_messages() );

        if ( $validator->fails() ) {
            throw new PickupException(
                $validator->errors()->firstMessage(),
                422,
                array( 'errors' => $validator->errors()->allMessages() )
            );
        }
    }
}
