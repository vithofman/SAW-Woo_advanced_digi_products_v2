<?php
/**
 * Template loader skeleton.
 *
 * @package SAW\WAP\Frontend
 */

declare( strict_types=1 );

namespace SAW\WAP\Frontend;

use SAW\WAP\Helpers\TemplateTags;

/**
 * Handles template overrides placeholder.
 */
class Templates {
    /**
     * Initialize hooks.
     */
    public static function init(): void {
        // TODO: Hook into template_include to load digital product template.
    }

    /**
     * Render helper for parts.
     */
    public static function render( string $template, array $vars = [] ): void {
        TemplateTags::render( $template, $vars );
    }
}
