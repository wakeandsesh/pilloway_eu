<?php
/**
 * Plugin bootstrapper hooks for Nova Post WooCommerce.
 *
 * @package NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Plugin;

use NovaPostForWoocommerce\Database\Migrations\PickupSchemaMigration;
use NovaPostForWoocommerce\Logger\Logger;
use NovaPostForWoocommerce\Models\Traits\TrackEvent;
use NovaPostForWoocommerce\Services\WebhookService;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Nova Post WooCommerce – plugin bootstrap hooks and wiring.
 *
 * Handles activation tasks and initializes the main plugin instance after
 * WooCommerce has loaded.
 *
 * @package NovaPostForWoocommerce
 */
class NovaPostForWoocommercePlugin {

    use TrackEvent;

    /**
     * Constructor.
     *
     * Registers activation hook and initializes the plugin on `plugins_loaded`.
     *
     * @return void
     */
    public function __construct() {
        register_activation_hook( NPFORWC_PLUGIN_FILE_PATH, array( $this, 'npforwc_woocommerce_activate' ) );
        register_deactivation_hook( NPFORWC_PLUGIN_FILE_PATH, array( $this, 'npforwc_woocommerce_deactivate' ) );
        add_action( 'plugins_loaded', array( $this, 'npforwc_woocommerce_init' ), 10 );
    }

    /**
     * Render an admin notice when WooCommerce is missing.
     *
     * @return void
     */
    public function npforwc_woocommerce_missing_wc_notice() {
        echo '<div class="error"><p><strong>' . sprintf(
            // translators: %s: HTML link to woocommerce.com.
            esc_html__( 'Nova Post Woocommerce requires WooCommerce to be installed and active. You can download %s here.', 'nova-post-for-woocommerce' ),
            '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
        ) . '</strong></p></div>';
    }

    /**
     * Activation callback.
     *
     * Ensures WooCommerce is active and performs one-time setup tasks.
     *
     * @return void
     */
    public function npforwc_woocommerce_activate() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'npforwc_woocommerce_missing_wc_notice' ) );
            return;
        }

        $this->make_required_checkout_phone_field();

        PickupSchemaMigration::maybe_update();
        try {
            WebhookService::subscribe();
        } catch ( Throwable $e ) {
            Logger::write_log(
                'error',
                'Error with subscribing from webhook.',
                array(
                    'error' => $e->getMessage(),
                )
            );
        }

        $this->track_plugin_activated();
    }

    /**
     * Deactivation callback.
     *
     * @return void
     */
    public function npforwc_woocommerce_deactivate() {
        $this->restore_checkout_phone_field();

        try {
            WebhookService::unsubscribe();
        } catch ( Throwable $e ) {
            Logger::write_log(
                'error',
                'Error with unsubscribing from webhook: .',
                array(
                    'error' => $e->getMessage(),
                )
            );
        }
    }

    /**
     * Make the checkout phone field required.
     *
     * @return void
     */
    public function make_required_checkout_phone_field() {
        $current_state = get_option( 'woocommerce_checkout_phone_field', 'required' );
        if ( 'required' !== $current_state ) {
            update_option( 'npforwc_original_phone_field_status', $current_state );
        }
        update_option( 'woocommerce_checkout_phone_field', 'required' );
    }

    /**
     * Restore the checkout phone field to its original state.
     *
     * @return void
     */
    public function restore_checkout_phone_field() {
        $original_state = get_option( 'npforwc_original_phone_field_status' );
        if ( $original_state ) {
            update_option( 'woocommerce_checkout_phone_field', $original_state );
            delete_option( 'npforwc_original_phone_field_status' );
        }
    }

    /**
     * Initialize the plugin after WooCommerce loads.
     *
     * @return void
     */
    public function npforwc_woocommerce_init() {

        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'npforwc_woocommerce_missing_wc_notice' ) );
            return;
        }

        PickupSchemaMigration::maybe_update();

        if ( class_exists( 'NovaPostForWoocommerceDev\\Plugin\\NovaPostForWoocommerceDebug' ) ) {
            \NovaPostForWoocommerceDev\Plugin\NovaPostForWoocommerceDebug::instance();
        }

        NovaPostForWoocommerce::instance();
    }
}
