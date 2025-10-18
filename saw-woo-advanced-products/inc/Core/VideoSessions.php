<?php
/**
 * Video session management.
 *
 * ✅ SIMPLIFIED VERSION
 * ❌ REMOVED: update_progress (nepoužíváme)
 *
 * @package SAW\WAP\Core
 */

declare( strict_types=1 );

namespace SAW\WAP\Core;

/**
 * Handles video watch sessions.
 */
class VideoSessions {

	/**
	 * Verify že token patří uživateli.
	 * 
	 * @param int $token_id Token ID
	 * @param int $user_id  User ID
	 * @return bool True pokud token patří uživateli
	 */
	public static function verify_token_ownership( int $token_id, int $user_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'saw_video_access_tokens';

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} 
				 WHERE id = %d AND user_id = %d AND is_active = 1",
				$token_id,
				$user_id
			)
		);

		return $count > 0;
	}

	/**
	 * Start novou session nebo reuse existující incomplete.
	 * 
	 * @param int $token_id Token ID
	 * @return int|null Session ID nebo null
	 */
	public static function start_session( int $token_id ): ?int {
		global $wpdb;

		$table = $wpdb->prefix . 'saw_video_watch_sessions';

		// Check existující incomplete session
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} 
				 WHERE token_id = %d 
				 AND completed = 0
				 ORDER BY session_start DESC
				 LIMIT 1",
				$token_id
			)
		);

		if ( $existing ) {
			// Update session_start (nová návštěva)
			$wpdb->update(
				$table,
				[ 'session_start' => current_time( 'mysql' ) ],
				[ 'id' => $existing ],
				[ '%s' ],
				[ '%d' ]
			);

			return (int) $existing;
		}

		// Create new session
		$inserted = $wpdb->insert(
			$table,
			[
				'token_id'      => $token_id,
				'session_start' => current_time( 'mysql' ),
				'completed'     => 0,
				'ip_address'    => self::get_client_ip(),
				'user_agent'    => self::get_user_agent(),
			],
			[ '%d', '%s', '%d', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SAW-WAP: Failed to insert session. DB Error: ' . $wpdb->last_error );
			}
			return null;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * ❌ REMOVED: update_progress()
	 * Už nepoužíváme auto-save každých 10s
	 */

	/**
	 * Mark session jako completed.
	 * 
	 * ✅ SIMPLIFIED: Jen nastavit completed = 1
	 * 
	 * @param int $session_id Session ID
	 * @return bool True při úspěchu
	 */
	public static function mark_completed( int $session_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'saw_video_watch_sessions';

		// ✅ ZJEDNODUŠENO: Jen completed flag
		$updated = $wpdb->update(
			$table,
			[ 'completed' => 1 ],
			[ 'id' => $session_id ],
			[ '%d' ],
			[ '%d' ]
		);

		return false !== $updated;
	}

	/**
	 * Get session data.
	 * 
	 * @param int $session_id Session ID
	 * @return object|null Session object
	 */
	public static function get_session( int $session_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'saw_video_watch_sessions';

		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$session_id
			)
		);

		return $session ?: null;
	}

	/**
	 * Get client IP address.
	 * 
	 * @return string IP address
	 */
	private static function get_client_ip(): string {
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',  // Proxy
			'HTTP_X_REAL_IP',        // Nginx
			'REMOTE_ADDR',           // Direct
		];

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

				// Handle comma-separated list
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = explode( ',', $ip )[0];
				}

				$ip = trim( $ip );

				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Get user agent string.
	 * 
	 * @return string User agent (max 255 chars)
	 */
	private static function get_user_agent(): string {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		
		return substr( $user_agent, 0, 255 );
	}
}