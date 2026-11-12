<?php
/**
 * At Least One Division Filter Rule for Rakit Validation
 *
 * This file contains the validation rule that ensures at least one division filter is enabled.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Validation
 */

namespace NovaPostForWoocommerce\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Class AtLeastOneDivisionFilterRule
 *
 * Validates that at least one division filter checkbox is enabled.
 */
class AtLeastOneDivisionFilterRule extends Rule {

    /**
     * Check the value is valid.
     *
     * @param mixed $value The value to validate.
     * @return bool
     */
    public function check( $value ): bool {
        $postomat     = $this->get_bool( 'divisions_filter_postomat' );
        $post_branch  = $this->get_bool( 'divisions_filter_post_branch' );
        $cargo_branch = $this->get_bool( 'divisions_filter_cargo_branch' );
        $pudo         = $this->get_bool( 'divisions_filter_pudo' );

        return $postomat || $post_branch || $cargo_branch || $pudo;
    }

    /**
     * Helper to safely get boolean value from validation attributes.
     *
     * @param string $key Attribute key.
     * @return bool
     */
    private function get_bool( string $key ): bool {
        $attr = $this->validation->getAttribute( $key );
        $val  = $attr ? $attr->getValue() : null;
        return ! empty( $val );
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return __( 'At least one division type must be enabled.', 'nova-post-for-woocommerce' );
    }
}
