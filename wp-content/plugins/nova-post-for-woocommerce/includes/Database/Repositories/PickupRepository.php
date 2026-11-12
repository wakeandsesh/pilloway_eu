<?php
/**
 * Pickup repository service.
 *
 * @package NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Database\Repositories;

use NovaPostForWoocommerce\Database\Models\Pickup;
use NovaPostForWoocommerce\Resources\PickupResource;

/**
 * Pickup repository class.
 */
class PickupRepository {

    /**
     * The pickup model instance.
     *
     * @var Pickup
     */
    private Pickup $pickup_model;

    /**
     * Constructor.
     *
     * @param Pickup $model The pickup model instance.
     */
    public function __construct( Pickup $model ) {
        $this->pickup_model = $model;
    }

    /**
     * Create a new pickup from resource data.
     *
     * @param PickupResource $pickup_resource The pickup resource.
     * @return int|false The pickup ID or false on failure.
     */
    public function create( PickupResource $pickup_resource ) {
        $data = $pickup_resource->for_storage();

        return $this->pickup_model->create( $data );
    }

    /**
     * Get a pickup by its ID.
     *
     * @param string $pickup_id The pickup ID.
     * @return object The pickup object or null if not found.
     */
    public function find( string $pickup_id ) {
        return $this->pickup_model->find_by_id( $pickup_id );
    }

    /**
     * Increment the order count for a pickup.
     *
     * @param string   $pickup_id The pickup ID.
     * @param int|null $amount Optional increment amount (defaults to 1).
     * @return bool
     */
    public function increment_order_count( string $pickup_id, ?int $amount = null ): bool {
        return $this->pickup_model->increment( $pickup_id, 'order_count', $amount ?? 1 );
    }

    /**
     * Increment the lock version for a pickup by one.
     *
     * @param string $pickup_id The pickup ID.
     * @return bool
     */
    public function increment_lock_version( string $pickup_id ): bool {
        return $this->pickup_model->increment( $pickup_id, 'lock_version' );
    }

    /**
     * Update a pickup's status.
     *
     * @param string $pickup_id The pickup ID.
     * @param string $status The new status.
     * @return bool True on success, false on failure.
     */
    public function update_status( string $pickup_id, string $status ): bool {
        return (bool) $this->pickup_model->update_status( $pickup_id, $status );
    }

    /**
     * Delete a pickup by its ID.
     *
     * @param string $pickup_id The pickup ID.
     * @return bool True on success, false on failure.
     */
    public function delete( string $pickup_id ): bool {
        return (bool) $this->pickup_model->where( 'id', $pickup_id )->delete();
    }
}
