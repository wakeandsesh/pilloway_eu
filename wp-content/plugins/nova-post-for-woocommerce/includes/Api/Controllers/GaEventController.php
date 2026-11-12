<?php
/**
 * GaEvent Controller
 *
 * Sends GA4 event.
 *
 * @package NovaPostForWoocommerce\Api\Controllers
 */

namespace NovaPostForWoocommerce\Api\Controllers;

use NovaPostForWoocommerce\Models\Constants\Ga4Constants;
use NovaPostForWoocommerce\Services\Analytics\Ga4Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class GaEventController
 */
class GaEventController {
    private const ALLOWED_FE_EVENTS = array(
        Ga4Constants::NP_DELIVERY_METHOD_SELECTED,
        Ga4Constants::NP_WIDGET_CHECKOUT_VIEWED,
        Ga4Constants::NP_BRANCH_SELECTED,
        Ga4Constants::NP_MAP_CHECKOUT_OPENED,
    );

    /**
     * GA4 Service.
     *
     * @var Ga4Service
     */
    private Ga4Service $ga4_service;

    /**
     * UserStorageController constructor.
     */
    public function __construct() {
        $this->ga4_service = new Ga4Service();
    }

    /**
     * Send event initialised by FE.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function send( WP_REST_Request $request ) {
        $params = $request->get_params();
        if ( ! $this->is_valid( $params ) ) {
            return new WP_Error( 400, 'Not valid params' );
        }

        $event_name   = $params['event'];
        $event_params = $params['params'] ?? array();
        $is_debug     = $params['is_debug'] ?? false;

        $is_frontend_event = in_array( $params['event'], self::ALLOWED_FE_EVENTS, true );
        $this->ga4_service->send_event( $event_name, $event_params, $is_debug, $is_frontend_event );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Validate that params can be processed.
     *
     * @param array $params FE params.
     * @return bool
     */
    private function is_valid( array $params ): bool {
        return in_array( $params['event'] ?? '', self::ALLOWED_FE_EVENTS, true );
    }
}
