<?php
/**
 * Shipment API Service for handling Nova Post shipping operations.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

use Exception;
use NovaPostForWoocommerce\Database\Repositories\OrderRepository;
use NovaPostForWoocommerce\Factories\NovaPost;
use NovaPostForWoocommerce\Logger\Logger;
use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Exceptions\ShipmentException;
use WC_Order;
use NovaPostForWoocommerce\Helpers\ShipmentStatusHelper;
use NovaPostForWoocommerce\Services\ShipmentService;

/**
 * Handles all API interactions with Nova Post shipping service.
 *
 * This class is responsible for creating, retrieving, updating, and deleting
 * shipments through the Nova Post API, as well as handling related operations
 * such as printing shipping labels and tracking shipments.
 *
 * phpcs:disable WordPress.Security.EscapeOutput
 */
class ShipmentApiService {

    /**
     * Country code for customs fee calculation.
     */
    public const COUNTRY_UA = 'UA';

    /**
     * Create a shipment (waybill) for a specific order.
     *
     * @param int    $order_id The WooCommerce order ID.
     * @param string $note Optional note for the shipment.
     * @return array{
     *     track_number: string,
     *     tracking_url: string,
     *     status: string,
     *     shipment_id: string
     * } Shipment information.
     * @throws ShipmentException If the shipment cannot be created.
     */
    public static function create_shipment_from_order( $order_id, $note = '' ): array {
        $order = OrderRepository::get_order_or_fail( $order_id );

        if ( $order->get_meta( '_nova_post_shipment_id' ) ) {
            throw new ShipmentException( __( 'Shipment already exists for the order', 'nova-post-for-woocommerce' ), 409 );
        }

        $shipment_data    = self::prepare_shipment_data( $order, $note );
        $validation_error = self::validate_shipment_data( $shipment_data );
        if ( $validation_error ) {
            $order->update_meta_data( '_nova_post_shipment_errors', array( $validation_error ) );
            $order->save_meta_data();
            throw new ShipmentException(
                $validation_error,
                422,
                array(
                    'message' => $validation_error,
                )
            ); //phpcs:ignore
        }

        $api_response = NovaPost::get_client()->shipments()->create( $shipment_data );

        $order->update_meta_data( '_nova_post_shipment_id', $api_response['id'] ?? null );
        $order->update_meta_data( '_nova_post_shipment_number', $api_response['number'] ?? null );
        $order->update_meta_data( '_nova_post_shipment_status', isset( $api_response['id'] ) ? ShipmentStatusHelper::get_text_status( 1 ) : null );
        $order->update_meta_data( '_nova_post_shipment_status_code', isset( $api_response['id'] ) ? 1 : null );
        $order->update_meta_data( '_nova_post_shipment_api_request', $shipment_data );
        $order->update_meta_data( '_nova_post_shipment_api_response', $api_response );
        $order->delete_meta_data( '_nova_post_shipment_errors' );

        if ( isset( $api_response['errors'] ) ) {
            $order->update_meta_data( '_nova_post_shipment_errors', $api_response['errors'] );
        }

        $order->save_meta_data();

        if ( isset( $api_response['id'] ) ) {
            WebhookService::register_tracking_numbers( array( $api_response['number'] ) );
            ShipmentService::shipment_created( $order );
            return ShipmentService::get_order_shipment_info( $order );
        } else {
            $encoded_errors = wp_json_encode( $api_response['errors'] ?? null );
            throw new ShipmentException(
                __( 'Shipment creation failed', 'nova-post-for-woocommerce' ),
                400,
                array(
                    'message'      => $encoded_errors,
                    'api_request'  => $shipment_data,
                    'api_response' => $api_response,
                    'api_errors'   => $api_response['errors'] ?? null,
                )
            );
        }
    }

    /**
     * Prepare and validate shipment data for preview before creation.
     *
     * This method prepares all shipment data and validates it, returning both
     * the prepared data and any validation errors. Used for preview modal.
     *
     * @param int $order_id The WooCommerce order ID.
     * @return array{
     *     shipment_data: array,
     *     validation_error: string|null,
     *     is_valid: bool,
     *     delivery_type: array{
     *         is_local: bool,
     *         is_eu: bool,
     *         is_international: bool
     *     }
     * } Preview data with validation status.
     * @throws ShipmentException If order not found or shipment already exists.
     */
    public static function prepare_and_validate_shipment_preview( $order_id ): array {
        $order = OrderRepository::get_order_or_fail( $order_id );

        if ( $order->get_meta( '_nova_post_shipment_id' ) ) {
            throw new ShipmentException( 'Shipment already exists for the order', 409 );
        }

        $shipment_data    = self::prepare_shipment_data( $order );
        $validation_error = self::validate_shipment_data( $shipment_data );

        $shipment_data = self::prepare_preview_data( $shipment_data, $order );

        $is_local         = ShipmentService::is_local_delivery( $order );
        $is_eu            = ShipmentService::is_eu_delivery( $order );
        $is_international = ! $is_local && ! $is_eu;

        return array(
            'shipment_data'    => $shipment_data,
            'validation_error' => $validation_error,
            'is_valid'         => null === $validation_error,
            'delivery_type'    => array(
                'is_local'         => $is_local,
                'is_eu'            => $is_eu,
                'is_international' => $is_international,
            ),
        );
    }

