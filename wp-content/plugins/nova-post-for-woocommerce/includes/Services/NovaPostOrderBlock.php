<?php
/**
 * Nova Post order block renderer.
 *
 * @package NovaPostForWoocommerce\Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Helpers\View;

/**
 * Provides reusable rendering helpers for Nova Post order-related UI blocks.
 */
class NovaPostOrderBlock {

    /**
     * Render a Nova Post order block containing status, errors, and action buttons.
     *
     * @param array $args Rendering configuration.
     * @return void
     */

    const ACTION_CREATE_WAYBILL  = 'create_waybill';
    const ACTION_DELETE_SHIPMENT = 'delete_shipment';
    const ACTION_PRINT_LABEL     = 'print_label';
    const ACTION_PRINT_INVOICE   = 'print_invoice';
    const ACTION_PRINT_CUSTOMS   = 'print_customs';

    const PICKUP_BADGE_STATUS_ADDED   = 'added';
    const PICKUP_BADGE_STATUS_WAITING = 'waiting';
    const PICKUP_BADGE_STATUS_ERROR   = 'error';

    /**
     * Render the Nova Post order block.
     *
     * @param array $args Arguments for rendering the block.
     * @return void
     */
    public static function render( $args ) {
        $defaults = array(
            'order'             => null,
            'shipment_info'     => array(),
            'sprite_url'        => self::get_default_sprite_url(),
            'container_classes' => 'npw-content',
            'available_actions' => array(
                self::ACTION_CREATE_WAYBILL,
                self::ACTION_DELETE_SHIPMENT,
                self::ACTION_PRINT_LABEL,
                self::ACTION_PRINT_INVOICE,
                self::ACTION_PRINT_CUSTOMS,
            ),
            'hide_status_block' => false,
        );

        $config = wp_parse_args( $args, $defaults );

        $order           = $config['order'];
        $shipment_info   = is_array( $config['shipment_info'] ) ? $config['shipment_info'] : array();
        $has_track       = ! empty( $shipment_info['track_number'] );
        $container_class = self::prepare_container_classes( $config['container_classes'] );

        if ( $has_track ) {
            $config['available_actions'] = array_diff( $config['available_actions'], array( self::ACTION_CREATE_WAYBILL ) );
        }

        if ( ShipmentService::is_local_delivery( $order ) || ShipmentService::is_eu_delivery( $order ) ) {
            $config['available_actions'] = array_diff( $config['available_actions'], array( self::ACTION_PRINT_INVOICE, self::ACTION_PRINT_CUSTOMS ) );
        }

        echo '<div class="' . esc_attr( $container_class ) . '">';

        self::render_create_section( (int) $order->get_id(), $config['sprite_url'], $config['available_actions'] );

        // Errors section.
        $errors = isset( $shipment_info['shipment_errors'] ) ? $shipment_info['shipment_errors'] : array();
        self::render_errors_section( $errors );

        self::render_details_section(
            $order,
            $shipment_info,
            $config['sprite_url'],
            $has_track,
            $config['available_actions'],
            $config['hide_status_block']
        );

        echo '</div>';
    }

    /**
     * Normalize container class names.
     *
     * @param string|array $classes Class names as string or array.
     * @return string
     */
    private static function prepare_container_classes( $classes ) {
        if ( is_array( $classes ) ) {
            $classes = implode( ' ', $classes );
        }

        $classes = trim( preg_replace( '/\s+/', ' ', (string) $classes ) );

        if ( '' === $classes ) {
            $classes = 'npw-content';
        }

        return $classes;
    }

    /**
     * Render the create waybill section.
     *
     * @param int    $order_id          Order ID.
     * @param string $sprite_url        Sprite sheet URL.
     * @param array  $available_actions Available actions for the order.
     * @return void
     */
    public static function render_create_section( $order_id, $sprite_url, $available_actions ) {
        View::render(
            'shipment/button-create-waybill',
            array(
                'order_id'   => $order_id,
                'sprite_url' => $sprite_url,
                'is_hidden'  => ! in_array( self::ACTION_CREATE_WAYBILL, $available_actions, true ),
            )
        );
    }

