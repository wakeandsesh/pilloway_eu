<?php
/**
 * ModifyValue Interface File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules\Interfaces
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules\Interfaces;

interface ModifyValue {

    /**
     * Modify given value
     * so in current and next rules returned value will be used
     *
     * @param mixed $value The value to modify.
     * @return mixed
     */
    public function modifyValue( $value );
}
