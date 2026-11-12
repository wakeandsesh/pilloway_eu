<?php
/**
 * Google Analytics 4 Event object.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Models
 */

namespace NovaPostForWoocommerce\Models;

use NovaPostForWoocommerce\Models\Traits\MerchantIdentifier;

/**
 * Google Analytics 4 Event object.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Models
 */
class Ga4Event {

    use MerchantIdentifier;

    public const USER_ID_SESSION_KEY    = 'npforwc_shipping_user_id';
    public const SESSION_ID_SESSION_KEY = 'npforwc_shipping_session_id';
    public const CLIENT_ID_SESSION_KEY  = 'npforwc_shipping_client_id';

    /**
     * The WooCommerce session handler.
     *
     * @var \WC_Session|\WC_Session_Handler
     */
    private $session;

    /**
     * Get client ID.
     *
     * @return string
     */
    public function get_client_id(): string {
        $client_id = $this->get_session()->get( self::CLIENT_ID_SESSION_KEY );
        if ( $client_id ) {
            return $client_id;
        }

        $client_id = wp_generate_uuid4();

        $this->get_session()->set( self::CLIENT_ID_SESSION_KEY, $client_id );

        return $client_id;
    }

    /**
     * Get session ID.
     *
     * @return string
     */
    public function get_session_id(): string {
        $session_id = $this->get_session()->get( self::SESSION_ID_SESSION_KEY );
        if ( $session_id ) {
            return $session_id;
        }

        $session_id = hrtime( true );
        $this->get_session()->set( self::SESSION_ID_SESSION_KEY, $session_id );

        return $session_id;
    }

    /**
     * Retrieves or generates unique current user id.
     */
    public function get_user_id(): string {
        $user_id = $this->get_session()->get( self::USER_ID_SESSION_KEY );
        if ( $user_id ) {
            return $user_id;
        }
        $user_id = wp_hash( $this->get_session()->get_customer_unique_id() . $this->get_merchant_id() );
        $this->get_session()->set( self::USER_ID_SESSION_KEY, $user_id );

        return $user_id;
    }

    /**
     * Retrieve current user session object.
     */
    private function get_session() {
        if ( isset( $this->session ) ) {
            return $this->session;
        }

        $this->session = WcSession::init_and_get();
        return $this->session;
    }
}
