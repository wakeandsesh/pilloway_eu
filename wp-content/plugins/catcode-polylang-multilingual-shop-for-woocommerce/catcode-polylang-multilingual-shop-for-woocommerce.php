<?php
/**
 * Plugin Name: Polylang Multilingual Shop for WooCommerce
 * Plugin URI: https://catcode.com.ua/modules/catcode-polylang-multilingual-shop-for-woocommerce/
 * Update URI: https://catcode.com.ua/modules/catcode-polylang-multilingual-shop-for-woocommerce/
 * Description: Runs a multilingual WooCommerce store on the free Polylang: translatable products, variations, categories, tags and attributes, one-click product translation, shared price / stock / SKU across languages, shop, cart, checkout and account pages per language, the cart follows the language switch, the order keeps its language and customer e-mails go out in it. Classic and block checkout, HPOS compatible.
 * Version: 1.0.2
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: CatCode
 * Author URI: https://catcode.com.ua
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: catcode-polylang-multilingual-shop-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 11.1
 *
 * @package CatCodePolylangShop
 */

defined( 'ABSPATH' ) || exit;

/*
 * This build is sold on catcode.com.ua. The free copy on wordpress.org has its own slug
 * (catcode-multilingual-shop-for-woocommerce-and-polylang); should a copy with this slug ever
 * appear there, the Update URI header keeps WordPress from offering it as an "update" and the
 * filter below drops such an offer if it still arrives. The wordpress.org build carries neither.
 */
add_filter(
	'site_transient_update_plugins',
	static function ( $value ) {
		$file = plugin_basename( __FILE__ );
		if ( is_object( $value ) && isset( $value->response[ $file ]->package ) && false !== strpos( (string) $value->response[ $file ]->package, 'downloads.wordpress.org' ) ) {
			unset( $value->response[ $file ] );
		}
		return $value;
	}
);


/*
 * The free copy from wordpress.org (catcode-multilingual-shop-for-woocommerce-and-polylang) steps aside
 * by itself whenever this build is active, so it is loaded first only during the request that activates
 * this build next to it. This build then sits that one request out instead of redeclaring its classes;
 * from the next request on only this build runs, on the same settings and data. A second copy of this
 * build unpacked into another folder sits out the same way, and the owner is told which one to remove.
 */
if ( defined( 'CATCODE_PLMS_FILE' ) && __FILE__ !== CATCODE_PLMS_FILE ) {
	$catcode_plms_other = basename( dirname( __FILE__ ) );
	add_action(
		'admin_notices',
		static function () use ( $catcode_plms_other ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-warning"><p>' . esc_html(
				sprintf(
					/* translators: 1: folder of the copy in use, 2: folder of the copy that is paused. */
					__( 'Two copies of Polylang Multilingual Shop are active. The copy in "%1$s" is used; the copy in "%2$s" is paused — deactivate and delete the one you do not need.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
					basename( dirname( CATCODE_PLMS_FILE ) ),
					$catcode_plms_other
				)
			) . '</p></div>';
		}
	);
	// Declared while paused too, or WooCommerce lists this build as incompatible.
	add_action(
		'before_woocommerce_init',
		static function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
			}
		}
	);
	unset( $catcode_plms_other );
	return;
}

defined( 'CATCODE_PLMS_VERSION' ) || define( 'CATCODE_PLMS_VERSION', '1.0.2' );
defined( 'CATCODE_PLMS_FILE' ) || define( 'CATCODE_PLMS_FILE', __FILE__ );
defined( 'CATCODE_PLMS_DIR' ) || define( 'CATCODE_PLMS_DIR', plugin_dir_path( __FILE__ ) );
defined( 'CATCODE_PLMS_URL' ) || define( 'CATCODE_PLMS_URL', plugin_dir_url( __FILE__ ) );
defined( 'CATCODE_PLMS_BASENAME' ) || define( 'CATCODE_PLMS_BASENAME', plugin_basename( __FILE__ ) );

foreach (
	array(
		'includes/Core/Settings.php',
		'includes/Pro/License.php',
		'includes/Core/Lang.php',
		'includes/Core/Registry.php',
		'includes/Core/Environment.php',
		'includes/Product/Terms.php',
		'includes/Product/Fields.php',
		'includes/Product/Variations.php',
		'includes/Product/Translator.php',
		'includes/Product/Sync.php',
		'includes/Product/Stock.php',
		'includes/Product/Sku.php',
		'includes/Front/Pages.php',
		'includes/Front/Cart.php',
		'includes/Front/Strings.php',
		'includes/Front/ScriptTranslations.php',
		'includes/Order/OrderLanguage.php',
		'includes/Order/Emails.php',
		'includes/Order/Coupons.php',
		'includes/Pro/Texts.php',
		'includes/Pro/Csv.php',
		'includes/Pro/Bulk.php',
		'includes/Admin/ProductAdmin.php',
		'includes/Admin/PagesTool.php',
		'includes/Admin/SettingsPage.php',
		'includes/Admin/Ajax.php',
		'includes/Core/Plugin.php',
	) as $catcode_plms_inc
) {
	require_once CATCODE_PLMS_DIR . $catcode_plms_inc;
}
unset( $catcode_plms_inc );

// Updates of this Pro build come from catcode.com.ua (see the Update URI header):
// Dashboard → Updates shows new versions, and the zip is handed out to a licence
// that covers updates and is activated on this site.
require_once CATCODE_PLMS_DIR . 'includes/Pro/class-ccplms-updater.php';
CCPLMS_Updater::init(
	array(
		'file' => __FILE__,
		'slug' => \CatCode\PolylangShop\Pro\License::EXPECTED_SLUG,
		'key'  => static function () {
			return \CatCode\PolylangShop\Pro\License::key();
		},
	)
);

/*
 * Polylang reads the list of translatable post types and taxonomies very early and caches it,
 * so the registry filters go in right away, before any plugins_loaded callback.
 */
\CatCode\PolylangShop\Core\Registry::register();

register_deactivation_hook(
	__FILE__,
	static function () {
		$next = wp_next_scheduled( 'catcode_plms_license_check' );
		if ( $next ) {
			wp_unschedule_event( $next, 'catcode_plms_license_check' );
		}
	}
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			// The order language is stored as order meta through the CRUD layer only.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		\CatCode\PolylangShop\Core\Plugin::instance()->boot();
	},
	20
);
