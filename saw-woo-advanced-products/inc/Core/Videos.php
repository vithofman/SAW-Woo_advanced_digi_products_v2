<?php
/**
 * Video helper and watch endpoint handler.
 *
 * @package SAW\WAP\Core
 */

declare( strict_types=1 );

namespace SAW\WAP\Core;

use SAW\WAP\Core\VideoTokenManager;

/**
 * Video management and watch endpoint.
 */
class Videos {
	/**
	 * Token manager instance.
	 *
	 * @var VideoTokenManager
	 */
	private static ?VideoTokenManager $token_manager = null;

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		// Register rewrite rules
		add_action( 'init', [ self::class, 'add_watch_endpoint' ] );

		// Add query var
		add_filter( 'query_vars', [ self::class, 'add_query_vars' ] );

		// Handle watch template redirect
		add_action( 'template_redirect', [ self::class, 'watch_template_redirect' ], 1 );
	}

	/**
	 * Get token manager instance (singleton).
	 *
	 * @return VideoTokenManager
	 */
	private static function get_token_manager(): VideoTokenManager {
		if ( null === self::$token_manager ) {
			self::$token_manager = new VideoTokenManager();
		}

		return self::$token_manager;
	}

	/**
	 * Register rewrite rule for /watch/{token}/.
	 *
	 * Pattern zachytí 64-znakový hex string (SHA256 token).
	 * Priority 'top' zajistí že rule běží před WP default rules.
	 */
	public static function add_watch_endpoint(): void {
		add_rewrite_rule(
			'^watch/([a-f0-9]{64})/?$',
			'index.php?saw_watch_token=$matches[1]',
			'top'
		);

		self::log_debug( 'Watch endpoint rewrite rule registered' );
	}

	/**
	 * Add custom query var for watch token.
	 *
	 * @param array<string> $vars Existing query vars.
	 * @return array<string> Modified query vars.
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = 'saw_watch_token';
		return $vars;
	}

	/**
	 * Handle watch page template redirect (DEBUG VERZE S VÝPISY).
	 */
	public static function watch_template_redirect(): void {
		echo '<div style="background:#ffeb3b;color:#000;padding:15px;margin:10px;border:2px solid #f57c00;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 1:</strong> watch_template_redirect called</div>';

		// Získat token z query var
		echo '<div style="background:#ffeb3b;color:#000;padding:15px;margin:10px;border:2px solid #f57c00;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 2:</strong> About to get query var</div>';

		$token = get_query_var( 'saw_watch_token', '' );

		echo '<div style="background:#ffeb3b;color:#000;padding:15px;margin:10px;border:2px solid #f57c00;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 3:</strong> token = ' . esc_html( $token ) . '</div>';

		echo '<div style="background:#ffeb3b;color:#000;padding:15px;margin:10px;border:2px solid #f57c00;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 4:</strong> About to check if empty</div>';

		// Pokud není watch request → return (normální WP flow)
		if ( empty( $token ) ) {
			echo '<div style="background:#f44336;color:#fff;padding:15px;margin:10px;border:2px solid #b71c1c;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 5:</strong> Token is EMPTY! Exiting...</div>';
			return;
		}

		echo '<div style="background:#4caf50;color:#fff;padding:15px;margin:10px;border:2px solid #2e7d32;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 6:</strong> Token not empty, continuing...</div>';

		echo '<div style="background:#2196f3;color:#fff;padding:15px;margin:10px;border:2px solid #1565c0;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 7:</strong> Checking if user logged in...</div>';

		// 1. Musí být přihlášený uživatel
		if ( ! is_user_logged_in() ) {
			echo '<div style="background:#f44336;color:#fff;padding:15px;margin:10px;border:2px solid #b71c1c;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 8:</strong> User NOT logged in</div>';

			wp_safe_redirect(
				add_query_arg(
					'redirect_to',
					urlencode( home_url( '/watch/' . $token . '/' ) ),
					wp_login_url()
				)
			);
			exit;
		}

		echo '<div style="background:#4caf50;color:#fff;padding:15px;margin:10px;border:2px solid #2e7d32;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 9:</strong> User IS logged in (ID: ' . get_current_user_id() . ')</div>';

		echo '<div style="background:#2196f3;color:#fff;padding:15px;margin:10px;border:2px solid #1565c0;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 10:</strong> About to get token manager...</div>';

		$current_user_id = get_current_user_id();

		// 2. Validovat token přes VideoTokenManager
		try {
			echo '<div style="background:#2196f3;color:#fff;padding:15px;margin:10px;border:2px solid #1565c0;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 11:</strong> Getting token manager instance...</div>';

			$token_manager = self::get_token_manager();

			echo '<div style="background:#4caf50;color:#fff;padding:15px;margin:10px;border:2px solid #2e7d32;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 12:</strong> Token manager created</div>';

			echo '<div style="background:#2196f3;color:#fff;padding:15px;margin:10px;border:2px solid #1565c0;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 13:</strong> Validating token...</div>';

			$access = $token_manager->validateToken( $token );

			echo '<div style="background:#4caf50;color:#fff;padding:15px;margin:10px;border:2px solid #2e7d32;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 14:</strong> Token validation completed</div>';

			if ( null === $access ) {
				echo '<div style="background:#f44336;color:#fff;padding:15px;margin:10px;border:2px solid #b71c1c;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 15:</strong> Access is NULL!</div>';
				die( 'STOPPED AT: Access is null' );
			}

			echo '<div style="background:#4caf50;color:#fff;padding:15px;margin:10px;border:2px solid #2e7d32;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 16:</strong> Access OK!</div>';

		} catch ( \Exception $e ) {
			echo '<div style="background:#f44336;color:#fff;padding:15px;margin:10px;border:2px solid #b71c1c;font-family:monospace;font-size:14px;"><strong>SAW DEBUG EXCEPTION:</strong> ' . esc_html( $e->getMessage() ) . '</div>';
			die( 'STOPPED AT: Exception - ' . $e->getMessage() );
		}

		echo '<div style="background:#9c27b0;color:#fff;padding:15px;margin:10px;border:2px solid #6a1b9a;font-family:monospace;font-size:14px;"><strong>SAW DEBUG 17:</strong> WE MADE IT THIS FAR!</div>';

		die( 'DEBUG: Manually stopping here to see how far we got' );
	}

	/**
	 * Redirect s error message.
	 *
	 * @param string $url     Redirect URL.
	 * @param string $message Error message.
	 */
	private static function redirect_with_error( string $url, string $message ): void {
		wc_add_notice( $message, 'error' );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Načíst watch video template.
	 *
	 * Template se hledá v tomto pořadí:
	 * 1. {theme}/woocommerce/saw-wap/watch-video.php
	 * 2. {plugin}/templates/watch-video.php
	 */
	private static function load_watch_template(): void {
		// Prevent caching
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		// Locate template
		$template = SAW_WAP_PATH . 'templates/watch-video.php';

		// Theme override
		$theme_template = locate_template( [ 'woocommerce/saw-wap/watch-video.php' ] );
		if ( $theme_template ) {
			$template = $theme_template;
		}

		self::log_debug( 'Loading watch template', [ 'template' => $template ] );

		// Load template
		get_header();

		if ( file_exists( $template ) ) {
			include $template;
		} else {
			self::log_error( 'Watch template not found', [ 'path' => $template ] );
			echo '<p>' . esc_html__( 'Template pro přehrávání videa nebyl nalezen.', 'saw-wap' ) . '</p>';
		}

		get_footer();
		exit;
	}

	/**
	 * Získat video metadata podle tokenu.
	 *
	 * @param object $access Token access object.
	 * @return object|null Video objekt nebo null.
	 */
	private static function get_video_by_token( object $access ): ?object {
		global $wpdb;

		$table_name = $wpdb->prefix . 'saw_video_metadata';

		$video = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name}
				 WHERE product_id = %d
				 AND video_index = %d
				 LIMIT 1",
				$access->product_id,
				$access->video_index
			)
		);

		return $video ?: null;
	}

	/**
	 * Získat všechna videa produktu (seřazená podle lesson_order).
	 *
	 * @param int $product_id Product ID.
	 * @return array Array video objektů.
	 */
	private static function get_all_product_videos( int $product_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'saw_video_metadata';

		$videos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name}
				 WHERE product_id = %d
				 ORDER BY lesson_order ASC",
				$product_id
			)
		);

		return $videos ?: [];
	}

	/**
	 * Získat token pro konkrétní video uživatele.
	 *
	 * Pro navigaci (předchozí/další video).
	 *
	 * @param int $user_id     User ID.
	 * @param int $product_id  Product ID.
	 * @param int $video_index Video index.
	 * @return string|null Token nebo null pokud neexistuje.
	 */
	private static function get_user_video_token( int $user_id, int $product_id, int $video_index ): ?string {
		global $wpdb;

		$table_name = $wpdb->prefix . 'saw_video_access_tokens';

		$token = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT access_token FROM {$table_name}
				 WHERE user_id = %d
				 AND product_id = %d
				 AND video_index = %d
				 AND is_active = 1
				 AND access_expires > NOW()
				 LIMIT 1",
				$user_id,
				$product_id,
				$video_index
			)
		);

		return $token ?: null;
	}

	/**
	 * Získat progress uživatele v kurzu.
	 *
	 * @param int $user_id    User ID.
	 * @param int $product_id Product ID.
	 * @return array{completed: int, total: int, percent: float}
	 */
	public static function get_user_progress( int $user_id, int $product_id ): array {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'saw_video_watch_sessions';
		$tokens_table   = $wpdb->prefix . 'saw_video_access_tokens';
		$metadata_table = $wpdb->prefix . 'saw_video_metadata';

		// Total videí (kromě free)
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$metadata_table}
				 WHERE product_id = %d
				 AND is_free = 0",
				$product_id
			)
		);

		// Dokončená videa
		$completed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT t.video_index)
				 FROM {$tokens_table} t
				 INNER JOIN {$sessions_table} s ON s.token_id = t.id
				 WHERE t.user_id = %d
				 AND t.product_id = %d
				 AND s.completed = 1",
				$user_id,
				$product_id
			)
		);

		$percent = $total > 0 ? round( ( $completed / $total ) * 100, 1 ) : 0;

		return [
			'completed' => $completed,
			'total'     => $total,
			'percent'   => $percent,
		];
	}

	/**
	 * Log info message (pouze pokud WP_DEBUG).
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	private static function log_info( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [INFO] Videos: %s%s', $message, $context_str ) );
	}

	/**
	 * Log debug message (pouze pokud WP_DEBUG).
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	private static function log_debug( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [DEBUG] Videos: %s%s', $message, $context_str ) );
	}

	/**
	 * Log error message (vždy).
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context data.
	 */
	private static function log_error( string $message, array $context = [] ): void {
		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [ERROR] Videos: %s%s', $message, $context_str ) );
	}
}
