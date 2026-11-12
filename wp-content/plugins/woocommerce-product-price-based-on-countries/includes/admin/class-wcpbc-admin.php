<?php
/**
 * WooCommerce Price Based Country Admin
 *
 * @package WCPBC
 * @version 1.8.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Admin Class
 */
class WCPBC_Admin {

	/**
	 * Hook actions and filters
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'update_geoip_database' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ), 10 );
		add_action( 'woocommerce_system_status_report', array( __CLASS__, 'system_status_report' ) );
		add_action( 'wp_ajax_wc_price_based_country_remote_addr_check', array( __CLASS__, 'remote_addr_check' ) );
		add_action( 'load-woocommerce_page_wc-reports', array( 'WCPBC_Admin_Legacy_Reports', 'init' ) );
		add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'settings_price_based_country' ) );
		add_filter( 'woocommerce_paypal_supported_currencies', array( __CLASS__, 'paypal_supported_currencies' ) );
		add_filter( 'woocommerce_gateway_payfast_available_currencies', array( __CLASS__, 'paypal_supported_currencies' ) );
		add_filter( 'woocommerce_debug_tools', array( __CLASS__, 'debug_tools' ), 20 );
		add_action( 'woocommerce_system_status_tool_executed', array( __CLASS__, 'system_status_tool_executed' ) );

		do_action( 'wc_price_based_country_admin_init' );
	}

	/**
	 * Update the GeoIP database
	 *
	 * @since 1.7.7
	 * @version 1.7.12 Do a safe redirect to remove the get parameter.
	 * @version 1.8.20 Deprecated since WC 3.9+.
	 */
	public static function update_geoip_database() {
		if ( version_compare( WC_VERSION, '3.9', '>=' ) ) {
			return;
		}
		if ( ! empty( $_GET['wcpbc_update_geoip_database'] ) && current_user_can( 'manage_woocommerce' ) && check_admin_referer( 'wcpbc-update-geoipdb', 'wcpbc_update_geoip_database' ) && is_callable( array( 'WC_Geolocation', 'update_database' ) ) ) {
			WCPBC_Update_GeoIP_DB::update_database();
			set_transient( 'wcpbc_updated_geoip', wcpbc_geoipdb_exists() ? 'yes' : 'no' );
			wp_safe_redirect( wp_get_referer() ? remove_query_arg( array( 'wcpbc-update-geoipdb', 'trashed', 'untrashed', 'deleted', 'ids' ), wp_get_referer() ) : admin_url( 'admin.php?page=wc-settings&tab=price-based-country' ) );
			exit;
		}
	}

	/**
	 * Add Price Based Country settings tab to woocommerce settings
	 *
	 * @param array $settings Array of setting pages.
	 * @return array
	 */
	public static function settings_price_based_country( $settings ) {
		$settings[] = include 'settings/class-wc-settings-price-based-country.php';
		return $settings;
	}

	/**
	 * PayPal supported currencies
	 *
	 * @since 1.6.4
	 * @param array $paypal_currencies Array of currencies.
	 * @return array
	 */
	public static function paypal_supported_currencies( $paypal_currencies ) {

		$base_currency = wcpbc_get_base_currency();

		if ( ! in_array( $base_currency, $paypal_currencies, true ) ) {
			foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
				if ( in_array( $zone->get_currency(), $paypal_currencies, true ) ) {
					$paypal_currencies[] = $base_currency;
					break;
				}
			}
		}

