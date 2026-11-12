<?php
/**
 * Order bulk actions handler for Nova Post WooCommerce plugin.
 *
 * @package NovaPostForWoocommerce
 * @subpackage Admin
 */

namespace NovaPostForWoocommerce\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles custom bulk actions for WooCommerce orders.
 */
class OrderBulkActions {
    /**
     * The bulk action key for creating pickup requests.
     */
    private const BULK_ACTION_PICKUP_REQUEST = 'npforwc_create_pickup_request';

    /**
     * The bulk action key for creating shipments.
     */
    private const BULK_ACTION_CREATE_SHIPMENTS = 'npforwc_create_shipments';

    /**
     * The bulk action key for printing markings (100x100).
     */
    private const BULK_ACTION_PRINT_MARKINGS = 'npforwc_print_markings';

    /**
     * The bulk action key for printing commercial invoice.
     */
    private const BULK_ACTION_PRINT_INVOICE = 'npforwc_print_invoice';

    /**
     * The bulk action key for printing customs declaration.
     */
    private const BULK_ACTION_PRINT_CUSTOMS = 'npforwc_print_customs';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize the class and register hooks.
     */
    public function init(): void {
        add_filter( 'bulk_actions-edit-shop_order', array( $this, 'register_bulk_actions' ) );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'register_bulk_actions' ) );

        add_action(
            'admin_footer',
            function () {
                $screen = get_current_screen();
                if ( $screen && 'woocommerce_page_wc-orders' === $screen->id ) {
                    echo '<div id="nova-post-order-page-app-root"></div>';
                }
            }
        );

        add_action( 'admin_notices', array( $this, 'display_pickup_success_notice' ) );
    }

    /**
     * Register custom bulk actions.
     *
     * @param array $actions Existing bulk actions.
     * @return array Modified bulk actions.
     */
    public function register_bulk_actions( array $actions ): array {
        $actions[ self::BULK_ACTION_CREATE_SHIPMENTS ] = __( 'Nova Post: Create Shipments', 'nova-post-for-woocommerce' );
        $actions[ self::BULK_ACTION_PICKUP_REQUEST ]   = __( 'Nova Post: Create Pickup', 'nova-post-for-woocommerce' );
        $actions[ self::BULK_ACTION_PRINT_MARKINGS ]   = __( 'Nova Post: Print Markings (100х100)', 'nova-post-for-woocommerce' );
        $actions[ self::BULK_ACTION_PRINT_INVOICE ]    = __( 'Nova Post: Print Commercial Invoice', 'nova-post-for-woocommerce' );
        $actions[ self::BULK_ACTION_PRINT_CUSTOMS ]    = __( 'Nova Post: Print Customs Declaration', 'nova-post-for-woocommerce' );
        return $actions;
    }

    /**
     * Display a success notice after adding shipments to a pickup.
     *
     * @return void
     */
    public function display_pickup_success_notice(): void {
        if ( get_transient( 'np_pickup_shipments_added' ) ) {
            $count = (int) get_transient( 'np_pickup_shipments_added' );
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php
                    // translators: %d is the number of shipments.
                    echo esc_html( sprintf( _n( '%d shipment added to pickup', '%d shipments added to pickup', $count, 'nova-post-for-woocommerce' ), $count ) );
                    ?>
                </p>
            </div>
            <?php
            delete_transient( 'np_pickup_shipments_added' );
        }
    }

    /**
     * Set a transient to show a success notice.
     *
     * @param int $count The number of shipments added.
     * @return void
     */
    public static function add_pickup_success_notice( int $count ): void {
        set_transient( 'np_pickup_shipments_added', $count, MINUTE_IN_SECONDS );
    }
}
