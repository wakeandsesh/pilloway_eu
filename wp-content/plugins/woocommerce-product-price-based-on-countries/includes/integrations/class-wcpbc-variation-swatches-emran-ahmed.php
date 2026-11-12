<?php
/**
 * Handle integration with Variation Swatches for WooCommerce Pro by Emran Ahmed.
 *
 * @see https://wordpress.org/plugins/woo-variation-swatches/
 *
 * @since 3.1.2
 * @package WCPBC/Integrations
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Variation_Swatches_Emran_Ahmed class.
 */
class WCPBC_Variation_Swatches_Emran_Ahmed {

	/**
	 * Admin notice.
	 *
	 * @var string
	 */
	private static $notice = '';

	/**
	 * Checks the environment for compatibility problems.
	 *
	 * @return boolean
	 */
	public static function check_environment() {

		$compatible     = true;
		$plugin_version = defined( 'WOO_VARIATION_SWATCHES_PRO_PLUGIN_VERSION' ) ? WOO_VARIATION_SWATCHES_PRO_PLUGIN_VERSION : 'unknown';
		$min_version    = '2.0.19';

		if ( 'unknown' === $plugin_version || version_compare( $plugin_version, $min_version, '<' ) ) {
			// translators: 1: HTML tag, 2: HTML tag, 3: Variation Swatches for WooCommerce version.
			self::$notice = sprintf( __( '%1$sPrice Based on Country & Variation Swatches for WooCommerce Pro%2$s compatibility %1$srequires%2$s Variation Swatches for WooCommerce Pro %1$s+%4$s%2$s. You are running Variation Swatches for WooCommerce Pro %3$s.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>', $plugin_version, $min_version );
			add_action( 'admin_notices', array( __CLASS__, 'min_version_notice' ) );

			$compatible = false;
		}

		return $compatible;
	}

	/**
	 * Display admin minimun version required
	 */
	public static function min_version_notice() {
		echo '<div id="message" class="error"><p>' . wp_kses_post( self::$notice ) . '</p></div>';
	}

	/**
	 * Hook actions and filters
	 */
	public static function init() {
		if ( ! self::check_environment() ) {
			return;
		}

		add_action( 'rest_api_init', [ __CLASS__, 'rest_api_init' ] );
		add_filter( 'wc_price_based_country_frontend_rest_routes', [ __CLASS__, 'frontend_rest_routes' ] );
	}

	/**
	 * REST API init.
	 */
	public static function rest_api_init() {
		static $doing = false;

		if ( empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			return;
		}

		$route = untrailingslashit( $GLOBALS['wp']->query_vars['rest_route'] );

		if ( $doing || 1 !== preg_match( '/\/woo-variation-swatches\/v[0-9]+\/archive-product\/[0-9]+/', $route ) ) {
			return;
		}
		// Do no cache the archive product REST API response.
		WC_Cache_Helper::set_nocache_constants();

		add_filter(
			'woo_variation_swatches_rest_api_headers',
			function( $headers ) {
				foreach ( wp_get_nocache_headers() as $nocacheheader => $value ) {
					if ( empty( $value ) ) {
						unset( $headers[ $nocacheheader ] );
					} else {
						$headers[ $nocacheheader ] = $value;
					}
				}
				return $headers;
			}
		);

		$doing = true;
	}

	/**
	 * Adds the variation swatches rest route to the frontend routes.
	 *
	 * @param array $routes Array of frontedn routes.
	 */
	public static function frontend_rest_routes( $routes ) {
		$routes[] = 'woo-variation-swatches/';
		return $routes;
	}
}

WCPBC_Variation_Swatches_Emran_Ahmed::init();
