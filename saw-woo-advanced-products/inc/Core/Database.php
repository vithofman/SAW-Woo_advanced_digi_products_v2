<?php
/**
 * Database management - table creation and upgrades.
 *
 * @package SAW\WAP\Core
 */

declare( strict_types=1 );

namespace SAW\WAP\Core;

/**
 * Handles database table creation and version management.
 */
class Database {
	/**
	 * Current database schema version.
	 * Increment this when making schema changes.
	 */
	private const DB_VERSION = '1.0.0';

	/**
	 * Option name for storing DB version.
	 */
	private const VERSION_OPTION = 'sawwap_db_version';

	/**
	 * Create or upgrade database tables.
	 * Called on plugin activation and can be called manually for upgrades.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_prefix    = $wpdb->prefix;

		// Get current DB version
		$current_version = get_option( self::VERSION_OPTION, '0.0.0' );

		// If already on latest version, skip
		if ( version_compare( $current_version, self::DB_VERSION, '>=' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create all tables
		self::create_video_metadata_table( $table_prefix, $charset_collate );
		self::create_video_access_tokens_table( $table_prefix, $charset_collate );
		self::create_video_watch_sessions_table( $table_prefix, $charset_collate );

		// Update DB version
		update_option( self::VERSION_OPTION, self::DB_VERSION );

		// Log success
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'SAW-WAP: Database tables created/updated to version %s', self::DB_VERSION ) );
		}
	}

	/**
	 * Create video metadata table.
	 * Stores information about videos belonging to products.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Charset and collation.
	 */
	private static function create_video_metadata_table( string $table_prefix, string $charset_collate ): void {
		$table_name = $table_prefix . 'saw_video_metadata';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'WooCommerce product ID',
			video_index INT NOT NULL COMMENT '0=free preview, 1+=paid lessons',
			video_title VARCHAR(200) NOT NULL COMMENT 'Lesson title',
			video_url VARCHAR(500) NOT NULL COMMENT 'YouTube/Vimeo/custom URL',
			video_provider ENUM('youtube', 'vimeo', 'custom') NOT NULL DEFAULT 'youtube',
			video_duration INT DEFAULT 0 COMMENT 'Duration in seconds',
			video_description TEXT COMMENT 'Lesson description',
			video_thumbnail VARCHAR(500) DEFAULT '' COMMENT 'Custom thumbnail URL',
			lesson_order INT NOT NULL COMMENT 'Display order in course',
			is_free BOOLEAN DEFAULT FALSE COMMENT 'Free preview video',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_product (product_id),
			INDEX idx_product_order (product_id, lesson_order),
			INDEX idx_free (is_free),
			UNIQUE KEY unique_product_index (product_id, video_index)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create video access tokens table.
	 * Stores secure tokens for accessing paid videos.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Charset and collation.
	 */
	private static function create_video_access_tokens_table( string $table_prefix, string $charset_collate ): void {
		$table_name = $table_prefix . 'saw_video_access_tokens';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'WordPress user ID',
			product_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'WooCommerce product ID',
			video_index INT NOT NULL COMMENT 'Which video in the course',
			order_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'WooCommerce order ID',
			access_token VARCHAR(64) NOT NULL COMMENT 'SHA256 hash - 64 chars',
			access_granted DATETIME NOT NULL COMMENT 'When access was granted',
			access_expires DATETIME NOT NULL COMMENT 'When access expires',
			last_accessed DATETIME NULL COMMENT 'Last time token was used',
			last_ip VARCHAR(45) NULL COMMENT 'Last IP address (IPv6 support)',
			access_count INT DEFAULT 0 COMMENT 'Number of times accessed',
			is_active BOOLEAN DEFAULT TRUE COMMENT 'Can be revoked by admin',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_token (access_token),
			INDEX idx_user_product (user_id, product_id),
			INDEX idx_expires (access_expires, is_active),
			INDEX idx_order (order_id),
			INDEX idx_active (is_active),
			UNIQUE KEY unique_user_product_video (user_id, product_id, video_index)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create video watch sessions table.
	 * Tracks user viewing sessions and progress.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Charset and collation.
	 */
	private static function create_video_watch_sessions_table( string $table_prefix, string $charset_collate ): void {
		$table_name = $table_prefix . 'saw_video_watch_sessions';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			token_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Foreign key to access_tokens',
			session_start DATETIME NOT NULL COMMENT 'When user started watching',
			session_end DATETIME NULL COMMENT 'When user stopped/paused',
			watch_duration INT DEFAULT 0 COMMENT 'Actual watch time in seconds',
			progress_percent TINYINT DEFAULT 0 COMMENT '0-100 percent completed',
			completed BOOLEAN DEFAULT FALSE COMMENT 'Watched to the end',
			ip_address VARCHAR(45) COMMENT 'Session IP address',
			user_agent TEXT COMMENT 'Browser user agent',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_token (token_id),
			INDEX idx_session (session_start, session_end),
			INDEX idx_completed (completed),
			FOREIGN KEY (token_id) 
				REFERENCES {$table_prefix}saw_video_access_tokens(id) 
				ON DELETE CASCADE
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop all plugin tables.
	 * Used for clean uninstall (not on deactivation!).
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$table_prefix = $wpdb->prefix;

		$tables = [
			$table_prefix . 'saw_video_watch_sessions',
			$table_prefix . 'saw_video_access_tokens',
			$table_prefix . 'saw_video_metadata',
		];

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		// Delete version option
		delete_option( self::VERSION_OPTION );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'SAW-WAP: Database tables dropped' );
		}
	}

	/**
	 * Check if tables exist.
	 *
	 * @return bool True if all tables exist.
	 */
	public static function tables_exist(): bool {
		global $wpdb;

		$table_prefix = $wpdb->prefix;

		$tables = [
			$table_prefix . 'saw_video_metadata',
			$table_prefix . 'saw_video_access_tokens',
			$table_prefix . 'saw_video_watch_sessions',
		];

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$result = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
			if ( $result !== $table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get current database version.
	 *
	 * @return string Version string (e.g., '1.0.0').
	 */
	public static function get_version(): string {
		return get_option( self::VERSION_OPTION, '0.0.0' );
	}

	/**
	 * Get required database version (current schema version).
	 *
	 * @return string Version string.
	 */
	public static function get_required_version(): string {
		return self::DB_VERSION;
	}

	/**
	 * Check if database needs upgrade.
	 *
	 * @return bool True if upgrade needed.
	 */
	public static function needs_upgrade(): bool {
		$current_version  = self::get_version();
		$required_version = self::get_required_version();

		return version_compare( $current_version, $required_version, '<' );
	}
}