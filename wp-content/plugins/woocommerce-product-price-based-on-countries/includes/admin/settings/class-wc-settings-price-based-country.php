<?php
/**
 * WooCommerce Price Based Country settings page
 *
 * @version 2.3.0
 * @package WCPBC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Settings_Price_Based_Country' ) ) :

	/**
	 * WC_Settings_Price_Based_Country Class
	 */
	class WC_Settings_Price_Based_Country extends WC_Settings_Page {

		/**
		 * Edit zone ID.
		 *
		 * @var string
		 */
		protected $edit_zone;

		/**
		 * Delete zone ID.
		 *
		 * @var string
		 */
		protected $delete_zone;

		/**
		 * Constructor.
		 */
		public function __construct() {

			$this->id          = 'price-based-country';
			$this->label       = __( 'Pricing zones', 'woocommerce-product-price-based-on-countries' );
			$this->edit_zone   = empty( $_GET['zone_id'] ) ? false : wc_clean( wp_unslash( $_GET['zone_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$this->delete_zone = empty( $_GET['delete_zone'] ) ? false : wc_clean( wp_unslash( $_GET['delete_zone'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

			parent::__construct();

			$this->backward_compatibility();

			add_action( 'load-woocommerce_page_wc-settings', array( $this, 'init' ), 5 );
		}

		/**
		 * Init hooks.
		 */
		public function init() {
			self::handle_actions();

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_filter( 'admin_body_class', array( $this, 'admin_body_class' ), 9999 );
		}

		/**
		 * Get sections
		 *
		 * @return array
		 */
		public function get_own_sections() {
			$sections = array(
				''        => __( 'Pricing zones', 'woocommerce-product-price-based-on-countries' ),
				'options' => __( 'Options', 'woocommerce-product-price-based-on-countries' ),
			);

			if ( wcpbc_is_pro() ) {
				$sections['license'] = __( 'License', 'woocommerce-product-price-based-on-countries' );
			} else {
				$sections['free-pro'] = __( 'Free vs Pro', 'woocommerce-product-price-based-on-countries' );
			}

			return $sections;
		}

		/**
		 * Checks the current tabs is price-based-country
		 *
		 * @return bool
		 */
		protected function is_me() {
			global $current_tab;
			return 'price-based-country' === $current_tab;
		}

		/**
		 * Checks the current section
		 *
		 * @param string $section String to check.
		 * @return bool
		 */
		protected function is_section( $section ) {
			global $current_section;

			if ( ! $this->is_me() ) {
				return false;
			}

			$is_section = false;

			if ( is_array( $section ) ) {
				foreach ( $section as $check ) {
					$is_section = $this->is_section( $check );
					if ( $is_section ) {
						break;
					}
				}
			} else {

				switch ( $section ) {
					case 'zones':
						$is_section = ( empty( $current_section ) || 'zones' === $current_section );
						break;
					case 'zone-list':
						$is_section = $this->is_section( 'zones' ) && ! $this->edit_zone;
						break;
					case 'zone-updated':
						$is_section = $this->is_section( 'edit-zone' ) && ! empty( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification
						break;
					case 'edit-zone':
						$is_section = $this->is_section( 'zones' ) && $this->edit_zone;
						break;
					case 'zone-deleted':
						$is_section = $this->is_section( 'zone-list' ) && ! empty( $_GET['deleted'] ); // phpcs:ignore WordPress.Security.NonceVerification
						break;
					case 'delete-zone':
						$is_section = $this->is_section( 'zone-list' ) && $this->delete_zone;
						break;
					case 'license':
						$is_section = 'license' === $current_section && wcpbc_is_pro();
						break;
					case 'free-pro':
						$is_section = 'free-pro' === $current_section && ! wcpbc_is_pro();
						break;
					default:
						$is_section = $section === $current_section;
				}
			}

			return $is_section;
		}

		/**
		 * Handle action.
		 */
		protected function handle_actions() {

			if ( $this->is_section( 'delete-zone' ) ) {
				// Delete zone.
				$this->delete_zone();

			} elseif ( $this->is_section( 'zone-deleted' ) ) {
				// Add message to inform deleted zone.
				WC_Admin_Settings::add_message( __( 'Zone have been deleted.', 'woocommerce-product-price-based-on-countries' ) );

			} elseif ( $this->is_section( 'zone-updated' ) ) {
				// Display "zone updated" notice.
				add_action( 'woocommerce_sections_' . $this->id, array( $this, 'update_zone_notice' ), 20 );
			}
		}

		/**
		 * Enqueue admin scripts and styles.
		 */
		public function enqueue_scripts() {
			if ( ! $this->is_me() ) {
				return;
			}

			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_register_script( 'wcpbc-tooltip-confirm', WCPBC()->plugin_url() . 'assets/js/admin/tooltip-confirm' . $suffix . '.js', array( 'jquery' ), WCPBC()->version, true );
			wp_register_style( 'wcpbc-tooltip-confirm-styles', WCPBC()->plugin_url() . 'assets/css/admin/tooltip-confirm' . $suffix . '.css', array(), WCPBC()->version );

			if ( $this->is_section( array( 'edit-zone', 'options' ) ) ) {

				// Edit settings screens.
				wp_enqueue_style( 'wcpbc-tooltip-confirm-styles' );
				wp_enqueue_style( 'wcpbc-settings-styles', WCPBC()->plugin_url() . 'assets/css/admin/settings' . $suffix . '.css', array(), WCPBC()->version );
				wp_enqueue_script( 'wcpbc-settings', WCPBC()->plugin_url() . 'assets/js/admin/settings' . $suffix . '.js', array( 'jquery', 'wcpbc-tooltip-confirm', 'woocommerce_admin' ), WCPBC()->version, true );
			}

			if ( $this->is_section( 'edit-zone' ) ) {

				// Edit zone settings screen.
				wp_register_script( 'wcpbc-settings-edit-zone', WCPBC()->plugin_url() . 'assets/js/admin/settings-edit-zone' . $suffix . '.js', array_merge( array( 'wcpbc-settings' ), ( wcpbc_is_pro() ? array( 'wcpbc-price-preview' ) : array() ) ), WCPBC()->version, true );
				wp_localize_script(
					'wcpbc-settings-edit-zone',
					'wcpbc_settings_edit_zone_params',
					array(
						'eur_countries'     => wcpbc_get_currencies_countries( 'EUR' ),
						'allowed_countries' => 'all' !== get_option( 'woocommerce_allowed_countries', 'all' ) ? array_keys( WC()->countries->get_allowed_countries() ) : false,
						'i18n'              => array(
							'and' => __( 'and', 'woocommerce-product-price-based-on-countries' ),
						),
					)
				);
				wp_enqueue_script( 'wcpbc-settings-edit-zone' );

				add_action( 'admin_footer', [ $this, 'print_script_templates' ] );

			} elseif ( $this->is_section( 'zone-list' ) ) {

				// Zones table.
				wp_enqueue_style( 'wcpbc-tooltip-confirm-styles' );
				wp_enqueue_style( 'wcpbc-settings-zone-list-styles', WCPBC()->plugin_url() . 'assets/css/admin/settings-zone-list' . $suffix . '.css', array(), WCPBC()->version );
				wp_enqueue_script( 'wcpbc-settings-zone-list', WCPBC()->plugin_url() . 'assets/js/admin/settings-zone-list' . $suffix . '.js', array( 'jquery', 'jquery-ui-sortable', 'utils', 'wcpbc-tooltip-confirm' ), WCPBC()->version, true );
				wp_localize_script(
					'wcpbc-settings-zone-list',
					'wcpbc_settings_zone_list_params',
					array(
						'i18n' => array(
							'reorder_helptip' => __( 'Drag and drop to re-order the pricing zones.', 'woocommerce-product-price-based-on-countries' ),
						),
					)
				);

			} elseif ( $this->is_section( 'free-pro' ) ) {

				// Free vs Pro section.
				wp_enqueue_style( 'wcpbc-free-pro-styles', WCPBC()->plugin_url() . 'assets/css/admin/free-vs-pro' . $suffix . '.css', array(), WCPBC()->version );

			} elseif ( $this->is_section( 'license' ) ) {

				// Free vs Pro section.
				wp_enqueue_style( 'wcpbc-license', WCPBC()->plugin_url() . 'assets/css/admin/license' . $suffix . '.css', array(), WCPBC()->version );
			}

			if ( ! wcpbc()->is_pro_compatible() && $this->is_section( array( 'zone-list', 'edit-zone', 'options' ) ) ) {
				add_action( 'admin_footer', array( $this, 'output_incompatible_pro_notice' ), 999 );
			}
		}

		/**
		 * Print script templates.
		 */
		public function print_script_templates() {
			if ( 'all' === get_option( 'woocommerce_allowed_countries', 'all' ) ) {
				return;
			}

			$count = 0;
			foreach ( [ 'singular', 'plural' ] as $class ) {
				$count++;
				?>
				<script type="text/html" id="tmpl-allowed-countries-warning-<?php echo esc_attr( $class ); ?>">
				<div class="notice notice-warning inline">
					<p>
						<?php
						printf(
							// translators: 1: List of countries. 2 Option name. 3 HTML tag.
							esc_html( _n( '%1$s is not included in the %2$s option%3$s.', '%1$s are not included in the %2$s option%3$s.', $count, 'woocommerce-product-price-based-on-countries' ) ),
							'{{data.countries}}',
							sprintf(
								'<strong>%s</strong>',
								esc_html__( 'Selling location(s)', 'woocommerce-product-price-based-on-countries' )
							),
							wc_help_tip( esc_html__( 'WooCommerce > Settings > General', 'woocommerce-product-price-based-on-countries' ) )
						);
						echo '&nbsp;';
						esc_html_e( 'The countries not included in this option are unavailable at checkout.', 'woocommerce-product-price-based-on-countries' );
						?>
					</p>
				</div>
				</script>
				<?php
			}
		}

		/**
		 * Admin body classes
		 *
		 * @param string $admin_body_class Body classes string.
		 */
		public function admin_body_class( $admin_body_class ) {
			if ( ! $this->is_me() ) {
				return $admin_body_class;
			}

			$classes = explode( ' ', trim( $admin_body_class ) );

			$classes[] = 'wc-price-based-country-settings-page';

			foreach ( [ 'zone-list', 'edit-zone', 'free-pro', 'license' ] as $section ) {
				if ( ! $this->is_section( $section ) ) {
					continue;
				}
				$classes[] = "wc-price-based-country-settings-{$section}";
			}

			$admin_body_class = implode( ' ', array_unique( $classes ) );
			return " $admin_body_class ";
		}

		/**
		 * Delete a zone
		 */
		protected function delete_zone() {

			check_admin_referer( 'wc-price-based-country-delete-zone' );

			$zone = WCPBC_Pricing_Zones::get_zone_by_id( $this->delete_zone );

			if ( ! $zone ) {
				wp_die( esc_html__( 'Zone does not exist!', 'woocommerce-product-price-based-on-countries' ) );
			}

			WCPBC_Pricing_Zones::delete( $zone );

			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=price-based-country&deleted=1' ) );
			exit;
		}

		/**
		 * Output the zone update notice
		 */
		public function update_zone_notice() {
			?>
			<div id="message" class="updated inline">
				<p><strong><?php esc_html_e( 'Zone updated successfully.', 'woocommerce-product-price-based-on-countries' ); ?></strong></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=price-based-country' ) ); ?>">&larr; <?php esc_html_e( 'Back to the zones list', 'woocommerce-product-price-based-on-countries' ); ?></a>
					<a style="margin-left:15px;" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=price-based-country&zone_id=new' ) ); ?>"><?php esc_html_e( 'Add a new zone', 'woocommerce-product-price-based-on-countries' ); ?></a>
				</p>
			</div>
			<?php
		}

		/**
		 * Get settings array for options section
		 *
		 * @see https://developer.woocommerce.com/2021/06/18/developer-advisory-settings-page-infrastructure-refactor/
		 * @return array
		 */
		public function get_settings_for_default_section() {

			$options  = array();
			$settings = include dirname( __FILE__ ) . '/wcpbc-settings-options.php';

			foreach ( $settings as $section ) {

				$fields = empty( $section['fields'] ) ? array() : $section['fields'];

				foreach ( $fields as $option ) {

					$add_pro_option = empty( $option['is_pro'] ) || ( ! empty( $option['is_pro'] ) && wcpbc_is_pro() );

					if ( isset( $option['id'] ) && 'wc_price_based_country_' === substr( $option['id'], 0, 23 ) && $add_pro_option ) {

						if ( ! empty( $option['name'] ) ) {
							$option['field_name'] = $option['name'];
						}

						$options[] = $option;
					}
				}
			}

			return $options;
		}

		/**
		 * Output the settings
		 */
		public function output() {

			if ( $this->is_section( 'edit-zone' ) ) {

				$this->output_edit_zone_screen();

			} elseif ( $this->is_section( 'zone-list' ) ) {

				$this->output_zones_list_table();

			} elseif ( $this->is_section( 'options' ) ) {

				$this->output_options_screen();

			} elseif ( $this->is_section( 'license' ) ) {
				try {

					self::output_license_screen();

				} catch ( Exception $e ) {
					WCPBC_Debug_Logger::log_error( $e->getMessage(), __METHOD__ );
					// Unsupported PRO version. Use the default page.
					WCPBC_License_Settings::output_fields();
				}
			} elseif ( $this->is_section( 'free-pro' ) ) {

				$GLOBALS['hide_save_button'] = true; // @codingStandardsIgnoreLine

				include dirname( __FILE__ ) . '/views/html-free-pro.php';
			}
		}

		/**
		 * Ouput the pricing zones table.
		 */
		protected function output_zones_list_table() {
			printf(
				'<h2 class="wc-table-list-header">%s <a href="%s" class="page-title-action">%s</a></h2>',
				esc_html( 'Pricing zones', 'woocommerce-product-price-based-on-countries' ),
				esc_url( admin_url( 'admin.php?page=wc-settings&tab=price-based-country&zone_id=new' ) ),
				esc_html( 'Add pricing zone', 'woocommerce-product-price-based-on-countries' )
			);
			?>
			<div class="pricingzones-table-heading">
				<p><?php esc_html_e( 'A Pricing Zone is a group of countries to which you sell your products at a different price and (or) currency.', 'woocommerce-product-price-based-on-countries' ); ?></p>
				<div class="pricingzones-tools">
					<a href="#" class="button" id="export-pricingzones">
						<?php esc_html_e( 'Export CSV', 'woocommerce-product-price-based-on-countries' ); ?>
					</a>
					<?php if ( wcpbc_is_pro() ) : ?>
						<a href="<?php echo esc_url( admin_url( '/admin.php?import=wcpbc_pricing_zone_csv' ) ); ?>" class="button">
							<?php esc_html_e( 'Import CSV', 'woocommerce-product-price-based-on-countries' ); ?>
						</a>
					<?php else : ?>
					<a href="#" class="button wcpbc-show-upgrade-pro-popup">
						<?php esc_html_e( 'Import CSV', 'woocommerce-product-price-based-on-countries' ); ?>
						<span class="wcpbc-upgrade-pro">PRO</span>
					</a>
					<?php endif; ?>
				</div>
			</div>
			<?php

			// Zone list table.
			include_once WCPBC()->plugin_path() . 'includes/admin/class-wcpbc-admin-zone-list-table.php';

			$table_list = new WCPBC_Admin_Zone_List_Table();
			$table_list->prepare_items();
			$table_list->views();
			$table_list->display();
		}


		/**
		 * Outputs of edit zone screen.
		 */
		protected function output_edit_zone_screen() {
			$GLOBALS['hide_save_button'] = true; // @codingStandardsIgnoreLine

			// Single zone screen.
			if ( 'new' === $this->edit_zone ) {
				$zone = WCPBC_Pricing_Zones::create();
			} else {
				$zone = WCPBC_Pricing_Zones::get_zone_by_id( $this->edit_zone );
			}

			if ( ! $zone ) {
				wp_die( esc_html__( 'Zone does not exist!', 'woocommerce-product-price-based-on-countries' ) );
			}

			require_once dirname( __FILE__ ) . '/class-wcpbc-output-settings.php';
			$settings = include dirname( __FILE__ ) . '/wcpbc-settings-zone.php';

			new WCPBC_Output_Settings( $settings );
		}

		/**
		 * Outputs options screen.
		 */
		protected function output_options_screen() {
			$GLOBALS['hide_save_button'] = true; // @codingStandardsIgnoreLine

			require_once dirname( __FILE__ ) . '/class-wcpbc-output-settings.php';
			$settings = include dirname( __FILE__ ) . '/wcpbc-settings-options.php';

			new WCPBC_Output_Settings( $settings );
		}

		/**
		 * Display the license settings page.
		 *
		 * @since 3.3.0
		 */
		protected function output_license_screen() {
			if ( ! is_callable( [ 'WCPBC_License_Settings', 'instance' ] ) ) {
				return;
			}

			$GLOBALS['hide_save_button'] = true; // @codingStandardsIgnoreLine

			WCPBC_License_Settings::instance()->check_license_status();

			$license = WCPBC_License_Settings::instance()->get_license_data();

			$license['key']          = WCPBC_License_Settings::instance()->get_license_key();
			$license['expired']      = 'active' !== $license['status'];
			$license['expiring']     = 'yes' === $license['renewal_period'];
			$license['is_connected'] = ! empty( $license['key'] ) && false === $license['expired'] && WCPBC_License_Settings::instance()->is_license_active();
			$license['expires']      = empty( $license['expires'] ) ? '' : date_i18n( wc_date_format(), strtotime( $license['expires'] ) );
			$license['actions']      = [];

			if ( empty( $license['key'] ) ) {

				$license['actions'][] = [
					'status'       => 'error',
					'icon'         => 'info',
					// Translators: 1,2: HTML tag.
					'message'      => sprintf( __( '%1$sActivate%2$s your license to enable the plugin updates and get support. If you do not have a license yet, you need to %1$spurchase%2$s one.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>' ),
					'button_label' => __( 'Purchase', 'woocommerce-product-price-based-on-countries' ),
					'button_url'   => wcpbc_home_url( 'license-page-empty' ),
				];

			} elseif ( 'active' !== $license['status'] ) {

				$action = [
					'status'       => 'error',
					'icon'         => 'info',
					// Translators: 1,2: HTML tag.
					'message'      => sprintf( __( 'To receive updates and support, you need to %1$spurchase%2$s a new license.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>' ),
					'button_label' => __( 'Purchase', 'woocommerce-product-price-based-on-countries' ),
					'button_url'   => wcpbc_home_url( 'license-page-expired' ),
				];

				if ( 'error' === $license['status'] ) {

					$action['message'] = __( 'An error occurred during the last request.', 'woocommerce-product-price-based-on-countries' );

					if ( ! empty( $license['error_message'] ) ) {
						$action['message'] .= ' ' . __( 'Please copy and paste this information in your ticket when contacting support:', 'woocommerce-product-price-based-on-countries' );
						$action['message'] .= sprintf( '<code class="license-error">%s</code>', $license['error_message'] );
					}

					$action['button_label'] = __( 'Get support', 'woocommerce-product-price-based-on-countries' );
					$action['button_url']   = wcpbc_home_url( 'license-page-error', 'support' );

				} elseif ( 'expired' === $license['status'] ) {
					// Translators: 1: license key. 1,2: HTML tag. 4: date.
					$action['message'] = sprintf( __( 'The license %1$s %2$sexpired%3$s on %4$s.', 'woocommerce-product-price-based-on-countries' ), '<code>...' . substr( $license['key'], -5 ) . '</code>', '<strong>', '</strong>', $license['expires'] )
										. ' ' . $action['message'];
				} else {
					// Translators: license key.
					$action['message'] = sprintf( __( 'The license %1$s %2$sis not valid or expired%3$s more than 3 months ago.', 'woocommerce-product-price-based-on-countries' ), '<code>...' . substr( $license['key'], -5 ) . '</code>', '<strong>', '</strong>' )
										. ' ' . $action['message'];
				}

				$license['actions'][] = $action;

			} elseif ( ! $license['is_connected'] ) {

				$license['actions'][] = [
					'status'       => 'error',
					'icon'         => 'info',
					// Translators: 1,2: HTML tag.
					'message'      => sprintf( __( '%1$sActivate%2$s your license to enable the plugin updates and get support.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>' ),
					'button_label' => '',
					'button_url'   => '',
				];
			} else {

				// Check for plugin updates.
				$plugins                = get_site_transient( 'update_plugins' );
				$plugin_slug            = defined( 'WCPBC_PRO_PLUGIN_FILE' ) ? plugin_basename( WCPBC_PRO_PLUGIN_FILE ) : false;
				$license['new_version'] = isset( $plugins->response[ $plugin_slug ]->new_version ) ? $plugins->response[ $plugin_slug ]->new_version : false;

				if ( $license['new_version'] && version_compare( WC_Product_Price_Based_Country_Pro::$version, $license['new_version'], '<' ) ) {

					$license['actions'][] = [
						'status'       => 'warning',
						'icon'         => 'update',
						// Translators: 1: version. 2,3: HTML tag.
						'message'      => sprintf( __( 'Version %1$s is %2$savailable%3$s.', 'woocommerce-product-price-based-on-countries' ), $license['new_version'], '<strong>', '</strong>' ),
						'button_label' => __( 'Update', 'woocommerce-product-price-based-on-countries' ),
						'button_url'   => current_user_can( 'update_plugins' ) ? wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $plugin_slug, 'upgrade-plugin_' . $plugin_slug ) : false,
					];
				} else {

					$last_update_check = false;

					if ( $plugins && $plugins->last_checked ) {
						$last_update_check = $plugins->last_checked + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
					}

					$license['actions'][] = [
						'status'  => 'success',
						'icon'    => 'info',
						// Translators: 1: version. 2,3: HTML tag.
						'message' => sprintf( __( 'Current version %1$s. Last checked on %2$s at %3$s.', 'woocommerce-product-price-based-on-countries' ), WC_Product_Price_Based_Country_Pro::$version, date_i18n( wc_date_format(), $last_update_check ), date_i18n( wc_time_format(), $last_update_check ) ),
					];
				}

				if ( $license['expiring'] ) {

					$license['actions'][] = [
						'status'       => 'warning',
						'icon'         => 'warning',
						// Translators: 1: version. 2,3: HTML tag.
						'message'      => sprintf( __( 'Your license will expire soon. %1$sRenew your license%2$s to continue receiving updates and support.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>' ),
						'button_label' => __( 'Renew now', 'woocommerce-product-price-based-on-countries' ),
						'button_url'   => add_query_arg(
							array(
								'utm_medium'   => 'banner',
								'utm_source'   => 'license-page',
								'utm_campaign' => 'Activate license',
							),
							empty( $license['renewal_url'] ) ? 'https://www.pricebasedcountry.com/pricing/' : $license['renewal_url']
						),
					];
				}

				if ( ! empty( $license['error_message'] ) ) {
					$action = [
						'status'       => 'error',
						'icon'         => 'info',
						// Translators: 1,2: HTML tag.
						'button_label' => __( 'Get support', 'woocommerce-product-price-based-on-countries' ),
						'button_url'   => wcpbc_home_url( 'license-page-error', 'support' ),
					];

					$action['message']  = __( 'An error occurred during the last request.', 'woocommerce-product-price-based-on-countries' );
					$action['message'] .= ' ' . __( 'Please copy and paste this information in your ticket when contacting support:', 'woocommerce-product-price-based-on-countries' );
					$action['message'] .= sprintf( '<code class="license-error">%s</code>', $license['error_message'] );

					$license['actions'][] = $action;
				}
			}

			include dirname( __FILE__ ) . '/views/html-license.php';
		}

		/**
		 * Disable the settings to prevent user to save options if the Pro version is incompatible.
		 */
		public function output_incompatible_pro_notice() {
			include dirname( __FILE__ ) . '/views/html-incompatible-pro-version.php';
		}

		/**
		 * Save settings
		 */
		public function save() {

			if ( $this->is_section( 'edit-zone' ) ) {

				$this->save_zone();

			} elseif ( $this->is_section( 'zone-list' ) ) {

				$this->save_zones_bulk();

			} elseif ( $this->is_section( 'license' ) ) {

				WCPBC_License_Settings::save_fields();

			} elseif ( $this->is_section( 'options' ) ) {
				// Save settings.
				$settings = $this->get_settings();
				WC_Admin_Settings::save_fields( $settings );

				// Update WooCommerce Default Customer Address.
				if ( 'geolocation_ajax' === get_option( 'woocommerce_default_customer_address' ) && 'yes' === get_option( 'wc_price_based_country_caching_support', 'no' ) ) {
					update_option( 'woocommerce_default_customer_address', 'geolocation' );
				}
			}
		}

		/**
		 * Save a zone from the $_POST array.
		 */
		protected function save_zone() {

			check_admin_referer( 'woocommerce-settings' );

			if ( 'new' === $this->edit_zone ) {
				$zone = WCPBC_Pricing_Zones::create();
			} else {
				$zone = WCPBC_Pricing_Zones::get_zone_by_id( $this->edit_zone );
			}

			if ( ! $zone ) {
				wp_die( esc_html__( 'Zone does not exist!', 'woocommerce-product-price-based-on-countries' ) );
			}

			$postdata = wp_unslash( $_POST );
			$errors   = WCPBC_Pricing_Zones::populate( $zone, $postdata );

			if ( ! is_wp_error( $errors ) ) {

				// Save the zone.
				$errors = $zone->save();
			}

			if ( is_wp_error( $errors ) ) {

				WC_Admin_Settings::add_error( $errors->get_error_message() );

			} else {

				do_action( 'wc_price_based_country_settings_zone_saved', $zone->get_id() );

				wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=price-based-country&zone_id=' . $zone->get_id() . '&updated=1' ) );
				exit;
			}
		}

		/**
		 * Save zones in bulk.
		 */
		protected function save_zones_bulk() {

			check_admin_referer( 'woocommerce-settings' );

			$postdata = wp_unslash( wc_clean( $_POST ) );

			if ( empty( $postdata['pricing_zone_order'] ) || ! is_array( $postdata['pricing_zone_order'] ) ) {
				return;
			}

			$changed = false;
			$zones   = WCPBC_Pricing_Zones::get_zones();

			foreach ( $postdata['pricing_zone_order'] as $order => $id ) {

				if ( ! is_string( $id ) || ! isset( $zones[ $id ] ) ) {
					continue;
				}

				if ( $order !== $zones[ $id ]->get_order() ) {
					$zones[ $id ]->set_order( $order );
					$changed = true;
				}

				if ( isset( $postdata['enabled'][ $order ] ) && wc_string_to_bool( $postdata['enabled'][ $order ] ) !== $zones[ $id ]->get_enabled() ) {
					$zones[ $id ]->set_enabled( $postdata['enabled'][ $order ] );
					$changed = true;
				}
			}

			if ( $changed ) {
				WCPBC_Pricing_Zones::bulk_save( $zones );
			}
		}

		/**
		 * Backward compatibility for WC version prior 5.5.
		 */
		protected function backward_compatibility() {
			if ( version_compare( WC_VERSION, '5.5', '<' ) ) {
				add_filter(
					'woocommerce_get_sections_' . $this->id,
					function( $sections ) {
						return $this->get_own_sections();
					}
				);

				add_filter(
					'woocommerce_get_settings_' . $this->id,
					function( $settings ) {
						if ( $this->is_section( 'options' ) ) {
							return $this->get_settings_for_default_section();
						}
						return $settings;
					}
				);
			}
		}
	}

endif;

return new WC_Settings_Price_Based_Country();
