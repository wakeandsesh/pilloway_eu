<?php
/**
 * Nova Post shipping for WooCommerce Plugin.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Plugin;

use NovaPostForWoocommerce\Admin\AdminAlerts;
use NovaPostForWoocommerce\Admin\AdminAssets;
use NovaPostForWoocommerce\Admin\AdminSetup;
use NovaPostForWoocommerce\Admin\OrderBulkActions;
use NovaPostForWoocommerce\Admin\OrderListColumn;
use NovaPostForWoocommerce\Admin\OrderShippingData;
use NovaPostForWoocommerce\Admin\OrderSidebarMetaBox;
use NovaPostForWoocommerce\Api\ApiRoutes;
use NovaPostForWoocommerce\Checkout\CheckoutSetup;
use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Models\Traits\TrackEvent;
use NovaPostForWoocommerce\Services\EmailsService;
use NovaPostForWoocommerce\Services\ProductPageService;
use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodAddress;
use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodDivision;

defined( 'ABSPATH' ) || exit;

/**
 * The main class.
 *
 * @class NovaPostForWoocommerce
 */
final class NovaPostForWoocommerce {

    use TrackEvent;

    /**
     * This class instance.
     *
     * @var NovaPostForWoocommerce single instance of this class.
     */
    private static $instance;

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init_admin();
        $this->init_checkout();

        $this->init_api();
        $this->init_shipping_method();
        $this->init_product_page_fields();
        $this->init_emails();
        $this->init_ga_events();
    }

    /**
     * Init emails.
     *
     * @return void
     */
    private function init_emails() {
        add_action( 'woocommerce_email_order_details', array( EmailsService::class, 'add_tracking_number_to_emails' ), 20, 4 );
    }

    /**
     * Initialize the API.
     */
    private function init_api() {
        add_action(
            'rest_api_init',
            function () {
                $api_routes = new ApiRoutes();
                $api_routes->register_routes();
            }
        );
    }

    /**
     * Init Admin.
     *
     * @return void
     */
    private function init_admin() {
        if ( is_admin() ) {
            new AdminSetup();
            new AdminAssets();
            new OrderListColumn();
            new OrderBulkActions();
            new OrderSidebarMetaBox();
            new OrderShippingData();
            new AdminAlerts();
        }
    }

    /**
     * Initialize the shipping methods.
     */
    private function init_shipping_method() {
        add_filter(
            'woocommerce_shipping_methods',
            function ( $methods ) {
                if ( ! NovaPostSettings::get_instance()->is_available() ) {
                    return $methods;
                }

                $methods['nova_post_division'] = NovaPostShippingMethodDivision::class;
                $methods['nova_post_address']  = NovaPostShippingMethodAddress::class;
                return $methods;
            }
        );
    }

    /**
     * Init Checkout.
     *
     * @return void
     */
    private function init_checkout() {
        new CheckoutSetup();
    }

    /**
     * Init product page fields.
     *
     * @return void
     */
    private function init_product_page_fields() {
        ProductPageService::add_cross_border_fields();
    }

    /**
     * Subscribing Google Analytics events for WooCommerce webhooks when order is created.
     */
    private function init_ga_events() {
        // Classic Checkout.
        add_action( 'woocommerce_checkout_order_created', array( $this, 'track_checkout_completed' ) );
        // Old Checkout.
        add_action( 'woocommerce_new_order', array( $this, 'track_checkout_completed' ) );
    }

    /**
     * Cloning is forbidden.
     */
    public function __clone() {
        wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'nova-post-for-woocommerce' ), NPFORWC_PLUGIN_VERSION );
    }

    /**
     * Unserializing instances of this class is forbidden.
     */
    public function __wakeup() {
        wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'nova-post-for-woocommerce' ), NPFORWC_PLUGIN_VERSION );
    }

    /**
     * Gets the main instance.
     *
     * Ensures only one instance can be loaded.
     *
     * @return NovaPostForWoocommerce
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
