<?php
/**
 * Webhook Service for Nova Post tracking push.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Factories\NovaPost;
use NovaPostForWoocommerce\Helpers\ShipmentStatusHelper;
use NovaPostForWoocommerce\Logger\Logger;
use NovaPostForWoocommerce\Http\Client;
use NovaPostForWoocommerce\Models\JwtTokenDBStorage;
use NovaDigital\NovaPost\DI\ContainerBuilder;
use NovaDigital\NovaPost\Http\NovaPostClient;
use NovaDigital\NovaPost\NovaPostApi;
use NovaDigital\NovaPost\Storage\JwtTokenStorageInterface;
use Psr\Log\LoggerInterface;
use Exception;
use WC_Order;

/**
 * Handles Webhook logic.
 */
class WebhookService {

    const OPTION_SUBSCRIPTION_ID           = 'npforwc_webhook_subscription_id';
    const OPTION_SECRET_TOKEN              = 'npforwc_webhook_secret_token';
    const MAX_TRACKING_NUMBERS_PER_REQUEST = 200;
    const MAX_TRACKING_NUMBERS_TOTAL_LIMIT = 5000;
    const DELETE_CODE                      = '2';

    /**
     * Subscribe to Nova Post tracking-push webhook.
     */
    public static function subscribe() {
        if ( get_option( 'npforwc_shipping_debug', false ) ) {
            return; // Webhooks are only registered for Production.
        }

        $secret_token = get_option( self::OPTION_SECRET_TOKEN );
        if ( ! $secret_token ) {
            $secret_token = wp_generate_password( 32, false );
            update_option( self::OPTION_SECRET_TOKEN, $secret_token );
        }

        $subscription_id = get_option( self::OPTION_SUBSCRIPTION_ID );
        if ( $subscription_id ) {
            return;
        }

        $url = get_rest_url( null, 'np-shipping/v1/tracking-push' ) . '?token=' . $secret_token;

        try {
            $response = NovaPost::get_client()->subscriptions()->create(
                array(
                    'type'        => 'numbers',
                    'url'         => $url,
                    'isActive'    => true,
                    'secretToken' => $secret_token,
                    'eventTypes'  => array(),
                )
            );

            if ( ! empty( $response['id'] ) ) {
                $new_subscription_id = $response['id'];
                update_option( self::OPTION_SUBSCRIPTION_ID, $new_subscription_id );
                Logger::write_log( 'info', 'Nova Post Webhook subscribed successfully.', $response );

                // phpcs:ignore
                /*
                // TODO: Logic for getting all existed shipments and try to register it to webhook.
                foreach ( self::get_all_tracking_numbers() as $chunk ) {
                    self::register_tracking_numbers( $chunk, $new_subscription_id );
                }
                */
            }
        } catch ( Exception $e ) {
            Logger::write_log( 'error', 'Failed to subscribe to Nova Post Webhook.', array( 'error' => $e->getMessage() ) );
        }

        return false;
    }

    /**
     * Register tracking numbers to the webhook subscription.
     *
     * @param array       $numbers         Array of tracking numbers.
     * @param string|null $subscription_id Optional. The subscription ID. Default null.
     *
     * @throws \Exception If the API returns validation errors.
     */
    public static function register_tracking_numbers( array $numbers, ?string $subscription_id = null ) {
        $subscription_id = $subscription_id ?? get_option( self::OPTION_SUBSCRIPTION_ID );
        if ( ! $subscription_id || empty( $numbers ) ) {
            Logger::write_log(
                'error',
                'Failed to register tracking numbers to Webhook.',
                array(
                    'error' => 'register_tracking_numbers EXIT - NO SUB ID OR EMPTY NUMBERS',
                )
            );
            return;
        }

        $chunks = array_chunk( $numbers, self::MAX_TRACKING_NUMBERS_PER_REQUEST );

        foreach ( $chunks as $chunk ) {
            try {
                $response = self::send_add_numbers_request_with_retry( $subscription_id, $chunk );

                Logger::write_log(
                    'info',
                    'Registered tracking numbers to webhook subscription.',
                    array(
                        'numbers'          => $chunk,
                        'response'         => $response,
                        '$subscription_id' => $subscription_id,
                    )
                );
            } catch ( Exception $e ) {
                Logger::write_log(
                    'error',
                    'Failed to register tracking numbers to webhook subscription.',
                    array(
                        'error'            => $e->getMessage(),
                        'numbers'          => $chunk,
                        '$subscription_id' => $subscription_id,
                    )
                );
            }
        }
    }

