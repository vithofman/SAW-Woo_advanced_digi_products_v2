<?php
/**
 * Access control skeleton.
 *
 * @package SAW\WAP\Core
 */

declare( strict_types=1 );

namespace SAW\WAP\Core;

/**
 * Handles customer access to digital content.
 */
class Access {
    /**
     * Initialize hooks.
     */
    public static function init(): void {
        // TODO: Implement access grants and checks.
    }

    /**
     * Check if user has access to product.
     */
    public static function user_has_access( int $user_id, int $product_id ): bool {
        // TODO: Replace with real access check logic.
        return false;
    }
}
