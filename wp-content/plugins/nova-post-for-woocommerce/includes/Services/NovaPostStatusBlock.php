<?php
/**
 * Service for rendering the combined Pickup and Delivery status block
 * used in the WooCommerce orders list.
 *
 * @package NovaPostForWoocommerce\Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Helpers\View;
use NovaPostForWoocommerce\Models\Constants\OrderMetaConstants;
use WC_Order;

/**
 * Handles rendering of the status block for an order.
 */
class NovaPostStatusBlock {
    /**
     * Render the status block for a given order.
     *
     * @param WC_Order $order          The order object.
     * @param array    $shipment_info  Shipment data returned by ShipmentService::get_order_shipment_info().
     * @param bool     $hidden Whether to hide the status block output.
     */
    public static function render( WC_Order $order, array $shipment_info, bool $hidden = false ) {
        // If hidden flag is set, do not output anything.
        if ( $hidden ) {
            return;
        }

        // Common data.
        $order_id    = $order->get_id();
        $status      = $shipment_info['last_status'] ?? '';
        $status_code = (string) ( $shipment_info['last_status_code'] ?? '' );

        // Determine if we have a tracking number.
        $has_track   = ! empty( $shipment_info['track_number'] );
        $hidden_attr = ! $has_track ? ' hidden' : '';

        // Open container.
        echo '<div class="npw-wrap-statuses-block"' . esc_attr( $hidden_attr ) . '>';

        $pickup_id               = (int) $order->get_meta( OrderMetaConstants::NP_PICKUP_ID, true );
        $pickup_validation_error = PickupService::get_pickup_validation_error_message( $order, $pickup_id );

        if ( $pickup_id ) {
            $pickup_number                = $order->get_meta( OrderMetaConstants::NP_PICKUP_NUMBER, true );
            $pickup_badge_status          = NovaPostOrderBlock::PICKUP_BADGE_STATUS_ADDED;
            $pickup_badge_tooltip_message = sprintf(
                /* translators: %s: pickup request number. */
                __( 'Shipment added to pickup request #%s', 'nova-post-for-woocommerce' ),
                $pickup_number
            );
        } elseif ( $pickup_validation_error ) {
            $pickup_badge_status          = NovaPostOrderBlock::PICKUP_BADGE_STATUS_ERROR;
            $pickup_badge_tooltip_message = $pickup_validation_error;
        } else {
            $pickup_badge_status          = NovaPostOrderBlock::PICKUP_BADGE_STATUS_WAITING;
            $pickup_badge_tooltip_message = __( 'Shipment is waiting to be added to a pickup.', 'nova-post-for-woocommerce' );
        }

        echo '<div class="npw-status-col">';
        echo '<span class="npw-status-label">' . esc_html__( 'Pickup', 'nova-post-for-woocommerce' ) . '</span>';
        View::render(
            'pickup/status-badge-pickup-icon',
            array(
                'status'       => $pickup_badge_status,
                'tooltip_text' => $pickup_badge_tooltip_message,
                'is_hidden'    => ! $has_track,
            )
        );
        echo '</div>';

        echo '<div class="npw-divider"></div>';

        echo '<div class="npw-status-col">';
        echo '<span class="npw-status-label">' . esc_html__( 'Delivery', 'nova-post-for-woocommerce' ) . '</span>';
        View::render(
            'shipment/status-badge-icon',
            array(
                'status'      => $status,
                'status_code' => $status_code,
                'order_id'    => $order_id,
                'is_hidden'   => trim( (string) $status ) === '',
            )
        );
        echo '</div>';

        echo '</div>';
    }
}
