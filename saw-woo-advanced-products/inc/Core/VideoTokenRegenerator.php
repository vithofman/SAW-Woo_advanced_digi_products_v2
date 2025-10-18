<?php
/**
 * Video Token Regenerator - automatically generates missing tokens
 * 
 * ŘEŠENÍ PROBLÉMU:
 * Když admin přidá nové video do produktu, existující zákazníci
 * automaticky dostanou token pro přístup (pokud jim ještě nevypršel přístup).
 *
 * TŘI ZPŮSOBY POUŽITÍ:
 * 1. Automaticky při uložení produktu (default behavior)
 * 2. Manuálně admin buttonem v editaci produktu
 * 3. WP-CLI command: wp saw regenerate-tokens --product-id=123
 * 
 * @package SAW\WAP\Core
 */

declare( strict_types=1 );

namespace SAW\WAP\Core;

use SAW\WAP\Core\VideoTokenManager;

/**
 * Class VideoTokenRegenerator
 */
class VideoTokenRegenerator {

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		// 🔥 AUTOMATICKÉ GENEROVÁNÍ při uložení produktu
		add_action( 'save_post_product', [ self::class, 'auto_regenerate_on_save' ], 20, 2 );
		
		// 🖱️ MANUÁLNÍ BUTTON v admin editaci produktu
		add_action( 'admin_post_saw_regenerate_tokens', [ self::class, 'handle_manual_regeneration' ] );
		
