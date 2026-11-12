<?php
/**
 * Async Shipment API Service
 *
 * Handles asynchronous bulk shipment creation with JWT token caching and rate limiting.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Factories\NovaPost;
use NovaPostForWoocommerce\Services\ShipmentApiService;
use NovaPostForWoocommerce\Logger\Logger;
use NovaDigital\NovaPost\NovaPostApi;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use NovaDigital\NovaPost\JwtTokenProvider;
use NovaPostForWoocommerce\Exceptions\ShipmentException;
use NovaPostForWoocommerce\Database\Repositories\OrderRepository;
use NovaPostForWoocommerce\Services\OrderService;
use NovaPostForWoocommerce\Helpers\ShipmentStatusHelper;
use GuzzleHttp\Exception\RequestException;

/**
 * Async Shipment API Service
 *
 * Provides asynchronous bulk shipment creation functionality.
 */
class AsyncShipmentApiService {

    /**
     * Create multiple shipments asynchronously.
     *
     * @param array $order_ids Array of WooCommerce order IDs.
     * @param int   $delay_step Delay between requests in milliseconds.
     * @return array Results of bulk creation.
     * @throws ShipmentException If shipment data is invalid during preparation.
     */
    public function create_bulk_shipments_async( array $order_ids, $delay_step = null ): array {

        $delay_step = is_numeric( $delay_step ) ? (int) $delay_step : 1100;

        $use_sandbox = (bool) get_option( 'npforwc_shipping_debug' );

        $client = $this->get_http_client( $use_sandbox );

        $results = array(
            'success' => array(),
            'failed'  => array(),
            'skipped' => array(),
        );

        // 1. Get JWT Token using SDK's JwtTokenProvider (with caching).
        try {
            $jwt_token = $this->get_jwt_token();
        } catch ( \Exception $e ) {
            return $this->auth_failed_result( $order_ids, $results, $e );
        }

        $promises = array();
        $delay_ms = 0;

        // 2. Preload orders to avoid N+1 queries.
        $orders = OrderRepository::get_orders_by_ids( $order_ids );
        // Re-key orders by ID for O(1) lookup.
        $orders_by_id = array();
        foreach ( $orders as $order ) {
            $orders_by_id[ $order->get_id() ] = $order;
        }

        // 3. Prepare requests.
        foreach ( $order_ids as $order_id ) {
            try {
                if ( ! isset( $orders_by_id[ $order_id ] ) ) {
                    throw new ShipmentException( __( 'Order not found', 'nova-post-for-woocommerce' ), 404 );
                }
                $order = $orders_by_id[ $order_id ];

                $promise_or_skipped = $this->create_shipment_promise( $order, $client, $jwt_token, $delay_ms );

                if ( is_array( $promise_or_skipped ) && isset( $promise_or_skipped['skipped'] ) ) {
                    $results['skipped'][] = $promise_or_skipped['skipped'];
                    continue;
                }

                $promises[ $order_id ] = $promise_or_skipped;

                // Increment delay by the specified step (default 1100ms) for the next request.
                $delay_ms += $delay_step;

            } catch ( \Exception $e ) {
                $results['failed'][] = array(
                    'order_id' => $order_id,
                    'error'    => $e->getMessage(),
                    'note'     => 'Api request error',
                );
            }
        }

        // 3. Wait for all requests to complete.
        $responses = PromiseUtils::settle( $promises )->wait();

        // 4. Process results.
        $successful_tracking_numbers = array();
        foreach ( $responses as $order_id => $response ) {
            try {
                $processing_result = $this->handle_single_response( $order_id, $response );

                if ( isset( $processing_result['success'] ) ) {
                    $results['success'][] = $processing_result['success'];
                    if ( ! empty( $processing_result['success']['track_number'] ) ) {
                        $successful_tracking_numbers[] = $processing_result['success']['track_number'];
                    }
                } elseif ( isset( $processing_result['failed'] ) ) {
                    $results['failed'][] = $processing_result['failed'];
                }
            } catch ( \Exception $e ) {
                $results['failed'][] = array(
                    'order_id' => $order_id,
                    'error'    => 'Response processing error: ' . $e->getMessage(),
                    'note'     => 'Response exception',
                );
            }
        }

        if ( ! empty( $successful_tracking_numbers ) ) {
            WebhookService::register_tracking_numbers( $successful_tracking_numbers );
        }

        return $results;
    }

    /**
     * Get configured HTTP client for API requests.
     *
     * @param bool $use_sandbox Whether to use sandbox environment.
     * @return HttpClient Configured Guzzle HTTP client.
     */
    private function get_http_client( $use_sandbox ) {

        $base_url = $use_sandbox ? NovaPostApi::SANDBOX_BASE_URL : NovaPostApi::PRODUCTION_BASE_URL;

        $client = new HttpClient(
            array(
                'base_uri' => $base_url,
                'timeout'  => 30,
                'headers'  => array(
                    'User-Agent' => 'NovaPost-SDK/1.0',
                    'Accept'     => 'application/json',
                ),
            )
        );

        return $client;
    }

