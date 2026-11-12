<?php
/**
 * WooCommerce Session Handler.
 *
 * @package NovaPostForWoocommerce\Models
 */

namespace NovaPostForWoocommerce\Models;

use function WC;

/**
 * WooCommerce Session Handler.
 */
class WcSession {

    /**
     * Initialize the WooCommerce session.
     *
     * @return \WC_Session|\WC_Session_Handler The WooCommerce session instance.
     */
    public static function init_and_get() {

        if ( ! WC()->session ) {
            WC()->session = new \WC_Session_Handler();
            WC()->session->init();
        }

        return WC()->session;
    }
}
