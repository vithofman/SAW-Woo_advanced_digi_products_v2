<?php
/**
 * Video helper functions.
 *
 * @package SAW\WAP\Helpers
 */

declare( strict_types=1 );

namespace SAW\WAP\Helpers;

/**
 * Video rendering and formatting helpers.
 */
class VideoHelpers {

	/**
	 * Render video embed iframe.
	 *
	 * Podporuje YouTube, Vimeo, a custom URL.
	 *
	 * @param string $url      Video URL.
	 * @param string $provider Video provider ('youtube', 'vimeo', 'custom').
	 * @param string $title    Video title pro accessibility.
	 *
	 * @return string HTML iframe tag.
	 */
	public static function render_video_embed( string $url, string $provider, string $title ): string {
		// Auto-detect provider pokud není specifikovaný nebo je prázdný
		if ( empty( $provider ) || 'custom' === $provider ) {
			$provider = self::detect_provider( $url );
		}

		// Získat embed URL podle providera
		$embed_url = '';

		switch ( $provider ) {
			case 'youtube':
				$video_id = self::extract_youtube_id( $url );
				if ( $video_id ) {
					$embed_url = sprintf(
						'https://www.youtube.com/embed/%s?autoplay=0&rel=0&modestbranding=1&enablejsapi=1',
						$video_id
					);
				}
				break;

			case 'vimeo':
				$video_id = self::extract_vimeo_id( $url );
				if ( $video_id ) {
					$embed_url = sprintf(
						'https://player.vimeo.com/video/%s?autoplay=0&color=ffffff&title=0&byline=0&portrait=0',
						$video_id
					);
				}
				break;

			case 'custom':
			default:
				// Pro custom URL použít přímo (např. self-hosted video)
				$embed_url = esc_url( $url );
				break;
		}

		// Pokud se nepodařilo získat embed URL, vrátit chybovou zprávu
		if ( empty( $embed_url ) ) {
			return sprintf(
				'<div class="saw-video-error"><p>%s</p></div>',
				esc_html__( 'Nelze načíst video. Zkontrolujte URL.', 'saw-wap' )
			);
		}

		// Vygenerovat iframe s bezpečnostními atributy
		$iframe = sprintf(
			'<iframe src="%s" frameborder="0" allow="autoplay; fullscreen; encrypted-media; picture-in-picture" allowfullscreen sandbox="allow-scripts allow-same-origin allow-presentation" title="%s" loading="lazy"></iframe>',
			esc_url( $embed_url ),
			esc_attr( $title )
		);

		return $iframe;
	}

	/**
	 * Auto-detect video provider z URL.
	 *
	 * @param string $url Video URL.
	 *
	 * @return string Provider ('youtube', 'vimeo', nebo 'custom').
	 */
	private static function detect_provider( string $url ): string {
		if ( strpos( $url, 'youtube.com' ) !== false || strpos( $url, 'youtu.be' ) !== false ) {
			return 'youtube';
		}

		if ( strpos( $url, 'vimeo.com' ) !== false ) {
			return 'vimeo';
		}

		return 'custom';
	}

