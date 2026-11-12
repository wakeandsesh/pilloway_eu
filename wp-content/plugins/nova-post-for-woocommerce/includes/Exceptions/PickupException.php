<?php
/**
 * Pickup Exception.
 *
 * @package NovaPostForWoocommerce\Exceptions
 */

namespace NovaPostForWoocommerce\Exceptions;

/**
 * Custom exception class for pickup-related errors.
 */
class PickupException extends NovaPostException {
    const TIME_INTERVALS_UNAVAILABLE = 'time_intervals_unavailable';
    const PICKUP_CREATION_FAILED     = 'pickup_creation_failed';
    const PICKUP_UPDATE_FAILED       = 'pickup_update_failed';
}
