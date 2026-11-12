<?php
/**
 * Texts typed into WooCommerce settings, per language (through Polylang strings,
 * Languages → Translations): payment method titles, descriptions and
 * instructions (bank details, "pay on delivery" notes — on the thank-you page and
 * in e-mails), shipping method titles, the store notice and the checkout texts
 * (privacy note, terms checkbox).
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Pro;

use CatCode\PolylangShop\Core\Lang;
use CatCode\PolylangShop\Front\Strings;

defined( 'ABSPATH' ) || exit;

class Texts {

	public const GROUP_PAYMENT  = 'WooCommerce: payment methods';
	public const GROUP_SHIPPING = 'WooCommerce: shipping methods';
	public const GROUP_STORE    = 'WooCommerce: checkout texts';

	private const GATEWAY_KEYS = array( 'title', 'description', 'instructions' );

	private const STORE_OPTIONS = array(
		'woocommerce_demo_store_notice',
		'woocommerce_checkout_privacy_policy_text',
		'woocommerce_registration_privacy_policy_text',
		'woocommerce_checkout_terms_and_conditions_checkbox_text',
	);

	/** @var array<string,array<string,string>> gateway id => property => text as saved in the settings. */
	private $originals = array();

	/** @var \WC_Payment_Gateways|null */
	private $gateways = null;

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_strings' ) );
		add_action( 'wc_payment_gateways_initialized', array( $this, 'gateways_ready' ), 20 );
		add_action( 'catcode_plms_switched', array( $this, 'retranslate_gateways' ) );
		add_action( 'catcode_plms_restored', array( $this, 'retranslate_gateways' ) );
		add_filter( 'woocommerce_gateway_title', array( $this, 'gateway_text' ), 20, 2 );
		add_filter( 'woocommerce_gateway_description', array( $this, 'gateway_text' ), 20, 2 );
		add_filter( 'woocommerce_shipping_rate_label', array( $this, 'shipping_label' ), 20 );
		add_action( 'woocommerce_blocks_checkout_enqueue_data', array( $this, 'block_gateway_texts' ), 5 );
		add_action( 'woocommerce_blocks_cart_enqueue_data', array( $this, 'block_gateway_texts' ), 5 );
		foreach ( self::STORE_OPTIONS as $option ) {
			add_filter( 'option_' . $option, array( $this, 'store_text' ), 20 );
		}
	}

	public function register_strings(): void {
		if ( ! Strings::on_strings_screen() || ! function_exists( 'pll_register_string' ) || ! function_exists( 'WC' ) ) {
			return;
		}
		foreach ( (array) WC()->payment_gateways()->payment_gateways() as $gateway ) {
			if ( ! $gateway instanceof \WC_Payment_Gateway ) {
				continue;
			}
			$settings = get_option( $gateway->get_option_key(), array() );
			if ( ! is_array( $settings ) ) {
				continue;
			}
			foreach ( self::GATEWAY_KEYS as $key ) {
				if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== trim( $settings[ $key ] ) ) {
					pll_register_string( $gateway->id . '_' . $key, $settings[ $key ], self::GROUP_PAYMENT, 'title' !== $key );
				}
			}
		}
		if ( class_exists( 'WC_Shipping_Zones' ) ) {
			$zones   = (array) \WC_Shipping_Zones::get_zones();
			$zones[] = array( 'zone_id' => 0 );
			foreach ( $zones as $zone_data ) {
				$zone = \WC_Shipping_Zones::get_zone( (int) $zone_data['zone_id'] );
				if ( ! $zone ) {
					continue;
				}
				foreach ( (array) $zone->get_shipping_methods() as $method ) {
					$title = isset( $method->instance_settings['title'] ) ? (string) $method->instance_settings['title'] : '';
					if ( '' !== trim( $title ) ) {
						pll_register_string( $method->id . '_' . $method->instance_id . '_title', $title, self::GROUP_SHIPPING );
					}
				}
			}
		}
		foreach ( self::STORE_OPTIONS as $option ) {
			$value = get_option( $option, '' );
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				pll_register_string( $option, $value, self::GROUP_STORE, true );
			}
		}
	}

	/**
	 * Gateways keep their texts in properties read once from the settings; they are
	 * translated there, so every place that prints them (checkout, thank-you page,
	 * e-mails) gets the language.
	 *
	 * @param \WC_Payment_Gateways $gateways Gateways registry.
	 */
	public function gateways_ready( $gateways ): void {
		if ( ! $gateways instanceof \WC_Payment_Gateways ) {
			return;
		}
		$this->gateways = $gateways;
		foreach ( (array) $gateways->payment_gateways() as $gateway ) {
			if ( ! $gateway instanceof \WC_Payment_Gateway ) {
				continue;
			}
			foreach ( self::GATEWAY_KEYS as $key ) {
				if ( isset( $gateway->$key ) && is_string( $gateway->$key ) ) {
					$this->originals[ $gateway->id ][ $key ] = $gateway->$key;
				}
			}
		}
		$this->retranslate_gateways();
	}

	public function retranslate_gateways(): void {
		if ( null === $this->gateways ) {
			return;
		}
		$lang = Lang::current();
		foreach ( (array) $this->gateways->payment_gateways() as $gateway ) {
			if ( ! $gateway instanceof \WC_Payment_Gateway || empty( $this->originals[ $gateway->id ] ) ) {
				continue;
			}
			foreach ( $this->originals[ $gateway->id ] as $key => $original ) {
				$gateway->$key = '' === $lang ? $original : Lang::string( $original, $lang );
			}
		}
	}

	/**
	 * The block checkout does not ask the gateway objects: WooCommerce's own block
	 * integrations (bank transfer, cheque, cash on delivery, PayPal Standard) read the
	 * settings once on init and hand title and description to the page from there.
	 * Right before that data goes out (priority 10), they re-read the settings in the
	 * page language. Integrations of other plugins are left alone — re-running their
	 * set-up could have side effects.
	 */
	public function block_gateway_texts(): void {
		$lang = Lang::current();
		if ( '' === $lang || ! class_exists( '\Automattic\WooCommerce\Blocks\Package' ) || ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry' ) ) {
			return;
		}
		try {
			$registry = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry::class );
		} catch ( \Throwable $e ) {
			return;
		}
		if ( ! is_object( $registry ) || ! method_exists( $registry, 'get_all_registered' ) ) {
			return;
		}
		$translate = static function ( $settings ) use ( $lang ) {
			if ( is_array( $settings ) ) {
				foreach ( self::GATEWAY_KEYS as $key ) {
					if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
						$settings[ $key ] = Lang::string( $settings[ $key ], $lang );
					}
				}
			}
			return $settings;
		};
		foreach ( (array) $registry->get_all_registered() as $name => $method ) {
			if ( ! is_object( $method ) || ! method_exists( $method, 'initialize' ) || 0 !== strpos( get_class( $method ), 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\' ) ) {
				continue;
			}
			$option = 'option_woocommerce_' . sanitize_key( (string) $name ) . '_settings';
			add_filter( $option, $translate, 99 );
			$method->initialize();
			remove_filter( $option, $translate, 99 );
		}
	}

	/**
	 * @param string $text Title / description.
	 * @param string $id   Gateway ID.
	 */
	public function gateway_text( $text, $id = '' ) {
		unset( $id );
		$lang = Lang::current();
		return '' === $lang || ! is_string( $text ) ? $text : Lang::string( $text, $lang );
	}

	/**
	 * @param string $label Shipping rate label (the method title).
	 */
	public function shipping_label( $label ) {
		$lang = Lang::current();
		return '' === $lang || ! is_string( $label ) ? $label : Lang::string( $label, $lang );
	}

	/**
	 * @param mixed $value Option value.
	 * @return mixed
	 */
	public function store_text( $value ) {
		$lang = Lang::current();
		return '' === $lang || ! is_string( $value ) || '' === $value ? $value : Lang::string( $value, $lang );
	}
}