    /**
     * Send addNumbers request to Nova Post API with retry logic to handle eventual consistency.
     *
     * @param string $subscription_id The subscription ID.
     * @param array  $chunk           The chunk of tracking numbers to register.
     *
     * @return array The API response.
     * @throws \Exception If the API returns a validation error after retries.
     */
    private static function send_add_numbers_request_with_retry( string $subscription_id, array $chunk ): array {
        $max_retries = 1; // TODO: can be changed to more in future.
        $delay_time  = 3;
        $attempt     = 0;
        $success     = false;
        $response    = array();

        while ( $attempt < $max_retries && ! $success ) {
            ++$attempt;

            if ( $attempt > 1 ) {
                Logger::write_log( 'error', "register_tracking_numbers retry attempt {$attempt} after delay..." );
            }

            $response = NovaPost::get_client()->subscriptions()->addNumbers(
                $subscription_id,
                array( 'numbers' => array_values( $chunk ) )
            );

            if ( ! empty( $response['errors'] ) ) {
                if ( $attempt < $max_retries ) {
                    sleep( $delay_time );
                    continue;
                }

                Logger::write_log( 'error', 'API Validation Error: ' . wp_json_encode( $response['errors'] ) );
                throw new \Exception( 'API Validation Error: ' . wp_json_encode( $response['errors'] ) );
            }

            $success = true;
        }

        return $response;
    }

    /**
     * Send delete subscription request to Nova Post API with retry logic to handle eventual consistency.
     *
     * @param NovaPostApi $nova_post_api   The API client instance.
     * @param string      $subscription_id The subscription ID.
     *
     * @return array The API response.
     * @throws \Exception If the API returns a validation error after retries.
     */
    private static function send_delete_subscription_request_with_retry( NovaPostApi $nova_post_api, string $subscription_id ): array {
        $max_retries = 3;
        $delay_time  = 3;
        $attempt     = 0;
        $success     = false;
        $response    = array();

        while ( $attempt < $max_retries && ! $success ) {
            ++$attempt;

            if ( $attempt > 1 ) {
                Logger::write_log( 'error', "Unsubscribe retry attempt {$attempt} after delay..." );
            }

            $response = $nova_post_api->subscriptions()->delete( $subscription_id );

            if ( is_array( $response ) && ! empty( $response['errors'] ) ) {
                if ( $attempt < $max_retries ) {
                    sleep( $delay_time );
                    continue;
                }
                throw new \Exception( 'API Validation Error: ' . wp_json_encode( $response['errors'] ) );
            }

            $success = true;
        }

        return is_array( $response ) ? $response : array();
    }

    /**
     * Get all existing tracking numbers from orders.
     *
     * @return array Array of tracking numbers.
     */
    public static function get_all_tracking_numbers() {
        global $wpdb;

        $meta_key_pattern = '%_nova_post_shipment_number%';
        $chunk_size       = self::MAX_TRACKING_NUMBERS_PER_REQUEST;
        $max_limit        = self::MAX_TRACKING_NUMBERS_TOTAL_LIMIT;

        // 1. Query standard postmeta
        $offset = 0;
        while ( $offset < $max_limit ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $postmeta_results = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_value != '' ORDER BY meta_id DESC LIMIT %d OFFSET %d",
                    $meta_key_pattern,
                    $chunk_size,
                    $offset
                )
            );

