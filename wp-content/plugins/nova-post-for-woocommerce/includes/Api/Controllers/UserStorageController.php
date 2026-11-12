<?php
/**
 * Session Controller
 *
 * Handles session-related API endpoints for the Nova Post WooCommerce plugin.
 *
 * @package NovaPostForWoocommerce\Api\Controllers
 */

namespace NovaPostForWoocommerce\Api\Controllers;

use NovaPostForWoocommerce\Models\Traits\TrackEvent;
use NovaPostForWoocommerce\Models\UserStorage;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class UserStorageController
 *
 * Handles user storage data operations for the Nova Post WooCommerce plugin.
 */
class UserStorageController {

    use TrackEvent;

    /**
     * The user storage instance.
     *
     * @var UserStorage
     */
    private $user_storage;

    /**
     * UserStorageController constructor.
     */
    public function __construct() {
        $this->user_storage = UserStorage::get_instance();
    }

    /**
     * Retrieves all data.
     *
     * @return WP_REST_Response Response containing all data.
     */
    public function get_data(): WP_REST_Response {
        return new WP_REST_Response( $this->user_storage->all() );
    }

    /**
     * Stores data from the request.
     *
     * @param WP_REST_Request $request The request containing data to store.
     * @return WP_REST_Response Response containing updated data.
     */
    public function store_data( WP_REST_Request $request ): WP_REST_Response {
        $params = $request->get_params();
        $this->user_storage->update( $params );

        return new WP_REST_Response( $this->user_storage->all() );
    }
}
