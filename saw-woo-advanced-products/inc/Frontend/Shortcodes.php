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
		
		// My Account shortcode
		add_shortcode( 'saw_my_account', array( self::class, 'render_my_account' ) );
	}

	/**
	 * =========================================================================
	 * SHORTCODE: [saw_my_account]
	 * =========================================================================
	 * 
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public static function render_my_account( $atts = array() ): string {
		$atts = is_array( $atts ) ? $atts : array();
		
		// ⚠️ NIKDY NEPOUŽÍVAT exit() nebo wp_safe_redirect() V SHORTCODU!
		// To zabíjí Oxygen template rendering!
		
		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( add_query_arg( array() ) );
			
			return sprintf(
				'<div style="padding: 80px 20px; text-align: center; background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%); border-radius: 16px; margin: 40px 0;">
					<div style="max-width: 500px; margin: 0 auto; background: white; padding: 60px 40px; border-radius: 12px;">
						<div style="font-size: 72px; margin-bottom: 24px;">🔒</div>
						<h2 style="font-size: 32px; margin: 0 0 16px 0; color: #1a1a1a;">Přihlášení vyžadováno</h2>
						<p style="font-size: 18px; color: #666; margin: 0 0 40px 0;">Pro přístup k vašemu účtu se prosím přihlaste.</p>
						<a href="%s" style="display: inline-block; padding: 18px 48px; background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%); color: #fff; text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 18px;">
							Přihlásit se →
						</a>
					</div>
				</div>',
				esc_url( $login_url )
			);
		}
		
		$current_user = wp_get_current_user();
		$user_id = $current_user->ID;
		$current_endpoint = MyAccount::get_current_endpoint();
		
		self::enqueue_my_account_assets();
		
		$data = array(
			'user'             => $current_user,
			'user_id'          => $user_id,
			'current_endpoint' => $current_endpoint,
			'menu_items'       => MyAccount::get_menu_items( $current_endpoint ),
		);
		
		ob_start();
		
		try {
			extract( $data, EXTR_SKIP );
			
			$template_path = SAW_WAP_PATH . 'templates/shortcode-my-account.php';
			
			if ( ! file_exists( $template_path ) ) {
				throw new \Exception( 'Template not found: ' . $template_path );
			}
			
			include $template_path;
			
		} catch ( \Exception $e ) {
			ob_end_clean();
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP My Account Error: ' . $e->getMessage() );
			}
			
			return sprintf(
				'<div style="padding: 20px; background: #fee; border: 2px solid #c00; border-radius: 8px; margin: 20px 0;">
					<p style="color: #c00; font-weight: bold; margin: 0 0 10px;">❌ Chyba načítání účtu</p>
					<p style="margin: 0;">%s</p>
				</div>',
				esc_html( $e->getMessage() )
			);
		}
		
		return ob_get_clean();
	}

	/**
	 * Enqueue CSS and JS for My Account
	 */
	private static function enqueue_my_account_assets(): void {
		wp_enqueue_style(
			'sawwap-my-account',
			SAW_WAP_URL . 'assets/css/my-account.css',
			array(),
			filemtime( SAW_WAP_PATH . 'assets/css/my-account.css' )
		);
		
		wp_enqueue_script(
			'sawwap-my-account',
			SAW_WAP_URL . 'assets/js/my-account.js',
			array( 'jquery' ),
			filemtime( SAW_WAP_PATH . 'assets/js/my-account.js' ),
			true
		);
		
		wp_enqueue_script(
			'sawwap-my-account-inline',
			SAW_WAP_URL . 'assets/js/my-account-inline.js',
			array(),
			filemtime( SAW_WAP_PATH . 'assets/js/my-account-inline.js' ),
			true
		);
		
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
	 */
	public static function render_video_course_player( $atts ): string {
		
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		
		$token = trim( $token, " \t\n\r\0\x0B/" );
		
		if ( empty( $token ) ) {
			return self::render_error( 'missing_token', 'video_player' );
		}
		
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return self::render_error( 'invalid_format', 'video_player' );
		}
		
		$current_user_id = get_current_user_id();
		
		if ( 0 === $current_user_id ) {
			$login_url = wp_login_url( add_query_arg( 'token', $token, get_permalink() ) );
			wp_safe_redirect( $login_url );
			exit;
		}
		
		try {
			$token_manager = new VideoTokenManager();
			$access = $token_manager->validateToken( $token );
		} catch ( \Exception $e ) {
			return self::render_error( 'invalid_token', 'video_player' );
		}
		
		if ( ! $access ) {
			return self::render_error( 'invalid_token', 'video_player' );
		}
		
		$product_id = (int) $access->product_id;
		$video_index = (int) $access->video_index;
		$user_id = (int) $access->user_id;
		
		if ( $user_id !== $current_user_id ) {
			return self::render_error( 'access_denied', 'video_player' );
		}
		
		$video = self::get_video_by_token( $product_id, $video_index );
		
		if ( ! $video ) {
			return self::render_error( 'video_not_found', 'video_player' );
		}
		
		$product = wc_get_product( $product_id );
		
		if ( ! $product ) {
			return self::render_error( 'product_not_found', 'video_player' );
		}
		
		$all_videos = self::get_all_product_videos( $product_id );
		$prev_token = self::get_prev_video_token( $user_id, $product_id, $video_index );
		$next_token = self::get_next_video_token( $user_id, $product_id, $video_index );
		$progress = self::get_user_progress( $user_id, $product_id );
		
		self::enqueue_video_player_assets( $token );
		
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
		
		ob_start();
		
		try {
			extract( $data, EXTR_SKIP );
			
			$template_path = SAW_WAP_PATH . 'templates/shortcode-video-course-player.php';
			
			if ( ! file_exists( $template_path ) ) {
				throw new \Exception( 'Template not found: ' . $template_path );
			}
			
			include $template_path;
			
		} catch ( \Exception $e ) {
			ob_end_clean();
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP Video Player Error: ' . $e->getMessage() );
			}
			
			return self::render_error( 'template_error', 'video_player' );
		}
		
		return ob_get_clean();
	}

	private static function enqueue_video_player_assets( string $token ): void {
		wp_enqueue_style( 'sawwap-watch-video' );
		wp_enqueue_script( 'sawwap-video-player-tracker' );
		
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
			'<div style="padding: 40px; text-align: center; background: #fff3cd; border: 2px solid #ffc107; border-radius: 12px; margin: 40px 0;">
				<div style="font-size: 48px; margin-bottom: 16px;">⚠️</div>
				<h3 style="margin: 0 0 12px 0; color: #856404;">Přístup odmítnut</h3>
				<p style="margin: 0 0 24px 0; color: #856404;">%s</p>
				<a href="%s" style="display: inline-block; padding: 12px 24px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600;">
					← Zpět na Můj účet
				</a>
			</div>',
			esc_html( $message ),
			esc_url( home_url( '/muj-ucet/' ) )
		);
	}
}