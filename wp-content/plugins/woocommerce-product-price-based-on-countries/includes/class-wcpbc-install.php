<?php
/**
 * WooCommerce Price Based Country install
 *
 * @version 1.8.5
 * @package WCPBC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WCPBC_Install Class
 */
class WCPBC_Install {

	/**
	 * Database update files.
	 *
	 * @var array
	 */
	private static $db_updates = array(
		'1.3.2'  => 'wcpbc_update_132',
		'1.6.0'  => 'wcpbc_update_160',
		'1.6.2'  => 'wcpbc_update_162',
		'1.8.21' => 'wcpbc_update_1821',
		'2.0.0'  => 'wcpbc_update_200',
		'2.0.3'  => 'wcpbc_update_200',
		'2.0.28' => 'wcpbc_update_228',
		'4.0.12' => 'wcpbc_update_4012',
		'4.2.2'  => 'wcpbc_update_422',
	);

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wc_price_based_country_update_database', array( __CLASS__, 'update_database' ) );
		add_action( 'admin_init', array( __CLASS__, 'check_version' ), 10 );
		add_action( 'in_plugin_update_message-woocommerce-product-price-based-on-countries/woocommerce-product-price-based-on-countries.php', array( __CLASS__, 'in_plugin_update_message' ) );
	}

	/**
	 * Update database to the last version.
	 *
	 * @param string $current_version Current version.
	 */
	public static function update_database( $current_version ) {

		include_once dirname( __FILE__ ) . '/wcpbc-update-functions.php';

		foreach ( self::$db_updates as $version => $callback ) {
			if ( version_compare( $current_version, $version, '<' ) ) {
				if ( function_exists( $callback ) ) {
					call_user_func( $callback );
				}
			}
		}
	}

	/**
	 * Check version and run the updater is required.
	 */
	public static function check_version() {
		if ( defined( 'IFRAME_REQUEST' ) ) {
			return;
		}

		$current_db_version = self::get_install_version();
		$needs_db_update    = false;

		if ( false !== $current_db_version && version_compare( $current_db_version, WCPBC()->version, '<' ) ) {
			$update_versions = array_keys( self::$db_updates );
			$needs_db_update = version_compare( $current_db_version, end( $update_versions ), '<' );

			if ( $needs_db_update && ! as_next_scheduled_action( 'wc_price_based_country_update_database' ) ) {
				as_enqueue_async_action( 'wc_price_based_country_update_database', [ 'current_version' => $current_db_version ] );
			}
		}

		if ( WCPBC()->version !== $current_db_version ) {

			self::update_wcpbc_version();

			if ( false === $current_db_version ) {

				// New install.
				self::new_install();

			} else {

				// Update options.
				self::update_options( $current_db_version );
			}
		}
	}

	/**
	 * Get version installed.
	 */
	private static function get_install_version() {
		$install_version = get_option( 'wc_price_based_country_version', false );

		if ( empty( $install_version ) && get_option( '_oga_wppbc_countries_groups' ) ) {
			$install_version = '1.3.1';
		}
		return empty( $install_version ) ? false : $install_version;
	}

	/**
	 * Update WCPBC version to current.
	 */
	private static function update_wcpbc_version() {
		delete_option( 'wc_price_based_country_version' );
		add_option( 'wc_price_based_country_version', WCPBC()->version );
	}

	/**
	 * First install actions.
	 *
	 * @since 2.3.0
	 */
	private static function new_install() {
		self::create_options();
		self::set_gla_integration_option();
		self::deactivate_wc_payments_multicurrency();

		do_action( 'wc_price_based_country_installed' );
	}

	/**
	 * Update options on version update.
	 *
	 * @param string $current_version Current plugin version.
	 * @since 2.3.0
	 */
	private static function update_options( $current_version ) {

		if ( version_compare( $current_version, '2.3.0', '<' ) ) {

			self::set_gla_integration_option();
			self::deactivate_wc_payments_multicurrency();
		}

		if ( version_compare( $current_version, '4.0.10', '<' ) ) {
			delete_transient( 'wcpbc_products_onsale' );
		}
	}

	/**
	 * Plugin activation. Check if the product version has changed.
	 */
	public static function plugin_activate() {
		self::maybe_set_activation_redirect();
		self::deactivate_wc_payments_multicurrency();
	}

	/**
	 * See if we need to redirect after activation or not.
	 */
	private static function maybe_set_activation_redirect() {
		$wp_list_table = function_exists( '_get_list_table' ) ? _get_list_table( 'WP_Plugins_List_Table' ) : false;

		if ( false === self::get_install_version() &&
			is_callable( array( $wp_list_table, 'current_action' ) ) && in_array( $wp_list_table->current_action(), [ 'activate', 'activate-plugin' ], true ) &&
			! is_network_admin() &&
			function_exists( 'WC' )
		) {
			// Set the redirect cookie.
			setcookie( '_wcpbc_activation_redirect', '1' );
		}
	}

	/**
	 * Display plugin upgrade notice.
	 *
	 * @param array $args An array of plugin metadata.
	 */
	public static function in_plugin_update_message( $args ) {

		$transient_name = 'wcpbc_upgrade_notice_' . $args['Version'];
		$upgrade_notice = get_transient( $transient_name );

		if ( false === $upgrade_notice ) {
			$response = wp_safe_remote_get( 'https://plugins.svn.wordpress.org/woocommerce-product-price-based-on-countries/trunk/readme.txt' );

			if ( ! is_wp_error( $response ) && ! empty( $response['body'] ) ) {
				$upgrade_notice = self::parse_update_notice( $response['body'] );
				set_transient( $transient_name, $upgrade_notice, DAY_IN_SECONDS );
			}
		}

		echo wp_kses_post( $upgrade_notice );
	}

	/**
	 * Parse update notice from readme file.
	 *
	 * @param  string $content Readme content.
	 * @return string
	 */
	private static function parse_update_notice( $content ) {
		// Output Upgrade Notice.
		$matches        = null;
		$regexp         = '~==\s*Upgrade Notice\s*==\s*=\s*(.*)\s*=(.*)(=\s*' . preg_quote( WCPBC()->version ) . '\s*=|$)~Uis';
		$upgrade_notice = '';

		if ( preg_match( $regexp, $content, $matches ) ) {
			$version = trim( $matches[1] );
			$notices = (array) preg_split( '~[\r\n]+~', trim( $matches[2] ) );

			if ( version_compare( WCPBC()->version, $version, '<' ) ) {

				$upgrade_notice .= '<span class="wc_plugin_upgrade_notice wc_pbc_upgrade_notice">';

				foreach ( $notices as $index => $line ) {
					$upgrade_notice .= wp_kses_post( preg_replace( '~\[([^\]]*)\]\(([^\)]*)\)~', '<a href="${2}">${1}</a>', $line ) );
				}

				$upgrade_notice .= '</span> ';
			}
		}

		return $upgrade_notice;
	}

	/**
	 * Create default options.
	 *
	 * @since 2.3.0
	 * @version 3.1.0 Add the install timestamp.
	 */
	private static function create_options() {
		// Price based to tax based. Default shipping.
		$price_based_on = 'shipping';
		if ( wc_tax_enabled() && in_array( get_option( 'woocommerce_tax_based_on' ), array( 'billing', 'shipping' ), true ) ) {
			$price_based_on = get_option( 'woocommerce_tax_based_on' );
		}
		update_option( 'wc_price_based_country_based_on', $price_based_on );

		// Enable shipping currency conversion.
		update_option( 'wc_price_based_country_shipping_exchange_rate', 'yes' );

		// Init pricing zones.
		update_option( 'wc_price_based_country_regions', [] );

		// Set installed at.
		WCPBC_Helper_Options::update( 'install_timestamp', current_time( 'timestamp' ) );
	}

	/**
	 * Default Google Listing and Ads option.
	 *
	 * @since 2.3.0
	 */
	private static function set_gla_integration_option() {
		$value = array(
			'mode'    => 'gla_country',
			'country' => '',
		);

		if ( class_exists( 'WCPBC_Google_Listing_And_Ads' ) ) {
			$base_location = wc_get_base_location();

			if ( WCPBC_Google_Listing_And_Ads::get_gla_target_country() !== $base_location['country'] ) {
				$value = array(
					'mode'    => 'specific',
					'country' => $base_location['country'],
				);
			}
		}

		delete_option( 'wc_price_based_country_gla_integration' );
		add_option( 'wc_price_based_country_gla_integration', $value );
	}

	/**
	 * Deactivate WooCommerce Payments multi-currency feature.
	 *
	 * @since 2.3.0
	 */
	private static function deactivate_wc_payments_multicurrency() {
		if ( ! class_exists( 'class-wcpbc-wc-payments' ) ) {
			include_once dirname( __FILE__ ) . '/integrations/class-wcpbc-wc-payments.php';
		}

		WCPBC_WC_Payments::deactivate_multicurrency_feature();
	}
}
