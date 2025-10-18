<?php
/**
 * Template helper functions and wrappers.
 *
 * @package SAW\WAP\Helpers
 */

declare( strict_types=1 );

namespace SAW\WAP\Helpers;

/**
 * Template tags for theme overrides.
 */
class TemplateTags {
    /**
     * Locate a template within the theme or fallback to plugin path.
     *
     * @param string $template Template relative path.
     * @return string
     */
    public static function locate( string $template ): string {
        $template = ltrim( $template, '/' );
        $paths    = [
            trailingslashit( get_stylesheet_directory() ) . 'woocommerce/saw-wap/' . $template,
            trailingslashit( get_template_directory() ) . 'woocommerce/saw-wap/' . $template,
            SAW_WAP_PATH . 'templates/' . $template,
        ];

        foreach ( $paths as $path ) {
            if ( is_readable( $path ) ) {
                return $path;
            }
        }

        return SAW_WAP_PATH . 'templates/' . $template;
    }

    /**
     * Load a template with optional variables.
     *
     * @param string $template Template path relative to templates/.
     * @param array  $vars     Variables to extract.
     */
    public static function render( string $template, array $vars = [] ): void {
        $path = self::locate( $template );
        if ( ! empty( $vars ) ) {
            extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        }

        include $path;
    }
}
