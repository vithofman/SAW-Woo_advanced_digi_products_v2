<?php
/**
 * WooCommerce checkout hooks - token generation on order completion.
 *
 * @package SAW\WAP\WC
 */

declare( strict_types=1 );

namespace SAW\WAP\WC;

use SAW\WAP\Core\VideoTokenManager;

/**
 * Checkout adjustments and token generation.
 */
class Hooks_Checkout {
	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		// Hook když objednávka je označena jako completed
		add_action( 'woocommerce_order_status_completed', [ self::class, 'create_video_access' ] );
		
		// Hook když objednávka je označena jako processing (pro instant access)
		add_action( 'woocommerce_order_status_processing', [ self::class, 'create_video_access' ] );
		
		// TODO: Implementovat digital consent checkbox v budoucnosti
	}

	/**
	 * Vytvoří přístupové tokeny pro všechna videa v objednávce.
	 * 
	 * Tato metoda se volá automaticky když WooCommerce změní status objednávky
	 * na "completed" nebo "processing". Projde všechny produkty v objednávce,
	 * zkontroluje které jsou video kurzy a vygeneruje tokeny pro každé video.
	 *
	 * @param int $order_id ID WooCommerce objednávky.
	 * 
	 * @return void
	 */
	public static function create_video_access( int $order_id ): void {
		// Validace order_id
		if ( $order_id <= 0 ) {
			self::log_error( 'Invalid order_id provided', [ 'order_id' => $order_id ] );
			return;
		}

		// Prevence duplicitních tokenů
		// Zkontrolujeme zda už jsme pro tuto objednávku tokeny nevytvořili
		$tokens_created = get_post_meta( $order_id, '_saw_tokens_created', true );
		if ( '1' === $tokens_created ) {
			self::log_debug( 'Tokens already created for this order, skipping', [ 'order_id' => $order_id ] );
			return;
		}

		// Získat objednávku z WooCommerce
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			self::log_error( 'Order not found', [ 'order_id' => $order_id ] );
			return;
		}

		// Získat user_id zákazníka
		$user_id = $order->get_user_id();
		
		// Edge case: Guest checkout (user_id = 0)
		if ( 0 === $user_id ) {
			self::log_error( 'Cannot create tokens for guest orders (user_id = 0)', [ 'order_id' => $order_id ] );
			
			// Přidat poznámku k objednávce pro admina
			$order->add_order_note(
				__( 'SAW-WAP: Nepodařilo se vytvořit přístupové tokeny. Objednávka je od "hosta" bez účtu. Zákazník musí mít WordPress účet pro přístup k videím.', 'saw-wap' )
			);
			
			return;
		}

		self::log_info( 'Starting token generation', [
			'order_id' => $order_id,
			'user_id'  => $user_id,
		] );

		// Inicializace token managera
		$token_manager = new VideoTokenManager();

		// Získat všechny produkty z objednávky
		$items = $order->get_items();
		
		if ( empty( $items ) ) {
			self::log_debug( 'Order has no items', [ 'order_id' => $order_id ] );
			return;
		}

		// Tracking pro reporting
		$processed_products = [];
		$total_tokens       = 0;
		$errors             = [];

		// Projít všechny produkty v objednávce
		foreach ( $items as $item ) {
			try {
				// Získat product object
				$product = $item->get_product();
				
				if ( ! $product ) {
					self::log_debug( 'Item has no product', [ 'item_id' => $item->get_id() ] );
					continue;
				}

				$product_id = $product->get_id();

				// Zkontrolovat zda je to video produkt
				$is_video_product = (bool) (int) $product->get_meta( 'sawwap_is_video_product', true );
				
				if ( ! $is_video_product ) {
					self::log_debug( 'Product is not a video product, skipping', [ 'product_id' => $product_id ] );
					continue;
				}

				self::log_info( 'Processing video product', [
					'product_id'   => $product_id,
					'product_name' => $product->get_name(),
				] );

				// Získat videa pro tento produkt z databáze
				$videos = self::get_product_videos( $product_id );

				if ( empty( $videos ) ) {
					self::log_debug( 'Product has no videos', [ 'product_id' => $product_id ] );
					
					// Přidat poznámku k objednávce
					$order->add_order_note(
						sprintf(
							/* translators: %s: product name */
							__( 'SAW-WAP: Produkt "%s" je označen jako video kurz, ale nemá přidaná žádná videa.', 'saw-wap' ),
							$product->get_name()
						)
					);
					
					continue;
				}

				// Získat custom access_days z product meta (nebo použít default z settings)
				$access_days = (int) $product->get_meta( 'sawwap_access_days', true );
				if ( 0 === $access_days ) {
					// Fallback na globální nastavení
					$access_days = (int) get_option( 'sawwap_default_access_days', 365 );
				}

				$product_tokens = 0;

				// Pro každé video vygenerovat token
				foreach ( $videos as $video ) {
					// Přeskočit free videa (preview)
					if ( ! empty( $video['is_free'] ) ) {
						self::log_debug( 'Skipping free video', [
							'product_id'  => $product_id,
							'video_index' => $video['video_index'],
							'video_title' => $video['video_title'],
						] );
						continue;
					}

					// Zkontrolovat zda token už neexistuje (prevence duplicit)
					if ( self::token_exists( $user_id, $product_id, (int) $video['video_index'] ) ) {
						self::log_debug( 'Token already exists, skipping', [
							'user_id'     => $user_id,
							'product_id'  => $product_id,
							'video_index' => $video['video_index'],
						] );
						continue;
					}

					// Generovat token
					try {
						$token = $token_manager->generateToken(
							$user_id,
							$product_id,
							(int) $video['video_index'],
							$order_id,
							$access_days
						);

						$product_tokens++;
						$total_tokens++;

						self::log_info( 'Token generated successfully', [
							'user_id'     => $user_id,
							'product_id'  => $product_id,
							'video_index' => $video['video_index'],
							'video_title' => $video['video_title'],
							'token'       => substr( $token, 0, 16 ) . '...', // Log jen prvních 16 znaků
							'access_days' => $access_days,
						] );

					} catch ( \Exception $e ) {
						$error_msg = sprintf(
							'Failed to generate token for product %d, video %d: %s',
							$product_id,
							$video['video_index'],
							$e->getMessage()
						);
						
						self::log_error( $error_msg );
						$errors[] = $error_msg;
					}
				}

				// Track processed product
				$processed_products[] = [
					'product_id'   => $product_id,
					'product_name' => $product->get_name(),
					'tokens'       => $product_tokens,
				];

				self::log_info( 'Product processing completed', [
					'product_id'    => $product_id,
					'tokens_created' => $product_tokens,
				] );

			} catch ( \Exception $e ) {
				$error_msg = sprintf(
					'Error processing item %d: %s',
					$item->get_id(),
					$e->getMessage()
				);
				
				self::log_error( $error_msg );
				$errors[] = $error_msg;
			}
		}

		// Pokud byly vytvořeny nějaké tokeny, označit objednávku
		if ( $total_tokens > 0 ) {
			update_post_meta( $order_id, '_saw_tokens_created', '1' );

			// Přidat poznámku k objednávce
			$order->add_order_note(
				sprintf(
					/* translators: %d: number of tokens */
					__( 'SAW-WAP: Vytvořeno %d přístupových tokenů pro video kurzy.', 'saw-wap' ),
					$total_tokens
				)
			);

			// Trigger custom hook pro další zpracování (např. email notifikace)
			$product_ids = array_column( $processed_products, 'product_id' );
			
			/**
			 * Fires after video access tokens are successfully created.
			 * 
			 * Tento hook použijeme v dalším kroku pro odeslání welcome emailu.
			 *
			 * @param int   $user_id     WordPress user ID.
			 * @param int   $order_id    WooCommerce order ID.
			 * @param array $product_ids Array of product IDs that tokens were created for.
			 */
			do_action( 'saw_tokens_created', $user_id, $order_id, $product_ids );

			self::log_info( 'Token generation completed successfully', [
				'order_id'      => $order_id,
				'user_id'       => $user_id,
				'total_tokens'  => $total_tokens,
				'products'      => count( $processed_products ),
			] );
		} else {
			self::log_debug( 'No tokens were created', [ 'order_id' => $order_id ] );
		}

		// Pokud byly nějaké chyby, přidat je do order notes
		if ( ! empty( $errors ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: error messages */
					__( 'SAW-WAP: Některé tokeny se nepodařilo vytvořit: %s', 'saw-wap' ),
					implode( '; ', $errors )
				)
			);
		}
	}

	/**
	 * Získá videa pro daný produkt z databáze.
	 * 
	 * Vrací pouze NON-FREE videa (is_free = 0), protože free videa
	 * nepotřebují tokeny - jsou dostupná všem.
	 *
	 * @param int $product_id Product ID.
	 * 
	 * @return array Array videí, každé jako associative array.
	 */
	private static function get_product_videos( int $product_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'saw_video_metadata';

		// Prepared statement pro bezpečnost
		$videos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name}
				 WHERE product_id = %d
				 ORDER BY lesson_order ASC",
				$product_id
			),
			ARRAY_A
		);

		return $videos ?: [];
	}

	/**
	 * Zkontroluje zda token už existuje pro danou kombinaci.
	 * 
	 * Prevence duplicit - díky UNIQUE KEY (user_id, product_id, video_index)
	 * v DB by nemělo dojít k duplicate entry, ale kontrolujeme preventivně.
	 *
	 * @param int $user_id     User ID.
	 * @param int $product_id  Product ID.
	 * @param int $video_index Video index.
	 * 
	 * @return bool True pokud token existuje.
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
	 * Log info message (pouze pokud WP_DEBUG).
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * 
	 * @return void
	 */
	private static function log_info( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [INFO] Hooks_Checkout: %s%s', $message, $context_str ) );
	}

	/**
	 * Log debug message (pouze pokud WP_DEBUG).
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * 
	 * @return void
	 */
	private static function log_debug( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [DEBUG] Hooks_Checkout: %s%s', $message, $context_str ) );
	}

	/**
	 * Log error message (vždy).
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context data.
	 * 
	 * @return void
	 */
	private static function log_error( string $message, array $context = [] ): void {
		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [ERROR] Hooks_Checkout: %s%s', $message, $context_str ) );
	}
}