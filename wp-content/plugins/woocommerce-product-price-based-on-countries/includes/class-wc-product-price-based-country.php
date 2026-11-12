<?php
/**
 * WooCommerce Price Based on Country main class
 *
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Product_Price_Based_Country Class
 */
class WC_Product_Price_Based_Country {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '4.3.6';

	/**
	 * The front-end pricing zone
	 *
	 * @var WCPBC_Pricing_Zone
	 */
	public $current_zone = null;

	/**
	 * Min WC required version.
	 *
	 * @var string
	 */
	protected $min_wc_version = '4.0';

	/**
	 * Min Pro required version.
	 *
	 * @var string
	 */
	protected $min_pro_version = '4.1.0';

	/**
	 * Enviroment alert
	 *
	 * @var string
	 */
	protected $environment_alert = '';

	/**
	 * The single instance of the class.
	 *
	 * @var WC_Product_Price_Based_Country
	 */
	private static $instance = false;

	/**
	 * Main WC_Product_Price_Based_Country Instance
	 *
	 * @static
	 * @see WCPBC()
	 * @return WC_Product_Price_Based_Country
	 */
	public static function instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url() {
		return plugin_dir_url( WCPBC_PLUGIN_FILE );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path() {
		return plugin_dir_path( WCPBC_PLUGIN_FILE );
	}

	/**
	 * Return the plugin base name
	 *
	 * @return string
	 * @since 1.7.4
	 */
	public function plugin_basename() {
		return plugin_basename( WCPBC_PLUGIN_FILE );
	}

	/**
	 * Return the plugin base name
	 *
	 * @return string
	 * @since 3.0.2
	 */
	public function is_pro_compatible() {
		if ( wcpbc_is_pro() ) {
			return isset( WC_Product_Price_Based_Country_Pro::$version ) && version_compare( WC_Product_Price_Based_Country_Pro::$version, $this->min_pro_version, '>=' );
		}
		return true;
	}

	/**
	 * WC_Product_Price_Based_Country Constructor.
	 */
	public function __construct() {
		$this->includes();

		spl_autoload_register( array( $this, 'autoload' ) );

		register_activation_hook( WCPBC_PLUGIN_FILE, array( 'WCPBC_Install', 'plugin_activate' ) );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WCPBC_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ), 5 );
		add_action( 'plugins_loaded', array( 'WCPBC_Integrations', 'add' ), 15 );
	}

	/**
	 * Include required files used in admin and on the frontend.
	 */
	private function includes() {

		include_once $this->plugin_path() . 'includes/wcpbc-core-functions.php';

		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			include_once $this->plugin_path() . 'includes/class-wcpbc-install.php';
		}
	}

	/**
	 * Auto-load plugin classes on demand to reduce memory consumption.
	 *
	 * @param string $classname Class name.
	 */
	public function autoload( $classname ) {

		if ( substr( $classname, 0, 6 ) !== 'WCPBC_' ) {
			return;
		}

		$path = false;

		$class_map = [
			'WCPBC_Helper_Options'            => 'class-wcpbc-helper-options.php',
			'WCPBC_Pricing_Zone'              => 'class-wcpbc-pricing-zone.php',
			'WCPBC_Pricing_Zones'             => 'class-wcpbc-pricing-zones.php',
			'WCPBC_Integrations'              => 'class-wcpbc-integrations.php',
			'WCPBC_Frontend'                  => 'class-wcpbc-frontend.php',
			'WCPBC_Frontend_Pricing'          => 'class-wcpbc-frontend-pricing.php',
			'WCPBC_Ajax_Geolocation'          => 'class-wcpbc-ajax-geolocation.php',
			'WCPBC_Update_GeoIP_DB'           => 'class-wcpbc-update-geoip-db.php',
			'WCPBC_Product_Sync'              => 'class-wcpbc-product-sync.php',
			'WCPBC_Debug_Logger'              => 'class-wcpbc-debug-logger.php',
			'WCPBC_Store_API'                 => 'class-wcpbc-store-api.php',
			'WCPBC_Country_Flags'             => 'class-wcpbc-country-flags.php',
			'WCPBC_Blocks_Controller'         => 'class-wcpbc-blocks-controller.php',
			'WCPBC_Runtime_Meta'              => 'class-wcpbc-runtime-meta.php',
			'WCPBC_Schedule_Recurring_Action' => 'class-wcpbc-schedule-recurring-action.php',
			'WCPBC_Admin_Analytics'           => 'admin/class-wcpbc-admin-analytics.php',
			'WCPBC_Admin_Notices'             => 'admin/class-wcpbc-admin-notices.php',
			'WCPBC_Admin'                     => 'admin/class-wcpbc-admin.php',
			'WCPBC_Admin_Meta_Boxes'          => 'admin/class-wcpbc-admin-meta-boxes.php',
			'WCPBC_Admin_Ads'                 => 'admin/class-wcpbc-admin-ads.php',
			'WCPBC_Setup_Wizard'              => 'admin/class-wcpbc-setup-wizard.php',
			'WCPBC_Cache_Plugins_Helper'      => 'admin/class-wcpbc-cache-plugins-helper.php',
			'WCPBC_Admin_Legacy_Reports'      => 'admin/class-wcpbc-admin-legacy-reports.php',
		];

		if ( isset( $class_map[ $classname ] ) ) {

			$path = $class_map[ $classname ];

		} elseif ( substr( $classname, 0, 18 ) === 'WCPBC_Product_Meta' ) {
			$path = 'product-meta/';
			if ( substr( $classname, 18, 4 ) === '_Job' ) {
				$path .= 'jobs/';
			}

			$path .= 'class-' . str_replace( '_', '-', strtolower( $classname ) ) . '.php';

		} elseif ( substr( $classname, -6 ) === '_Block' ) {
			$path .= 'block-types/class-' . str_replace( '_', '-', strtolower( $classname ) ) . '.php';

		} elseif ( substr( $classname, -9 ) === 'Interface' ) {
			$path .= 'interfaces/' . str_replace( '_', '-', strtolower( $classname ) ) . '.php';

		} elseif ( substr( $classname, -5 ) === 'Trait' ) {
			$path .= 'traits/' . str_replace( '_', '-', strtolower( $classname ) ) . '.php';
		}

		if ( ! $path ) {
			return;
		}

		$fullpath = $this->plugin_path() . "includes/{$path}";

		if ( is_readable( $fullpath ) ) {
			include_once $fullpath;
		}
	}

	/**
	 * Localisation
	 *
	 * @since 1.6.3
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'woocommerce-product-price-based-on-countries', false, dirname( plugin_basename( WCPBC_PLUGIN_FILE ) ) . '/languages/' );
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @since 1.6.11
	 * @param mixed $links Plugin Action links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {

		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=price-based-country' ) . '" aria-label="' . esc_attr__( 'View Price Based on Country settings', 'woocommerce-product-price-based-on-countries' ) . '">' . esc_html__( 'Settings', 'woocommerce-product-price-based-on-countries' ) . '</a>',
		);

		if ( ! wcpbc_is_pro() ) {
			$action_links['get-pro'] = sprintf( '<a target="_blank" rel="noopener noreferrer" style="color:#46b450;" href="%s">%s</a>', wcpbc_home_url( 'plugins-cta-link' ), esc_html__( 'Get Pro', 'woocommerce-product-price-based-on-countries' ) );
		}

		return array_merge( $action_links, $links );
	}

	/**
	 * Show row meta on the plugin screen.
	 *
	 * @since 1.7.0
	 * @param mixed $links Plugin Row Meta.
	 * @param mixed $file  Plugin Base file.
	 * @return array
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( plugin_basename( WCPBC_PLUGIN_FILE ) === $file ) {
			$row_meta = array(
				'docs' => sprintf( '<a target="_blank" rel="noopener noreferrer" href="%s">%s</a>', esc_url( wcpbc_home_url( 'plugins-doc-link', 'docs' ) ), esc_html__( 'Docs', 'woocommerce-product-price-based-on-countries' ) ),
			);

			return array_merge( $links, $row_meta );
		}

		return (array) $links;
	}

	/**
	 * After plugin row. Displays an alert if the Pro version is incompatible.
	 *
	 * @since 3.0.2
	 */
	public function after_plugin_row() {

		$wp_list_table = _get_list_table(
			'WP_Plugins_List_Table',
			array(
				'screen' => get_current_screen(),
			)
		);

		printf(
			'<tr class="plugin-update-tr active" id="price-based-country-pro-incompatible-pro">' .
			'<td colspan="%s" class="plugin-update colspanchange">' .
			'<div class="update-message notice inline notice-error notice-alt"><p>%s</p></div>' .
			'</td>' .
			'</tr>',
			esc_attr( $wp_list_table->get_column_count() ),
			wp_kses_post( $this->environment_alert )
		);

		if ( function_exists( 'wcpbc_enqueue_js' ) ) {
			wcpbc_enqueue_js(
				"jQuery('tr[data-plugin=\"woocommerce-product-price-based-on-countries/woocommerce-product-price-based-on-countries.php\"]').addClass('update');"
			);
		}
	}

	/**
	 * Checks the environment for compatibility problems.
	 *
	 * @return boolean
	 */
	private function check_environment() {
		if ( ! defined( 'WC_VERSION' ) ) {
			// translators: HTML Tags.
			$this->environment_alert = sprintf( __( '%1$sPrice Based on Country%2$s requires WooCommerce to be activated to work. Learn how to install Price Based on Country in the %3$sGetting Started Guide%4$s.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>', '<a href="https://www.pricebasedcountry.com/docs/getting-started/">', '</a>' );
			return false;
		}

		if ( version_compare( WC_VERSION, $this->min_wc_version, '<' ) ) {
			// translators: HTML Tags.
			$this->environment_alert = sprintf( __( 'The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-product-price-based-on-countries' ), $this->min_wc_version, WC_VERSION );
			return false;
		}

		if ( ! $this->is_pro_compatible() ) {

			$this->environment_alert = sprintf(
				// translators: HTML Tags.
				__( '%1$sYou use a deprecated version of %2$sPrice Based on Country PRO%3$s, which is incompatible with your current "Price Based on Country (Basic or Free)" version. The plugin features have been deactivated to prevent issues and fatal errors on your site.%6$sUpdate %2$sPrice Based on Country PRO%3$s to the latest version.%4$s%5$s', 'woocommerce-product-price-based-on-countries' ),
				'<span class="wc-price-based-country-deprecated-pro-notice">',
				'<strong>',
				'</strong>',
				sprintf(
					'<span class="wc-price-based-country-check-status"><a href="%s" class="button">%s</a></span>',
					esc_url( admin_url( 'admin.php?page=wc-settings&tab=price-based-country&section=license' ) ),
					esc_html__( 'Check license status', 'woocommerce-product-price-based-on-countries' )
				),
				'</span>',
				'<br>'
			);

			add_action( 'action_scheduler_init', [ $this, 'schedule_notify_admin_deprecated_pro' ] );

			if ( class_exists( 'WCPBC_Admin' ) ) {
				// Admin init to allow users set the license.
				WCPBC_Admin::init();

				// Displays warning message after plugin row.
				add_action( 'after_plugin_row_woocommerce-product-price-based-on-countries/woocommerce-product-price-based-on-countries.php', array( $this, 'after_plugin_row' ), 20 );
			}
			return false;
		}

		return true;
	}

	/**
	 * Init plugin
	 *
	 * @since 1.8.0
	 */
	public function init_plugin() {
		if ( ! $this->check_environment() ) {
			add_action( 'admin_notices', array( $this, 'environment_notice' ) );
			return;
		}

		if ( class_exists( 'WCPBC_Install' ) ) {
			// Admin request.
			WCPBC_Install::init();
			WCPBC_Admin::init();
			WCPBC_Admin_Meta_Boxes::init();
			WCPBC_Admin_Notices::init();
			WCPBC_Admin_Ads::init();
			WCPBC_Setup_Wizard::init();
			WCPBC_Cache_Plugins_Helper::init();
		}

		WCPBC_Debug_Logger::init();
		WCPBC_Frontend::init();
		WCPBC_Ajax_Geolocation::init();
		WCPBC_Product_Meta_Data::init();
		WCPBC_Admin_Analytics::init();
		WCPBC_Integrations::init();
		WCPBC_Blocks_Controller::init();
		WCPBC_Runtime_Meta::init();

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.4', '>=' ) && version_compare( WC_VERSION, '3.9', '<' ) ) {
			WCPBC_Update_GeoIP_DB::init();
		}

		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
		add_action( 'woocommerce_init', array( $this, 'frontend_init' ), 0 );
		add_action( 'init', array( $this, 'ajax_frontend_init' ), 9999 );

		WCPBC_Schedule_Recurring_Action::add( 'wc_price_based_country_scheduled_sales', 'tomorrow 00:00:01' );
	}

	/**
	 * Display the environment alert
	 */
	public function environment_notice() {
		echo '<div id="message" class="error">' . sprintf( '<p><strong>%1$s</strong></p>%2$s', 'Price Based on Country - ' . esc_html__( 'Heads up!', 'woocommerce-product-price-based-on-countries' ), wp_kses_post( wpautop( $this->environment_alert ) ) ) . '</div>';
	}

	/**
	 * Register Widgets
	 *
	 * @since 1.5.0
	 */
	public function register_widgets() {
		if ( class_exists( 'WC_Widget' ) ) {
			include_once $this->plugin_path() . 'includes/class-wcpbc-widget-country-selector.php';
			register_widget( 'WCPBC_Widget_Country_Selector' );
		}
	}

	/**
	 * Init front-end
	 */
	public function frontend_init() {

		if ( $this->is_rest_api_frontend() ) {
			WCPBC_Store_API::init();
		}

		if ( ! $this->is_frontend() || apply_filters( 'wc_price_based_country_stop_pricing', false ) ) {
			// Do only on frontend requests.
			return;
		}

		$this->frontend_pricing_init();
	}

	/**
	 * Frontend pricing init.
	 *
	 * @since 3.0.0
	 */
	public function frontend_pricing_init() {
		if ( did_action( 'wc_price_based_country_before_frontend_init' ) || doing_action( 'wc_price_based_country_before_frontend_init' ) ) {
			// do only once.
			return;
		}

		if ( ! did_action( 'before_woocommerce_init' ) || doing_action( 'before_woocommerce_init' ) ) {
			/* translators: 1: frontend_pricing_init 2: woocommerce_init */
			wc_doing_it_wrong( __FUNCTION__, sprintf( __( '%1$s should not be called before the %2$s action.', 'woocommerce-product-price-based-on-countries' ), 'WC_Product_Price_Based_Country::frontend_pricing_init', 'woocommerce_init' ), '3.0' );
			return;
		}

		do_action( 'wc_price_based_country_before_frontend_init' );

		// Set the current zone.
		$this->current_zone = wcpbc_get_zone_by_country();

		// Init frontend pricing.
		WCPBC_Frontend_Pricing::init();

		do_action( 'wc_price_based_country_frontend_init' );
	}

	/**
	 * Init frontend on AJAX requests. Improve compatibility with plugins which adds the "wp_ajax_nopriv_..." action on the 'init' hook.
	 */
	public function ajax_frontend_init() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && ! did_action( 'wc_price_based_country_frontend_init' ) ) {
			$this->frontend_init();
		}
	}

	/**
	 * Is a frontend request?.
	 *
	 * @return bool
	 */
	private function is_frontend() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}
		return wcpbc_is_woocommerce_frontend() && ! defined( 'DOING_CRON' ) && ! $this->is_rest_api_for_jetpack() && ( ! is_admin() || $this->is_ajax_frontend() );
	}

	/**
	 * Check the referer type.
	 *
	 * @since 2.0.10
	 * @param string $type admin or frontend.
	 */
	private function is_referer_type( $type ) {
		$type         = is_array( $type ) ? $type : array( $type );
		$referer      = false;
		$http_referer = wp_get_referer();

		if ( $http_referer ) {
			$http_referer = str_replace( array( 'https://', 'http://', 'www.' ), '', trailingslashit( $http_referer ) );
			$admin_url    = str_replace( array( 'https://', 'http://', 'www.' ), '', trailingslashit( admin_url( '/' ) ) );
			$referer      = ( false !== strpos( $http_referer, $admin_url ) ) ? 'admin' : 'frontend';
		}

		return in_array( $referer, $type, true );
	}

	/**
	 * Is AJAX frontend request?.
	 */
	private function is_ajax_frontend() {
		$action = defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['action'] ) ? wc_clean( wp_unslash( $_REQUEST['action'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $action ) {
			return false;
		}

		if ( in_array( $action, array( 'as_async_request_queue_runner', 'wc_facebook_background_product_sync', 'wc_facebook_sync_products' ), true ) ) {
			return false;
		}

		$is_ajax_frontend = false;

		if ( $this->is_referer_type( false ) ) {
			// Check the ajax no priv action.
			$is_ajax_frontend = has_action( 'wp_ajax_nopriv_' . $action );
		} else {
			$is_ajax_frontend = $this->is_referer_type( 'frontend' );
		}

		return apply_filters( 'wc_price_based_country_is_ajax_frontend', $is_ajax_frontend, $action );
	}

	/**
	 * Is Rest API frontend request?.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	private function is_rest_api_frontend() {

		if ( $this->is_referer_type( 'admin' ) || empty( $_SERVER['REQUEST_URI'] ) || empty( $_SERVER['HTTP_HOST'] ) ) {
			return false;
		}

		$request  = trailingslashit( str_replace( [ 'https://', 'http://', 'www.' ], '', wc_clean( wp_unslash( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) ) ) );
		$rest_url = preg_quote( trailingslashit( str_replace( [ 'https://', 'http://', 'www.' ], '', get_rest_url() ) ), '/' );
		$pattern  = '/^' . $rest_url . '/';

		if ( 1 !== preg_match( $pattern, $request ) ) {
			return false;
		}

		if ( 1 === preg_match( $pattern, $request ) && $this->is_referer_type( 'frontend' ) ) {
			return true;
		}

		// Check the known API REST routes if no HTTP referer.
		$rest_api_routes = [
			'wc\/store\/',
			'elementor\-pro\/v\d+\/refresh\-loop',
			'woo\-variation\-swatches',
			'wholesale\/v\d+\/products',
		];

		$pattern = '/^' . $rest_url . '(' . implode( '|', $rest_api_routes ) . ')/';

		if ( 1 === preg_match( $pattern, $request ) ) {
			return true;
		}

		/**
		 * Allow third-party frontend API REST routes.
		 */
		$rest_api_routes = apply_filters( 'wc_price_based_country_frontend_rest_routes', [] );
		$rest_api_routes = is_array( $rest_api_routes ) ? array_filter( $rest_api_routes, 'is_string' ) : [];

		if ( ! empty( $rest_api_routes ) ) {
			$pattern = '/^' . $rest_url . '(' . implode( '|', $rest_api_routes ) . ')/';

			return 1 === @preg_match( $pattern, $request ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		return false;
	}

	/**
	 * Is Rest API for Jetpack request?.
	 * Prevent issues with the WooCommerce app (android ios)
	 *
	 * @since 2.0.22
	 * @return bool
	 */
	private function is_rest_api_for_jetpack() {
		return isset( $_GET['rest_route'], $_GET['_for'] ) && 'jetpack' === $_GET['_for'] && '/wc/' === substr( wc_clean( wp_unslash( $_GET['rest_route'] ) ), 0, 4 ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Schedule an email to the site admin when deprecated PRO version.
	 *
	 * @since 4.0
	 */
	public function schedule_notify_admin_deprecated_pro() {
		$admin_email_hook  = "wc_price_based_country_notify_admin_deprecated_pro/{$this->min_pro_version}";
		$scheduled_actions = as_get_scheduled_actions(
			[
				'hook'     => $admin_email_hook,
				'per_page' => 1,
			],
			'ids'
		);
		if ( ! isset( $scheduled_actions[0] ) ) {
			as_schedule_single_action( strtotime( '+30 minutes' ), $admin_email_hook );
		}

		add_action( $admin_email_hook, [ $this, 'notify_admin_deprecated_pro' ] );
	}

	/**
	 * Email the site admin when deprecated PRO version.
	 *
	 * @since 4.0
	 */
	public function notify_admin_deprecated_pro() {

		if ( get_transient( 'wcpbc_notify_admin_deprecated_pro' ) === $this->min_pro_version ) {
			// Do only once.
			return;
		}

		if ( '' !== get_option( 'blogname' ) ) {
			$site_title = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		} else {
			$site_title = wp_parse_url( home_url(), PHP_URL_HOST );
		}

		$to      = get_option( 'admin_email' );
		$message = sprintf(
			// Translators: 1: Site URL 2: admin email.
			__(
				'Howdy!

You use a deprecated version of "Price Based on Country PRO", which is incompatible with your current "Price Based on Country (Basic or Free)" version.

The plugin features have been deactivated to prevent issues and fatal errors on your site. This means that "Price Based on Country" is not working on your site.

You must update "Price Based on Country PRO" to the latest version to enable the plugin features.

Visit %1$s to check the "Price Based on Country PRO" license status and plugin updates.

Alternatively, you can deactivate "Price Based on Country PRO" to enable the "Free" plugin features.

This automated email has been sent to %2$s',
				'woocommerce-product-price-based-on-countries'
			),
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=price-based-country&section=license' ) ),
			$to
		);
		// Translators: %s: Site title.
		$subject = sprintf( __( '[%s] "Price Based on Country" is inactive on your site.', 'woocommerce-product-price-based-on-countries' ), $site_title );

		wp_mail( $to, $subject, $message );

		set_transient( 'wcpbc_notify_admin_deprecated_pro', $this->min_pro_version, WEEK_IN_SECONDS );
	}
}
