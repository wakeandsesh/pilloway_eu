<?php
/**
 * BeforeValidate Interface File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules\Interfaces
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules\Interfaces;

interface BeforeValidate {

    /**
     * Before validate hook
     *
     * @return void
     */
    public function beforeValidate();
}