		return $paypal_currencies;
	}

	/**
	 * Enqueue admin assets.
	 */
	public static function admin_assets() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		if ( ! ( in_array( $screen_id, wcpbc_get_screen_ids(), true ) || 'plugins' === $screen_id || 'dashboard' === $screen_id ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// JS.
		wp_register_script( 'wcpbc_admin', WCPBC()->plugin_url() . 'assets/js/admin/wcpbc_admin' . $suffix . '.js', array( 'jquery', 'woocommerce_admin', 'accounting' ), WCPBC()->version, true );
		wp_register_script( 'wcpbc_notices', WCPBC()->plugin_url() . 'assets/js/admin/notices' . $suffix . '.js', array( 'jquery' ), WCPBC()->version, true );
		wp_register_script( 'wcpbc_system_report', WCPBC()->plugin_url() . 'assets/js/admin/system-report' . $suffix . '.js', array( 'jquery' ), WCPBC()->version, false );

		wp_localize_script(
			'wcpbc_admin',
			'wcpbc_admin_params',
			array(
				'ajax_url'                 => admin_url( 'admin-ajax.php' ),
				'product_type_supported'   => array_keys( wcpbc_product_types_supported() ),
				'product_type_third_party' => array_keys( wcpbc_product_types_supported( 'third-party' ) ),
				'is_pro'                   => wcpbc_is_pro() ? '1' : '',
				'i18n_delete_zone_alert'   => __( 'Are you sure you want to delete this zone? This action cannot be undone', 'woocommerce-product-price-based-on-countries' ),
				'i18n_default_zone_name'   => __( 'Zone', 'woocommerce-product-price-based-on-countries' ),
			)
		);

		wp_localize_script(
			'wcpbc_notices',
			'wcpbc_notices_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);

		wp_localize_script(
			'wcpbc_system_report',
			'wcpbc_system_report_params',
			array(
				'ajax_url'                => admin_url( 'admin-ajax.php' ),
				'remote_addr_check_nonce' => wp_create_nonce( 'remote-addr-check' ),
				// Translators: PHP Code.
				'define_constant_alert'   => sprintf( esc_html__( 'Your server does not include the customer IP in HTTP_X_FORWARDED_FOR. Fix it by adding %s to your config.php.', 'woocommerce-product-price-based-on-countries' ), "<code>define( 'WCPBC_USE_REMOTE_ADDR', true );</code>" ),
				'ip_no_match'             => esc_html__( 'The first IP not empty of your server variables does not match with your real IP.', 'woocommerce-product-price-based-on-countries' ),
				'geoipdb_required'        => esc_html__( 'The MaxMind GeoIP database is required.', 'woocommerce-product-price-based-on-countries' ),
			)
		);

		wp_enqueue_script( 'wcpbc_notices' );

		if ( in_array( $screen_id, array( 'product', 'shop_coupon', 'shop_order', 'shop_subscription' ), true ) ) {

			wp_enqueue_script( 'wcpbc_admin' );

		} elseif ( 'woocommerce_page_wc-status' === $screen_id ) {

			wp_enqueue_script( 'wcpbc_system_report' );
		}

		// Styles.
		wp_enqueue_style( 'wcpbc_admin_styles', WCPBC()->plugin_url() . 'assets/css/admin/admin' . $suffix . '.css', array(), WCPBC()->version );
	}


	/**
	 * Add plugin info to WooCommerce System Status Report
	 *
	 * @since 1.6.3
	 */
	public static function system_status_report() {
		include_once 'views/html-admin-page-status-report.php';
	}

	/**
	 * Handle Ajax request that checks the REMOTE_ADDR IP country against the real external IP.
	 *
	 * @since 1.8.0
	 */
	public static function remote_addr_check() {
		check_ajax_referer( 'remote-addr-check', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}
		$external_ip = isset( $_POST['external_ip'] ) ? sanitize_text_field( wp_unslash( $_POST['external_ip'] ) ) : false;
		if ( isset( $_POST['remote_addr'] ) ) {
			$remote_addr = sanitize_text_field( wp_unslash( $_POST['remote_addr'] ) );
		} else {
			$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : false;
		}

		if ( $external_ip && $remote_addr ) {
			$external_ip_country = WC_Geolocation::geolocate_ip( $external_ip, false, false );
			$remote_addr_country = WC_Geolocation::geolocate_ip( $remote_addr, false, false );

			wp_send_json( array( 'result' => ( $external_ip_country['country'] === $remote_addr_country['country'] ? '1' : '0' ) ) );
		}

		wp_send_json( array( 'result' => '0' ) );
	}

	/**
	 * A list of available tools for use in the system status section.
	 *
	 * @since 1.8.8
	 * @version 4.0.4 Replace DB update with Sync Price with children.
	 * @param array $debug_tools Debug tools.
	 * @return array
	 */
	public static function debug_tools( $debug_tools ) {
		$debug_tools['wcpbc_sync_price_with_children'] = array(
			'name'     => 'Price Based on Country: ' . _x( 'Synchronize the price of variable products', 'WooCommerce tool name', 'woocommerce-product-price-based-on-countries' ),
			'button'   => __( 'Update', 'woocommerce-product-price-based-on-countries' ),
			'desc'     => __( 'This tool will update the price of the variable products with a minimum and maximum price of its variations. It solves sorting issues.', 'woocommerce-product-price-based-on-countries' ),
			'callback' => (
				function() {
					$job = 'Sync_Price_With_Children_Batch';
					$actions = as_get_scheduled_actions(
						[
							'hook'     => WCPBC_Product_Meta_Job::ACTION_HOOK,
							'group'    => WCPBC_Product_Meta_Job::ACTION_HOOK,
							'status'   => [ ActionScheduler_Store::STATUS_PENDING, ActionScheduler_Store::STATUS_RUNNING ],
							'per_page' => -1,
						]
					);

					foreach ( $actions as $action_id => $action ) {
						$action_args = is_callable( [ $action, 'get_args' ] ) ? $action->get_args() : [];
						if ( isset( $action_args['job'] ) && $job === $action_args['job'] ) {
							return __( 'The tool is running in the background. Please wait for it to finish before running it again.', 'woocommerce-product-price-based-on-countries' );
						}
					}

					WCPBC_Product_Meta_Job::create( $job )->run_async();
					return __( 'The tool is running in the background.', 'woocommerce-product-price-based-on-countries' );
				}
			),
		);
		return $debug_tools;
	}

	/**
	 * Runs after a WooCommerce system status tool has been executed.
	 *
	 * @param array $tool  Details about the tool that has been executed.
	 */
	public static function system_status_tool_executed( $tool ) {
		$action = isset( $tool['id'] ) ? $tool['id'] : false;
		if ( 'clear_transients' === $action ) {
			// Delete plugin transient on WooCommerce clear transients tool.
			delete_transient( 'wcpbc_products_onsale' );
		}
	}
}

