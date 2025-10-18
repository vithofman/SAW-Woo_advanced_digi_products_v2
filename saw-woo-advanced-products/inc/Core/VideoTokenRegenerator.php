<?php
/**
 * Video Token Regenerator
 * 
 * Automaticky generuje chybějící tokeny když admin přidá nová videa.
 *
 * @package SAW\WAP\Core
 */

declare(strict_types=1);

namespace SAW\WAP\Core;

use SAW\WAP\Core\VideoTokenManager;

if (!defined('ABSPATH')) {
	exit;
}

class VideoTokenRegenerator {

	/**
	 * Initialize hooks
	 */
	public static function init(): void {
		// Automatická regenerace při uložení produktu
		add_action('save_post_product', [__CLASS__, 'auto_regenerate_on_save'], 20, 2);
		
		// Manuální regenerace button handler
		add_action('admin_post_saw_regenerate_tokens', [__CLASS__, 'handle_manual_regeneration']);
	}

	/**
	 * Automatická regenerace při uložení produktu
	 */
	public static function auto_regenerate_on_save(int $post_id, \WP_Post $post): void {
		// Skip autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		
		// Skip revisions
		if (wp_is_post_revision($post_id)) {
			return;
		}
		
		// Skip pokud není produkt
		if ('product' !== get_post_type($post_id)) {
			return;
		}
		
		// Získat produkt
		$product = wc_get_product($post_id);
		if (!$product) {
			return;
		}
		
		// Skip pokud není video produkt
		$is_video_product = (bool)(int)$product->get_meta('sawwap_is_video_product', true);
		if (!$is_video_product) {
			return;
		}
		
		// Capability check
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}
		
		self::log_info('Auto-regeneration triggered', ['product_id' => $post_id]);
		
		// Regenerovat tokeny
		$result = self::regenerate_missing_tokens($post_id);
		
