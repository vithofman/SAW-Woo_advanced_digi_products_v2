<?php
/**
 * Generic helper utilities.
 *
 * @package SAW\WAP\Helpers
 */

declare( strict_types=1 );

namespace SAW\WAP\Helpers;

/**
 * Utility helpers.
 */
class Utils {
    /**
     * Sanitize a float value ensuring non-negative result.
     */
    public static function sanitize_positive_float( $value, float $default = 0.0 ): float {
        if ( is_string( $value ) ) {
            $value = str_replace( ',', '.', $value );
        }

        if ( ! is_numeric( $value ) ) {
            return $default;
        }

        $value = (float) $value;

        return $value >= 0 ? $value : $default;
    }

    /**
     * Sanitize non-negative integer.
     */
    public static function sanitize_non_negative_int( $value, int $default = 0 ): int {
        if ( is_string( $value ) ) {
            $value = trim( $value );
        }

        if ( '' === $value ) {
            return $default;
        }

        if ( ! is_numeric( $value ) ) {
            return $default;
        }

        $value = (int) $value;

        return max( 0, $value );
    }

    /**
     * Sanitize URL.
     */
    public static function sanitize_url( string $value ): string {
        return esc_url_raw( trim( $value ) );
    }

    /**
     * Sanitize checkbox value to boolean.
     */
    public static function sanitize_checkbox( $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }

        if ( is_string( $value ) ) {
            $value = strtolower( $value );
            return in_array( $value, [ '1', 'true', 'yes', 'on' ], true );
        }

        if ( is_numeric( $value ) ) {
            return (int) $value === 1;
        }

        return false;
    }

    /**
     * Sanitize JSON string (returns canonical representation or empty array JSON).
     */
    public static function sanitize_json( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '[]';
        }

        $decoded = json_decode( $value, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return '[]';
        }

        return wp_json_encode( $decoded );
    }
}
