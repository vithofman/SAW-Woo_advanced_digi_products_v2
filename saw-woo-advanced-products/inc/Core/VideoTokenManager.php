<?php
/**
 * Video Token Manager - secure token generation and validation.
 *
 * @package SAW\WAP\Core
 */

namespace SAW\WAP\Core;

/**
 * Class VideoTokenManager
 *
 * Manages secure access tokens for video content.
 */
class VideoTokenManager {
	private const TOKEN_SALT = 'SAW_WAP_TOKEN_SALT_2025';
	private const DEFAULT_ACCESS_DAYS = 365;
	private const MAX_GENERATION_ATTEMPTS = 5;

	private $wpdb;
	private $tokens_table;

	public function __construct() {
		global $wpdb;
		$this->wpdb         = $wpdb;
		$this->tokens_table = $wpdb->prefix . 'saw_video_access_tokens';
	}

	public function generateToken(
		$user_id,
		$product_id,
		$video_index,
		$order_id,
		$access_days = null
	) {
		$user_id     = (int) $user_id;
		$product_id  = (int) $product_id;
		$video_index = (int) $video_index;
		$order_id    = (int) $order_id;
		
		if ( $user_id <= 0 || $product_id <= 0 || $video_index < 0 || $order_id <= 0 ) {
			$this->log_error( 'Invalid parameters for token generation', compact( 'user_id', 'product_id', 'video_index', 'order_id' ) );
			throw new \Exception( 'Invalid parameters for token generation' );
		}

		if ( null === $access_days ) {
			$access_days = $this->get_product_access_days( $product_id );
		}

		for ( $attempt = 1; $attempt <= self::MAX_GENERATION_ATTEMPTS; $attempt++ ) {
			try {
				$token = $this->generate_unique_token( $user_id, $product_id, $video_index, $order_id );

				$exists = $this->wpdb->get_var(
					$this->wpdb->prepare(
						"SELECT COUNT(*) FROM {$this->tokens_table} WHERE access_token = %s",
						$token
					)
				);

				if ( $exists > 0 ) {
					$this->log_debug( "Token collision on attempt {$attempt}, regenerating..." );
					continue;
				}

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

		throw new \Exception( 'Unexpected error in token generation' );
	}

	public function validateToken( $token ) {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			$this->log_debug( 'Invalid token format', compact( 'token' ) );
			return null;
		}

		$access = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->tokens_table} WHERE access_token = %s LIMIT 1",
				$token
			)
		);

		if ( ! $access ) {
			$this->log_debug( 'Token not found in database', compact( 'token' ) );
			return null;
		}

		if ( ! (bool) $access->is_active ) {
			$this->log_debug( 'Token is inactive', compact( 'token' ) );
			return null;
		}

		$now = current_time( 'mysql' );
		if ( $access->access_expires < $now ) {
			$this->log_debug( 'Token expired', array(
				'token'        => $token,
				'expires'      => $access->access_expires,
				'current_time' => $now,
			) );
			return null;
		}

		$this->update_token_usage( $access->id );

		$this->log_info( 'Token validated successfully', array(
			'token_id'    => $access->id,
			'user_id'     => $access->user_id,
			'product_id'  => $access->product_id,
			'video_index' => $access->video_index,
		) );

		return $access;
	}

	public function revokeToken( $token ) {
		$updated = $this->wpdb->update(
			$this->tokens_table,
			array( 'is_active' => 0 ),
			array( 'access_token' => $token ),
			array( '%d' ),
			array( '%s' )
		);

		if ( false === $updated ) {
			$this->log_error( 'Failed to revoke token', compact( 'token' ) );
			return false;
		}

		$this->log_info( 'Token revoked', compact( 'token' ) );
		return true;
	}

	public function extendAccess( $token, $days ) {
		$days = (int) $days;
		
		if ( $days <= 0 ) {
			$this->log_error( 'Invalid days parameter for extendAccess', compact( 'token', 'days' ) );
			return false;
		}

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

		$new_expires = gmdate( 'Y-m-d H:i:s', strtotime( $current_expires ) + ( $days * DAY_IN_SECONDS ) );

		$updated = $this->wpdb->update(
			$this->tokens_table,
			array( 'access_expires' => $new_expires ),
			array( 'access_token' => $token ),
			array( '%s' ),
			array( '%s' )
		);

		if ( false === $updated ) {
			$this->log_error( 'Failed to extend token access', compact( 'token', 'days' ) );
			return false;
		}

		$this->log_info( 'Token access extended', array(
			'token'            => $token,
			'days'             => $days,
			'previous_expires' => $current_expires,
			'new_expires'      => $new_expires,
		) );

		return true;
	}

	public function getUserProductTokens( $user_id, $product_id ) {
		$user_id    = (int) $user_id;
		$product_id = (int) $product_id;
		
		$tokens = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->tokens_table} WHERE user_id = %d AND product_id = %d ORDER BY video_index ASC",
				$user_id,
				$product_id
			)
		);

		return $tokens ? $tokens : array();
	}

	private function generate_unique_token( $user_id, $product_id, $video_index, $order_id ) {
		$components = array(
			$user_id,
			$product_id,
			$video_index,
			$order_id,
			self::TOKEN_SALT,
			microtime( true ),
			wp_generate_password( 32, true, true ),
		);

		$data  = implode( ':', $components );
		$token = hash( 'sha256', $data );

		return $token;
	}

	private function insert_token_record(
		$user_id,
		$product_id,
		$video_index,
		$order_id,
		$token,
		$access_days
	) {
		$now            = current_time( 'mysql' );
		$access_expires = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + ( $access_days * DAY_IN_SECONDS ) );

		$inserted = $this->wpdb->insert(
			$this->tokens_table,
			array(
				'user_id'        => (int) $user_id,
				'product_id'     => (int) $product_id,
				'video_index'    => (int) $video_index,
				'order_id'       => (int) $order_id,
				'access_token'   => $token,
				'access_granted' => $now,
				'access_expires' => $access_expires,
				'is_active'      => 1,
			),
			array(
				'%d',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
			)
		);

		return false !== $inserted;
	}

	private function update_token_usage( $token_id ) {
		$token_id = (int) $token_id;
		$now      = current_time( 'mysql' );
		$ip       = $this->get_client_ip();

		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->tokens_table} SET last_accessed = %s, access_count = access_count + 1, last_ip = %s WHERE id = %d",
				$now,
				$ip,
				$token_id
			)
		);
	}

	private function get_product_access_days( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return self::DEFAULT_ACCESS_DAYS;
		}

		$access_days = (int) $product->get_meta( 'sawwap_access_days', true );

		return $access_days > 0 ? $access_days : self::DEFAULT_ACCESS_DAYS;
	}

	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

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

	private function log_info( $message, $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [INFO] VideoTokenManager: %s%s', $message, $context_str ) );
	}

	private function log_debug( $message, $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [DEBUG] VideoTokenManager: %s%s', $message, $context_str ) );
	}

	private function log_error( $message, $context = array() ) {
		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
		error_log( sprintf( 'SAW-WAP [ERROR] VideoTokenManager: %s%s', $message, $context_str ) );
	}
}