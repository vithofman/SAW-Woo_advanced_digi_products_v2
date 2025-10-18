<?php
/**
 * Videos class - DEPRECATED - DO NOT USE!
 * 
 * This class was used for the old rewrite rule system (/watch/{token}/).
 * We now use SHORTCODE system instead: [saw_video_course_player]
 * 
 * DO NOT INITIALIZE THIS CLASS! It creates full HTML documents which
 * conflicts with Oxygen Builder templates!
 * 
 * @package SAW\WAP\Core
 * @deprecated Use Shortcodes::render_video_course_player() instead
 */

namespace SAW\WAP\Core;

use SAW\WAP\Core\VideoTokenManager;

class Videos {
	private static $token_manager = null;

	/**
	 * DO NOT CALL THIS METHOD!
	 * This class is deprecated and should not be used.
	 * 
	 * @deprecated Use shortcode [saw_video_course_player] instead
	 */
	public static function init() {
		// ❌ DEAKTIVOVÁNO - Způsobuje konflikt s Oxygen Builder!
		// add_action( 'init', array( 'SAW\WAP\Core\Videos', 'add_watch_endpoint' ) );
		// add_filter( 'query_vars', array( 'SAW\WAP\Core\Videos', 'add_query_vars' ) );
		// add_action( 'template_redirect', array( 'SAW\WAP\Core\Videos', 'watch_template_redirect' ), 1 );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'SAW-WAP: Videos::init() was called but is DEPRECATED. Use shortcode [saw_video_course_player] instead!' );
		}
	}

	private static function get_token_manager() {
		if ( null === self::$token_manager ) {
			self::$token_manager = new VideoTokenManager();
		}
		return self::$token_manager;
	}

	public static function add_watch_endpoint() {
		// DEAKTIVOVÁNO
	}

	public static function add_query_vars( $vars ) {
		// DEAKTIVOVÁNO
		return $vars;
	}

	public static function watch_template_redirect() {
		// DEAKTIVOVÁNO
	}

	private static function load_watch_template() {
		// DEAKTIVOVÁNO
	}

	private static function get_video_by_token( $access ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'saw_video_metadata';

		$video = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE product_id = %d AND video_index = %d LIMIT 1",
				$access->product_id,
				$access->video_index
			)
		);

		return $video ? $video : null;
	}

	private static function get_all_product_videos( $product_id ) {
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

	public static function get_user_progress( $user_id, $product_id ) {
		global $wpdb;

		$sessions_table = $wpdb->prefix . 'saw_video_watch_sessions';
		$tokens_table   = $wpdb->prefix . 'saw_video_access_tokens';
		$metadata_table = $wpdb->prefix . 'saw_video_metadata';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$metadata_table} WHERE product_id = %d AND is_free = 0",
				$product_id
			)
		);

		$completed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT t.video_index) FROM {$tokens_table} t INNER JOIN {$sessions_table} s ON s.token_id = t.id WHERE t.user_id = %d AND t.product_id = %d AND s.completed = 1",
				$user_id,
				$product_id
			)
		);

		$percent = $total > 0 ? round( ( $completed / $total ) * 100, 1 ) : 0;

		return array(
			'completed' => $completed,
			'total'     => $total,
			'percent'   => $percent,
		);
	}

	private static function log_debug( $message, $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'SAW-WAP [DEBUG] Videos (DEPRECATED): ' . $message );
		}
	}
}