    /**
     * Build failed results array for authentication errors.
     *
     * @param array      $order_ids Order IDs that failed.
     * @param array      $results   Existing results array.
     * @param \Exception $e         The exception that occurred.
     * @return array Updated results array with failed orders.
     */
    private function auth_failed_result( $order_ids, $results, $e ) {

        foreach ( $order_ids as $order_id ) {
            $results['failed'][] = array(
                'order_id' => $order_id,
                'error'    => 'Authentication failed: ' . $e->getMessage(),
                'note'     => 'Authentication exception',
            );
        }

        return $results;
    }

    /**
     * Get JWT Token.
     *
     * @return string JWT Token.
     * @throws \Exception If authentication fails.
     */
    private function get_jwt_token() {
        $container      = NovaPost::get_container();
        $token_provider = $container->get( JwtTokenProvider::class );

        return $token_provider->get();
    }

    /**
     * Create shipment promise for a single order.
     *
     * @param \WC_Order  $order     WooCommerce Order object.
     * @param HttpClient $client    Guzzle Client.
     * @param string     $jwt_token JWT Token.
     * @param int        $delay_ms  Delay in milliseconds.
     * @return \GuzzleHttp\Promise\PromiseInterface|array Promise or skipped array.
     * @throws ShipmentException If validation fails.
     */
    private function create_shipment_promise( $order, $client, $jwt_token, $delay_ms ) {
        if ( $order->get_meta( '_nova_post_shipment_id' ) ) {
            return array(
                'skipped' => array(
                    'order_id' => $order->get_id(),
                    'reason'   => __( 'Shipment already exists', 'nova-post-for-woocommerce' ),
                ),
            );
        }

        if ( ! OrderService::has_nova_post_shipping( $order ) ) {
            return array(
                'skipped' => array(
                    'order_id' => $order->get_id(),
                    'reason'   => __( 'Not a Nova Post shipping method', 'nova-post-for-woocommerce' ),
                ),
            );
        }

        $shipment_data    = ShipmentApiService::prepare_shipment_data( $order );
        $validation_error = ShipmentApiService::validate_shipment_data( $shipment_data );

        if ( $validation_error ) {
            $order->update_meta_data( '_nova_post_shipment_errors', array( $validation_error ) );
            $order->save_meta_data();
            throw new ShipmentException( esc_html( $validation_error ), 400 );
        }

        return $client->postAsync(
            'shipments',
            array(
                'headers' => array(
                    'Authorization' => $jwt_token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                'json'    => $shipment_data,
                'delay'   => $delay_ms,
            )
        );
    }

    /**
     * Handle single API response.
     *
     * @param int   $order_id Order ID.
     * @param array $response Response from Promise::settle.
     * @return array Result array with success or failed key.
     */
    private function handle_single_response( $order_id, $response ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return array(
                'failed' => array(
                    'order_id' => $order_id,
                    'error'    => 'Order not found during response processing',
                    'note'     => 'System error',
                ),
            );
        }

        if ( 'fulfilled' === $response['state'] ) {
            $response_body = $response['value']->getBody();
            $api_response  = json_decode( $response_body, true );

            // Ensure we have a valid array response.
            if ( ! is_array( $api_response ) ) {
                $api_response = array(
                    'errors' => array( 'Invalid JSON response from API' ),
                    'raw'    => substr( $response_body, 0, 500 ),
                );
            }

            $order->update_meta_data( '_nova_post_shipment_api_response', $api_response );
            $order->update_meta_data( '_nova_post_shipment_api_request', ShipmentApiService::prepare_shipment_data( $order ) );

            $order->update_meta_data( '_nova_post_shipment_id', $api_response['id'] ?? null );
            $order->update_meta_data( '_nova_post_shipment_number', $api_response['number'] ?? null );
            $order->update_meta_data( '_nova_post_shipment_status', ShipmentStatusHelper::get_text_status( 1 ) );
            $order->update_meta_data( '_nova_post_shipment_status_code', 1 );
            $order->delete_meta_data( '_nova_post_shipment_errors' );

            $order->save_meta_data();

            if ( isset( $api_response['id'] ) ) {
                ShipmentService::shipment_created( $order );
            }

            return array(
                'success' => array(
                    'order_id'     => $order_id,
                    'track_number' => $api_response['number'] ?? '',
                    'cost'         => $api_response['cost'] ?? '',
                ),
            );
        } else {
            $reason    = $response['reason'];
            $error_msg = 'Unknown error';

            if ( $reason instanceof RequestException && $reason->hasResponse() ) {
                $error_body = json_decode( $reason->getResponse()->getBody()->getContents(), true );
                $error_msg  = implode( ', ', $error_body['errors'] ?? array( $error_msg ) );
            } elseif ( $reason instanceof \Exception ) {
                $error_msg = $reason->getMessage();
            }

            $order->update_meta_data( '_nova_post_shipment_api_response', $error_body ?? null );
            $order->update_meta_data( '_nova_post_shipment_errors', $error_msg ?? null );
            $order->update_meta_data( '_nova_post_shipment_api_request', ShipmentApiService::prepare_shipment_data( $order ) );

            $order->save_meta_data();

            Logger::write_log(
                \WC_Log_Levels::ERROR,
                'Bulk shipment creating error',
                array(
                    'request'  => ShipmentApiService::prepare_shipment_data( $order ),
                    'response' => $error_body ?? $error_msg,
                )
            );

            return array(
                'failed' => array(
                    'order_id' => $order_id,
                    'error'    => $error_msg,
                    'note'     => 'Response not fulfilled',
                ),
            );
        }
    }
}
