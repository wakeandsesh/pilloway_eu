<?php
/**
 * Classic Checkout State Controller.
 *
 * @package NovaPostForWoocommerce\Api\Controllers
 */

namespace NovaPostForWoocommerce\Api\Controllers;

use NovaPostForWoocommerce\Services\ClassicCheckoutStateService;

use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns a normalized snapshot of classic checkout state for the Nova Post widget.
 */
class ClassicCheckoutStateController {

    /**
     * Get classic checkout state.
     *
     * @return WP_REST_Response
     */
    public function get_state(): WP_REST_Response {
        $service = new ClassicCheckoutStateService();
        return new WP_REST_Response( $service->get_state() );
    }
}