    /**
     * Get shipment (waybill) information for a specific order.
     *
     * @param int $order_id The WooCommerce order ID.
     * @return array{
     *     track_number: string,
     *     shipment_id: string,
     *     shipment_api_request: array,
     *     shipment_api_response: array,
     *     last_status: string
     * } Shipment information.
     * @throws ShipmentException If the shipment is not found.
     */
    public static function get_shipment( $order_id ): array {
        $order = OrderRepository::get_order_or_fail( $order_id );

        $shipment_id = $order->get_meta( '_nova_post_shipment_id' );

        if ( ! $shipment_id ) {
            throw ShipmentException::notFound();
        }

        return ShipmentService::get_order_shipment_info( $order );
    }

    /**
     * Get the current status of a shipment.
     *
     * @param int $order_id The WooCommerce order ID.
     * @return array{
     *     status: string,
     *     status_text: string,
     *     last_updated: string,
     *     tracking_url: string
     * @throws ShipmentException If the shipment is not found or there's an API error.
     */
    public static function get_shipment_status( $order_id ): array {
        $order = OrderRepository::get_order_or_fail( $order_id );

        $shipment_id = $order->get_meta( '_nova_post_shipment_id' );

        if ( ! $shipment_id ) {
            throw ShipmentException::notFound();
        }

        $current_status = null;
        if ( $shipment_id ) {
            try {
                $api_response   = NovaPost::get_client()->shipments()->get( array( 'ids' => array( $shipment_id ) ) );
                $current_status = $api_response['items'][0]['status'] ?? null;
                $order->update_meta_data( '_nova_post_shipment_status', $current_status );
                $order->save_meta_data();
            } catch ( Exception $e ) {
                $current_status = 'unknown';

                Logger::write_log(
                    'error',
                    'Unknown status of get shipment.',
                    array(
                        'error' => $e->getMessage(),
                    )
                );
            }
        }

        return array(
            'track_number'   => $order->get_meta( '_nova_post_shipment_number' ),
            'current_status' => $current_status,
            'last_status'    => $order->get_meta( '_nova_post_shipment_status' ),
            'shipment_id'    => $shipment_id,
        );
    }

    /**
     * Get all shipments statuses.
     *
     * @deprecated Webhook-based status updates replace polling. Use WebhookService instead.
     *
     * @param array $order_ids The WooCommerce order IDs.
     *
     * @throws ShipmentException If the shipment is not found or there's an API error.
     *
     * @return array
     */
    public static function get_shipments_statuses( $order_ids ) {
        $orders = array();
        foreach ( $order_ids as $order_id ) {
            $order = OrderRepository::get_order_or_fail( $order_id );

            $tracking_number = $order->get_meta( '_nova_post_shipment_number' );

            if ( ! $tracking_number ) {
                continue;
            }

            $orders[ $tracking_number ] = $order;
        }

        $api_response = NovaPost::get_client()->shipments()->trackingHistory( array( 'numbers' => array_keys( $orders ) ) );

        $result = array();

        if ( ! empty( $api_response['items'] ) ) {
            foreach ( $api_response['items'] as $item ) {
                $order = $orders[ $item['number'] ];

                $last_history_item = end( $item['history_tracking'] );
                $status_code       = $last_history_item['code'];
                $status            = ShipmentStatusHelper::get_text_status( $status_code );

                $result[ $order->get_id() ] = array(
                    'status_code' => $status_code,
                    'status'      => $status,
                );

                $order->update_meta_data( '_nova_post_shipment_status', $status );
                $order->update_meta_data( '_nova_post_shipment_status_code', $status_code );
                $order->save_meta_data();
            }
        }

        if ( ! empty( $api_response['errors'] ) ) {
            throw new ShipmentException(
                '',
                400,
                array(
                    'message' => __( 'Failed to get shipments statuses via API', 'nova-post-for-woocommerce' ),
                    'errors'  => $api_response['errors'],
                ),
            );
        }

        return array( 'items' => $result );
    }

