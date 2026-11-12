<?php
/**
 * Exists In Table Validation Rule
 *
 * Validates if a value exists in a specified database table.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Validation
 */

namespace NovaPostForWoocommerce\Validation\Rules;

use wpdb;
use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Validates that a value exists in a specified database table.
 */
class ExistsInCustomTableRule extends Rule {

    /**
     * Fillable parameters.
     *
     * @var array
     */
    protected $fillableParams = array( 'table', 'column' ); // phpcs:ignore

    /**
     * Check the value is valid.
     *
     * @param mixed $value The value to validate.
     * @return bool
     */
    public function check( $value ): bool {
        global $wpdb;

        $this->requireParameters( array( 'table' ) );

        $table  = $this->parameter( 'table' );
        $column = $this->parameter( 'column' ) ?? 'id';

        if ( ! str_starts_with( $table, $wpdb->prefix ) ) {
            $table = $wpdb->prefix . $table;
        }

        // We can't update $this->parameter('table') to reflect prefix in message easily
        // unless we modify the internal params array which is protected/private.
        // But for validation check it's fine.

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = %s";

        return (bool) $wpdb->get_var( $wpdb->prepare( $sql, $value ) ); // phpcs:ignore
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return sprintf(
            /* translators: 1: column name, 2: table name */
            __( 'The selected %1$s does not exist in the %2$s table.', 'nova-post-for-woocommerce' ),
            $this->column,
            $this->table
        );
    }
}
