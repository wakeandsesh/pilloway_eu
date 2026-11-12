<?php
/**
 * Service class for handling Nova Post pickup operations.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Database\Repositories\PickupRepository;
use NovaPostForWoocommerce\Database\Repositories\OrderRepository;
use NovaPostForWoocommerce\Helpers\MeasurementUnitConstants;
use NovaPostForWoocommerce\Exceptions\PickupException;
use NovaPostForWoocommerce\Resources\PickupResource;
use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Database\Models\Pickup;
use WC_Order;

/**
 * Handles pickup-related operations for Nova Post shipping.
 *
 * phpcs:disable WordPress.Security.EscapeOutput
 */
class PickupService {
    /**
     * Maximum number of shipments allowed per pickup.
     */
    const MAX_SHIPMENTS_PER_PICKUP = 15;

    /**
     * The API service for pickup operations.
     *
     * @var PickupApiService
     */
    private PickupApiService $api_service;

    /**
     * The repository for pickup storage.
     *
     * @var PickupRepository
     */
    private PickupRepository $pickup_repository;

    /**
     * Plugin settings.
     *
     * @var NovaPostSettings
     */
    private NovaPostSettings $settings;

    /**
     * Constructor.
     *
     * @param PickupApiService $api_service The pickup API service.
     */
    public function __construct( PickupApiService $api_service ) {
        $this->api_service       = $api_service;
        $this->pickup_repository = new PickupRepository( new Pickup() );
        $this->settings          = NovaPostSettings::get_instance();
    }

    /**
     * Get time intervals for pickup.
     *
     * @param array $params The parameters for the request.
     * @return array The time intervals.
     * @throws PickupException If there are validation errors or the API call fails.
     */
    public function get_available_time_intervals( array $params ): array {
        return $this->api_service->fetch_time_intervals( $params );
    }

    /**
     * Build parameters for pickup time intervals request.
     *
     * @param array $request_data Request data containing order IDs.
     * @return array Built parameters for the API request.
     */
    public function build_time_intervals_params( array $request_data ): array {
        return array_merge(
            array(
                'type'        => 'PickupNextDay',
                'countryCode' => $this->settings->sender_country,
            ),
            $this->get_sender_location_params(),
            $this->get_shipments_total_weight( $request_data )
        );
    }

    /**
     * Get sender location parameters depending on an address type.
     *
     * @return array Sender location parameters.
     */
    private function get_sender_location_params(): array {
        if ( 'division' === $this->settings->address_type ) {
            return array(
                'longitude'  => $this->settings->sender_division_longitude,
                'latitude'   => $this->settings->sender_division_latitude,
                'divisionId' => $this->settings->sender_division_id,
            );
        }

        return $this->get_sender_address_parts();
    }

    /**
     * Get total weight parameters for the pickup request.
     *
     * @param array $request_data Request data containing order IDs.
     * @return array Total weight of shipments in kilograms.
     */
    private function get_shipments_total_weight( array $request_data ): array {
        $orders = OrderRepository::get_orders_by_ids( $request_data['orderIds'] );

        $total_weight = 0;
        foreach ( $orders as $order ) {
            $shipment_response = $order->get_meta( '_nova_post_shipment_api_response' );
            if ( ! empty( $shipment_response['parcels'] ) ) {
                $total_weight += array_sum( array_column( $shipment_response['parcels'], 'actualWeight' ) );
            }
        }

        if ( $total_weight <= 0 ) {
            return array();
        }

        return array(
            'maxWeightPlaceRecipient' => (int) ceil(
                $total_weight / MeasurementUnitConstants::KG_TO_GR_MULTIPLIER
            ),
        );
    }

    /**
     * Create a new pickup request.
     *
     * @param array $params The pickup request parameters.
     * @return array The API response.
     * @throws PickupException If there are validation errors or the API call fails.
     */
    public function create_pickup_request( array $params ): array {
        $pickup = $this->api_service->create_pickup( $params );

        $resource = new PickupResource(
            array_merge(
                $pickup,
                array(
                    'request_payload'  => $params,
                    'response_payload' => $pickup,
                )
            )
        );

        $pickup_id = $this->pickup_repository->create( $resource );
        if ( ! $pickup_id ) {
            throw new PickupException( __( 'Failed to save pickup to database', 'nova-post-for-woocommerce' ), 500 );
        }

        return $resource->to_array();
    }

