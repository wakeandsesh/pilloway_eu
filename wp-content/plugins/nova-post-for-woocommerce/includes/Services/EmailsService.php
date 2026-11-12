<?php
/**
 * Email service for handling Nova Post tracking information in WooCommerce emails.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

/**
 * Class EmailsService
 *
 * Handles email-related functionality for Nova Post tracking information.
 */
class EmailsService {

    /**
     * Adds Nova Post tracking number to WooCommerce order emails.
     *
     * @param \WC_Order $order         Order object.
     * @param bool      $sent_to_admin Whether the email is sent to the admin.
     * @param bool      $plain_text    Whether the email is plain text.
     * @return void
     */
    public static function add_tracking_number_to_emails( $order, $sent_to_admin, $plain_text = false ) {
        $tracking_number = $order->get_meta( '_nova_post_shipment_number' );

        if ( $tracking_number ) {
            $link = ShipmentService::get_tracking_url( $order, 'recipient' );
            if ( $plain_text ) {
                echo "\n" . esc_html__( 'Nova Post Tracking Number:', 'nova-post-for-woocommerce' ) . ' ' . esc_html( $tracking_number );
                echo "\n" . esc_html__( 'Tracking Link:', 'nova-post-for-woocommerce' ) . ' ' . esc_url( $link );
            } else {
                echo '<div style="border-bottom: 1px solid rgba(0, 0, 0, .2); margin-bottom: 10px;">';
                echo '<p><strong>' . esc_html__( 'Nova Post Tracking Number:', 'nova-post-for-woocommerce' ) . '</strong> ' . esc_html( $tracking_number ) . '</p>';
                echo '<p><strong>' . esc_html__( 'Tracking Link:', 'nova-post-for-woocommerce' ) . '</strong> <a href="' . esc_url( $link ) . '" target="_blank">' . esc_url( $link ) . '</a></p>';
                echo '</div>';
            }
        }
    }
}