    /**
     * Prepare data to review before create shipment.
     *
     * This method prepares all necessary data for creating a new shipment
     * in the Nova Post system based on the WooCommerce order.
     *
     * @param array    $shipment_data prepared shipment data.
     * @param WC_Order $order The WooCommerce order object.
     * @return array{
     *     status: string,
     *     clientOrder: string,
     *     note: string,
     *     payerType: string,
     *     payerContractNumber: string|null,
     *     invoice: array,
     *     services: array,
     *     parcels: array,
     *     sender: array,
     *     recipient: array
     * } Prepared shipment data for API request.
     */
    private static function prepare_preview_data( &$shipment_data, $order ): array {
        $settings = NovaPostSettings::get_instance();

        if ( $shipment_data['sender']['divisionId'] ) {
            $shipment_data['sender']['divisionData'] = array(
                'name'    => $settings->sender_division_name ?? __( 'Unknown', 'nova-post-for-woocommerce' ),
                'address' => $settings->sender_division_address ?? __( 'Unknown', 'nova-post-for-woocommerce' ),
            );
        }

        if ( $shipment_data['recipient']['divisionId'] ) {
            $division_data = $order->get_meta( '_nova_post_division' );

            // Ensure division data has the expected structure.
            if ( is_array( $division_data ) && ! empty( $division_data ) ) {
                $shipment_data['recipient']['divisionData'] = array(
                    'name'    => $division_data['name'] ?? __( 'Unknown', 'nova-post-for-woocommerce' ),
                    'address' => $division_data['address'] ?? __( 'Unknown', 'nova-post-for-woocommerce' ),
                );
            } else {
                $shipment_data['recipient']['divisionData'] = array(
                    'name'    => __( 'Unknown', 'nova-post-for-woocommerce' ),
                    'address' => __( 'Unknown', 'nova-post-for-woocommerce' ),
                );
            }
        }

        return $shipment_data;
    }

    /**
     * Prepare shipment data for Nova Post API.
     *
     * This method prepares all necessary data for creating a new shipment
     * in the Nova Post system based on the WooCommerce order.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @param string   $note Optional note for the shipment.
     * @return array{
     *     status: string,
     *     clientOrder: string,
     *     note: string,
     *     payerType: string,
     *     payerContractNumber: string|null,
     *     invoice: array,
     *     services: array,
     *     parcels: array,
     *     sender: array,
     *     recipient: array
     * } Prepared shipment data for API request.
     */
    public static function prepare_shipment_data( $order, $note = '' ): array {
        $settings = NovaPostSettings::get_instance();

        $is_same_country       = ShipmentService::is_local_delivery( $order );
        $payer_contract_number = trim( (string) ( $is_same_country ? $settings->domestic_cn : $settings->international_cn ) );

        $invoice_data = ShipmentInvoiceService::prepare_invoice_data( $order, $settings );

        $shipment_data = array(
            'status'              => ShipmentService::STATUS_READY_TO_SHIP,
            'clientOrder'         => $order->get_order_number(),
            'note'                => $note,
            'payerType'           => 'Sender',
            'payerContractNumber' => empty( $payer_contract_number ) ? null : $payer_contract_number,
            'invoice'             => $invoice_data,
            'services'            => array(),
            'parcels'             => array(),
            'sender'              => ShipmentSenderService::prepare_sender_data( $settings ),
            'recipient'           => ShipmentRecipientService::prepare_recipient_data( $order ),
        );

        if ( self::COUNTRY_UA === $settings->sender_country && ! $is_same_country ) {
            $resolver          = ShipmentSettingsResolverService::get_instance();
            $resolved_settings = $resolver->resolve(
                $order->get_shipping_country(),
                $invoice_data['cost'] ?? 0,
                $settings->customs_fee_payer ?? 'Sender',
                $settings->delivery_tariff ?? 'standard',
                $settings->sender_country,
                $invoice_data['currency'] ?? $order->get_currency()
            );

            $shipment_data['deliveryType'] = $resolved_settings['delivery_tariff'];

            if ( is_array( $shipment_data['invoice'] ) ) {
                $shipment_data['invoice']['payerFeesCustoms'] = $resolved_settings['customs_fee_payer'];
                $shipment_data['invoice']['incoterm']         = $resolved_settings['incoterm'];
            }
        }

        if ( $order->get_payment_method() === 'cod' && self::COUNTRY_UA !== $settings->sender_country ) {
            $shipment_data['services'][] = ShipmentCodService::prepare_cod_data( $order, $settings );
        }

        $shipment_data['parcels'] = ShipmentParcelsService::prepare_parcels_data( $order, $settings );

        return $shipment_data;
    }

