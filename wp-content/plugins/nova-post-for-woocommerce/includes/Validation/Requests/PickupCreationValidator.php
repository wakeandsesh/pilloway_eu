<?php
/**
 * Pickup Creation Validator
 *
 * @package NovaPostWoocommerce\Validation\Requests
 */

namespace NovaPostForWoocommerce\Validation\Requests;

use NovaPostForWoocommerce\Exceptions\PickupException;
use NovaPostForWoocommerce\Validation\Validator;

/**
 * Validates pickup creation API requests.
 *
 * phpcs:disable WordPress.Security.EscapeOutput
 */
class PickupCreationValidator {
    /**
     * Get validation rules for the initial request.
     */
    private static function request_rules(): array {
        return array(
            'windowStart' => 'required|date|after_or_equal:now',
            'windowEnd'   => 'required|date|after:windowStart',
        );
    }

    /**
     * Get validation rules for the built parameters.
     */
    private static function params_rules(): array {
        return array(
            'countryCode'           => 'required|string|size:2|regex:/^[A-Z]{2}$/',
            'fullName'              => 'required|string|max:100',
            'phone'                 => 'required|string|min:8|max:14',
            'email'                 => 'nullable|email|max:100',
            'companyName'           => 'required_if_filled:companyTin|string|min:2|max:255',
            'companyTin'            => 'required_if_filled:companyName|string|min:2|max:20',
            'note'                  => 'nullable|string|max:255',
            'services'              => 'nullable|array',
            'services.*'            => 'string',
            'addressParts'          => 'required|array',
            'addressParts.postCode' => 'required|string|max:10',
            'addressParts.region'   => 'required|string|max:100',
            'addressParts.city'     => 'required|string|max:100',
            'addressParts.street'   => 'required|string|max:100',
            'addressParts.building' => 'required|string|max:100',
            'addressParts.flat'     => 'present|string|max:10',
            'addressParts.block'    => 'nullable|string|max:100',
            'addressParts.note'     => 'nullable|string|max:100',
            'pickedTimeFrom'        => array(
                'required',
                'date:Y-m-d\TH:i:s.u\Z',
                'regex:/^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}.[0-9]{6}Z$/',
            ),
            'pickedTimeTo'          => array(
                'required',
                'date:Y-m-d\TH:i:s.u\Z',
                'regex:/^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}.[0-9]{6}Z$/',
                'after:pickedTimeFrom',
            ),
        );
    }

    /**
     * Validate the initial request.
     *
     * @param array $request_data Request data to validate.
     * @throws PickupException If validation fails.
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
    }

    /**
     * Validate the built parameters.
     *
     * @param array $params Parameters to validate.
     * @throws PickupException If validation fails.
     */
    public static function validate_params( array $params ): void {
        $validator = Validator::validate( $params, self::params_rules() );

        if ( $validator->fails() ) {
            throw new PickupException(
                $validator->errors()->firstMessage(),
                422,
                array( 'errors' => $validator->errors()->allMessages() )
            );
        }
    }
}
