<?php
/**
 * REST API Controller
 *
 * @package NovaPostForWoocommerce\Api
 */

namespace NovaPostForWoocommerce\Api;

use NovaPostForWoocommerce\Api\Controllers\AdminSettingsController;
use NovaPostForWoocommerce\Api\Controllers\BulkPrintController;
use NovaPostForWoocommerce\Api\Controllers\BulkShipmentController;
use NovaPostForWoocommerce\Api\Controllers\CheckoutWidgetController;
use NovaPostForWoocommerce\Api\Controllers\ClassicCheckoutStateController;
use NovaPostForWoocommerce\Api\Controllers\GaEventController;
use NovaPostForWoocommerce\Api\Controllers\OrderController;
use NovaPostForWoocommerce\Api\Controllers\UserStorageController;
use NovaPostForWoocommerce\Api\Controllers\ShipmentController;
use NovaPostForWoocommerce\Api\Controllers\PickupController;
use NovaPostForWoocommerce\Api\Controllers\WebhookController;
use WP_REST_Server;
use NovaPostForWoocommerce\Models\WcSession;
use NovaPostForWoocommerce\Api\Infrastructure\Pipeline;
use NovaPostForWoocommerce\Api\Middleware\TrimStrings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Controller
 */
class ApiRoutes {

    /**
     * The namespace.
     *
     * @var string
     */
    protected $namespace = 'np-shipping/v1';

    /**
     * Global middleware stack.
     *
     * @var array
     */
    protected $global_middlewares = array(
        TrimStrings::class,
    );

    /**
     * Helper to wrap callback with global + route specific middleware.
     *
     * @param callable $callback          The controller callback.
     * @param array    $route_middlewares Route specific middlewares.
     * @return callable
     */
    protected function pipeline( callable $callback, array $route_middlewares = array() ) {
        $stack = array_merge( $this->global_middlewares, $route_middlewares );
        return Pipeline::through( $stack, $callback );
    }

    /**
     * Register the routes.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/tracking-push',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new WebhookController() )->handle_tracking_push( $request ) ),
                'permission_callback' => fn() => true,
            )
        );

        register_rest_route(
            $this->namespace,
            '/settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => $this->pipeline( fn( $request ) => ( new AdminSettingsController() )->get_settings( $request ) ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => $this->pipeline( fn( $request ) => ( new AdminSettingsController() )->update_settings( $request ) ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/settings/api-keys',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => $this->pipeline( fn( $request ) => ( new AdminSettingsController() )->update_settings_api_keys( $request ) ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/settings/get-page-state',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => $this->pipeline( fn( $request ) => ( new AdminSettingsController() )->get_settings_page_state( $request ) ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/available-countries',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => $this->pipeline( fn() => ( new AdminSettingsController() )->get_countries() ),
                'permission_callback' => fn() => true,
            )
        );

        register_rest_route(
            $this->namespace,
            '/checkout-widget/find-divisions',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new CheckoutWidgetController() )->find_divisions( $request ) ),
                'permission_callback' => fn() => true,
                'args'                => array(
                    'prohibitedSending'  => array(
                        'type' => 'boolean',
                    ),
                    'prohibitedIssuance' => array(
                        'type' => 'boolean',
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/classic-checkout-state',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => $this->pipeline( fn() => ( new ClassicCheckoutStateController() )->get_state() ),
                'permission_callback' => array( $this, 'user_has_session' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/user-storage',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => $this->pipeline( fn( $request ) => ( new UserStorageController() )->get_data( $request ) ),
                    'permission_callback' => array( $this, 'user_has_session' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => $this->pipeline( fn( $request ) => ( new UserStorageController() )->store_data( $request ) ),
                    'permission_callback' => array( $this, 'user_has_session' ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/ga-event',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => $this->pipeline( fn( $request ) => ( new GaEventController() )->send( $request ) ),
                    'permission_callback' => array( $this, 'user_has_session' ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/orders/(?P<orderId>\d+)/shipment-preview',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new ShipmentController() )->get_shipment_preview( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/orders/(?P<orderId>\d+)/create-shipment',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new ShipmentController() )->create_shipment( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/orders/(?P<orderId>\d+)/get-shipment',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new ShipmentController() )->get_shipment( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/orders/(?P<orderId>\d+)/get-shipment-status',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new ShipmentController() )->get_shipment_status( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/orders/get-shipment-statuses',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new ShipmentController() )->get_shipment_statuses( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/orders/(?P<orderId>\d+)/delete-shipment',
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new ShipmentController() )->delete_shipment( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/shipments/bulk-create',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new BulkShipmentController() )->create_bulk_shipments( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/shipments/bulk-print',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new BulkPrintController() )->print_bulk_documents( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/shipments/bulk-print/download/(?P<filename>[a-zA-Z0-9_\-]+\.pdf)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new BulkPrintController() )->download_pdf( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/orders/(?P<orderId>\d+)/print-shipment/(?P<type>\w+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new ShipmentController() )->print_shipment( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/orders/(?P<orderId>\d+)/update-division',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new OrderController() )->update_division( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/orders/(?P<orderId>\d+)/get-division',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new OrderController() )->get_division( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/pickups/time-intervals',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new PickupController() )->find_time_intervals( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/pickups',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new PickupController() )->create_pickup( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/pickups/(?P<pickupId>\d+)/shipments',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new PickupController() )->add_shipments( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/pickups/(?P<pickupId>\d+)/status',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => $this->pipeline( fn( $request ) => ( new PickupController() )->update_status( $request ) ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );
    }

    /**
     * Check permissions for the REST API.
     *
     * @return bool
     */
    public function permissions_check() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Check if the user has a session.
     *
     * @return bool
     */
    public function user_has_session() {
        return WcSession::init_and_get()->has_session();
    }
}
