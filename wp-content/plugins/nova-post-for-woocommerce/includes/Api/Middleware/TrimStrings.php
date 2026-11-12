<?php
/**
 * TrimStrings Middleware
 *
 * @package NovaPostForWoocommerce\Api\Middleware
 */

namespace NovaPostForWoocommerce\Api\Middleware;

use WP_REST_Request;

use NovaPostForWoocommerce\Api\Infrastructure\MiddlewareInterface;

/**
 * Class TrimStrings
 *
 * @package NovaPostForWoocommerce\Api\Middleware
 */
class TrimStrings implements MiddlewareInterface {
    /**
     * Handle an incoming request.
     *
     * @param WP_REST_Request $request The request object.
     * @param callable        $next    The next middleware or controller.
     * @return mixed
     */
    public function handle( WP_REST_Request $request, callable $next ) {
        $params = $request->get_params();

        foreach ( $params as $key => $value ) {
            $cleaned_value = $this->transform( $value );
            $request->set_param( $key, $cleaned_value );
        }

        return $next( $request );
    }

    /**
     * Transform the value.
     *
     * @param mixed $value The value to transform.
     * @return mixed
     */
    protected function transform( $value ) {
        if ( is_array( $value ) ) {
            return array_map( array( $this, 'transform' ), $value );
        }

        if ( is_string( $value ) ) {
            return trim( $value );
        }

        return $value;
    }
}
