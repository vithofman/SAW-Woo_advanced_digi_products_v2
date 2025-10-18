<?php
/**
 * Video progress AJAX handlers.
 *
 * ✅ SIMPLIFIED VERSION - pouze start_session a mark_completed
 * ❌ REMOVED: save_progress (nepoužíváme auto-save)
 *
 * @package SAW\WAP\REST
 */

declare( strict_types=1 );

namespace SAW\WAP\REST;

use SAW\WAP\Core\VideoSessions;

/**
 * AJAX endpoints pro video tracking.
 */
class VideoProgressAPI {

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		// ✅ Jen 2 endpointy
		add_action( 'wp_ajax_saw_start_session', [ self::class, 'start_session' ] );
		add_action( 'wp_ajax_saw_mark_completed', [ self::class, 'mark_completed' ] );
		
		// ❌ ODSTRANĚNO: saw_save_progress
	}

	/**
	 * Start novou watch session.
	 * 
	 * POST params:
	 * - token_id: ID tokenu z wp_saw_video_access_tokens
	 * - video_index: Index videa
	 * 
	 * Returns:
	 * - session_id: ID vytvořené session
	 */
	public static function start_session(): void {
		check_ajax_referer( 'saw_watch_nonce', 'nonce' );

		$token_id    = isset( $_POST['token_id'] ) ? absint( $_POST['token_id'] ) : 0;
		$video_index = isset( $_POST['video_index'] ) ? absint( $_POST['video_index'] ) : 0;

		if ( ! $token_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid token ID', 'saw-wap' ) ] );
		}

		// Verify user má přístup
		$user_id = get_current_user_id();
		
		if ( ! VideoSessions::verify_token_ownership( $token_id, $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'saw-wap' ) ] );
		}

		// Start session
		$session_id = VideoSessions::start_session( $token_id );

		if ( ! $session_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to create session', 'saw-wap' ) ] );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'SAW-WAP: Session started - ID: %d, Token: %d, User: %d',
				$session_id,
				$token_id,
				$user_id
			) );
		}

		wp_send_json_success( [
			'session_id' => $session_id,
			'message'    => __( 'Session started', 'saw-wap' ),
		] );
	}

	/**
	 * Mark session jako completed (90%+ reached).
	 * 
	 * POST params:
	 * - session_id: ID session
	 * 
	 * Returns:
	 * - completed: true
	 * - course_progress: {completed, total, percent}
	 */
	public static function mark_completed(): void {
		check_ajax_referer( 'saw_watch_nonce', 'nonce' );

		$session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;

		if ( ! $session_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid session ID', 'saw-wap' ) ] );
		}

		// Get session data
		$session = VideoSessions::get_session( $session_id );

		if ( ! $session ) {
			wp_send_json_error( [ 'message' => __( 'Session not found', 'saw-wap' ) ] );
		}

		// Mark completed
		$updated = VideoSessions::mark_completed( $session_id );

		if ( ! $updated ) {
			wp_send_json_error( [ 'message' => __( 'Failed to mark completed', 'saw-wap' ) ] );
		}

		// Calculate celkový kurz progress
		$course_progress = self::calculate_course_progress( (int) $session->token_id );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'SAW-WAP: ✅ Video completed - Session: %d, Token: %d, Course: %d/%d (%d%%)',
				$session_id,
				$session->token_id,
				$course_progress['completed'],
				$course_progress['total'],
				$course_progress['percent']
			) );
		}

		/**
		 * Hook: Video completed (90%+)
		 * 
		 * @param int $token_id   Token ID
		 * @param int $session_id Session ID
		 */
		do_action( 'saw_video_completed', (int) $session->token_id, $session_id );

		wp_send_json_success( [
			'message'         => __( 'Video completed!', 'saw-wap' ),
			'completed'       => true,
			'course_progress' => $course_progress,
		] );
	}

	/**
	 * Calculate course progress for user.
	 * 
	 * @param int $token_id Token ID
	 * @return array {completed, total, percent}
	 */
	private static function calculate_course_progress( int $token_id ): array {
		global $wpdb;

		// Get token data
		$tokens_table = $wpdb->prefix . 'saw_video_access_tokens';
		$token = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, product_id FROM {$tokens_table} WHERE id = %d",
				$token_id
			)
		);

		if ( ! $token ) {
			return [
				'completed' => 0,
				'total'     => 0,
				'percent'   => 0,
			];
		}

		$user_id    = (int) $token->user_id;
		$product_id = (int) $token->product_id;

		// Total videos (excluding free)
		$metadata_table = $wpdb->prefix . 'saw_video_metadata';
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$metadata_table} 
				 WHERE product_id = %d AND is_free = 0",
				$product_id
			)
		);

		// Completed videos
		$sessions_table = $wpdb->prefix . 'saw_video_watch_sessions';
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

		return [
			'completed' => $completed,
			'total'     => $total,
			'percent'   => $percent,
		];
	}
}