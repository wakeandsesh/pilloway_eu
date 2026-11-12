<?php
/**
 * WooCommerce Blocks (block checkout) integration for the LiqPay gateway.
 *
 * @package CcLiqPayWC
 */

namespace CatCode\LiqPayWC\Gateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

final class Blocks extends AbstractPaymentMethodType {

	/** @var string */
	protected $name = Method::ID;

	/** @var Method|null */
	private $gateway;

	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . Method::ID . '_settings', array() );
		$gateways       = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$gw             = $gateways[ Method::ID ] ?? null;
		$this->gateway  = $gw instanceof Method ? $gw : null;
	}

	public function is_active() {
		return $this->gateway ? $this->gateway->is_available() : false;
	}

	public function get_payment_method_script_handles() {
		$handle = 'lpwc-blocks';
		wp_register_script(
			$handle,
			CATCODE_LIQPAY_URL . 'assets/js/blocks.js',
			array( 'wc-blocks-registry', 'wp-element', 'wp-html-entities' ),
			CATCODE_LIQPAY_VERSION,
			true
		);
		return array( $handle );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->gateway ? $this->gateway->title : __( 'Card payment (LiqPay)', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
			'description' => $this->gateway ? $this->gateway->description : '',
			'supports'    => array( 'products' ),
		);
	}
}
