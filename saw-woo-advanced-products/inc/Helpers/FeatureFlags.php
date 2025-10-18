<?php
/**
 * Feature flag helper.
 *
 * @package SAW\WAP\Helpers
 */

declare( strict_types=1 );

namespace SAW\WAP\Helpers;

/**
 * Handle feature flags stored in options.
 */
class FeatureFlags {
    /**
     * Option key storing the flags.
     */
    private const OPTION = 'sawwap_feature_flags';

    /**
     * Initialize helper hooks.
     */
    public static function init(): void {
        add_filter( 'sawwap_feature_flag_enabled', [ self::class, 'filter_flag' ], 10, 2 );
    }

    /**
     * Check if a feature flag is enabled.
     */
    public static function is_enabled( string $flag ): bool {
        $flags = get_option( self::OPTION, [] );
        return is_array( $flags ) && ! empty( $flags[ $flag ] );
    }

    /**
     * Filter callback used by helpers.
     *
     * @param bool   $enabled Current value.
     * @param string $flag    Flag name.
     */
    public static function filter_flag( bool $enabled, string $flag ): bool {
        return $enabled || self::is_enabled( $flag );
    }
}
