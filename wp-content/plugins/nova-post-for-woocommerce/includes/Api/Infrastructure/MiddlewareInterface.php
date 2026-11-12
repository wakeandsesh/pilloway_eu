<?php
/**
 * Middleware Interface
 *
 * @package NovaPostForWoocommerce\Api\Middleware
 */

namespace NovaPostForWoocommerce\Api\Infrastructure;

use WP_REST_Request;

interface MiddlewareInterface {
    /**
     * Handle an incoming request.
     *
     * @param WP_REST_Request $request The request object.
     * @param callable        $next    The next middleware or controller.
     * @return mixed
     */
    public function handle( WP_REST_Request $request, callable $next );
}
