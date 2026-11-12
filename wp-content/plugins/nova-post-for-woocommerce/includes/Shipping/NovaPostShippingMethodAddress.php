<?php
/**
 * NovaPostShippingMethod class
 *
 * @package NovaPostForWoocommerce\Shipping
 */

namespace NovaPostForWoocommerce\Shipping;

/**
 * Nova Post shipping method for address delivery.
 *
 * Handles the address-based shipping method for Nova Post delivery service.
 */
class NovaPostShippingMethodAddress extends NovaPostShippingMethodAbstract {

    public const METHOD_ID = 'nova_post_address';

    /**
     * Constructor for the shipping class.
     *
     * @param int $instance_id Instance ID for the shipping method.
     */
    public function __construct( $instance_id = 0 ) {
        // Set unique titles/descriptions before calling parent constructor.
        $this->method_title       = __( 'Nova Post Address', 'nova-post-for-woocommerce' );
        $this->method_description = __( 'Adds a Nova Post shipping method (Address)', 'nova-post-for-woocommerce' );
        parent::__construct( $instance_id );
    }

    /**
     * Get the destination type (e.g., 'division' or 'address').
     *
     * @return string
     */
    protected function get_destination_type(): string {
        return 'address';
    }

    /**
     * Get the prefix for settings keys (e.g., 'shipping_division_' or 'shipping_address_').
     *
     * @return string
     */
    protected function get_settings_prefix(): string {
        return 'shipping_address_';
    }

    /**
     * Get the concrete METHOD_ID for the child class.
     *
     * @return string
     */
    public static function get_method_id(): string {
        return self::METHOD_ID;
    }
}