    /**
     * Build parameters for pickup creation.
     *
     * @param array $request_data Request data containing pickup date and time interval.
     * @return array Built parameters for the API request.
     */
    public function build_pickup_params( array $request_data ): array {
        return array_merge(
            array( 'countryCode' => $this->settings->sender_country ),
            $this->get_sender_contact_details(),
            $this->get_sender_company_details(),
            $this->get_sender_address_parts(),
            $this->get_datetime_interval( $request_data )
        );
    }

    /**
     * Get sender contact details.
     *
     * @return array{
     *     fullName: string,
     *     phone: string,
     *     email: string
     * }
     */
    private function get_sender_contact_details(): array {
        return array(
            'fullName' => $this->settings->sender_first_name . ' ' . $this->settings->sender_last_name,
            'phone'    => $this->settings->sender_phone,
            'email'    => $this->settings->sender_email,
        );
    }

    /**
     * Get sender company details.
     *
     * @return array{
     *     companyName: string,
     *     companyTin: string
     * }
     */
    private function get_sender_company_details(): array {
        return array(
            'companyName' => $this->settings->company_name,
            'companyTin'  => $this->settings->company_tin,
        );
    }

    /**
     * Get sender address parts.
     *
     * @return array{
     *     addressParts: array{
     *         postCode: string,
     *         region: string,
     *         city: string,
     *         street: string,
     *         house: string,
     *         flat: string
     *     }
     * }
     */
    private function get_sender_address_parts(): array {
        return array(
            'addressParts' => array(
                'postCode' => $this->settings->sender_postal_code,
                'region'   => $this->settings->sender_country,
                'city'     => $this->settings->sender_city,
                'street'   => $this->settings->sender_street,
                'building' => $this->settings->sender_building,
                'flat'     => '',
            ),
        );
    }

    /**
     * Get datetime interval for pickup.
     *
     * @param array $request_data Request data containing a pickup time window.
     * @return array{
     *     pickedTimeFrom: string,
     *     pickedTimeTo: string
     * }
     */
    private function get_datetime_interval( array $request_data ): array {
        return array(
            'pickedTimeFrom' => $request_data['windowStart'],
            'pickedTimeTo'   => $request_data['windowEnd'],
        );
    }

    /**
     * Retrieves pickup data from the repository and ensures it is returned as an array.
     *
     * @param int|string $pickup_id The unique identifier for the pickup.
     * @return array The pickup data, cast from object to array if necessary.
     */
    public function get_pickup_data( $pickup_id ): array {
        $pickup = $this->pickup_repository->find( $pickup_id );

        return (array) $pickup;
    }

    /**
     * Add shipments to a pickup.
     *
     * @param int   $pickup_id The pickup ID.
     * @param int[] $order_ids The order IDs to add.
     * @return array The result of the operation.
     * @throws PickupException If the operation fails.
     */
    public function add_shipments_to_pickup( int $pickup_id, array $order_ids ): array {
        $pickup = $this->pickup_repository->find( $pickup_id );
        if ( 'draft' !== strtolower( $pickup->status ) ) {
            throw new PickupException(
                __( 'Wrong pickup status. Must be in `Draft` to add shipments', 'nova-post-for-woocommerce' ),
                400,
            );
        }

        $orders       = OrderRepository::get_orders_by_ids( $order_ids );
        $shipment_ids = $this->validate_shipments_for_pickup( $pickup_id, $orders );

        if ( $pickup->order_count + count( $shipment_ids ) > self::MAX_SHIPMENTS_PER_PICKUP ) {
            throw new PickupException(
                sprintf(
                    /* translators: %d: maximum shipments per pickup. */
                    __( 'Cannot exceed %d shipments per pickup', 'nova-post-for-woocommerce' ),
                    self::MAX_SHIPMENTS_PER_PICKUP
                ),
                400,
            );
        }

        $shipment_payload = array( 'shipments' => array_map( fn( $id ) => array( 'shipmentId' => $id ), $shipment_ids ) );

        $shipments = $this->api_service->add_shipments_to_pickup( $pickup_id, $shipment_payload );

        OrderRepository::assign_pickup( $orders, $pickup );
        $this->pickup_repository->increment_order_count( $pickup_id, count( $orders ) );
        $this->pickup_repository->increment_lock_version( $pickup_id );

        return $shipments;
    }