    /**
     * Validate shipment data before sending to API.
     *
     * This method validates that all required fields are present in the shipment data
     * before it's sent to the Nova Post API.
     *
     * @param array $shipment_data The shipment data to validate.
     * @return string|null Error message if validation fails, null if validation passes.
     */
    public static function validate_shipment_data( $shipment_data ): ?string {
        if ( empty( $shipment_data['sender']['phone'] ) ) {
            return __( 'Sender phone is required', 'nova-post-for-woocommerce' );
        }

        if ( empty( $shipment_data['sender']['email'] ) ) {
            return __( 'Sender email is required', 'nova-post-for-woocommerce' );
        }

        if ( empty( $shipment_data['sender']['name'] ) ) {
            return __( 'Sender name is required', 'nova-post-for-woocommerce' );
        }

        if ( empty( $shipment_data['recipient']['phone'] ) ) {
            return __( 'Recipient phone is required', 'nova-post-for-woocommerce' );
        }

        if ( empty( $shipment_data['recipient']['email'] ) ) {
            return __( 'Recipient email is required', 'nova-post-for-woocommerce' );
        }

        if ( empty( $shipment_data['recipient']['name'] ) ) {
            return __( 'Recipient name is required', 'nova-post-for-woocommerce' );
        }

        if ( ! isset( $shipment_data['recipient']['divisionId'] ) && ! isset( $shipment_data['recipient']['addressParts'] ) ) {
            return __( 'Either division or address must be specified for recipient', 'nova-post-for-woocommerce' );
        }

        if ( empty( $shipment_data['parcels'] ) ) {
            return __( 'At least one parcel is required', 'nova-post-for-woocommerce' );
        }

        return null;
    }

    /**
     * Delete a shipment by order ID.
     *
     * This method deletes a shipment from the Nova Post system and removes
     * all related meta-data from the WooCommerce order.
     *
     * @param int $order_id The WooCommerce order ID.
     * @return array{
     *     success: bool,
     *     api_response: array
     * } Deletion result.
     * @throws ShipmentException If the shipment cannot be deleted.
     */
    public static function delete_shipment_by_order_id( int $order_id ): array {
        $order = OrderRepository::get_order_or_fail( $order_id );

        $shipment_id = $order->get_meta( '_nova_post_shipment_id' );

        if ( empty( $shipment_id ) ) {
            throw ShipmentException::notFound();
        }

        try {
            $api_response = NovaPost::get_client()->shipments()->delete( $shipment_id );

            if ( ! empty( $api_response['errors'] ) ) {
                throw new ShipmentException(
                    __( 'Failed to delete waybill', 'nova-post-for-woocommerce' ),
                    400,
                    array( 'errors' => $api_response['errors'] )
                );
            }

            $order->delete_meta_data( '_nova_post_shipment_id' );
            $order->delete_meta_data( '_nova_post_shipment_number' );
            $order->delete_meta_data( '_nova_post_shipment_status' );
            $order->delete_meta_data( '_nova_post_shipment_status_code' );
            $order->delete_meta_data( '_nova_post_shipment_api_request' );
            $order->delete_meta_data( '_nova_post_shipment_api_response' );
            $order->delete_meta_data( '_nova_post_shipment_errors' );
            $order->save_meta_data();

            return array(
                'success'      => true,
                'api_response' => $api_response,
            );

        } catch ( Exception $e ) {
            throw new ShipmentException( $e->getMessage(), 500 );
        }
    }

    /**
     * Get printable documents for shipments.
     *
     * This method retrieves printable documents (like shipping labels) for shipments
     * from the Nova Post API.
     *
     * @param array $params {
     *     Optional. An array of parameters for the API request.
     *
     *     @type string $type The type of document to print (e.g., 'marking' for shipping labels).
     *     @type array  $numbers Array of shipment numbers to print.
     * }
     * @return array{
     *     data: array,
     *     type: string,
     *     errors: array
     * } The API response containing the printable documents.
     * @throws ShipmentException If there's an error retrieving the documents.
     */
    public static function get_shipment_print( $params ) {

        $api_response = NovaPost::get_client()->shipments()->print( $params );

        if ( ! empty( $api_response['errors'] ) ) {
            throw new ShipmentException(
                __( 'Failed to get printable documents.', 'nova-post-for-woocommerce' ),
                400,
                array(
                    'message' => __( 'Failed to get printable documents.', 'nova-post-for-woocommerce' ),
                    'errors'  => $api_response['errors'],
                )
            );
        }

        return $api_response;
    }

    /**
     * Get shipments by parameters.
     *
     * @param array $params API parameters (e.g., ['numbers' => [...]]).
     * @return array API response.
     * @throws ShipmentException If API request fails.
     */
    public static function get_shipments( array $params ): array {
        try {
            return NovaPost::get_client()->shipments()->get( $params );
        } catch ( \Exception $e ) {
            throw new ShipmentException( $e->getMessage(), 500 );
        }
    }
}
