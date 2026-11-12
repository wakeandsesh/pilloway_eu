<?php
/**
 * Handles database operations for WooCommerce shipping zone methods.
 *
 * @package NovaPostForWoocommerce\Database\Models
 */

namespace NovaPostForWoocommerce\Database\Models;

use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodAddress;
use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodDivision;

/**
 * Class WoocommerceShippingZoneMethod
 *
 * Provides methods to interact with WooCommerce shipping zone methods.
 */
class WoocommerceShippingZoneMethod {

    /**
     * The name of the database table.
     *
     * @var string
     */
    private $table_name;

    /**
     * WordPress database access abstraction object.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Constructor for the WoocommerceShippingZoneMethod class.
     * Initializes the database table name and WordPress database object.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'woocommerce_shipping_zone_methods';
        $this->wpdb       = $wpdb;
    }

    /**
     * Check if any Nova Post shipping methods are enabled in any shipping zones.
     *
     * @return bool True if any Nova Post shipping methods are enabled, false otherwise.
     */
    public function check_nova_post_methods(): bool {

        $method_ids = array(
            NovaPostShippingMethodAddress::METHOD_ID,
            NovaPostShippingMethodDivision::METHOD_ID,
        );

        // phpcs:disable
        $placeholders = implode( ',', array_fill( 0, count( $method_ids ), '%s' ) );

        $query        = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE method_id IN ($placeholders) AND is_enabled = 1", // phpcs:ignore
            $method_ids
        );

        $count = (int) $this->wpdb->get_var( $query );
        // phpcs:enable

        return $count > 0;
    }
}