    /**
     * Validates shipments for a pickup and returns valid shipment IDs.
     *
     * @param int   $pickup_id The ID of the pickup.
     * @param array $orders The list of orders to validate.
     * @return array Array of valid shipment IDs.
     * @throws PickupException If any order is not eligible for the pickup.
     */
    public function validate_shipments_for_pickup( int $pickup_id, array $orders ): array {
        $valid_shipment_ids = array();
        $validation_errors  = array();

        foreach ( $orders as $order ) {
            $validation_error = self::get_pickup_validation_message( $order, $pickup_id );
            if ( null !== $validation_error ) {
                $validation_errors[ $order->get_id() ] = $validation_error;
            } else {
                $valid_shipment_ids[] = $order->get_meta( '_nova_post_shipment_id', true );
            }
        }

        if ( ! empty( $validation_errors ) ) {
            $formatted_errors = array();
            foreach ( $validation_errors as $order_id => $error_msg ) {
                $formatted_errors[] = sprintf(
                    /* translators: %d: order ID, %s: error message */
                    __( 'Order #%1$d: %2$s', 'nova-post-for-woocommerce' ),
                    $order_id,
                    $error_msg
                );
            }

            throw new PickupException(
                __( 'Some orders are not eligible for pickup', 'nova-post-for-woocommerce' ),
                400,
                array( 'errors' => $formatted_errors )
            );
        }

        return $valid_shipment_ids;
    }

    /**
     * Get a validation error message for pickup eligibility.
     *
     * @param WC_Order $order Order instance.
     * @param int      $pickup_id Pickup ID to validate against.
     * @return string|null
     */
    public static function get_pickup_validation_error_message( WC_Order $order, int $pickup_id ): ?string {
        return self::get_pickup_validation_message( $order, $pickup_id );
    }

    /**
     * Get a pickup validation error message for the provided order.
     *
     * @param WC_Order $order Order instance.
     * @param int      $pickup_id Pickup ID being validated.
     * @return string|null Comma-separated list of errors, or null if valid.
     */
    private static function get_pickup_validation_message( WC_Order $order, int $pickup_id ): ?string {
        $errors = array();

        $existing_pickup_id = (int) $order->get_meta( '_nova_post_pickup_id', true );
        if ( $existing_pickup_id && ( $existing_pickup_id !== $pickup_id ) ) {
            $errors[] = __( 'Already assigned to another pickup', 'nova-post-for-woocommerce' );
        }

        $shipment_api_request = $order->get_meta( '_nova_post_shipment_api_request', true );

        $shipment_sender = $shipment_api_request['sender'] ?? array();
        if ( empty( $shipment_sender['addressParts'] ) || ! empty( $shipment_sender['divisionId'] ) ) {
            $errors[] = __( 'Division delivery not supported. Use a full address for pickup', 'nova-post-for-woocommerce' );
        }

        if ( ! self::is_sender_matching_settings( $shipment_sender ) ) {
            $errors[] = __( "Sender details don't match current settings", 'nova-post-for-woocommerce' );
        }

        $shipment_api_response = $order->get_meta( '_nova_post_shipment_api_response', true );

        $status = $shipment_api_response['status'] ?? '';
        if ( ShipmentService::STATUS_READY_TO_SHIP !== $status ) {
            $errors[] = __( 'Status must be: ', 'nova-post-for-woocommerce' ) . ShipmentService::STATUS_READY_TO_SHIP;
        }

        $services = $shipment_api_response['services'] ?? array();

        $payment_status = ShipmentService::get_shipment_payment_status( $services );
        if ( ! in_array( $payment_status, ShipmentService::VALID_PAYMENT_STATUSES, true ) ) {
            $allowed_statuses = implode( ', ', array_map( 'strval', ShipmentService::VALID_PAYMENT_STATUSES ) );
            $errors[]         = __( 'Payment status must be one of: ', 'nova-post-for-woocommerce' ) . $allowed_statuses;
        }

        return ! empty( $errors ) ? implode( '; ', $errors ) : null;
    }

