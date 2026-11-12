<?php
/**
 * Order shipping data display for Nova Post WooCommerce plugin.
 *
 * Displays and allows editing of Nova Post division information
 * on the order edit page after the shipping address section.
 *
 * @package NovaPostForWoocommerce\Admin
 * @since   1.3.0
 */

namespace NovaPostForWoocommerce\Admin;

use NovaPostForWoocommerce\Helpers\View;
use NovaPostForWoocommerce\Services\OrderService;
use NovaPostForWoocommerce\Services\ShipmentService;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Displays Nova Post division information on the order edit page.
 *
 * Shows the selected division under the shipping address section
 * with ability to edit the division selection.
 *
 * @since 1.3.0
 */
class OrderShippingData {

    /**
     * Register hooks for displaying division data on order edit page.
     *
     * @since 1.3.0
     */
    public function __construct() {
        // Display division info after shipping address (both classic and HPOS).
        add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_division_info' ), 10, 1 );
    }

    /**
     * Display division information after shipping address.
     *
     * @since 1.3.0
     *
     * @param WC_Order $order The order object.
     * @return void
     */
    public function display_division_info( $order ) {
        if ( ! OrderService::has_nova_post_division_shipping( $order ) ) {
            return;
        }

        $shipment_info = ShipmentService::get_order_shipment_info( $order );

        $division_info    = OrderService::get_order_division_info( $order );
        $division_id      = $division_info['division_id'] ?? '';
        $division_type    = $division_info['division_type'] ?? '';
        $division_name    = $division_info['division']['shortName'] ?? '';
        $division_address = $division_info['division']['address'] ?? '';

        // Load view template.
        View::render(
            'order/division-info',
            array(
                'order_id'         => $order->get_id(),
                'shipment_info'    => $shipment_info,
                'division_id'      => $division_id,
                'division_type'    => $division_type,
                'division_name'    => $division_name,
                'division_address' => $division_address,
                'sprite_url'       => plugins_url( 'assets/images/icons-sprite.svg', NPFORWC_PLUGIN_FILE_PATH ),
            )
        );
    }
}