	/**
	 * Extract YouTube video ID z URL.
	 *
	 * Podporuje formáty:
	 * - https://www.youtube.com/watch?v=ABC123
	 * - https://youtu.be/ABC123
	 * - https://www.youtube.com/embed/ABC123
	 *
	 * @param string $url YouTube URL.
	 *
	 * @return string|null Video ID nebo null.
	 */
	public static function extract_youtube_id( string $url ): ?string {
		// Pattern 1: youtube.com/watch?v=VIDEO_ID
		if ( preg_match( '/[?&]v=([a-zA-Z0-9_-]{11})/', $url, $matches ) ) {
			return $matches[1];
		}

		// Pattern 2: youtu.be/VIDEO_ID
		if ( preg_match( '/youtu\.be\/([a-zA-Z0-9_-]{11})/', $url, $matches ) ) {
			return $matches[1];
		}

		// Pattern 3: youtube.com/embed/VIDEO_ID
		if ( preg_match( '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/', $url, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Extract Vimeo video ID z URL.
	 *
	 * Podporuje formáty:
	 * - https://vimeo.com/123456789
	 * - https://player.vimeo.com/video/123456789
	 *
	 * @param string $url Vimeo URL.
	 *
	 * @return string|null Video ID nebo null.
	 */
	public static function extract_vimeo_id( string $url ): ?string {
		// Pattern 1: vimeo.com/VIDEO_ID
		if ( preg_match( '/vimeo\.com\/(\d+)/', $url, $matches ) ) {
			return $matches[1];
		}

		// Pattern 2: player.vimeo.com/video/VIDEO_ID
		if ( preg_match( '/player\.vimeo\.com\/video\/(\d+)/', $url, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Format duration v sekundách na čitelný string.
	 *
	 * @param int $seconds Počet sekund.
	 *
	 * @return string Formátovaný čas.
	 */
	public static function format_duration( int $seconds ): string {
		if ( $seconds < 60 ) {
			/* translators: %d: number of seconds */
			return sprintf( _n( '%d sec', '%d sec', $seconds, 'saw-wap' ), $seconds );
		}

		if ( $seconds < 3600 ) {
			$minutes = floor( $seconds / 60 );
			/* translators: %d: number of minutes */
			return sprintf( _n( '%d min', '%d min', $minutes, 'saw-wap' ), $minutes );
		}

		$hours   = floor( $seconds / 3600 );
		$minutes = floor( ( $seconds % 3600 ) / 60 );

		if ( $minutes > 0 ) {
			/* translators: 1: hours, 2: minutes */
			return sprintf( __( '%1$dh %2$dmin', 'saw-wap' ), $hours, $minutes );
		}

		/* translators: %d: number of hours */
		return sprintf( _n( '%dh', '%dh', $hours, 'saw-wap' ), $hours );
	}

	/**
	 * Format countdown do expirace tokenu.
	 *
	 * Vrací text, CSS třídu, a počet zbývajících dní.
	 *
	 * @param string $expires_datetime MySQL datetime string.
	 *
	 * @return array{text: string, class: string, days_left: int} Countdown data.
	 */
	public static function format_countdown( string $expires_datetime ): array {
		$now     = current_time( 'timestamp' );
		$expires = strtotime( $expires_datetime );

		$diff_seconds = $expires - $now;
		$days_left    = (int) floor( $diff_seconds / DAY_IN_SECONDS );

		// Určit text a CSS třídu podle zbývajících dní
		if ( $days_left > 30 ) {
			/* translators: %d: number of days */
			$text  = sprintf( __( 'Přístup do: %d dní', 'saw-wap' ), $days_left );
			$class = 'saw-countdown-ok';
		} elseif ( $days_left >= 7 ) {
			/* translators: %d: number of days */
			$text  = sprintf( __( 'Brzy vyprší: %d dní', 'saw-wap' ), $days_left );
			$class = 'saw-countdown-warning';
		} else {
			/* translators: %d: number of days */
			$text  = sprintf( __( 'POZOR: Vyprší za %d dní', 'saw-wap' ), max( 0, $days_left ) );
			$class = 'saw-countdown-critical';
		}

		return [
			'text'      => $text,
			'class'     => $class,
			'days_left' => max( 0, $days_left ),
		];
	}

	/**
	 * Check pokud je video dokončené.
	 *
	 * @param int $user_id     User ID.
	 * @param int $product_id  Product ID.
	 * @param int $video_index Video index.
	 *
	 * @return bool True pokud dokončeno.
	 */
	public static function is_video_completed( int $user_id, int $product_id, int $video_index ): bool {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'saw_video_watch_sessions';
		$tokens_table   = $wpdb->prefix . 'saw_video_access_tokens';

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
				 FROM {$sessions_table} s
				 INNER JOIN {$tokens_table} t ON s.token_id = t.id
				 WHERE t.user_id = %d
				 AND t.product_id = %d
				 AND t.video_index = %d
				 AND s.completed = 1",
				$user_id,
				$product_id,
				$video_index
			)
		);

		return $count > 0;
	}

	/**
	 * Get completion icon podle stavu.
	 *
	 * @param bool $is_completed Je dokončené?
	 * @param bool $is_current   Je aktuální?
	 *
	 * @return string HTML span s ikonou.
	 */
	public static function get_completion_icon( bool $is_completed, bool $is_current ): string {
		if ( $is_completed ) {
			return '<span class="saw-icon saw-icon-completed" aria-label="' . esc_attr__( 'Dokončeno', 'saw-wap' ) . '">✓</span>';
		}

		if ( $is_current ) {
			return '<span class="saw-icon saw-icon-current" aria-label="' . esc_attr__( 'Aktuální', 'saw-wap' ) . '">►</span>';
		}

		return '<span class="saw-icon saw-icon-pending" aria-label="' . esc_attr__( 'Neshlédnuto', 'saw-wap' ) . '">○</span>';
	}

	/**
	 * Get token pro konkrétní video uživatele.
	 *
	 * @param int $user_id     User ID.
	 * @param int $product_id  Product ID.
	 * @param int $video_index Video index.
	 *
	 * @return string|null Token nebo null.
	 */
	public static function get_user_video_token( int $user_id, int $product_id, int $video_index ): ?string {
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
}