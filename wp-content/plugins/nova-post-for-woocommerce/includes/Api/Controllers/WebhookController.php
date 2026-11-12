<?php
/**
 * Webhook Controller
 *
 * @package NovaPostForWoocommerce\Api\Controllers
 */

namespace NovaPostForWoocommerce\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use NovaPostForWoocommerce\Services\WebhookService;
use NovaPostForWoocommerce\Logger\Logger;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Class WebhookController
 */
class WebhookController {

    /**
     * Handle incoming tracking-push webhook.
     *
     * @param WP_REST_Request $request REST Request.
     * @return WP_REST_Response
     */
    public function handle_tracking_push( WP_REST_Request $request ) {
        $raw_body   = $request->get_body();
        $auth_error = $this->check_authorization( $request );
        if ( $auth_error ) {
            return $auth_error;
        }

        $payload = json_decode( $raw_body, true );
        if ( empty( $payload ) ) {
            $payload = $request->get_json_params();
        }
        $this->log_payload( $payload, $request );

        $payload_error = $this->validate_payload( $payload );
        if ( $payload_error ) {
            return $payload_error;
        }

        $this->process_payload( $payload );
        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Check the incoming headers for authorization.
     *
     * @param WP_REST_Request $request REST Request.
     * @return WP_REST_Response|null Returns a WP_REST_Response on error, or null on success.
     */
    private function check_authorization( WP_REST_Request $request ) {
        $x_np_key = $request->get_header( 'X-NP-key' ) ?? $request->get_header( 'x-np-key' );

        if ( ! WebhookService::verify_token( $x_np_key ) ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        return null;
    }

    /**
     * Validate the webhook payload.
     *
     * @param mixed $payload The parsed JSON payload.
     * @return WP_REST_Response|null Returns a WP_REST_Response on error, or null on success.
     */
    private function validate_payload( $payload ) {
        if ( ! is_array( $payload ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid payload format' ), 400 );
        }

        $tracking_number = $payload['shipment_number'] ?? $payload['number'] ?? '';
        if ( empty( $tracking_number ) ) {
            return new WP_REST_Response( array( 'error' => 'Missing shipment number in payload' ), 400 );
        }

        if ( ! isset( $payload['history_tracking'] ) || ! is_array( $payload['history_tracking'] ) ) {
            return new WP_REST_Response( array( 'error' => 'Missing or invalid history_tracking in payload' ), 400 );
        }

        return null;
    }

    /**
     * Log the webhook payload.
     *
     * @param mixed           $payload The parsed JSON payload.
     * @param WP_REST_Request $request The REST request object.
     */
    private function log_payload( $payload, WP_REST_Request $request ) {
        $headers = $request->get_headers();

        Logger::write_log(
            'debug',
            'Incoming tracking-push webhook received.',
            array(
                'payload' => $payload,
            )
        );
    }

    /**
     * Process the webhook payload asynchronously.
     *
     * @param array $payload The parsed JSON payload.
     */
    private function process_payload( array $payload ) {
        try {
            WebhookService::handle_payload( $payload );
        } catch ( Throwable $e ) {
            Logger::write_log(
                'debug',
                'Process payload - error',
                array(
                    'error' => $e->getMessage(),
                )
            );

            unset( $e ); // Appease PHPCS empty catch warning.
        }
    }
}
