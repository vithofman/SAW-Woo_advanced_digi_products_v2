<?php
/**
 * Shortcodes for SAW-WAP Plugin
 *
 * @package SAW\WAP\Frontend
 */

declare(strict_types=1);

namespace SAW\WAP\Frontend;

use SAW\WAP\Core\VideoTokenManager;
use SAW\WAP\Core\MyAccount;
use SAW\WAP\Helpers\VideoHelpers;

/**
 * Registers and handles all plugin shortcodes.
 */
class Shortcodes {
	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		// Video course player shortcode
		add_shortcode( 'saw_video_course_player', array( self::class, 'render_video_course_player' ) );
		
		// My Account shortcode (NEW!)
		add_shortcode( 'saw_my_account', array( self::class, 'render_my_account' ) );
	}

	/**
	 * =========================================================================
	 * SHORTCODE: [saw_my_account]
	 * =========================================================================
	 * 
	 * Renders complete My Account system
	 * 
	 * Usage: [saw_my_account]
	 * URL with tabs: /my-account/?endpoint=courses
	 * 
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public static function render_my_account( $atts = array() ): string {
		// Normalize attributes
		$atts = is_array( $atts ) ? $atts : array();
		
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			// For non-AJAX requests, redirect to login
			if ( ! wp_doing_ajax() && ! defined( 'REST_REQUEST' ) ) {
				$current_url = add_query_arg( array() );
				$login_url = wp_login_url( $current_url );
				
				// Use wp_safe_redirect with proper status code
				wp_safe_redirect( $login_url, 302 );
				exit;
			}
			
			// For AJAX/REST, return error message
			return self::render_error( 'not_logged_in', 'my_account' );
		}
		
		// Get current user
		$current_user = wp_get_current_user();
		$user_id = $current_user->ID;
		
		// Get current endpoint from URL
		$current_endpoint = MyAccount::get_current_endpoint();
		
		// Enqueue My Account assets
		self::enqueue_my_account_assets();
		
		// Prepare data for template
		$data = array(
			'user'             => $current_user,
			'user_id'          => $user_id,
			'current_endpoint' => $current_endpoint,
			'menu_items'       => MyAccount::get_menu_items( $current_endpoint ),
		);
		
		// Start output buffering
		ob_start();
		
		try {
			// Extract data to variables (EXTR_SKIP = don't overwrite existing vars)
			extract( $data, EXTR_SKIP );
			
			// Include main My Account template
			$template_path = SAW_WAP_PATH . 'templates/shortcode-my-account.php';
			
			// Check if template exists
			if ( ! file_exists( $template_path ) ) {
				throw new \Exception( 'Template not found: ' . $template_path );
			}
			
			// Include the template
			include $template_path;
			
		} catch ( \Exception $e ) {
			// Clear buffer on error
			ob_end_clean();
			
			// Log error if debug mode is enabled
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP My Account Error: ' . $e->getMessage() );
			}
			
			// Return user-friendly error message
			return sprintf(
				'<div class="saw-error" style="padding: 20px; background: #fee; border: 2px solid #c00; border-radius: 8px; margin: 20px 0;">
					<p style="color: #c00; font-weight: bold; margin: 0 0 10px;">❌ Chyba načítání účtu</p>
					<p style="margin: 0;">%s</p>
				</div>',
				esc_html( $e->getMessage() )
			);
		}
		
		// Get buffered content
		$output = ob_get_clean();
		
		// Return the output (WordPress will render it where shortcode is placed)
		return $output;
	}


	/**
	 * Enqueue CSS and JS for My Account
	 */
	private static function enqueue_my_account_assets(): void {
		// My Account CSS
		wp_enqueue_style(
			'sawwap-my-account',
			SAW_WAP_URL . 'assets/css/my-account.css',
			array(),
			filemtime( SAW_WAP_PATH . 'assets/css/my-account.css' ) // Cache busting
		);
		
		// My Account JavaScript
		wp_enqueue_script(
			'sawwap-my-account',
			SAW_WAP_URL . 'assets/js/my-account.js',
			array( 'jquery' ),
			filemtime( SAW_WAP_PATH . 'assets/js/my-account.js' ), // Cache busting
			true // Load in footer
		);
		
		// Mobile menu inline script
		wp_enqueue_script(
			'sawwap-my-account-inline',
			SAW_WAP_URL . 'assets/js/my-account-inline.js',
			array(),
			filemtime( SAW_WAP_PATH . 'assets/js/my-account-inline.js' ),
			true
		);
		
		// Localize script for AJAX
		wp_localize_script(
			'sawwap-my-account',
			'sawwapAccountData',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'saw_my_account_nonce' ),
				'currentUserId' => get_current_user_id(),
				'strings'       => array(
					'loading'        => __( 'Načítání...', 'saw-wap' ),
					'error'          => __( 'Nastala chyba. Zkuste to prosím znovu.', 'saw-wap' ),
					'saved'          => __( 'Uloženo', 'saw-wap' ),
					'confirmDelete'  => __( 'Opravdu chcete smazat?', 'saw-wap' ),
				),
			)
		);
	}

	/**
	 * =========================================================================
	 * SHORTCODE: [saw_video_course_player]
	 * =========================================================================
	 * 
	 * Render video course player shortcode
	 * 
	 * Usage: [saw_video_course_player]
	 * URL: /watch/?token=abc123...
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public static function render_video_course_player( $atts ): string {
		
		// Get token from URL
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		
		// Trim whitespace AND trailing slashes
		$token = trim( $token, " \t\n\r\0\x0B/" );
		
		if ( empty( $token ) ) {
			return self::render_error( 'missing_token', 'video_player' );
		}
		
		// Validace formátu tokenu (SHA256 = 64 hex znaků)
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP Shortcode: Invalid token format: ' . $token . ' (length: ' . strlen( $token ) . ')' );
			}
			return self::render_error( 'invalid_format', 'video_player' );
		}
		
		// Security check - musí být přihlášený
		$current_user_id = get_current_user_id();
		
		if ( 0 === $current_user_id ) {
			// Redirect na login s return URL
			$login_url = wp_login_url( add_query_arg( 'token', $token, get_permalink() ) );
			wp_safe_redirect( $login_url );
			exit;
		}
		
		// Validate token
		try {
			$token_manager = new VideoTokenManager();
			$access = $token_manager->validateToken( $token );
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP Shortcode: Token validation exception: ' . $e->getMessage() );
			}
			return self::render_error( 'invalid_token', 'video_player' );
		}
		
		if ( ! $access ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP Shortcode: Token validation failed for: ' . $token );
			}
			return self::render_error( 'invalid_token', 'video_player' );
		}
		
		// ✅ OPRAVA: Cast na int před použitím (databáze vrací string)
		$product_id = (int) $access->product_id;
		$video_index = (int) $access->video_index;
		$user_id = (int) $access->user_id;
		
		// Security check - token musí patřit přihlášenému uživateli
		if ( $user_id !== $current_user_id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP Shortcode: User ID mismatch. Token user: ' . $user_id . ', Current user: ' . $current_user_id );
			}
			return self::render_error( 'access_denied', 'video_player' );
		}
		
		// Load data
		$video = self::get_video_by_token( $product_id, $video_index );
		
		if ( ! $video ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP Shortcode: Video not found for product ' . $product_id . ', index ' . $video_index );
			}
			return self::render_error( 'video_not_found', 'video_player' );
		}
		
		$product = wc_get_product( $product_id );
		
		if ( ! $product ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP Shortcode: Product not found: ' . $product_id );
			}
			return self::render_error( 'product_not_found', 'video_player' );
		}
		
		// Get all videos for navigation and progress
		$all_videos = self::get_all_product_videos( $product_id );
		
		// Get navigation tokens (previous/next)
		$prev_token = self::get_prev_video_token( $user_id, $product_id, $video_index );
		$next_token = self::get_next_video_token( $user_id, $product_id, $video_index );
		
		// Get user's progress for this product
		$progress = self::get_user_progress( $user_id, $product_id );
		
		// Enqueue video player assets
		self::enqueue_video_player_assets( $token );
		
		// Prepare data for template
		$data = array(
			'token'         => $token,
			'video'         => $video,
			'product'       => $product,
			'all_videos'    => $all_videos,
			'prev_token'    => $prev_token,
			'next_token'    => $next_token,
			'progress'      => $progress,
			'user_id'       => $user_id,
			'access'        => $access,
			'current_index' => $video_index,
		);
		
		// Start output buffering
		ob_start();
		
		try {
			// Extract data to variables
			extract( $data, EXTR_SKIP );
			
			// Include template
			$template_path = SAW_WAP_PATH . 'templates/shortcode-video-course-player.php';
			
			if ( ! file_exists( $template_path ) ) {
				throw new \Exception( 'Template not found: ' . $template_path );
			}
			
			include $template_path;
			
		} catch ( \Exception $e ) {
			// Clear buffer on error
			ob_end_clean();
			
			// Log error
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP Video Player Error: ' . $e->getMessage() );
			}
			
			return self::render_error( 'template_error', 'video_player' );
		}
		
		// Return buffered content
		return ob_get_clean();
	}

	/**
	 * Enqueue assets for video player
	 */
	private static function enqueue_video_player_assets( string $token ): void {
		// Enqueue registered styles
		wp_enqueue_style( 'sawwap-watch-video' );
		
		// Enqueue video player tracker script
		wp_enqueue_script( 'sawwap-video-player-tracker' );
		
		// Localize script with data
		wp_localize_script(
			'sawwap-video-player-tracker',
			'sawwapVideoData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'saw_video_nonce' ),
				'token'   => $token,
			)
		);
	}

	/**
	 * Get video by product ID and video index
	 */
	private static function get_video_by_token( int $product_id, int $video_index ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'saw_video_metadata';
		
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE product_id = %d AND video_index = %d",
				$product_id,
				$video_index
			)
		);
	}

	/**
	 * Get all videos for a product
	 */
	private static function get_all_product_videos( int $product_id ): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'saw_video_metadata';
		
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE product_id = %d ORDER BY lesson_order ASC",
				$product_id
			)
		);
		
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get previous video token
	 */
	private static function get_prev_video_token( int $user_id, int $product_id, int $current_index ): ?string {
		global $wpdb;
		$tokens_table = $wpdb->prefix . 'saw_video_access_tokens';
		
		$token = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT access_token FROM {$tokens_table} 
				 WHERE user_id = %d AND product_id = %d AND video_index < %d AND is_active = 1
				 ORDER BY video_index DESC LIMIT 1",
				$user_id,
				$product_id,
				$current_index
			)
		);
		
		return $token ? (string) $token : null;
	}

	/**
	 * Get next video token
	 */
	private static function get_next_video_token( int $user_id, int $product_id, int $current_index ): ?string {
		global $wpdb;
		$tokens_table = $wpdb->prefix . 'saw_video_access_tokens';
		
		$token = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT access_token FROM {$tokens_table} 
				 WHERE user_id = %d AND product_id = %d AND video_index > %d AND is_active = 1
				 ORDER BY video_index ASC LIMIT 1",
				$user_id,
				$product_id,
				$current_index
			)
		);
		
		return $token ? (string) $token : null;
	}

	/**
	 * Get user's progress for all videos in a product
	 */
	private static function get_user_progress( int $user_id, int $product_id ): array {
		global $wpdb;
		$sessions_table = $wpdb->prefix . 'saw_video_watch_sessions';
		$tokens_table = $wpdb->prefix . 'saw_video_access_tokens';
		
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.video_index, MAX(s.progress_percent) as progress, MAX(s.completed) as completed
				 FROM {$tokens_table} t
				 LEFT JOIN {$sessions_table} s ON t.id = s.token_id
				 WHERE t.user_id = %d AND t.product_id = %d
				 GROUP BY t.video_index",
				$user_id,
				$product_id
			)
		);
		
		$progress = array();
		foreach ( $results as $row ) {
			$progress[ (int) $row->video_index ] = array(
				'progress'  => (int) $row->progress,
				'completed' => (bool) $row->completed,
			);
		}
		
		return $progress;
	}

	/**
	 * Render error message
	 */
	private static function render_error( string $error_code, string $context ): string {
		$messages = array(
			'missing_token'     => __( 'Token není uveden v URL.', 'saw-wap' ),
			'invalid_format'    => __( 'Neplatný formát tokenu.', 'saw-wap' ),
			'invalid_token'     => __( 'Token je neplatný nebo vypršel.', 'saw-wap' ),
			'access_denied'     => __( 'Nemáte oprávnění k tomuto obsahu.', 'saw-wap' ),
			'video_not_found'   => __( 'Video nebylo nalezeno.', 'saw-wap' ),
			'product_not_found' => __( 'Produkt nebyl nalezen.', 'saw-wap' ),
			'template_error'    => __( 'Chyba načítání šablony.', 'saw-wap' ),
			'not_logged_in'     => __( 'Musíte být přihlášeni.', 'saw-wap' ),
		);
		
		$message = isset( $messages[ $error_code ] ) ? $messages[ $error_code ] : __( 'Došlo k chybě.', 'saw-wap' );
		
		return sprintf(
			'<div class="saw-error-box" style="padding: 40px; text-align: center; background: #fff3cd; border: 2px solid #ffc107; border-radius: 12px; margin: 40px 0;">
				<div style="font-size: 48px; margin-bottom: 16px;">⚠️</div>
				<h3 style="margin: 0 0 12px 0; color: #856404;">%s</h3>
				<p style="margin: 0 0 24px 0; color: #856404;">%s</p>
				<a href="%s" style="display: inline-block; padding: 12px 24px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600;">
					%s
				</a>
			</div>',
			esc_html__( 'Přístup odmítnut', 'saw-wap' ),
			esc_html( $message ),
			esc_url( home_url( '/muj-ucet/' ) ),
			esc_html__( '← Zpět na Můj účet', 'saw-wap' )
		);
	}
}