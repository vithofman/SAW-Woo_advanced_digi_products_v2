<?php
/**
 * Video Token Manager - secure token generation and validation.
 *
 * Handles SHA256 token generation for video access control.
 * Tokens are stored in wp_saw_video_access_tokens table.
 *
 * @package SAW\WAP\Core
 */

declare( strict_types=1 );

namespace SAW\WAP\Core;

/**
 * Class VideoTokenManager
 *
 * Manages secure access tokens for video content.
 * Each token grants access to ONE specific video for ONE specific user.
 */
class VideoTokenManager {
	/**
	 * Salt pro token generation.
	 * DŮLEŽITÉ: Toto by mělo být v wp-config.php v produkci!
	 *
	 * Pro development používáme konstantu.
	 */
	private const TOKEN_SALT = 'SAW_WAP_TOKEN_SALT_2025';

	/**
	 * Výchozí počet dní platnosti tokenu.
	 * Používá se pokud není specifikováno jinak.
	 */
	private const DEFAULT_ACCESS_DAYS = 365;

	/**
	 * Maximální počet pokusů o generování tokenu.
	 * Prevence nekonečné smyčky pokud by došlo k duplicitě.
	 */
	private const MAX_GENERATION_ATTEMPTS = 5;

	/**
	 * Reference na WordPress databázi.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Název tabulky pro access tokeny.
	 *
	 * @var string
	 */
	private string $tokens_table;

	/**
	 * Constructor.
	 *
	 * Inicializuje WPDB referenci a názvy tabulek.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb         = $wpdb;
		$this->tokens_table = $wpdb->prefix . 'saw_video_access_tokens';
	}

	/**
	 * Vygeneruje bezpečný přístupový token.
	 *
	 * Token je SHA256 hash následujících komponent:
	 * - user_id: ID uživatele
	 * - product_id: ID produktu (kurzu)
	 * - video_index: Pořadí videa v kurzu (0-based)
	 * - order_id: ID WooCommerce objednávky
	 * - salt: Bezpečnostní konstanta
	 * - timestamp: Aktuální čas (mikrosekundy)
	 * - random: Náhodný string (extra entropie)
	 *
	 * @param int      $user_id       WordPress user ID.
	 * @param int      $product_id    WooCommerce product ID.
	 * @param int      $video_index   Video index v kurzu (0 = první video).
	 * @param int      $order_id      WooCommerce order ID.
	 * @param int|null $access_days   Počet dní platnosti (null = použije default nebo product meta).
	 *
	 * @return string SHA256 token (64 znaků hex).
	 *
	 * @throws \Exception Pokud nelze vygenerovat unikátní token po MAX_GENERATION_ATTEMPTS pokusech.
	 */
	public function generateToken(
		int $user_id,
		int $product_id,
		int $video_index,
		int $order_id,
		?int $access_days = null
	): string {
		// Validace vstupů
		if ( $user_id <= 0 || $product_id <= 0 || $video_index < 0 || $order_id <= 0 ) {
			$this->log_error( 'Invalid parameters for token generation', compact( 'user_id', 'product_id', 'video_index', 'order_id' ) );
			throw new \Exception( 'Invalid parameters for token generation' );
		}

		// Zjistit počet dní platnosti
		if ( null === $access_days ) {
			$access_days = $this->get_product_access_days( $product_id );
		}

		// Pokus o generování unikátního tokenu
		for ( $attempt = 1; $attempt <= self::MAX_GENERATION_ATTEMPTS; $attempt++ ) {
			try {
				// Generovat token
				$token = $this->generate_unique_token( $user_id, $product_id, $video_index, $order_id );

				// Zkontrolovat zda už neexistuje v DB (velmi nepravděpodobné díky mikrosekundám + random)
				$exists = $this->wpdb->get_var(
					$this->wpdb->prepare(
						"SELECT COUNT(*) FROM {$this->tokens_table} WHERE access_token = %s",
						$token
					)
				);

				if ( $exists > 0 ) {
					$this->log_debug( "Token collision on attempt {$attempt}, regenerating..." );
					continue; // Zkusit znovu
				}

				// Uložit token do databáze
				$inserted = $this->insert_token_record(
					$user_id,
					$product_id,
					$video_index,
					$order_id,
					$token,
					$access_days
				);

				if ( ! $inserted ) {
					throw new \Exception( 'Failed to insert token into database' );
				}

				// Úspěch!
				$this->log_info(
					'Token generated successfully',
					compact( 'user_id', 'product_id', 'video_index', 'order_id', 'access_days', 'attempt' )
				);

				return $token;

			} catch ( \Exception $e ) {
				$this->log_error( "Token generation attempt {$attempt} failed: " . $e->getMessage() );

				if ( $attempt === self::MAX_GENERATION_ATTEMPTS ) {
					throw new \Exception( 'Unable to generate unique token after ' . self::MAX_GENERATION_ATTEMPTS . ' attempts' );
				}
			}
		}

		// Tento bod by neměl být nikdy dosažen
		throw new \Exception( 'Unexpected error in token generation' );
	}

