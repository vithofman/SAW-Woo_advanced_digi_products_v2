<?php
/**
 * Shortcodes skeleton.
 *
 * @package SAW\WAP\Frontend
 */

declare( strict_types=1 );

namespace SAW\WAP\Frontend;

use SAW\WAP\Core\VideoTokenManager;
use SAW\WAP\Helpers\VideoHelpers;

/**
 * Registers shortcode placeholders.
 */
class Shortcodes {
	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_shortcode( 'saw_video_course_player', array( self::class, 'render_video_course_player' ) );
	}

	/**
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
			return self::render_error( 'missing_token' );
		}
		
		// Validace formátu tokenu (SHA256 = 64 hex znaků)
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP Shortcode: Invalid token format: ' . $token . ' (length: ' . strlen( $token ) . ')' );
			}
			return self::render_error( 'invalid_format' );
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
			return self::render_error( 'invalid_token' );
		}
		
		if ( ! $access ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP Shortcode: Token validation failed for: ' . $token );
			}
			return self::render_error( 'invalid_token' );
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
			return self::render_error( 'access_denied' );
		}
		
		// Load data
		$video = self::get_video_by_token( $product_id, $video_index );
		
		if ( ! $video ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP Shortcode: Video not found for product ' . $product_id . ', index ' . $video_index );
			}
			return self::render_error( 'video_not_found' );
		}
		
		$product = wc_get_product( $product_id );
		
		if ( ! $product ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP Shortcode: Product not found: ' . $product_id );
			}
			return self::render_error( 'product_not_found' );
		}
		
		// ✅ OPRAVA: Používáme už přetypované proměnné
		$all_videos = self::get_all_product_videos( $product_id );
		$progress = self::get_user_progress( $current_user_id, $product_id );
		
		// Find navigation
		$current_index = self::find_current_index( $all_videos, $video_index );
		$prev_token = self::get_prev_token( $all_videos, $current_index, $current_user_id, $product_id );
		$next_token = self::get_next_token( $all_videos, $current_index, $current_user_id, $product_id );
		
		// Enqueue assets
		self::enqueue_assets();
		
		// Render template
		ob_start();
		include SAW_WAP_PATH . 'templates/shortcode-video-course-player.php';
		return ob_get_clean();
	}

	/**
	 * Get video by product_id and video_index
	 * 
	 * ✅ OPRAVA: Změněna signatura - nemáme $access objekt
	 */
	private static function get_video_by_token( int $product_id, int $video_index ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'saw_video_metadata';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE product_id = %d AND video_index = %d LIMIT 1",
				$product_id,
				$video_index
			)
		);
	}

	/**
	 * Get all videos for product
	 */
	private static function get_all_product_videos( int $product_id ): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'saw_video_metadata';

		$videos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE product_id = %d ORDER BY lesson_order ASC",
				$product_id
			)
		);

		return $videos ? $videos : array();
	}

	/**
	 * Get user progress
	 */
	private static function get_user_progress( int $user_id, int $product_id ): array {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'saw_video_watch_sessions';
		$tokens_table = $wpdb->prefix . 'saw_video_access_tokens';
		$metadata_table = $wpdb->prefix . 'saw_video_metadata';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$metadata_table} WHERE product_id = %d AND is_free = 0",
				$product_id
			)
		);

		$completed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT t.video_index) 
				 FROM {$tokens_table} t 
				 INNER JOIN {$sessions_table} s ON s.token_id = t.id 
				 WHERE t.user_id = %d AND t.product_id = %d AND s.completed = 1",
				$user_id,
				$product_id
			)
		);

		$percent = $total > 0 ? round( ( $completed / $total ) * 100, 1 ) : 0;

		return array(
			'completed' => $completed,
			'total' => $total,
			'percent' => $percent,
		);
	}

	/**
	 * Find current video index
	 */
	private static function find_current_index( array $all_videos, int $video_index ): int {
		foreach ( $all_videos as $idx => $video ) {
			if ( (int) $video->video_index === $video_index ) {
				return $idx;
			}
		}
		return 0;
	}

	/**
	 * Get previous video token
	 */
	private static function get_prev_token( array $all_videos, int $current_index, int $user_id, int $product_id ) {
		if ( $current_index <= 0 ) {
			return null;
		}

		$prev_video = $all_videos[ $current_index - 1 ];
		return VideoHelpers::get_user_video_token( $user_id, $product_id, (int) $prev_video->video_index );
	}

	/**
	 * Get next video token
	 */
	private static function get_next_token( array $all_videos, int $current_index, int $user_id, int $product_id ) {
		if ( $current_index >= count( $all_videos ) - 1 ) {
			return null;
		}

		$next_video = $all_videos[ $current_index + 1 ];
		return VideoHelpers::get_user_video_token( $user_id, $product_id, (int) $next_video->video_index );
	}

	/**
	 * Enqueue assets
	 */
	private static function enqueue_assets(): void {
		wp_enqueue_style(
			'sawwap-video-course-player',
			SAW_WAP_URL . 'assets/css/video-course-player.css',
			array(),
			'1.0.0'
		);
	}

	/**
	 * Render error message
	 */
	private static function render_error( string $type = 'invalid_token' ): string {
		$messages = array(
			'missing_token' => 'Chybí přístupový token.',
			'invalid_format' => 'Neplatný formát tokenu.',
			'invalid_token' => 'Neplatný nebo vypršelý přístupový token.',
			'access_denied' => 'Tento token nepatří vašemu účtu.',
			'video_not_found' => 'Video nebylo nalezeno.',
			'product_not_found' => 'Kurz nebyl nalezen.',
		);

		$message = isset( $messages[ $type ] ) ? $messages[ $type ] : $messages['invalid_token'];

		return sprintf(
			'<div class="saw-error-message" style="padding: 40px; text-align: center; background: #fee; border: 2px solid #c00; border-radius: 8px; margin: 20px 0;">
				<p style="color: #c00; font-size: 18px; font-weight: 600; margin: 0 0 10px;">❌ Chyba přístupu</p>
				<p style="margin: 0 0 20px;">%s</p>
				<a href="%s" style="display: inline-block; padding: 12px 24px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 4px;">← Zpět na moje kurzy</a>
			</div>',
			esc_html( $message ),
			esc_url( wc_get_account_endpoint_url( 'saw-videos' ) )
		);
	}
}