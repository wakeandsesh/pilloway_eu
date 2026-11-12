<?php
/**
 * WCPBC_Setup_Wizard class.
 *
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( class_exists( 'WCPBC_Setup_Wizard' ) ) {
	return;
}

/**
 * WCPBC_Setup_Wizard Class
 */
class WCPBC_Setup_Wizard {

	/**
	 * Instance the object.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'do_admin_redirect' ), 20 );
		add_action( 'wp_ajax_wcpbc_setup_wizard_process_step', array( __CLASS__, 'process_step' ) );

		if ( ! ( self::is_setup_wizard_page() && current_user_can( 'manage_woocommerce' ) ) ) {
			return;
		}

		// Add hooks.
		add_action( 'admin_menu', array( __CLASS__, 'admin_menus' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'inject_before_notices' ), PHP_INT_MIN );
		add_action( 'admin_notices', array( __CLASS__, 'inject_after_notices' ), PHP_INT_MAX );
		add_filter( 'admin_body_class', array( __CLASS__, 'add_loading_classes' ), PHP_INT_MAX );
	}

	/**
	 * Is the setup wizard page?
	 *
	 * @return bool
	 */
	private static function is_setup_wizard_page() {
		return is_admin() && ! empty( $_GET['page'] ) && 'wcpbc-setup' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Handle redirects to setup/welcome page after install and updates.
	 *
	 * For setup wizard, cookie must be present, the user must have access rights, and we must ignore the network/bulk plugin updaters.
	 */
	public static function do_admin_redirect() {
		if ( isset( $_COOKIE['_wcpbc_activation_redirect'] ) && '1' === wc_clean( wp_unslash( $_COOKIE['_wcpbc_activation_redirect'] ) ) ) {
			$do_redirect = true;

			// On these pages, or during these events, postpone the redirect.
			if ( wp_doing_ajax() || is_network_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
				$do_redirect = false;
			}

			// On these pages, disable the redirect.
			if ( self::is_setup_wizard_page() ) {
				setcookie( '_wcpbc_activation_redirect', '0', time() - 3600 );
				$do_redirect = false;
			}

			if ( $do_redirect ) {
				setcookie( '_wcpbc_activation_redirect', '0', time() - 3600 );
				wp_safe_redirect( admin_url( 'admin.php?page=wcpbc-setup' ) );
				exit;
			}
		}
	}

	/**
	 * Add admin menus/screens.
	 */
	public static function admin_menus() {
		add_dashboard_page( 'Setup Wizard', '', 'manage_options', 'wcpbc-setup', array( __CLASS__, 'output' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public static function enqueue_scripts() {

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'wcpbc-setup-wizard-styles', WCPBC()->plugin_url() . "assets/css/admin/setup-wizard{$suffix}.css", array(), WCPBC()->version );
		wp_register_script( 'wcpbc-setup-wizard', WCPBC()->plugin_url() . "assets/js/admin/setup-wizard{$suffix}.js", array( 'jquery', 'jquery-blockui', 'wp-util' ), WCPBC()->version, true );
		wp_localize_script(
			'wcpbc-setup-wizard',
			'wcpbc_setup_wizard_params',
			array(
				'security' => wp_create_nonce( 'wcpbc-setup-wizard-save-step' ),
			)
		);
		wp_enqueue_script( 'wcpbc-setup-wizard' );
	}

	/**
	 * Runs before admin notices action and hides them.
	 */
	public static function inject_before_notices() {
		echo '<div style="display:none;">';
	}

	/**
	 * Runs after admin notices and closes div.
	 */
	public static function inject_after_notices() {
		echo '</div>';
	}

	/**
	 * Ouput the page.
	 */
	public static function output() {
		include dirname( __FILE__ ) . '/views/html-setup-wizard.php';
	}

	/**
	 * Set the admin full screen class when loading to prevent flashes of unstyled content.
	 *
	 * @param bool $classes Body classes.
	 * @return array
	 */
	public static function add_loading_classes( $classes ) {
		return 'wcpbc-admin-full-screen wcpbc-setup-wizard';
	}

	/**
	 * Returns if the system requires the MaxMind geoip database.
	 *
	 * @return bool
	 */
	private static function maxmind_geoip_required() {
		$required = true;
		$headers  = array(
			'MM_COUNTRY_CODE',
			'GEOIP_COUNTRY_CODE',
			'HTTP_CF_IPCOUNTRY',
			'HTTP_X_COUNTRY_CODE',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$required = false;
				break;
			}
		}

		return $required;
	}

	/**
	 * Returns the Maxmind license key.
	 *
	 * @return string
	 */
	private static function get_maxmind_license_key() {
		$maxmind_settings = get_option( 'woocommerce_maxmind_geolocation_settings' );
		return empty( $maxmind_settings['license_key'] ) ? '' : $maxmind_settings['license_key'];
	}

	/**
	 * Save data and return the next step.
	 */
	public static function process_step() {

		check_ajax_referer( 'wcpbc-setup-wizard-save-step', 'security' );

		$postdata = wc_clean( wp_unslash( $_POST ) );
		$callback = isset( $postdata['step'] ) ? 'process_step_' . $postdata['step'] : false;

		if ( $callback && is_callable( array( __CLASS__, $callback ) ) ) {

			self::{$callback}( $postdata );
		}

		wp_send_json_success();
	}

	/**
	 * Process geolocation step.
	 *
	 * @param array $postdata Post array.
	 */
	private static function process_step_geolocation( $postdata ) {
		if ( ! self::maxmind_geoip_required() ) {
			update_option( 'woocommerce_default_customer_address', 'geolocation' );
			return;
		}

		if ( empty( $postdata['maxmind_license_key'] ) ) {

			$data = new WP_Error( 'maxmind_license_key', __( 'Please, enter the MaxMind license key.', 'woocommerce-product-price-based-on-countries' ) );
			wp_send_json_error( $data );
		}

		if ( self::get_maxmind_license_key() === $postdata['maxmind_license_key'] && wcpbc_geoipdb_exists() ) {
			return;
		}

		$maxmind_itegration = WC()->integrations->get_integration( 'maxmind_geolocation' );

		if ( $maxmind_itegration && is_a( $maxmind_itegration, 'WC_Integration_MaxMind_Geolocation' ) ) {

			try {
				$value = $maxmind_itegration->validate_license_key_field( 'license_key', $postdata['maxmind_license_key'] );

				$maxmind_itegration->update_option( 'license_key', $value );

				update_option( 'woocommerce_default_customer_address', 'geolocation' );

			} catch ( Exception $e ) {
				$data = new WP_Error( 'maxmind_license_key', $e->getMessage() );

				wp_send_json_error( $data );
			}
		}
	}

	/**
	 * Process cache support step.
	 *
	 * @param array $postdata Post array.
	 */
	private static function process_step_cache_support( $postdata ) {
		if ( empty( $postdata['cachesupport'] ) ) {
			return;
		}

		$cache_support = 'yes' === $postdata['cachesupport'] ? 'yes' : 'no';

		update_option( 'wc_price_based_country_caching_support', $cache_support );
	}
}
