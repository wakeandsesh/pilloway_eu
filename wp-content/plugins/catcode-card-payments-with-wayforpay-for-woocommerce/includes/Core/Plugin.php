<?php
/**
 * Plugin bootstrap / wiring.
 *
 * @package CcWayForPayWC
 */

namespace CatCode\WayForPayWC\Core;

use CatCode\WayForPayWC\Gateway\Blocks;
use CatCode\WayForPayWC\Gateway\Method;
use CatCode\WayForPayWC\Webhook\Handler;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot() {

		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_filter( 'plugin_action_links_' . CATCODE_WAYFORPAY_BASENAME, array( $this, 'action_links' ) );

		( new Handler() )->register();

		// Block-based checkout support.
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $registry ) {
				if ( class_exists( \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType::class ) ) {
					$registry->register( new Blocks() );
				}
			}
		);

		// Fallback reconciliation when the buyer lands back on the thank-you page.
		add_action( 'woocommerce_thankyou', array( $this, 'on_thankyou' ), 10, 1 );
	}

	public function on_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== Method::ID || $order->is_paid() ) {
			return;
		}
		$gw = $this->gateway();
		if ( $gw ) {
			$gw->sync_order( $order );
		}
	}

	public function register_gateway( array $gateways ): array {
		$gateways[] = Method::class;
		return $gateways;
	}

	public function action_links( array $links ): array {
		$url  = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . Method::ID );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'catcode-card-payments-with-wayforpay-for-woocommerce' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	private function gateway(): ?Method {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$gw       = $gateways[ Method::ID ] ?? null;
		return $gw instanceof Method ? $gw : null;
	}
}
