<?php
/**
 * Asset registration and conditional enqueueing.
 *
 * @package SAW\WAP\Frontend
 */

declare( strict_types=1 );

namespace SAW\WAP\Frontend;

use SAW\WAP\Plugin;

/**
 * Manage frontend and admin assets.
 */
class Assets {
    /**
     * Hook into WordPress.
     */
    public static function init(): void {
        add_action( 'init', [ self::class, 'register_assets' ] );
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_frontend' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin' ] );
    }

    /**
     * Register styles and scripts.
     */
    public static function register_assets(): void {
        wp_register_style( 'sawwap-front', SAW_WAP_URL . 'assets/css/front.css', [], Plugin::VERSION );
        wp_register_style( 'sawwap-admin', SAW_WAP_URL . 'assets/css/admin.css', [], Plugin::VERSION );

        $deps = [ 'jquery' ];
        wp_register_script( 'sawwap-countdown', SAW_WAP_URL . 'assets/js/countdown.js', $deps, Plugin::VERSION, true );
        wp_register_script( 'sawwap-promo-progress', SAW_WAP_URL . 'assets/js/promo-progress.js', $deps, Plugin::VERSION, true );
        wp_register_script( 'sawwap-video-progress', SAW_WAP_URL . 'assets/js/video-progress.js', $deps, Plugin::VERSION, true );
        wp_register_script( 'sawwap-pdp-ui', SAW_WAP_URL . 'assets/js/pdp-ui.js', [ 'jquery' ], Plugin::VERSION, true );
        wp_register_script( 'sawwap-admin', SAW_WAP_URL . 'assets/js/admin.js', [ 'jquery' ], Plugin::VERSION, true );
    }

    /**
     * Enqueue frontend assets when relevant.
     */
    public static function enqueue_frontend(): void {
        if ( function_exists( 'is_product' ) && is_product() ) {
            wp_enqueue_style( 'sawwap-front' );
            wp_enqueue_script( 'sawwap-countdown' );
            wp_enqueue_script( 'sawwap-promo-progress' );
            wp_enqueue_script( 'sawwap-pdp-ui' );
        }

        if ( function_exists( 'is_account_page' ) && is_account_page() ) {
            wp_enqueue_style( 'sawwap-front' );
            wp_enqueue_script( 'sawwap-video-progress' );
        }

        if ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
            wp_enqueue_style( 'sawwap-front' );
        }
    }

    /**
     * Enqueue admin styles for product editor and settings page.
     *
     * @param string $hook Current admin page.
     */
    public static function enqueue_admin( string $hook ): void {
        if ( 'product_page_saw-wap-settings' === $hook || 'woocommerce_page_saw-wap-settings' === $hook ) {
            wp_enqueue_style( 'sawwap-admin' );
        }

        if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
            if ( $screen && 'product' === $screen->post_type ) {
                wp_enqueue_style( 'sawwap-admin' );
                wp_enqueue_script( 'sawwap-admin', SAW_WAP_URL . 'assets/js/admin.js', [ 'jquery' ], Plugin::VERSION, true );
            }
        }
    }
}
