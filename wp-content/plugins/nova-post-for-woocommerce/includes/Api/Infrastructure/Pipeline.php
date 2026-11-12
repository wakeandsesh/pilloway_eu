<?php
/**
 * Pipeline Class
 *
 * @package NovaPostForWoocommerce\Api\Middleware
 */

namespace NovaPostForWoocommerce\Api\Infrastructure;

use WP_REST_Request;
use Exception;

/**
 * Class Pipeline
 *
 * @package NovaPostForWoocommerce\Api\Infrastructure
 */
class Pipeline {

    /**
     * Wrap a controller callback with a stack of middleware.
     *
     * @param array    $middlewares         Array of Middleware class names or instances.
     * @param callable $controller_callback The final destination (controller method).
     *
     * @return callable The callable ready for WP register_rest_route.
     */
    public static function through( array $middlewares, callable $controller_callback ): callable {
        return function ( WP_REST_Request $request ) use ( $middlewares, $controller_callback ) {
            $pipeline = array_reduce(
                array_reverse( $middlewares ),
                array( self::class, 'wrap_middleware' ),
                self::prepare_destination( $controller_callback )
            );

            return $pipeline( $request );
        };
    }

    /**
     * Create a wrapper for the middleware layer.
     *
     * @param callable                   $next       The next layer in the pipeline.
     * @param string|MiddlewareInterface $middleware The middleware class name or instance.
     *
     * @return callable
     */
    private static function wrap_middleware( callable $next, $middleware ): callable {
        return function ( WP_REST_Request $request ) use ( $next, $middleware ) {
            $instance = self::resolve_middleware( $middleware );

            return $instance->handle( $request, $next );
        };
    }

    /**
     * Prepare the final destination execution in the pipeline.
     *
     * @param callable $controller_callback The controller's callback method.
     *
     * @return callable
     */
    private static function prepare_destination( callable $controller_callback ): callable {
        return function ( WP_REST_Request $request ) use ( $controller_callback ) {
            return call_user_func( $controller_callback, $request );
        };
    }

    /**
     * Resolve the middleware into an instance.
     *
     * @param string|MiddlewareInterface $middleware The middleware class name or instance.
     *
     * @return MiddlewareInterface
     * @throws Exception If the middleware does not implement the Middleware interface.
     */
    private static function resolve_middleware( $middleware ) {
        if ( is_string( $middleware ) ) {
            $middleware = new $middleware();
        }

        if ( ! $middleware instanceof MiddlewareInterface ) {
            throw new Exception( 'Middleware must implement Middleware interface' );
        }

        return $middleware;
    }
}
