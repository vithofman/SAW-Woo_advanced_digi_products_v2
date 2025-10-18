<?php
/**
 * Database upgrade and migration logic.
 *
 * @package SAW\WAP\Core
 */

declare( strict_types=1 );

namespace SAW\WAP\Core;

/**
 * Handles database migrations and upgrades between plugin versions.
 */
class Upgrades {
	/**
	 * Run upgrade routines if needed.
	 * Called on every plugins_loaded hook.
	 */
	public static function maybe_upgrade(): void {
		// Check if Database class exists
		if ( ! class_exists( Database::class ) ) {
			return;
		}

		// Check if upgrade is needed
		if ( ! Database::needs_upgrade() ) {
			return;
		}

		// Run database upgrade
		self::run_database_upgrade();
	}

	/**
	 * Execute database upgrade.
	 * This will create missing tables or update existing ones.
	 */
	private static function run_database_upgrade(): void {
		$current_version  = Database::get_version();
		$required_version = Database::get_required_version();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'SAW-WAP: Running database upgrade from %s to %s',
					$current_version,
					$required_version
				)
			);
		}

		// Create or update tables
		Database::create_tables();

		// Run version-specific migrations
		self::run_version_migrations( $current_version, $required_version );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'SAW-WAP: Database upgrade completed successfully' );
		}
	}

	/**
	 * Run version-specific migration scripts.
	 *
	 * @param string $from_version Current database version.
	 * @param string $to_version   Target database version.
	 */
	private static function run_version_migrations( string $from_version, string $to_version ): void {
		// Example: Upgrade from 1.0.0 to 1.1.0
		if ( version_compare( $from_version, '1.1.0', '<' ) && version_compare( $to_version, '1.1.0', '>=' ) ) {
			self::migrate_to_1_1_0();
		}

		// Example: Upgrade from 1.1.0 to 1.2.0
		if ( version_compare( $from_version, '1.2.0', '<' ) && version_compare( $to_version, '1.2.0', '>=' ) ) {
			self::migrate_to_1_2_0();
		}

		// Add more version migrations here as needed
	}

	/**
	 * Migration to version 1.1.0.
	 * Example: Add new column to existing table.
	 */
	private static function migrate_to_1_1_0(): void {
		global $wpdb;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'SAW-WAP: Running migration to 1.1.0' );
		}

		// Example migration (currently commented out as it's not needed yet)
		/*
		$table_name = $wpdb->prefix . 'saw_video_access_tokens';
		
		// Check if column exists
		$column = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = %s 
				AND TABLE_NAME = %s 
				AND COLUMN_NAME = %s",
				DB_NAME,
				$table_name,
				'device_id'
			)
		);
		
		// Add column if it doesn't exist
		if ( empty( $column ) ) {
			$wpdb->query(
				"ALTER TABLE {$table_name} 
				ADD COLUMN device_id VARCHAR(64) NULL COMMENT 'Device identifier for multi-device tracking'"
			);
		}
		*/
	}

	/**
	 * Migration to version 1.2.0.
	 * Placeholder for future migrations.
	 */
	private static function migrate_to_1_2_0(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'SAW-WAP: Running migration to 1.2.0' );
		}

		// Future migration code will go here
	}

	/**
	 * Get upgrade information for admin notice.
	 *
	 * @return array{needs_upgrade: bool, current_version: string, required_version: string}
	 */
	public static function get_upgrade_info(): array {
		return [
			'needs_upgrade'     => Database::needs_upgrade(),
			'current_version'   => Database::get_version(),
			'required_version'  => Database::get_required_version(),
		];
	}
}