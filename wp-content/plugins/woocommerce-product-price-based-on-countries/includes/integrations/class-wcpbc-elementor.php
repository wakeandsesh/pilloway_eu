<?php
/**
 * Handle compatiblity with Elementor plugin.
 *
 * @since 2.0.16
 * @package WCPBC/Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCPBC_Elementor' ) ) :

	/**
	 * WCPBC_Elementor class.
	 */
	class WCPBC_Elementor {

		/**
		 * Hook actions and filters
		 */
		public static function init() {
			add_filter( 'wc_price_based_country_dequeue_script', array( __CLASS__, 'is_preview' ) );
		}

		/**
		 * Is elementor preview mode?
		 *
		 * @param bool $value Value to return.
		 */
		public static function is_preview( $value ) {
			if ( isset( \Elementor\Plugin::$instance ) && isset( \Elementor\Plugin::$instance->preview ) && is_callable( array( \Elementor\Plugin::$instance->preview, 'is_preview_mode' ) ) ) {
				$value = $value || \Elementor\Plugin::$instance->preview->is_preview_mode();
			}
			return $value;
		}
	}

	WCPBC_Elementor::init();

endif;