    /**
     * Check if the shipment sender matches the current sender settings.
     *
     * @param array $shipment_sender The sender data from the shipment.
     * @return bool True if sender details match, false otherwise.
     */
    private static function is_sender_matching_settings( array $shipment_sender ): bool {
        $settings = NovaPostSettings::get_instance();

        $normalize_phone       = static fn( ?string $phone ): string => preg_replace( '/\D+/', '', (string) $phone );
        $normalize_text        = static fn( ?string $value ): string => mb_strtolower( trim( (string) $value ) );
        $normalize_email       = static fn( ?string $value ): string => strtolower( trim( (string) $value ) );
        $normalize_country     = static fn( ?string $value ): string => strtoupper( trim( (string) $value ) );
        $normalize_postal_code = static fn( ?string $value ): string => preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $value ) );

        $expected_name = trim( $settings->sender_first_name . ' ' . $settings->sender_last_name );
        $actual_name   = $shipment_sender['name'] ?? '';

        if ( $normalize_phone( $settings->sender_phone ) !== $normalize_phone( $shipment_sender['phone'] ?? '' ) ) {
            return false;
        }

        if ( $normalize_text( $expected_name ) !== $normalize_text( $actual_name ) ) {
            return false;
        }

        if ( $normalize_email( $settings->sender_email ) !== $normalize_email( $shipment_sender['email'] ?? '' ) ) {
            return false;
        }

        if ( $normalize_country( $settings->sender_country ) !== $normalize_country( $shipment_sender['countryCode'] ?? '' ) ) {
            return false;
        }

        $address_parts = $shipment_sender['addressParts'] ?? array();
        if ( ! is_array( $address_parts ) ) {
            return false;
        }

        $expected_address = array(
            'postCode' => $normalize_postal_code( $settings->sender_postal_code ),
            'city'     => $normalize_text( $settings->sender_city ),
            'street'   => $normalize_text( $settings->sender_street ),
            'building' => $normalize_text( $settings->sender_building ),
        );

        $actual_address = array(
            'postCode' => $normalize_postal_code( $address_parts['postCode'] ?? '' ),
            'city'     => $normalize_text( $address_parts['city'] ?? '' ),
            'street'   => $normalize_text( $address_parts['street'] ?? '' ),
            'building' => $normalize_text( $address_parts['building'] ?? '' ),
        );

        foreach ( $expected_address as $key => $expected_value ) {
            if ( '' === $expected_value || $expected_value !== $actual_address[ $key ] ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Update the status of an existing pickup.
     *
     * @param int   $pickup_id The pickup ID.
     * @param int[] $params Parameters to update.
     * @return array The result of the operation.
     * @throws PickupException If pickup has no shipments attached.
     */
    public function update_pickup_status( int $pickup_id, array $params ): array {
        $pickup = $this->pickup_repository->find( $pickup_id );
        if ( 0 === (int) $pickup->order_count ) {
            throw new PickupException(
                __( 'Pickup has no shipments', 'nova-post-for-woocommerce' ),
                400,
            );
        }

        $params['lockVersion'] = (int) $pickup->lock_version;

        $result = $this->api_service->update_pickup_status( $pickup_id, $params );

        if ( $result['success'] ) {
            $this->pickup_repository->update_status( $pickup_id, $params['status'] );
            $this->pickup_repository->increment_lock_version( $pickup_id );
        }

        return $result;
    }
}
