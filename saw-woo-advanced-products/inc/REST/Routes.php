<?php
/**
 * REST API routes skeleton.
 *
 * @package SAW\WAP\REST
 */

declare( strict_types=1 );

namespace SAW\WAP\REST;

/**
 * Registers REST routes placeholder.
 */
class Routes {
    /**
     * Initialize hooks.
     */
    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    /**
     * Register REST routes.
     */
    public static function register_routes(): void {
        // TODO: Add /sawwap/v1 endpoints.
    }
}
