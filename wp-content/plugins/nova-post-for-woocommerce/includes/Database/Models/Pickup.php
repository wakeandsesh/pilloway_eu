<?php
/**
 * Pickup model for database operations.
 *
 * @package NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Database\Models;

/**
 * Pickup model class.
 */
class Pickup extends BaseModel {

    /**
     * The database table name without a prefix.
     *
     * @var string
     */
    protected string $table = 'np_pickups';

    /**
     * Create a new pickup record.
     *
     * @param array $data Pickup data.
     * @return int|false The number of rows inserted, or false on error.
     */
    public function create( $data ) {
        return $this->insert( $data );
    }

    /**
     * Find a pickup by its ID.
     *
     * @param string $pickup_id The pickup ID.
     * @return object|null The `pickup` object or null if not found.
     */
    public function find_by_id( $pickup_id ) {
        return $this->where( 'id', $pickup_id )->first();
    }

    /**
     * Update pickup status.
     *
     * @param string $pickup_id The pickup ID.
     * @param string $status New status.
     * @return int|false The number of rows updated, or false on error.
     */
    public function update_status( $pickup_id, $status ) {
        return $this->where( 'id', $pickup_id )->update( array( 'status' => $status ) );
    }

    /**
     * Increment unsigned integer column by amount.
     *
     * @param int    $pickup_id The pickup ID.
     * @param string $column Column name to increment.
     * @param int    $amount Increment amount.
     * @return bool True on success, false on failure.
     */
    public function increment( int $pickup_id, string $column, int $amount = 1 ): bool {
        if ( ! in_array( $column, array( 'order_count', 'lock_version' ), true ) ) {
            return false;
        }

        return false !== $this->db->query(
            $this->db->prepare(
                "UPDATE {$this->get_table_name()}
                SET {$column} = {$column} + %d
                WHERE id = %d",
                $amount,
                $pickup_id
            )
        );
    }
}