		// Ulož notice
		if ($result['tokens_created'] > 0) {
			set_transient(
				'saw_regenerate_notice_' . get_current_user_id(),
				[
					'type' => 'success',
					'tokens_created' => $result['tokens_created'],
					'customers' => $result['affected_customers'],
					'errors' => $result['errors'],
				],
				30
			);
		}
	}

	/**
	 * Manuální regenerace - handler pro button
	 */
	public static function handle_manual_regeneration(): void {
		// Nonce check
		if (!isset($_GET['saw_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['saw_nonce'])), 'saw_regenerate_tokens')) {
			wp_die(
				esc_html__('Bezpečnostní kontrola selhala.', 'saw-wap'),
				esc_html__('Chyba', 'saw-wap'),
				['response' => 403]
			);
		}
		
		// Capability check
		if (!current_user_can('edit_products')) {
			wp_die(
				esc_html__('Nemáte oprávnění k této akci.', 'saw-wap'),
				esc_html__('Nedostatečná oprávnění', 'saw-wap'),
				['response' => 403]
			);
		}
		
		// Get product ID
		$product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
		
		if (!$product_id) {
			wp_die(
				esc_html__('Neplatné ID produktu.', 'saw-wap'),
				esc_html__('Chyba', 'saw-wap'),
				['response' => 400]
			);
		}
		
		self::log_info('Manual regeneration triggered', ['product_id' => $product_id, 'user_id' => get_current_user_id()]);
		
		// Regenerovat tokeny
		$result = self::regenerate_missing_tokens($product_id);
		
		// Ulož notice pro zobrazení po redirectu
		set_transient(
			'saw_regenerate_notice_' . get_current_user_id(),
			[
				'type' => $result['tokens_created'] > 0 ? 'success' : 'info',
				'tokens_created' => $result['tokens_created'],
				'customers' => $result['affected_customers'],
				'errors' => $result['errors'],
			],
			30
		);
		
		// Redirect zpět na edit product
		$redirect_url = admin_url('post.php?post=' . $product_id . '&action=edit');
		wp_safe_redirect($redirect_url);
		exit;
	}

	/**
	 * HLAVNÍ METODA: Regenerace chybějících tokenů
	 * 
	 * @param int $product_id Product ID
	 * @return array {tokens_created, affected_customers, errors}
	 */
	public static function regenerate_missing_tokens(int $product_id): array {
		global $wpdb;
		
		$result = [
			'tokens_created' => 0,
			'affected_customers' => 0,
			'errors' => [],
		];
		
		// 1. Získat všechna PLACENÁ videa produktu
		$videos = self::get_product_videos($product_id);
		
		if (empty($videos)) {
			self::log_debug('No paid videos found', ['product_id' => $product_id]);
			return $result;
		}
		
		self::log_info('Found videos', ['product_id' => $product_id, 'count' => count($videos)]);
		
		// 2. Získat všechny zákazníky kteří koupili tento produkt
		$customers = self::get_product_customers($product_id);
		
		if (empty($customers)) {
			self::log_debug('No customers found', ['product_id' => $product_id]);
			return $result;
		}
		
		self::log_info('Found customers', ['product_id' => $product_id, 'count' => count($customers)]);
		
		// 3. Token manager
		$token_manager = new VideoTokenManager();
		
		// 4. Pro každého zákazníka zkontroluj chybějící tokeny
		foreach ($customers as $customer) {
			$user_id = (int)$customer['user_id'];
			$order_id = (int)$customer['order_id'];
			$access_days = (int)$customer['access_days'];
			
			// Získat které video_index už má
			$existing_indices = self::get_user_video_indices($user_id, $product_id);
			
			// Najít chybějící videa
			$all_indices = array_column($videos, 'video_index');
			$missing_indices = array_diff($all_indices, $existing_indices);
			
			if (empty($missing_indices)) {
				continue; // Tento zákazník má všechna videa
			}
			
			$result['affected_customers']++;
			
			// Vygenerovat tokeny pro chybějící videa
			foreach ($missing_indices as $video_index) {
				try {
					$token = $token_manager->generateToken(
						$user_id,
						$product_id,
						(int)$video_index,
						$order_id,
						$access_days
					);
					
					if ($token) {
						$result['tokens_created']++;
						
						self::log_info('Token created', [
							'user_id' => $user_id,
							'product_id' => $product_id,
							'video_index' => $video_index,
						]);
					}
					
				} catch (\Exception $e) {
					$error_msg = sprintf(
						'Failed for user %d, video %d: %s',
						$user_id,
						$video_index,
						$e->getMessage()
					);
					
					$result['errors'][] = $error_msg;
					self::log_error('Token generation failed', ['error' => $e->getMessage()]);
				}
			}
		}
		
		self::log_info('Regeneration completed', $result);
		
		return $result;
	}

	/**
	 * Získat všechna PLACENÁ videa produktu
	 */
	private static function get_product_videos(int $product_id): array {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'saw_video_metadata';
		
		$videos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT video_index, video_title 
				 FROM {$table_name}
				 WHERE product_id = %d
				 AND is_free = 0
				 ORDER BY lesson_order ASC",
				$product_id
			),
			ARRAY_A
		);
		
		return $videos ?: [];
	}

	/**
	 * Získat všechny zákazníky s aktivním přístupem
	 */
	private static function get_product_customers(int $product_id): array {
		global $wpdb;
		
		$tokens_table = $wpdb->prefix . 'saw_video_access_tokens';
		
		$customers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT 
					user_id,
					order_id,
					DATEDIFF(MIN(access_expires), MIN(access_granted)) as access_days
				 FROM {$tokens_table}
				 WHERE product_id = %d
				 AND is_active = 1
				 AND access_expires > NOW()
				 GROUP BY user_id, order_id",
				$product_id
			),
			ARRAY_A
		);
		
		return $customers ?: [];
	}

	/**
	 * Získat které video_index už uživatel má
	 */
	private static function get_user_video_indices(int $user_id, int $product_id): array {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'saw_video_access_tokens';
		
		$indices = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT video_index 
				 FROM {$table_name}
				 WHERE user_id = %d
				 AND product_id = %d
				 AND is_active = 1",
				$user_id,
				$product_id
			)
		);
		
		return array_map('intval', $indices);
	}

	/**
	 * Logging
	 */
	private static function log_info(string $message, array $context = []): void {
		if (!defined('WP_DEBUG') || !WP_DEBUG) {
			return;
		}
		
		$context_str = !empty($context) ? ' | ' . wp_json_encode($context) : '';
		error_log(sprintf('SAW-WAP [INFO] TokenRegenerator: %s%s', $message, $context_str));
	}

	private static function log_debug(string $message, array $context = []): void {
		if (!defined('WP_DEBUG') || !WP_DEBUG) {
			return;
		}
		
		$context_str = !empty($context) ? ' | ' . wp_json_encode($context) : '';
		error_log(sprintf('SAW-WAP [DEBUG] TokenRegenerator: %s%s', $message, $context_str));
	}

	private static function log_error(string $message, array $context = []): void {
		$context_str = !empty($context) ? ' | ' . wp_json_encode($context) : '';
		error_log(sprintf('SAW-WAP [ERROR] TokenRegenerator: %s%s', $message, $context_str));
	}
}