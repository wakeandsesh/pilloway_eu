<?php
/**
 * Plugin Name: Pilloway Shipping Display
 * Description: Shows Nova Post carrier-rate guidance in WooCommerce Cart and Checkout Blocks.
 * Version: 1.2.0
 * Text Domain: pilloway-shipping-display
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 *
 * @package Pilloway_Shipping_Display
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the Nova Post zero-rate label and Blocks display in sync with the shipping destination.
 */
final class Pilloway_Shipping_Display {

	const VERSION             = '1.2.0';
	const RATE_META_KEY       = 'pilloway_shipping_display_mode';
	const CARRIER_RATES_MODE  = 'carrier_rates';

	/**
	 * Register plugin hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_polylang_strings' ), 20 );
		add_filter( 'woocommerce_shipping_rate_label', array( __CLASS__, 'filter_rate_label' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register configured Nova Post rate labels with Polylang Free.
	 */
	public static function register_polylang_strings() {
		if ( ! function_exists( 'pll_register_string' ) || ! class_exists( 'WC_Shipping_Zones' ) ) {
			return;
		}

		$zones = WC_Shipping_Zones::get_zones();
		$rest_of_world = WC_Shipping_Zones::get_zone_by( '0' );
		if ( $rest_of_world ) {
			$zones[] = array( 'shipping_methods' => $rest_of_world->get_shipping_methods() );
		}

		foreach ( $zones as $zone ) {
			foreach ( (array) ( $zone['shipping_methods'] ?? array() ) as $method ) {
				if ( ! is_object( $method ) || false === strpos( (string) ( $method->id ?? '' ), 'nova_post' ) ) {
					continue;
				}

				$label = is_callable( array( $method, 'get_title' ) )
					? (string) $method->get_title()
					: (string) ( $method->method_title ?? '' );
				if ( '' === trim( $label ) ) {
					continue;
				}

				pll_register_string(
					'Nova Post shipping rate ' . (int) ( $method->instance_id ?? 0 ),
					$label,
					'Pilloway checkout',
					false
				);
			}
		}
	}
	/**
	 * Load bundled translations for the plugin text domain.
	 */
	public static function load_textdomain() {
		load_plugin_textdomain(
			'pilloway-shipping-display',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Add a localized carrier-rate note to zero-cost Nova Post rates when shipping is not free.
	 *
	 * The Store API reads the rate label before its public metadata, so the mode is attached
	 * here using the same live cart and shipping-destination state that determines the label.
	 *
	 * @param string           $label Shipping rate label.
	 * @param WC_Shipping_Rate $rate  Shipping rate.
	 * @return string
	 */
	public static function filter_rate_label( $label, $rate ) {
		if ( ! self::is_nova_post_rate( $rate ) ) {
			return $label;
		}

		if ( ! is_admin() && function_exists( 'pll__' ) ) {
			$label = pll__( $label );
		}

		$can_add_metadata = is_callable( array( $rate, 'add_meta_data' ) );
		if ( $can_add_metadata ) {
			// Clear an old marker if the rate has since become nonzero or its state is unavailable.
			$rate->add_meta_data( self::RATE_META_KEY, '' );
		}

		if ( ! self::is_zero_cost_rate( $rate ) ) {
			return $label;
		}

		$mode = self::get_display_mode();
		if ( null === $mode ) {
			return $label;
		}

		if ( $can_add_metadata ) {
			$rate->add_meta_data( self::RATE_META_KEY, $mode );
		}

		if ( self::CARRIER_RATES_MODE !== $mode ) {
			return $label;
		}

		return sprintf(
			/* translators: %1$s is the shipping method name; %2$s explains how the carrier fee is charged. */
			__( '%1$s — %2$s', 'pilloway-shipping-display' ),
			$label,
			__( "At the carrier's rates", 'pilloway-shipping-display' )
		);
	}

	/** EU delivery always follows carrier tariffs; no order-value threshold. */
	private static function get_display_mode() {
		return self::CARRIER_RATES_MODE;
	}

	/**
	 * Check for a Nova Post rate using the method ID getter.
	 *
	 * @param mixed $rate Shipping rate candidate.
	 * @return bool
	 */
	private static function is_nova_post_rate( $rate ) {
		return is_object( $rate )
			&& is_callable( array( $rate, 'get_method_id' ) )
			&& false !== strpos( (string) $rate->get_method_id(), 'nova_post' );
	}

	/**
	 * Check for a zero-cost rate using the public cost getter.
	 *
	 * @param mixed $rate Shipping rate candidate.
	 * @return bool
	 */
	private static function is_zero_cost_rate( $rate ) {
		return is_object( $rate )
			&& is_callable( array( $rate, 'get_cost' ) )
			&& 0.0 === (float) $rate->get_cost();
	}

	/**
	 * Load the narrowly scoped Cart and Checkout Blocks assets.
	 */
	public static function enqueue_assets() {
		$is_cart     = function_exists( 'is_cart' ) && is_cart();
		$is_checkout = function_exists( 'is_checkout' ) && is_checkout();

		if ( ! $is_cart && ! $is_checkout ) {
			return;
		}

		$base_url = plugin_dir_url( __FILE__ );
		wp_enqueue_style(
			'pilloway-shipping-display',
			$base_url . 'assets/nova-post-shipping-display.css',
			array(),
			self::VERSION
		);

		if ( ! wp_script_is( 'wc-blocks-data-store', 'registered' ) ) {
			return;
		}

		wp_enqueue_script(
			'pilloway-shipping-display',
			$base_url . 'assets/nova-post-shipping-display.js',
			array( 'wp-data', 'wc-blocks-data-store' ),
			self::VERSION,
			true
		);
	}
}

Pilloway_Shipping_Display::init();
