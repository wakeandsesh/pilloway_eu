<?php
/**
 * Validates that a given order ID exists in WooCommerce.
 *
 * @package NovaPostForWoocommerce\Validation\Rules
 */

namespace NovaPostForWoocommerce\Validation\Rules;

use WC_Order;
use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Class ExistingWooOrderRule
 *
 * @package NovaPostForWoocommerce\Validation\Rules
 */
class ExistingWooOrderRule extends Rule {

    /**
     * Determine if the validation rule passes.
     *
     * @param  mixed $value     The value of the attribute.
     * @return bool
     */
    public function check( $value ): bool {
        $order = wc_get_order( (int) $value );
        return $order instanceof WC_Order;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return __( 'The selected WC order does not exist.', 'nova-post-for-woocommerce' );
    }
}
