<?php
namespace SAW\WAP\Core;

use SAW\WAP\Core\VideoTokenManager;

class Videos {
	private static $token_manager = null;

	public static function init() {
		add_action( 'init', array( 'SAW\WAP\Core\Videos', 'add_watch_endpoint' ) );
		add_filter( 'query_vars', array( 'SAW\WAP\Core\Videos', 'add_query_vars' ) );
		add_action( 'template_redirect', array( 'SAW\WAP\Core\Videos', 'watch_template_redirect' ), 1 );
	}

	private static function get_token_manager() {
		if ( null === self::$token_manager ) {
			self::$token_manager = new VideoTokenManager();
		}
		return self::$token_manager;
	}

	public static function add_watch_endpoint() {
		add_rewrite_rule(
			'^watch/([a-f0-9]{64})/?$',
			'index.php?saw_watch_token=$matches[1]',
			'top'
		);
	}

	public static function add_query_vars( $vars ) {
		$vars[] = 'saw_watch_token';
		return $vars;
	}

	public static function watch_template_redirect() {
		$token = get_query_var( 'saw_watch_token', '' );
		
		if ( empty( $token ) ) {
			return;
		}

		echo '<div style="background:#4caf50;color:#fff;padding:10px;margin:5px;">✅ Step 1: Token received</div>';

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect(
				add_query_arg(
					'redirect_to',
					urlencode( home_url( '/watch/' . $token . '/' ) ),
					wp_login_url()
				)
			);
			exit;
		}

		echo '<div style="background:#4caf50;color:#fff;padding:10px;margin:5px;">✅ Step 2: User logged in</div>';

		$current_user_id = get_current_user_id();

		try {
			$token_manager = self::get_token_manager();
			echo '<div style="background:#4caf50;color:#fff;padding:10px;margin:5px;">✅ Step 3: Token manager created</div>';

			$access = $token_manager->validateToken( $token );
			
			if ( null === $access ) {
				echo '<div style="background:#f44336;color:#fff;padding:10px;margin:5px;">❌ Invalid token</div>';
				wp_die( 'Invalid token' );
			}

			echo '<div style="background:#4caf50;color:#fff;padding:10px;margin:5px;">✅ Step 4: Token valid</div>';

		} catch ( Exception $e ) {
			echo '<div style="background:#f44336;color:#fff;padding:10px;margin:5px;">❌ Exception: ' . esc_html( $e->getMessage() ) . '</div>';
			wp_die( 'Error' );
		}

		if ( (int) $access->user_id !== $current_user_id ) {
			echo '<div style="background:#f44336;color:#fff;padding:10px;margin:5px;">❌ User mismatch</div>';
			wp_die( 'Access denied' );
		}

		echo '<div style="background:#4caf50;color:#fff;padding:10px;margin:5px;">✅ Step 5: User verified</div>';

		// TEST: Get video by token
		$video = self::get_video_by_token( $access );
		
		if ( ! $video ) {
			echo '<div style="background:#f44336;color:#fff;padding:10px;margin:5px;">❌ Video not found</div>';
			wp_die( 'Video not found' );
		}

		echo '<div style="background:#4caf50;color:#fff;padding:10px;margin:5px;">✅ Step 6: Video loaded: ' . esc_html( $video->video_title ) . '</div>';

		// TEST: Get product
		$product = wc_get_product( $access->product_id );
		
		if ( ! $product ) {
			echo '<div style="background:#f44336;color:#fff;padding:10px;margin:5px;">❌ Product not found</div>';
			wp_die( 'Product not found' );
		}

		echo '<div style="background:#4caf50;color:#fff;padding:10px;margin:5px;">✅ Step 7: Product loaded: ' . esc_html( $product->get_name() ) . '</div>';

		// TEST: Get all videos
		echo '<div style="background:#2196f3;color:#fff;padding:10px;margin:5px;">⏳ Step 8: Loading all videos...</div>';
		flush();

		$all_videos = self::get_all_product_videos( $access->product_id );

		echo '<div style="background:#4caf50;color:#fff;padding:10px;margin:5px;">✅ Step 9: Loaded ' . count( $all_videos ) . ' videos</div>';

		// TEST: Get progress
		echo '<div style="background:#2196f3;color:#fff;padding:10px;margin:5px;">⏳ Step 10: Calculating progress...</div>';
		flush();

		$progress = self::get_user_progress( $current_user_id, $access->product_id );

		echo '<div style="background:#4caf50;color:#fff;padding:10px;margin:5px;">✅ Step 11: Progress calculated: ' . $progress['completed'] . '/' . $progress['total'] . '</div>';

		// TEST: Prepare data
		global $saw_watch_data;
		$saw_watch_data = array(
			'access'        => $access,
			'video'         => $video,
			'product'       => $product,
			'all_videos'    => $all_videos,
			'current_index' => 0,
			'prev_token'    => null,
			'next_token'    => null,
			'progress'      => $progress,
		);

		echo '<div style="background:#4caf50;color:#fff;padding:10px;margin:5px;">✅ Step 12: Data prepared</div>';

		// TEST: Load template
		echo '<div style="background:#2196f3;color:#fff;padding:10px;margin:5px;">⏳ Step 13: Loading template...</div>';
		flush();

		self::load_watch_template();
	}

	private static function load_watch_template() {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	$template = SAW_WAP_PATH . 'templates/watch-video.php';

	$theme_template = locate_template( array( 'woocommerce/saw-wap/watch-video.php' ) );
	if ( $theme_template ) {
		$template = $theme_template;
	}

	if ( ! file_exists( $template ) ) {
		wp_die( 'Template not found' );
	}

	// ❌ VYPNI get_header() a get_footer() - způsobují chybu v theme!
	// get_header();

	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php bloginfo( 'name' ); ?> - Watch Video</title>
		<?php wp_head(); ?>
	</head>
	<body <?php body_class( 'saw-watch-page' ); ?>>
	<?php

	include $template;

	?>
	<?php wp_footer(); ?>
	</body>
	</html>
	<?php
	
	exit;
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
			error_log( 'SAW-WAP [DEBUG] Videos: ' . $message );
		}
	}
}