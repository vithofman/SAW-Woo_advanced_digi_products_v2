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
		// Frontend CSS
		wp_register_style( 'sawwap-front', SAW_WAP_URL . 'assets/css/front.css', [], Plugin::VERSION );
		wp_register_style( 'sawwap-watch-video', SAW_WAP_URL . 'assets/css/watch-video.css', [], Plugin::VERSION );
		
		// Admin CSS
		wp_register_style( 'sawwap-admin', SAW_WAP_URL . 'assets/css/admin.css', [], Plugin::VERSION );
		wp_register_style( 'sawwap-admin-video-repeater', SAW_WAP_URL . 'assets/css/admin-video-repeater.css', [], Plugin::VERSION );

		// Frontend JS
		$deps = [ 'jquery' ];
		wp_register_script( 'sawwap-countdown', SAW_WAP_URL . 'assets/js/countdown.js', $deps, Plugin::VERSION, true );
		wp_register_script( 'sawwap-promo-progress', SAW_WAP_URL . 'assets/js/promo-progress.js', $deps, Plugin::VERSION, true );
		wp_register_script( 'sawwap-video-progress', SAW_WAP_URL . 'assets/js/video-progress.js', $deps, Plugin::VERSION, true );
		wp_register_script( 'sawwap-pdp-ui', SAW_WAP_URL . 'assets/js/pdp-ui.js', $deps, Plugin::VERSION, true );
		wp_register_script( 'sawwap-watch-video-ui', SAW_WAP_URL . 'assets/js/watch-video-ui.js', $deps, Plugin::VERSION, true );
		
		// Admin JS
		wp_register_script( 'sawwap-admin', SAW_WAP_URL . 'assets/js/admin.js', [ 'jquery' ], Plugin::VERSION, true );
		wp_register_script( 
			'sawwap-admin-video-repeater', 
			SAW_WAP_URL . 'assets/js/admin-video-repeater.js', 
			[ 'jquery', 'jquery-ui-sortable' ], // jQuery UI Sortable pro drag & drop
			Plugin::VERSION, 
			true 
		);
	}

	/**
	 * Enqueue frontend assets when relevant.
	 */
	public static function enqueue_frontend(): void {
		// Product page assets
		if ( function_exists( 'is_product' ) && is_product() ) {
			wp_enqueue_style( 'sawwap-front' );
			wp_enqueue_script( 'sawwap-countdown' );
			wp_enqueue_script( 'sawwap-promo-progress' );
			wp_enqueue_script( 'sawwap-pdp-ui' );
		}

		// Account page assets
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			wp_enqueue_style( 'sawwap-front' );
			wp_enqueue_script( 'sawwap-video-progress' );
		}

		// Cart & Checkout assets
		if ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
			wp_enqueue_style( 'sawwap-front' );
		}

		// === WATCH VIDEO PAGE ASSETS ===
		// Detekce přes query var (nastaveno v Videos::add_query_vars())
		if ( get_query_var( 'saw_watch_token' ) ) {
			// Enqueue watch video CSS
			wp_enqueue_style( 'sawwap-watch-video' );
			
			// Enqueue watch video JavaScript
			wp_enqueue_script( 'sawwap-watch-video-ui' );
			
			// Localize script pro případné AJAX funkce (budoucí Krok 1.7)
			wp_localize_script(
				'sawwap-watch-video-ui',
				'sawwapWatchData',
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'saw_watch_nonce' ),
					'strings' => [
						'loading'       => __( 'Načítání...', 'saw-wap' ),
						'error'         => __( 'Nastala chyba. Zkuste to prosím znovu.', 'saw-wap' ),
						'progressSaved' => __( 'Progress uložen', 'saw-wap' ),
					],
				]
			);
		}
	}

	/**
	 * Enqueue admin styles for product editor and settings page.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue_admin( string $hook ): void {
		// Pro settings stránku
		if ( 'product_page_saw-wap-settings' === $hook || 'woocommerce_page_saw-wap-settings' === $hook ) {
			wp_enqueue_style( 'sawwap-admin' );
		}

		// Pro product edit stránku
		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			
			if ( $screen && 'product' === $screen->post_type ) {
				// Enqueue základní admin styly
				wp_enqueue_style( 'sawwap-admin' );
				wp_enqueue_script( 'sawwap-admin' );
				
				// Enqueue video repeater assets
				wp_enqueue_style( 'sawwap-admin-video-repeater' );
				wp_enqueue_script( 'sawwap-admin-video-repeater' );
				
				// WordPress už má jQuery UI Sortable, takže nemusíme nic dalšího
				// Jen se ujistíme že je načtený
				wp_enqueue_script( 'jquery-ui-sortable' );
			}
		}
	}
}