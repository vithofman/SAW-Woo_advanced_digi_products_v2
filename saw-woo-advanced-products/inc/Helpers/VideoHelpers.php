<?php
/**
 * Video helper functions.
 *
 * @package SAW\WAP\Helpers
 */

namespace SAW\WAP\Helpers;

/**
 * Video rendering and formatting helpers.
 */
class VideoHelpers {

	/**
	 * Render video embed iframe.
	 *
	 * @param string|null $url Video URL
	 * @param string|null $provider Provider (youtube/vimeo/custom)
	 * @param string|null $title Video title
	 * @return string HTML iframe
	 */
	public static function render_video_embed( $url, $provider = null, $title = '' ): string {
		
		// Handle NULL or empty URL
		if ( empty( $url ) ) {
			return sprintf(
				'<div class="saw-video-error"><p>%s</p></div>',
				esc_html__( 'URL videa není nastaveno.', 'saw-wap' )
			);
		}
		
		$url = (string) $url;
		$title = ! empty( $title ) ? (string) $title : 'Video';
		
		// Auto-detect provider if empty
		if ( empty( $provider ) || 'custom' === $provider ) {
			$provider = self::detect_provider( $url );
		}

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
				$embed_url = esc_url( $url );
				break;
		}

		if ( empty( $embed_url ) ) {
			return sprintf(
				'<div class="saw-video-error"><p>%s</p></div>',
				esc_html__( 'Nelze načíst video. Zkontrolujte URL.', 'saw-wap' )
			);
		}

		return sprintf(
			'<div class="saw-video-embed"><iframe src="%s" frameborder="0" allow="autoplay; fullscreen; encrypted-media; picture-in-picture" allowfullscreen title="%s" loading="lazy"></iframe></div>',
			esc_url( $embed_url ),
			esc_attr( $title )
		);
	}

	/**
	 * Auto-detect video provider from URL.
	 *
	 * @param string $url Video URL
	 * @return string Provider
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
	 * Extract YouTube video ID from URL.
	 *
	 * @param string $url YouTube URL
	 * @return string|null Video ID
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
	 * Extract Vimeo video ID from URL.
	 *
	 * @param string $url Vimeo URL
	 * @return string|null Video ID
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
	 * Format duration in seconds to readable string.
	 *
	 * @param int $seconds Duration in seconds
	 * @return string Formatted duration
	 */
	public static function format_duration( int $seconds ): string {
		$seconds = abs( $seconds ); // Ensure positive
		
		if ( $seconds < 60 ) {
			return sprintf( '%d sec', $seconds );
		}

		if ( $seconds < 3600 ) {
			$minutes = floor( $seconds / 60 );
			return sprintf( '%d min', $minutes );
		}

		$hours = floor( $seconds / 3600 );
		$minutes = floor( ( $seconds % 3600 ) / 60 );

		if ( $minutes > 0 ) {
			return sprintf( '%dh %dmin', $hours, $minutes );
		}

		return sprintf( '%dh', $hours );
	}

	/**
	 * Format access expiration countdown
	 *
	 * @param string|null $expires_datetime MySQL datetime
	 * @return string Formatted text
	 */
	public static function format_access_expires( $expires_datetime ): string {
		
		if ( empty( $expires_datetime ) ) {
			return 'Trvalý přístup';
		}
		
		$now = current_time( 'timestamp' );
		$expires = strtotime( $expires_datetime );
		
		if ( false === $expires ) {
			return 'Neplatné datum';
		}

		$diff_seconds = $expires - $now;
		$days_left = (int) floor( $diff_seconds / DAY_IN_SECONDS );

		if ( $days_left > 30 ) {
			return sprintf( 'Přístup do: %d dní', $days_left );
		} elseif ( $days_left >= 7 ) {
			return sprintf( 'Brzy vyprší: %d dní', $days_left );
		} elseif ( $days_left > 0 ) {
			return sprintf( 'POZOR: Vyprší za %d dní', $days_left );
		} else {
			return 'Přístup vypršel';
		}
	}

	/**
	 * Check if video is completed.
	 *
	 * @param int $user_id User ID
	 * @param int $product_id Product ID
	 * @param int $video_index Video index
	 * @return bool True if completed
	 */
	public static function is_video_completed( int $user_id, int $product_id, int $video_index ): bool {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'saw_video_watch_sessions';
		$tokens_table = $wpdb->prefix . 'saw_video_access_tokens';

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
	 * Get token for specific video.
	 *
	 * @param int $user_id User ID
	 * @param int $product_id Product ID
	 * @param int $video_index Video index
	 * @return string|null Token or null
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

		return $token ? (string) $token : null;
	}
}