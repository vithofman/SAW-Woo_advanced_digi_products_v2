<?php
/**
 * Video progress AJAX handlers.
 *
 * @package SAW\WAP\REST
 */

declare( strict_types=1 );

namespace SAW\WAP\REST;

use SAW\WAP\Core\VideoSessions;

/**
 * AJAX endpoints pro video progress tracking.
 */
class VideoProgressAPI {

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		// AJAX pro přihlášené uživatele
		add_action( 'wp_ajax_saw_start_session', [ self::class, 'start_session' ] );
		add_action( 'wp_ajax_saw_save_progress', [ self::class, 'save_progress' ] );
		add_action( 'wp_ajax_saw_mark_completed', [ self::class, 'mark_completed' ] );
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
		// Nonce check
		check_ajax_referer( 'saw_watch_nonce', 'nonce' );

		// Get params
		$token_id    = isset( $_POST['token_id'] ) ? absint( $_POST['token_id'] ) : 0;
		$video_index = isset( $_POST['video_index'] ) ? absint( $_POST['video_index'] ) : 0;

		if ( ! $token_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid token ID', 'saw-wap' ) ] );
		}

		// Verify user má přístup k tomuto tokenu
		$user_id = get_current_user_id();
		
		if ( ! VideoSessions::verify_token_ownership( $token_id, $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'saw-wap' ) ] );
		}

		// Start session
		$session_id = VideoSessions::start_session( $token_id );

		if ( ! $session_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to create session', 'saw-wap' ) ] );
		}

		// Log pro debug
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
	 * Save progress pro existující session.
	 * 
	 * POST params:
	 * - session_id: ID session
	 * - watch_duration: Sekundy sledování
	 * - progress_percent: 0-100 procent
	 */
	public static function save_progress(): void {
		check_ajax_referer( 'saw_watch_nonce', 'nonce' );

		$session_id       = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
		$watch_duration   = isset( $_POST['watch_duration'] ) ? absint( $_POST['watch_duration'] ) : 0;
		$progress_percent = isset( $_POST['progress_percent'] ) ? absint( $_POST['progress_percent'] ) : 0;

		if ( ! $session_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid session ID', 'saw-wap' ) ] );
		}

		// Clamp progress_percent 0-100
		$progress_percent = max( 0, min( 100, $progress_percent ) );

		// Update session
		$updated = VideoSessions::update_progress( $session_id, $watch_duration, $progress_percent );

		if ( ! $updated ) {
			wp_send_json_error( [ 'message' => __( 'Failed to save progress', 'saw-wap' ) ] );
		}

		// Debug log (pouze pokud WP_DEBUG)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ( $progress_percent % 25 === 0 ) ) {
			error_log( sprintf(
				'SAW-WAP: Progress saved - Session: %d, Duration: %ds, Progress: %d%%',
				$session_id,
				$watch_duration,
				$progress_percent
			) );
		}

		wp_send_json_success( [
			'message'          => __( 'Progress saved', 'saw-wap' ),
			'watch_duration'   => $watch_duration,
			'progress_percent' => $progress_percent,
		] );
	}

	/**
	 * Mark session jako completed.
	 * 
	 * POST params:
	 * - session_id: ID session
	 */
	public static function mark_completed(): void {
		check_ajax_referer( 'saw_watch_nonce', 'nonce' );

		$session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;

		if ( ! $session_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid session ID', 'saw-wap' ) ] );
		}

		// Get session data pro hooks
		$session = VideoSessions::get_session( $session_id );

		if ( ! $session ) {
			wp_send_json_error( [ 'message' => __( 'Session not found', 'saw-wap' ) ] );
		}

		// Mark completed
		$updated = VideoSessions::mark_completed( $session_id );

		if ( ! $updated ) {
			wp_send_json_error( [ 'message' => __( 'Failed to mark completed', 'saw-wap' ) ] );
		}

		// Log success
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'SAW-WAP: Video completed - Session: %d, Token: %d',
				$session_id,
				$session->token_id
			) );
		}

		/**
		 * Fires když uživatel dokončí video (≥ 90%).
		 * 
		 * Use cases:
		 * - Poslat gratulační email
		 * - Vygenerovat certifikát
		 * - Přidat gamifikační body
		 * 
		 * @param int $token_id   ID access tokenu
		 * @param int $session_id ID watch session
		 */
		do_action( 'saw_video_completed', (int) $session->token_id, $session_id );

		wp_send_json_success( [
			'message'   => __( 'Video completed!', 'saw-wap' ),
			'completed' => true,
		] );
	}
}