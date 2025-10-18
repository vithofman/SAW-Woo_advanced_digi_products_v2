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
	 * Handle watch page template redirect.
	 *
	 * Tento hook se spustí před načtením jakéhokoliv template.
	 * Pokud detekujeme watch request, převezmeme kontrolu.
	 */
	public static function watch_template_redirect(): void {
		// Získat token z query var
		$token = get_query_var( 'saw_watch_token', '' );

		// Pokud není watch request → return (normální WP flow)
		if ( empty( $token ) ) {
			return;
		}

		self::log_info( 'Watch request detected', [ 'token' => substr( $token, 0, 16 ) . '...' ] );

		// --- SECURITY CHECKS ---

		// 1. Musí být přihlášený uživatel
		if ( ! is_user_logged_in() ) {
			self::log_debug( 'User not logged in, redirecting to login' );

			wp_safe_redirect(
				add_query_arg(
					'redirect_to',
					urlencode( home_url( '/watch/' . $token . '/' ) ),
					wp_login_url()
				)
			);
			exit;
		}

		$current_user_id = get_current_user_id();

		// 2. Validovat token přes VideoTokenManager
		try {
			$token_manager = self::get_token_manager();
			$access        = $token_manager->validateToken( $token );

			if ( null === $access ) {
				self::log_debug( 'Token validation failed', [
					'token'   => substr( $token, 0, 16 ) . '...',
					'user_id' => $current_user_id,
				] );

				self::redirect_with_error(
					wc_get_account_endpoint_url( 'saw-videos' ),
					__( 'Přístup k videu byl odepřen. Token může být neplatný nebo již vypršel.', 'saw-wap' )
				);
			}

			// 3. Ověřit že token patří aktuálnímu uživateli
			if ( (int) $access->user_id !== $current_user_id ) {
				self::log_error( 'Token belongs to different user', [
					'token_user_id'   => $access->user_id,
					'current_user_id' => $current_user_id,
					'token'           => substr( $token, 0, 16 ) . '...',
				] );

				wp_die(
					esc_html__( 'Nemáte oprávnění k zobrazení tohoto videa.', 'saw-wap' ),
					esc_html__( 'Neautorizovaný přístup', 'saw-wap' ),
					[ 'response' => 403 ]
				);
			}

			self::log_info( 'Token validated successfully', [
				'token_id'    => $access->id,
				'user_id'     => $access->user_id,
				'product_id'  => $access->product_id,
				'video_index' => $access->video_index,
			] );

		} catch ( \Exception $e ) {
			self::log_error( 'Token validation exception: ' . $e->getMessage() );

			self::redirect_with_error(
				wc_get_account_endpoint_url( 'saw-videos' ),
				__( 'Nastala chyba při ověřování přístupu k videu.', 'saw-wap' )
			);
		}

		// --- DATA LOADING ---

		// Načíst video metadata z DB
		$video = self::get_video_by_token( $access );

		if ( ! $video ) {
			self::log_error( 'Video not found in database', [
				'product_id'  => $access->product_id,
				'video_index' => $access->video_index,
			] );

			self::redirect_with_error(
				wc_get_account_endpoint_url( 'saw-videos' ),
				__( 'Video nebylo nalezeno.', 'saw-wap' )
			);
		}

		// Načíst product info
		$product = wc_get_product( (int) $access->product_id );

		if ( ! $product ) {
			self::log_error( 'Product not found', [ 'product_id' => $access->product_id ] );

			self::redirect_with_error(
				wc_get_account_endpoint_url( 'saw-videos' ),
				__( 'Kurz již není dostupný.', 'saw-wap' )
			);
		}

		// Načíst všechna videa produktu (pro navigaci)
		$all_videos = self::get_all_product_videos( (int) $access->product_id );

		// Najít current index v seznamu
		$current_index = 0;
		foreach ( $all_videos as $idx => $vid ) {
			if ( (int) $vid->video_index === (int) $access->video_index ) {
				$current_index = $idx;
				break;
			}
		}

		// Předchozí a další video tokeny
		$prev_token = null;
		$next_token = null;

		if ( $current_index > 0 ) {
			$prev_video = $all_videos[ $current_index - 1 ];
			$prev_token = self::get_user_video_token( $current_user_id, (int) $access->product_id, (int) $prev_video->video_index );
		}

		if ( $current_index < count( $all_videos ) - 1 ) {
			$next_video = $all_videos[ $current_index + 1 ];
			$next_token = self::get_user_video_token( $current_user_id, (int) $access->product_id, (int) $next_video->video_index );
		}

		// Progress info
		$progress = self::get_user_progress( $current_user_id, (int) $access->product_id );

		// --- SET GLOBALS PRO TEMPLATE ---
		global $saw_watch_data;

		$saw_watch_data = [
			'access'        => $access,
			'video'         => $video,
			'product'       => $product,
			'all_videos'    => $all_videos,
			'current_index' => $current_index,
			'prev_token'    => $prev_token,
			'next_token'    => $next_token,
			'progress'      => $progress,
		];

		// --- LOAD TEMPLATE ---
		self::load_watch_template();
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