		// 💻 WP-CLI COMMAND
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'saw regenerate-tokens', [ self::class, 'cli_regenerate_tokens' ] );
		}
	}

	/**
	 * ═══════════════════════════════════════════════════════════
	 * AUTOMATICKÉ GENEROVÁNÍ PŘI ULOŽENÍ PRODUKTU
	 * ═══════════════════════════════════════════════════════════
	 * 
	 * Volá se automaticky když admin uloží produkt.
	 * Vygeneruje chybějící tokeny PRO VŠECHNY EXISTUJÍCÍ ZÁKAZNÍKY.
	 * 
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function auto_regenerate_on_save( int $post_id, \WP_Post $post ): void {
		// Skip autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip pokud není produkt
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}

		// Skip pokud není video produkt
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		$is_video_product = (bool) (int) $product->get_meta( 'sawwap_is_video_product', true );
		if ( ! $is_video_product ) {
			return;
		}

		// Capability check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		self::log_info( 'Auto-regeneration triggered by save_post', [ 'product_id' => $post_id ] );

		// Vygeneruj chybějící tokeny
		$result = self::regenerate_missing_tokens( $post_id );

		// Ulož notice pro zobrazení po redirect
		if ( $result['tokens_created'] > 0 ) {
			set_transient(
				'saw_regenerate_notice_' . get_current_user_id(),
				[
					'type'           => 'success',
					'tokens_created' => $result['tokens_created'],
					'customers'      => $result['affected_customers'],
				],
				30 // 30 sekund
			);

			self::log_info( 'Auto-regeneration completed', $result );
		}
	}

	/**
	 * ═══════════════════════════════════════════════════════════
	 * HLAVNÍ METODA: REGENERACE CHYBĚJÍCÍCH TOKENŮ
	 * ═══════════════════════════════════════════════════════════
	 * 
	 * Najde všechny zákazníky kteří koupili tento produkt a nemají
	 * tokeny pro všechna videa. Vygeneruje chybějící tokeny.
	 * 
	 * @param int  $product_id     Product ID.
	 * @param bool $force_all      Pokud true, regeneruje i existující tokeny.
	 * @param bool $skip_expired   Pokud true, přeskočí zákazníky s expirovaným přístupem.
	 * 
	 * @return array {
	 *     @type int $tokens_created     Počet vytvořených tokenů
	 *     @type int $affected_customers Počet zákazníků
	 *     @type int $skipped_expired    Počet přeskočených (expired)
	 *     @type array $errors           Seznam chyb
	 * }
	 */
	public static function regenerate_missing_tokens(
		int $product_id,
		bool $force_all = false,
		bool $skip_expired = true
	): array {
		global $wpdb;

		// Výsledky
		$result = [
			'tokens_created'     => 0,
			'affected_customers' => 0,
			'skipped_expired'    => 0,
			'errors'             => [],
		];

		// 1. Získat všechna videa tohoto produktu (NON-FREE)
		$videos = self::get_product_videos( $product_id );

		if ( empty( $videos ) ) {
			self::log_debug( 'No videos found for product', [ 'product_id' => $product_id ] );
			return $result;
		}

		self::log_info( 'Starting regeneration', [
			'product_id'   => $product_id,
			'videos_count' => count( $videos ),
			'force_all'    => $force_all,
			'skip_expired' => $skip_expired,
		] );

		// 2. Najít všechny zákazníky kteří koupili tento produkt
		$customers = self::get_product_customers( $product_id, $skip_expired );

		if ( empty( $customers ) ) {
			self::log_debug( 'No customers found for product', [ 'product_id' => $product_id ] );
			return $result;
		}

		self::log_info( 'Found customers', [
			'product_id'      => $product_id,
			'customers_count' => count( $customers ),
		] );

		// 3. Inicializace token managera
		$token_manager = new VideoTokenManager();

		// 4. Pro každého zákazníka zkontroluj chybějící tokeny
		foreach ( $customers as $customer ) {
			$user_id    = (int) $customer['user_id'];
			$order_id   = (int) $customer['order_id'];
			$access_days = (int) $customer['access_days'];

			// Zkontroluj expiraci (pokud skip_expired = true)
			if ( $skip_expired && ! empty( $customer['first_expires'] ) ) {
				$expires = strtotime( $customer['first_expires'] );
				$now     = current_time( 'timestamp' );

				if ( $expires < $now ) {
					$result['skipped_expired']++;
					self::log_debug( 'Skipping expired customer', [
						'user_id'    => $user_id,
						'expires'    => $customer['first_expires'],
						'product_id' => $product_id,
					] );
					continue;
				}
			}

			$customer_tokens_created = 0;

			// Pro každé video zkontroluj zda token existuje
			foreach ( $videos as $video ) {
				$video_index = (int) $video['video_index'];

				// Pokud force_all = false, přeskoč existující tokeny
				if ( ! $force_all && self::token_exists( $user_id, $product_id, $video_index ) ) {
					continue;
				}

				// Pokud force_all = true, smaž starý token
				if ( $force_all ) {
					self::delete_token( $user_id, $product_id, $video_index );
				}

				// Vygeneruj nový token
				try {
					$token = $token_manager->generateToken(
						$user_id,
						$product_id,
						$video_index,
						$order_id,
						$access_days
					);

					$customer_tokens_created++;
					$result['tokens_created']++;

					self::log_debug( 'Token generated', [
						'user_id'     => $user_id,
						'product_id'  => $product_id,
						'video_index' => $video_index,
						'video_title' => $video['video_title'],
					] );

				} catch ( \Exception $e ) {
					$error_msg = sprintf(
						'Failed to generate token for user %d, product %d, video %d: %s',
						$user_id,
						$product_id,
						$video_index,
						$e->getMessage()
					);

					$result['errors'][] = $error_msg;
					self::log_error( $error_msg );
				}
			}

			if ( $customer_tokens_created > 0 ) {
				$result['affected_customers']++;
			}
		}

		self::log_info( 'Regeneration completed', $result );

		return $result;
	}

	/**
	 * ═══════════════════════════════════════════════════════════
	 * MANUÁLNÍ REGENERACE (ADMIN BUTTON)
	 * ═══════════════════════════════════════════════════════════
	 */
	public static function handle_manual_regeneration(): void {
		// Nonce verification
		if ( ! isset( $_GET['saw_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['saw_nonce'] ) ), 'saw_regenerate_tokens' ) ) {
			wp_die( esc_html__( 'Security check failed', 'saw-wap' ) );
		}

		// Capability check
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'saw-wap' ) );
		}

		// Get product ID
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_die( esc_html__( 'Invalid product ID', 'saw-wap' ) );
		}

		// Regenerate
		$result = self::regenerate_missing_tokens( $product_id );

		// Set notice
		set_transient(
			'saw_regenerate_notice_' . get_current_user_id(),
			[
				'type'           => 'success',
				'tokens_created' => $result['tokens_created'],
				'customers'      => $result['affected_customers'],
				'errors'         => $result['errors'],
			],
			30
		);

		// Redirect back
		$redirect_url = admin_url( 'post.php?post=' . $product_id . '&action=edit' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * ═══════════════════════════════════════════════════════════
	 * WP-CLI COMMAND
	 * ═══════════════════════════════════════════════════════════
	 * 
	 * POUŽITÍ:
	 * wp saw regenerate-tokens --product-id=123
	 * wp saw regenerate-tokens --product-id=123 --force-all
	 * wp saw regenerate-tokens --all-products
	 */
	public static function cli_regenerate_tokens( array $args, array $assoc_args ): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		// Pokud --all-products, regeneruj všechny video produkty
		if ( isset( $assoc_args['all-products'] ) ) {
			\WP_CLI::line( 'Regenerating tokens for ALL video products...' );

			$video_products = self::get_all_video_products();

			if ( empty( $video_products ) ) {
				\WP_CLI::warning( 'No video products found.' );
				return;
			}

			$total_tokens   = 0;
			$total_customers = 0;

			foreach ( $video_products as $product_id ) {
				\WP_CLI::line( sprintf( 'Processing product ID: %d', $product_id ) );

				$result = self::regenerate_missing_tokens( $product_id );

				$total_tokens   += $result['tokens_created'];
				$total_customers += $result['affected_customers'];

				\WP_CLI::line( sprintf(
					'  → Created %d tokens for %d customers',
					$result['tokens_created'],
					$result['affected_customers']
				) );
			}

			\WP_CLI::success( sprintf(
				'Total: %d tokens created for %d customers across %d products',
				$total_tokens,
				$total_customers,
				count( $video_products )
			) );

			return;
		}

		// Single product
		$product_id = isset( $assoc_args['product-id'] ) ? absint( $assoc_args['product-id'] ) : 0;

		if ( ! $product_id ) {
			\WP_CLI::error( 'Please provide --product-id=123 or use --all-products' );
			return;
		}

		$force_all = isset( $assoc_args['force-all'] );

		\WP_CLI::line( sprintf( 'Regenerating tokens for product ID: %d', $product_id ) );

		if ( $force_all ) {
			\WP_CLI::line( 'Force mode: Will regenerate ALL tokens (including existing)' );
		}

		$result = self::regenerate_missing_tokens( $product_id, $force_all );

		if ( ! empty( $result['errors'] ) ) {
			\WP_CLI::warning( sprintf( 'Completed with %d errors:', count( $result['errors'] ) ) );
			foreach ( $result['errors'] as $error ) {
				\WP_CLI::line( '  - ' . $error );
			}
		}

		\WP_CLI::success( sprintf(
			'Created %d tokens for %d customers (skipped %d expired)',
			$result['tokens_created'],
			$result['affected_customers'],
			$result['skipped_expired']
		) );
	}

	/**
	 * ═══════════════════════════════════════════════════════════
	 * HELPER METHODS
	 * ═══════════════════════════════════════════════════════════
	 */

	/**
	 * Get all NON-FREE videos for product.
	 */
	private static function get_product_videos( int $product_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'saw_video_metadata';

		$videos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name}
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
	 * Get all customers who purchased this product.
	 * 
	 * @return array Array of {user_id, order_id, access_days, first_expires}
	 */
	private static function get_product_customers( int $product_id, bool $only_active = true ): array {
		global $wpdb;

		$tokens_table = $wpdb->prefix . 'saw_video_access_tokens';

		// Najít všechny unikátní kombinace user_id + order_id pro tento produkt
		$sql = "SELECT DISTINCT 
				t.user_id,
				t.order_id,
				MIN(t.access_expires) as first_expires,
				DATEDIFF(MIN(t.access_expires), MIN(t.access_granted)) as access_days
			FROM {$tokens_table} t
			WHERE t.product_id = %d";

		if ( $only_active ) {
			$sql .= " AND t.is_active = 1 AND t.access_expires > NOW()";
		}

		$sql .= " GROUP BY t.user_id, t.order_id
			  ORDER BY t.user_id ASC";

		$customers = $wpdb->get_results(
			$wpdb->prepare( $sql, $product_id ),
			ARRAY_A
		);

		return $customers ?: [];
	}

	/**
	 * Check if token exists.
	 */
	private static function token_exists( int $user_id, int $product_id, int $video_index ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'saw_video_access_tokens';

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name}
				 WHERE user_id = %d
				 AND product_id = %d
				 AND video_index = %d",
				$user_id,
				$product_id,
				$video_index
			)
		);

		return $count > 0;
	}

	/**
	 * Delete existing token (for force mode).
	 */
	private static function delete_token( int $user_id, int $product_id, int $video_index ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'saw_video_access_tokens';

		$deleted = $wpdb->delete(
			$table_name,
			[
				'user_id'     => $user_id,
				'product_id'  => $product_id,
				'video_index' => $video_index,
			],
			[ '%d', '%d', '%d' ]
		);

		return false !== $deleted;
	}

	/**
	 * Get all video product IDs.
	 */
	private static function get_all_video_products(): array {
		global $wpdb;

		$product_ids = $wpdb->get_col(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = 'sawwap_is_video_product'
			 AND meta_value = '1'"
		);

		return array_map( 'intval', $product_ids );
	}

	/**
	 * ═══════════════════════════════════════════════════════════
	 * LOGGING
	 * ═══════════════════════════════════════════════════════════
	 */

	private static function log_info( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [INFO] TokenRegenerator: %s%s', $message, $context_str ) );
	}

	private static function log_debug( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [DEBUG] TokenRegenerator: %s%s', $message, $context_str ) );
	}

	private static function log_error( string $message, array $context = [] ): void {
		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [ERROR] TokenRegenerator: %s%s', $message, $context_str ) );
	}
}