    /**
     * Render shipment errors section.
     *
     * @param mixed $errors Errors data.
     * @return void
     */
    public static function render_errors_section( $errors ) {
        View::render(
            'shipment/shipment-errors',
            array(
                'errors'    => $errors,
                'is_hidden' => empty( $errors ),
            )
        );
    }

    /**
     * Render the shipment details section with tracking info, status, actions, and a pickup status badge.
     *
     * @param \WC_Order $order WooCommerce order object.
     * @param array     $shipment_info Shipment data.
     * @param string    $sprite_url Sprite URL.
     * @param bool      $has_track Whether a shipment has a tracking number.
     * @param array     $available_actions Available actions for the order.
     * @param bool      $hide_status Whether to hide the status block output.
     * @return void
     */
    private static function render_details_section( $order, $shipment_info, $sprite_url, $has_track, $available_actions, $hide_status = false ) {
        $order_id = $order->get_id();
        echo '<div class="npw-content__details"' . esc_attr( self::hidden_attr( ! $has_track ) ) . '>';

        $tracking_url = $shipment_info['tracking_url'] ?? '';
        $track_number = $shipment_info['track_number'] ?? '';
        self::render_track_number(
            $track_number,
            $tracking_url,
            $sprite_url,
            trim( (string) $track_number ) === ''
        );

        // Render status block (pickup + delivery) using NovaPostStatusBlock service.
        NovaPostStatusBlock::render( $order, $shipment_info, $hide_status );

        self::render_actions( $order_id, $sprite_url, $shipment_info, $available_actions );

        echo '</div>';
    }

    /**
     * Render track number link.
     *
     * @param string $track_number Track number.
     * @param string $tracking_url Tracking URL.
     * @param string $sprite_url   Sprite URL.
     * @param bool   $is_hidden    Whether the link should be hidden.
     * @return void
     */
    private static function render_track_number( $track_number, $tracking_url, $sprite_url, $is_hidden = false ) {
        View::render(
            'shipment/link-track-number',
            array(
                'tracking_url' => $tracking_url,
                'track_number' => $track_number,
                'sprite_url'   => $sprite_url,
                'is_hidden'    => $is_hidden,
            )
        );
    }

    /**
     * Render shipment action buttons.
     *
     * @param int    $order_id          Order ID.
     * @param string $sprite_url        Sprite URL.
     * @param array  $shipment_info     Shipment data.
     * @param array  $available_actions Available actions for the order.
     * @return void
     */
    private static function render_actions( $order_id, $sprite_url, $shipment_info, $available_actions ) {
        // Actions container.
        echo '<div class="npw-content__actions">';

        // Print button.
        View::render(
            'shipment/button-print-label',
            array(
                'order_id'   => $order_id,
                'sprite_url' => $sprite_url,
                'is_hidden'  => in_array( self::ACTION_PRINT_LABEL, $available_actions, true ) ? false : true,
            )
        );

        // Invoice button.
        View::render(
            'shipment/button-print-invoice',
            array(
                'order_id'   => $order_id,
                'sprite_url' => $sprite_url,
                'is_hidden'  => in_array( self::ACTION_PRINT_INVOICE, $available_actions, true ) ? false : true,
            )
        );

        // Customs button.
        View::render(
            'shipment/button-print-customs',
            array(
                'order_id'   => $order_id,
                'sprite_url' => $sprite_url,
                'is_hidden'  => in_array( self::ACTION_PRINT_CUSTOMS, $available_actions, true ) ? false : true,
            )
        );

        // Delete button.
        View::render(
            'shipment/button-delete-shipment',
            array(
                'order_id'    => $order_id,
                'sprite_url'  => $sprite_url,
                'is_disabled' => 1 !== (int) $shipment_info['last_status_code'],
                'is_hidden'   => in_array( self::ACTION_DELETE_SHIPMENT, $available_actions, true ) ? false : true,
            )
        );

        echo '</div>';
    }

    /**
     * Generate hidden attribute when needed.
     *
     * @param bool $hidden Whether the element should be hidden.
     * @return string
     */
    private static function hidden_attr( $hidden ) {
        return $hidden ? ' hidden' : '';
    }

    /**
     * Get default sprite URL for Nova Post buttons.
     *
     * @return string
     */
    private static function get_default_sprite_url() {
        return plugins_url( 'assets/images/icons-sprite.svg', NPFORWC_PLUGIN_FILE_PATH );
    }
}
