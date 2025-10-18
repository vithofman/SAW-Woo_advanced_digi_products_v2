<?php
/**
 * Video session management.
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
	 * Prevence: User nemůže trackovat cizí videa.
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
	 * Start novou session nebo get existující.
	 * 
	 * Strategie: 
	 * - Pokud už má incomplete session pro tento token → use that
	 * - Jinak create new
	 * 
	 * @param int $token_id Token ID
	 * @return int|null Session ID nebo null při chybě
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
			// Update session_start = teď (nová návštěva)
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
				'token_id'         => $token_id,
				'session_start'    => current_time( 'mysql' ),
				'watch_duration'   => 0,
				'progress_percent' => 0,
				'completed'        => 0,
				'ip_address'       => self::get_client_ip(),
				'user_agent'       => self::get_user_agent(),
			],
			[ '%d', '%s', '%d', '%d', '%d', '%s', '%s' ]
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
	 * Update progress pro session.
	 * 
	 * @param int $session_id       Session ID
	 * @param int $watch_duration   Celkový watch time (sekundy)
	 * @param int $progress_percent 0-100 procent
	 * @return bool True při úspěchu
	 */
	public static function update_progress( int $session_id, int $watch_duration, int $progress_percent ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'saw_video_watch_sessions';

		$updated = $wpdb->update(
			$table,
			[
				'watch_duration'   => $watch_duration,
				'progress_percent' => $progress_percent,
				'session_end'      => current_time( 'mysql' ),
			],
			[ 'id' => $session_id ],
			[ '%d', '%d', '%s' ],
			[ '%d' ]
		);

		return false !== $updated;
	}

	/**
	 * Mark session jako completed.
	 * 
	 * @param int $session_id Session ID
	 * @return bool True při úspěchu
	 */
	public static function mark_completed( int $session_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'saw_video_watch_sessions';

		$updated = $wpdb->update(
			$table,
			[
				'completed'        => 1,
				'progress_percent' => 100,
				'session_end'      => current_time( 'mysql' ),
			],
			[ 'id' => $session_id ],
			[ '%d', '%d', '%s' ],
			[ '%d' ]
		);

		return false !== $updated;
	}

	/**
	 * Get session data.
	 * 
	 * @param int $session_id Session ID
	 * @return object|null Session object nebo null
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
	 * Supports: Cloudflare, proxy headers, direct connection.
	 * 
	 * @return string IP address (IPv4 nebo IPv6)
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

				// Handle comma-separated list (X-Forwarded-For)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = explode( ',', $ip )[0];
				}

				$ip = trim( $ip );

				// Validate IP
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
		
		// Truncate to 255 chars (DB column limit)
		return substr( $user_agent, 0, 255 );
	}
}