	/**
	 * Validuje token a vrací přístupová data.
	 *
	 * Kontroluje:
	 * - Existenci tokenu v DB
	 * - Platnost tokenu (is_active = 1)
	 * - Expiraci (access_expires > NOW())
	 *
	 * Při úspěšné validaci aktualizuje:
	 * - last_accessed = NOW()
	 * - access_count += 1
	 * - last_ip
	 *
	 * @param string $token SHA256 token (64 znaků).
	 *
	 * @return object|null Objekt s přístupovými daty nebo null pokud neplatný.
	 */
	public function validateToken( string $token ): ?object {
		// Validace formátu tokenu (64 hex znaků)
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			$this->log_debug( 'Invalid token format', compact( 'token' ) );
			return null;
		}

		// Získat token z databáze
		$access = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->tokens_table} 
				 WHERE access_token = %s 
				 LIMIT 1",
				$token
			)
		);

		// Token neexistuje
		if ( ! $access ) {
			$this->log_debug( 'Token not found in database', compact( 'token' ) );
			return null;
		}

		// Token je deaktivovaný
		if ( ! (bool) $access->is_active ) {
			$this->log_debug( 'Token is inactive', compact( 'token' ) );
			return null;
		}

		// Token vypršel
		$now = current_time( 'mysql' );
		if ( $access->access_expires < $now ) {
			$this->log_debug( 'Token expired', [
				'token'          => $token,
				'expires'        => $access->access_expires,
				'current_time'   => $now,
			] );
			return null;
		}

		// Token je validní → aktualizovat usage statistiky
		$this->update_token_usage( $access->id );

		$this->log_info( 'Token validated successfully', [
			'token_id'   => $access->id,
			'user_id'    => $access->user_id,
			'product_id' => $access->product_id,
			'video_index' => $access->video_index,
		] );

		return $access;
	}

	/**
	 * Deaktivuje token (nastaví is_active = 0).
	 *
	 * Použití:
	 * - Admin manuálně zruší přístup
	 * - Refund objednávky
	 * - Bezpečnostní incident
	 *
	 * @param string $token SHA256 token.
	 *
	 * @return bool True pokud úspěšně deaktivován.
	 */
	public function revokeToken( string $token ): bool {
		$updated = $this->wpdb->update(
			$this->tokens_table,
			[ 'is_active' => 0 ],
			[ 'access_token' => $token ],
			[ '%d' ],
			[ '%s' ]
		);

		if ( false === $updated ) {
			$this->log_error( 'Failed to revoke token', compact( 'token' ) );
			return false;
		}

		$this->log_info( 'Token revoked', compact( 'token' ) );
		return true;
	}

	/**
	 * Prodlouží platnost tokenu o X dní.
	 *
	 * @param string $token SHA256 token.
	 * @param int    $days  Počet dní o které prodloužit.
	 *
	 * @return bool True pokud úspěšně prodlouženo.
	 */
	public function extendAccess( string $token, int $days ): bool {
		if ( $days <= 0 ) {
			$this->log_error( 'Invalid days parameter for extendAccess', compact( 'token', 'days' ) );
			return false;
		}

		// Získat aktuální expiraci
		$current_expires = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT access_expires FROM {$this->tokens_table} WHERE access_token = %s",
				$token
			)
		);

		if ( ! $current_expires ) {
			$this->log_error( 'Token not found for extension', compact( 'token' ) );
			return false;
		}

		// Vypočítat novou expiraci (přidat dny k aktuální expiraci, ne k NOW())
		$new_expires = gmdate( 'Y-m-d H:i:s', strtotime( $current_expires ) + ( $days * DAY_IN_SECONDS ) );

		// Aktualizovat v DB
		$updated = $this->wpdb->update(
			$this->tokens_table,
			[ 'access_expires' => $new_expires ],
			[ 'access_token' => $token ],
			[ '%s' ],
			[ '%s' ]
		);

		if ( false === $updated ) {
			$this->log_error( 'Failed to extend token access', compact( 'token', 'days' ) );
			return false;
		}

		$this->log_info( 'Token access extended', [
			'token'            => $token,
			'days'             => $days,
			'previous_expires' => $current_expires,
			'new_expires'      => $new_expires,
		] );

		return true;
	}

	/**
	 * Získá všechny tokeny pro daného uživatele a produkt.
	 *
	 * Použití:
	 * - Zobrazení všech videí v kurzu v "Moje kurzy"
	 * - Admin overview
	 * - Bulk operations
	 *
	 * @param int $user_id    WordPress user ID.
	 * @param int $product_id WooCommerce product ID.
	 *
	 * @return array Array objektů s tokeny.
	 */
	public function getUserProductTokens( int $user_id, int $product_id ): array {
		$tokens = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->tokens_table} 
				 WHERE user_id = %d 
				 AND product_id = %d 
				 ORDER BY video_index ASC",
				$user_id,
				$product_id
			)
		);

		return $tokens ?: [];
	}

	/**
	 * Vygeneruje unikátní SHA256 hash.
	 *
	 * @param int $user_id     User ID.
	 * @param int $product_id  Product ID.
	 * @param int $video_index Video index.
	 * @param int $order_id    Order ID.
	 *
	 * @return string SHA256 hash (64 hex znaků).
	 */
	private function generate_unique_token( int $user_id, int $product_id, int $video_index, int $order_id ): string {
		// Komponenty pro hash
		$components = [
			$user_id,
			$product_id,
			$video_index,
			$order_id,
			self::TOKEN_SALT,
			microtime( true ), // Mikrosekundy pro extra unikátnost
			wp_generate_password( 32, true, true ), // Náhodný string
		];

		// Spojit do jednoho stringu
		$data = implode( ':', $components );

		// SHA256 hash
		$token = hash( 'sha256', $data );

		return $token;
	}

	/**
	 * Vloží nový token záznam do databáze.
	 *
	 * @param int    $user_id     User ID.
	 * @param int    $product_id  Product ID.
	 * @param int    $video_index Video index.
	 * @param int    $order_id    Order ID.
	 * @param string $token       Generated token.
	 * @param int    $access_days Access duration in days.
	 *
	 * @return bool True pokud úspěšně vloženo.
	 */
	private function insert_token_record(
		int $user_id,
		int $product_id,
		int $video_index,
		int $order_id,
		string $token,
		int $access_days
	): bool {
		$now            = current_time( 'mysql' );
		$access_expires = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + ( $access_days * DAY_IN_SECONDS ) );

		$inserted = $this->wpdb->insert(
			$this->tokens_table,
			[
				'user_id'        => $user_id,
				'product_id'     => $product_id,
				'video_index'    => $video_index,
				'order_id'       => $order_id,
				'access_token'   => $token,
				'access_granted' => $now,
				'access_expires' => $access_expires,
				'is_active'      => 1,
			],
			[
				'%d', // user_id
				'%d', // product_id
				'%d', // video_index
				'%d', // order_id
				'%s', // access_token
				'%s', // access_granted
				'%s', // access_expires
				'%d', // is_active
			]
		);

		return false !== $inserted;
	}

	/**
	 * Aktualizuje usage statistiky tokenu.
	 *
	 * @param int $token_id Token ID v databázi.
	 *
	 * @return void
	 */
	private function update_token_usage( int $token_id ): void {
		$now = current_time( 'mysql' );
		$ip  = $this->get_client_ip();

		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->tokens_table} 
				 SET last_accessed = %s,
				     access_count = access_count + 1,
				     last_ip = %s
				 WHERE id = %d",
				$now,
				$ip,
				$token_id
			)
		);
	}

	/**
	 * Získá počet dní přístupu z product meta nebo použije default.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return int Počet dní.
	 */
	private function get_product_access_days( int $product_id ): int {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return self::DEFAULT_ACCESS_DAYS;
		}

		$access_days = (int) $product->get_meta( 'sawwap_access_days', true );

		return $access_days > 0 ? $access_days : self::DEFAULT_ACCESS_DAYS;
	}

	/**
	 * Získá IP adresu klienta (podporuje proxy/load balancer).
	 *
	 * @return string IP adresa.
	 */
	private function get_client_ip(): string {
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',  // Proxy/Load balancer
			'HTTP_X_REAL_IP',        // Nginx proxy
			'REMOTE_ADDR',           // Fallback
		];

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

				// X-Forwarded-For může obsahovat více IP (první je klient)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = explode( ',', $ip )[0];
				}

				$ip = trim( $ip );

				// Validace IP
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Log info message (pouze pokud WP_DEBUG).
	 *
	 * @param string $message  Log message.
	 * @param array  $context  Additional context data.
	 *
	 * @return void
	 */
	private function log_info( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [INFO] VideoTokenManager: %s%s', $message, $context_str ) );
	}

	/**
	 * Log debug message (pouze pokud WP_DEBUG).
	 *
	 * @param string $message  Log message.
	 * @param array  $context  Additional context data.
	 *
	 * @return void
	 */
	private function log_debug( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [DEBUG] VideoTokenManager: %s%s', $message, $context_str ) );
	}

	/**
	 * Log error message (vždy).
	 *
	 * @param string $message  Error message.
	 * @param array  $context  Additional context data.
	 *
	 * @return void
	 */
	private function log_error( string $message, array $context = [] ): void {
		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [ERROR] VideoTokenManager: %s%s', $message, $context_str ) );
	}
}