            if ( empty( $postmeta_results ) ) {
                break;
            }

            $offset += $chunk_size;

            // Remove duplicates within this chunk.
            $postmeta_results = array_values( array_unique( $postmeta_results ) );
            if ( ! empty( $postmeta_results ) ) {
                yield $postmeta_results;
            }
        }

        // 2. Query HPOS wc_orders_meta if the table exists
        $table = $wpdb->prefix . 'wc_orders_meta';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

        if ( $table_exists === $table ) {
            $offset = 0;
            while ( $offset < $max_limit ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $hpos_results = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT meta_value FROM {$table} WHERE meta_key LIKE %s AND meta_value != '' ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $meta_key_pattern,
                        $chunk_size,
                        $offset
                    )
                );

                if ( empty( $hpos_results ) ) {
                    break;
                }

                $offset += $chunk_size;

                // Remove duplicates within this chunk.
                $hpos_results = array_values( array_unique( $hpos_results ) );
                if ( ! empty( $hpos_results ) ) {
                    yield $hpos_results;
                }
            }
        }
    }

    /**
     * Unsubscribe from Nova Post tracking-push webhook.
     */
    public static function unsubscribe() {
        $subscription_id = get_option( self::OPTION_SUBSCRIPTION_ID );
        $api_key         = OpensslService::decrypt( get_option( 'npforwc_shipping_api_key' ) );

        if ( ! $subscription_id || ! $api_key ) {
            return;
        }

        try {
            $container = ( new ContainerBuilder() )
                ->bind( LoggerInterface::class, Logger::class )
                ->bind( NovaPostClient::class, Client::class )
                ->bind( JwtTokenStorageInterface::class, JwtTokenDBStorage::class )
                ->setParameter( 'apiKey', $api_key )
                ->setParameter( 'useSandbox', false )
                ->build();

            $nova_post_api = new NovaPostApi( $container );

            self::send_delete_subscription_request_with_retry( $nova_post_api, $subscription_id );

            Logger::write_log( 'info', 'Nova Post Webhook unsubscribed successfully via API.' );

            delete_option( self::OPTION_SUBSCRIPTION_ID );
            delete_option( self::OPTION_SECRET_TOKEN );
            Logger::write_log( 'info', 'Nova Post Webhook local options deleted.' );
        } catch ( Exception $e ) {
            Logger::write_log( 'error', 'Failed to unsubscribe from Nova Post Webhook via API.', array( 'error' => $e->getMessage() ) );
        }
    }

    /**
     * Verify incoming webhook secret token.
     *
     * @param string $token The token from the request.
     * @return bool
     */
    public static function verify_token( $token ) {
        $expected_token = get_option( self::OPTION_SECRET_TOKEN );
        if ( ! $expected_token ) {
            return false;
        }

        return hash_equals( (string) $expected_token, (string) $token );
    }

    /**
     * Handle incoming webhook event.
     *
     * @param array $payload Webhook payload.
     * @return void
     */
    public static function handle_payload( array $payload ) {
        Logger::write_log( 'info', 'Received Nova Post webhook payload' );

        $tracking_number  = $payload['shipmentNumber'] ?? $payload['shipment_number'] ?? $payload['number'] ?? '';
        $tracking_history = isset( $payload['history_tracking'] ) && is_array( $payload['history_tracking'] ) ? $payload['history_tracking'] : array();

        Logger::write_log(
            'info',
            'Webhook: get tracking history',
            array(
                'tracking_history' => $tracking_history,
            )
        );

        self::process_history_tracking( $tracking_history, $tracking_number );
    }

    /**
     * Process tracking history array.
     *
     * @param array  $tracking_history Tracking history array.
     * @param string $tracking_number  Tracking number.
     * @return void
     */
    private static function process_history_tracking( array $tracking_history, string $tracking_number ) {
        if ( empty( $tracking_history ) ) {
            return;
        }

        $last_history_item = end( $tracking_history );

        if ( ! empty( $last_history_item['code'] ) ) {
            $status_code = (string) $last_history_item['code'];

            self::process_event( $status_code, $tracking_number );
        }
    }

    /**
     * Process a single webhook event.
     *
     * @param string $status_code      Event type.
     * @param string $tracking_number Tracking number.
     * @return void
     */
    private static function process_event( string $status_code, string $tracking_number ) {
        // Find tracking number and event type. Payload fields can vary.

        if ( ! $tracking_number || ! $status_code ) {
            return; // Cannot process.
        }

        // Find the WC order by tracking number.
        $order = self::get_order_by_tracking_number( $tracking_number );

        if ( empty( $order ) ) {
            return;
        }

        if ( self::DELETE_CODE === $status_code ) {
            self::handle_deletion( $order, $tracking_number );
        } else {
            // Handle all other events.
            self::handle_other_event( $order, $tracking_number, $status_code );
        }
    }

    /**
     * Handle other events (placeholder for future implementation).
     *
     * @param \WC_Order $order           The WooCommerce order object.
     * @param string    $tracking_number Tracking number.
     * @param string    $status_code     Event type.
     */
    private static function handle_other_event( \WC_Order $order, string $tracking_number, string $status_code ) {
        $status_text = ShipmentStatusHelper::get_text_status( $status_code );
        self::handle_status_update( $order, $status_code, $status_text, $tracking_number );
    }

    /**
     * Handle waybill deletion event.
     *
     * @param WC_Order $order The WooCommerce order.
     * @param string   $tracking_number Trackinh number.
     */
    private static function handle_deletion( WC_Order $order, string $tracking_number ) {
        self::clear_shipment_meta( $order );
        $order->save_meta_data();
        Logger::write_log(
            'info',
            'Webhook: Processed shipment deletion for order ' . $order->get_id(),
            array(
                'tracking_number' => $tracking_number,
            )
        );
    }

    /**
     * Clear all shipment-related metadata for an order.
     *
     * @param WC_Order $order The WooCommerce order.
     */
    public static function clear_shipment_meta( WC_Order $order ) {
        // Clear all shipment-related meta.
        $order->delete_meta_data( '_nova_post_shipment_id' );
        $order->delete_meta_data( '_nova_post_shipment_number' );
        $order->delete_meta_data( '_nova_post_shipment_status' );
        $order->delete_meta_data( '_nova_post_shipment_status_code' );
        $order->delete_meta_data( '_nova_post_shipment_api_request' );
        $order->delete_meta_data( '_nova_post_shipment_api_response' );
        $order->delete_meta_data( '_nova_post_shipment_errors' );
    }

    /**
     * Handle status update event.
     *
     * @param WC_Order $order The WooCommerce order.
     * @param mixed    $status_code Status code.
     * @param string   $status_text Status text.
     * @param string   $tracking_number Tracking number.
     */
    private static function handle_status_update( WC_Order $order, $status_code, $status_text, string $tracking_number ) {
        $order->update_meta_data( '_nova_post_shipment_status', $status_text );
        $order->update_meta_data( '_nova_post_shipment_status_code', $status_code );
        $order->save_meta_data();

        Logger::write_log(
            'info',
            'Webhook: Processed status update for order',
            array(
                'order_id'        => $order->get_id(),
                'tracking_number' => $tracking_number,
                'status_code'     => $status_code,
                'status_text'     => $status_text,
            )
        );
    }

    /**
     * Find WooCommerce order by Nova Post tracking number.
     *
     * @param string $tracking_number The tracking number.
     * @return WC_Order|null
     */
    private static function get_order_by_tracking_number( $tracking_number ) {
        $orders = wc_get_orders(
            array(
                'limit'      => 1,
                'meta_key'   => '_nova_post_shipment_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_value' => $tracking_number, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            )
        );

        if ( ! empty( $orders ) ) {
            return $orders[0];
        }

        return null;
    }
}
