<?php
/**
 * Lightweight caching helper built on top of WordPress transients.
 *
 * @package SAW\WAP\Helpers
 */

declare( strict_types=1 );

namespace SAW\WAP\Helpers;

/**
 * Cache helper.
 */
class Cache {
    /**
     * Option storing registered keys for invalidation.
     */
    private const OPTION_KEYS = 'sawwap_cache_keys';

    /**
     * Hook into plugin lifecycle.
     */
    public static function init(): void {
        add_action( 'sawwap_cache_invalidate', [ self::class, 'flush_all' ] );
    }

    /**
     * Build a namespaced cache key.
     */
    public static function build_key( string $seed ): string {
        return 'sawwap_' . md5( $seed );
    }

    /**
     * Get cached value.
     *
     * @param string $key Cache key.
     * @return mixed
     */
    public static function get( string $key ) {
        return get_transient( $key );
    }

    /**
     * Set cached value and register key for later invalidation.
     *
     * @param string $key Cache key.
     * @param mixed  $value Value to cache.
     * @param int    $ttl Time to live.
     */
    public static function set( string $key, $value, int $ttl ): void {
        set_transient( $key, $value, max( 0, $ttl ) );
        self::remember_key( $key );
    }

    /**
     * Delete cache entry.
     */
    public static function delete( string $key ): void {
        delete_transient( $key );
        self::forget_key( $key );
    }

    /**
     * Flush all registered cache keys.
     */
    public static function flush_all(): void {
        $keys = get_option( self::OPTION_KEYS, [] );
        if ( is_array( $keys ) ) {
            foreach ( $keys as $key ) {
                delete_transient( $key );
            }
        }

        update_option( self::OPTION_KEYS, [] );
    }

    /**
     * Remember a cache key.
     */
    private static function remember_key( string $key ): void {
        $keys = get_option( self::OPTION_KEYS, [] );
        if ( ! is_array( $keys ) ) {
            $keys = [];
        }

        if ( ! in_array( $key, $keys, true ) ) {
            $keys[] = $key;
            update_option( self::OPTION_KEYS, $keys );
        }
    }

    /**
     * Forget a cache key.
     */
    private static function forget_key( string $key ): void {
        $keys = get_option( self::OPTION_KEYS, [] );
        if ( ! is_array( $keys ) ) {
            return;
        }

        $keys = array_values( array_diff( $keys, [ $key ] ) );
        update_option( self::OPTION_KEYS, $keys );
    }
}
