<?php
/**
 * Settings screen registration.
 *
 * @package SAW\WAP\Admin
 */

declare( strict_types=1 );

namespace SAW\WAP\Admin;

use SAW\WAP\Helpers\Utils;

/**
 * WooCommerce > SAW-WAP settings page.
 */
class Settings {
    /**
     * Capability required for the settings page.
     */
    private const CAPABILITY = 'manage_woocommerce';

    /**
     * Menu slug.
     */
    private const MENU_SLUG = 'saw-wap-settings';

    /**
     * Register hooks.
     */
    public static function init(): void {
        add_action( 'admin_menu', [ self::class, 'register_menu' ] );
        add_action( 'admin_init', [ self::class, 'register_settings' ] );
        add_action( 'admin_post_sawwap_invalidate_cache', [ self::class, 'handle_invalidate_cache' ] );
    }

    /**
     * Add submenu under WooCommerce.
     */
    public static function register_menu(): void {
        add_submenu_page(
            'woocommerce',
            __( 'SAW – Woo Advanced Products', 'saw-wap' ),
            __( 'SAW-WAP', 'saw-wap' ),
            self::CAPABILITY,
            self::MENU_SLUG,
            [ self::class, 'render_settings_page' ]
        );
    }

    /**
     * Register plugin settings and fields.
     */
    public static function register_settings(): void {
        register_setting( 'sawwap_options', 'sawwap_points_rate', [
            'type'              => 'number',
            'sanitize_callback' => [ Utils::class, 'sanitize_positive_float' ],
            'default'           => 0.05,
        ] );

        register_setting( 'sawwap_options', 'sawwap_default_access_days', [
            'type'              => 'integer',
            'sanitize_callback' => [ Utils::class, 'sanitize_non_negative_int' ],
            'default'           => 365,
        ] );

        register_setting( 'sawwap_options', 'sawwap_max_points_discount_pct', [
            'type'              => 'integer',
            'sanitize_callback' => [ Utils::class, 'sanitize_non_negative_int' ],
            'default'           => 20,
        ] );

        register_setting( 'sawwap_options', 'sawwap_bundles_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => [ Utils::class, 'sanitize_checkbox' ],
            'default'           => false,
        ] );

        register_setting( 'sawwap_options', 'sawwap_licence_url', [
            'type'              => 'string',
            'sanitize_callback' => [ Utils::class, 'sanitize_url' ],
            'default'           => '',
        ] );

        register_setting( 'sawwap_options', 'sawwap_digital_consent_text', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        ] );

        register_setting( 'sawwap_options', 'sawwap_discount_mode', [
            'type'              => 'string',
            'sanitize_callback' => [ self::class, 'sanitize_discount_mode' ],
            'default'           => 'itemized',
        ] );

        register_setting( 'sawwap_options', 'sawwap_discount_rules', [
            'type'              => 'string',
            'sanitize_callback' => [ Utils::class, 'sanitize_json' ],
            'default'           => '[]',
        ] );

        register_setting( 'sawwap_options', 'sawwap_cache_ttl', [
            'type'              => 'integer',
            'sanitize_callback' => [ Utils::class, 'sanitize_non_negative_int' ],
            'default'           => 600,
        ] );

        register_setting( 'sawwap_options', 'sawwap_feature_flags', [
            'type'              => 'array',
            'sanitize_callback' => [ self::class, 'sanitize_feature_flags' ],
            'default'           => [],
        ] );

        add_settings_section( 'sawwap_general', __( 'General', 'saw-wap' ), null, self::MENU_SLUG );
        add_settings_field( 'sawwap_points_rate', __( 'Points conversion rate', 'saw-wap' ), [ self::class, 'render_points_rate_field' ], self::MENU_SLUG, 'sawwap_general' );
        add_settings_field( 'sawwap_default_access_days', __( 'Default access days', 'saw-wap' ), [ self::class, 'render_access_days_field' ], self::MENU_SLUG, 'sawwap_general' );
        add_settings_field( 'sawwap_max_points_discount_pct', __( 'Max points discount %', 'saw-wap' ), [ self::class, 'render_max_points_field' ], self::MENU_SLUG, 'sawwap_general' );
        add_settings_field( 'sawwap_bundles_enabled', __( 'Enable bundles', 'saw-wap' ), [ self::class, 'render_bundles_field' ], self::MENU_SLUG, 'sawwap_general' );

        add_settings_section( 'sawwap_legal', __( 'Legal & Texts', 'saw-wap' ), null, self::MENU_SLUG );
        add_settings_field( 'sawwap_licence_url', __( 'Licence URL', 'saw-wap' ), [ self::class, 'render_licence_url_field' ], self::MENU_SLUG, 'sawwap_legal' );
        add_settings_field( 'sawwap_digital_consent_text', __( 'Digital consent text', 'saw-wap' ), [ self::class, 'render_consent_text_field' ], self::MENU_SLUG, 'sawwap_legal' );

        add_settings_section( 'sawwap_discounts', __( 'Discounts', 'saw-wap' ), null, self::MENU_SLUG );
        add_settings_field( 'sawwap_discount_mode', __( 'Discount mode', 'saw-wap' ), [ self::class, 'render_discount_mode_field' ], self::MENU_SLUG, 'sawwap_discounts' );
        add_settings_field( 'sawwap_discount_rules', __( 'Discount rules (JSON)', 'saw-wap' ), [ self::class, 'render_discount_rules_field' ], self::MENU_SLUG, 'sawwap_discounts' );

        add_settings_section( 'sawwap_performance', __( 'Performance', 'saw-wap' ), null, self::MENU_SLUG );
        add_settings_field( 'sawwap_cache_ttl', __( 'Cache TTL (seconds)', 'saw-wap' ), [ self::class, 'render_cache_ttl_field' ], self::MENU_SLUG, 'sawwap_performance' );
        add_settings_field( 'sawwap_cache_button', __( 'Cache control', 'saw-wap' ), [ self::class, 'render_cache_button_field' ], self::MENU_SLUG, 'sawwap_performance' );

        add_settings_section( 'sawwap_features', __( 'Feature Flags', 'saw-wap' ), null, self::MENU_SLUG );
        add_settings_field( 'sawwap_feature_flags', __( 'Flags', 'saw-wap' ), [ self::class, 'render_feature_flags_field' ], self::MENU_SLUG, 'sawwap_features' );
    }

    /**
     * Render settings page.
     */
    public static function render_settings_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'saw-wap' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'SAW – Woo Advanced Products', 'saw-wap' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'sawwap_options' );
                do_settings_sections( self::MENU_SLUG );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle cache invalidation button.
     */
    public static function handle_invalidate_cache(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'saw-wap' ) );
        }

        check_admin_referer( 'sawwap_invalidate_cache' );

        /**
         * Fires when the admin requests cache invalidation.
         */
        do_action( 'sawwap_cache_invalidate' );

        wp_safe_redirect( add_query_arg( 'sawwap_cache_flushed', '1', wp_get_referer() ?: admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) );
        exit;
    }

    /**
     * Render numeric field for points rate.
     */
    public static function render_points_rate_field(): void {
        $value = (float) get_option( 'sawwap_points_rate', 0.05 );
        printf(
            '<input type="number" name="sawwap_points_rate" id="sawwap_points_rate" step="0.01" min="0" value="%s" class="small-text" /> <span class="description">%s</span>',
            esc_attr( (string) $value ),
            esc_html__( 'CZK → points conversion multiplier.', 'saw-wap' )
        );
    }

    /**
     * Render default access days field.
     */
    public static function render_access_days_field(): void {
        $value = (int) get_option( 'sawwap_default_access_days', 365 );
        printf(
            '<input type="number" name="sawwap_default_access_days" id="sawwap_default_access_days" min="0" step="1" value="%d" class="small-text" />',
            $value
        );
    }

    /**
     * Render max points field.
     */
    public static function render_max_points_field(): void {
        $value = (int) get_option( 'sawwap_max_points_discount_pct', 20 );
        printf(
            '<input type="number" name="sawwap_max_points_discount_pct" id="sawwap_max_points_discount_pct" min="0" max="100" step="1" value="%d" class="small-text" />',
            $value
        );
    }

    /**
     * Render bundles toggle.
     */
    public static function render_bundles_field(): void {
        $value = (bool) get_option( 'sawwap_bundles_enabled', false );
        printf(
            '<label><input type="checkbox" name="sawwap_bundles_enabled" value="1" %s /> %s</label>',
            checked( $value, true, false ),
            esc_html__( 'Enable bundle logic and related UI.', 'saw-wap' )
        );
    }

    /**
     * Render licence URL field.
     */
    public static function render_licence_url_field(): void {
        $value = (string) get_option( 'sawwap_licence_url', '' );
        printf(
            '<input type="url" class="regular-text" name="sawwap_licence_url" id="sawwap_licence_url" value="%s" placeholder="https://" />',
            esc_attr( $value )
        );
    }

    /**
     * Render consent text textarea.
     */
    public static function render_consent_text_field(): void {
        $value = (string) get_option( 'sawwap_digital_consent_text', '' );
        printf(
            '<textarea name="sawwap_digital_consent_text" id="sawwap_digital_consent_text" class="large-text" rows="4">%s</textarea>',
            esc_textarea( $value )
        );
    }

    /**
     * Render discount mode select.
     */
    public static function render_discount_mode_field(): void {
        $value = (string) get_option( 'sawwap_discount_mode', 'itemized' );
        ?>
        <select name="sawwap_discount_mode" id="sawwap_discount_mode">
            <option value="itemized" <?php selected( $value, 'itemized' ); ?>><?php esc_html_e( 'Mode A – Itemized line prices', 'saw-wap' ); ?></option>
            <option value="fee" <?php selected( $value, 'fee' ); ?>><?php esc_html_e( 'Mode B – Negative fee line', 'saw-wap' ); ?></option>
        </select>
        <?php
    }

    /**
     * Render discount rules textarea.
     */
    public static function render_discount_rules_field(): void {
        $value = (string) get_option( 'sawwap_discount_rules', '[]' );
        printf(
            '<textarea name="sawwap_discount_rules" id="sawwap_discount_rules" class="large-text code" rows="6">%s</textarea><p class="description">%s</p>',
            esc_textarea( $value ),
            esc_html__( 'JSON definition of discount rules. TODO: replace with visual editor.', 'saw-wap' )
        );
    }

    /**
     * Render cache TTL field.
     */
    public static function render_cache_ttl_field(): void {
        $value = (int) get_option( 'sawwap_cache_ttl', 600 );
        printf(
            '<input type="number" name="sawwap_cache_ttl" id="sawwap_cache_ttl" min="0" step="1" value="%d" class="small-text" />',
            $value
        );
    }

    /**
     * Render cache button field.
     */
    public static function render_cache_button_field(): void {
        $url = wp_nonce_url( admin_url( 'admin-post.php?action=sawwap_invalidate_cache' ), 'sawwap_invalidate_cache' );
        echo '<a href="' . esc_url( $url ) . '" class="button">' . esc_html__( 'Invalidate cache', 'saw-wap' ) . '</a>';
        if ( isset( $_GET['sawwap_cache_flushed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="description">' . esc_html__( 'Cache invalidated.', 'saw-wap' ) . '</p>';
        }
    }

    /**
     * Render feature flags checkboxes.
     */
    public static function render_feature_flags_field(): void {
        $value  = (array) get_option( 'sawwap_feature_flags', [] );
        $flags  = self::get_feature_flag_labels();
        foreach ( $flags as $flag => $label ) {
            printf(
                '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="sawwap_feature_flags[%1$s]" value="1" %2$s /> %3$s</label>',
                esc_attr( $flag ),
                checked( isset( $value[ $flag ] ) && Utils::sanitize_checkbox( $value[ $flag ] ), true, false ),
                esc_html( $label )
            );
        }
    }

    /**
     * Sanitize discount mode.
     */
    public static function sanitize_discount_mode( string $value ): string {
        $value   = strtolower( $value );
        $allowed = [ 'itemized', 'fee' ];

        return in_array( $value, $allowed, true ) ? $value : 'itemized';
    }

    /**
     * Sanitize feature flags array.
     *
     * @param mixed $value Submitted value.
     * @return array<string,bool>
     */
    public static function sanitize_feature_flags( $value ): array {
        $allowed = array_keys( self::get_feature_flag_labels() );
        $clean   = [];

        if ( ! is_array( $value ) ) {
            return $clean;
        }

        foreach ( $value as $flag => $enabled ) {
            if ( in_array( $flag, $allowed, true ) && Utils::sanitize_checkbox( $enabled ) ) {
                $clean[ $flag ] = true;
            }
        }

        return $clean;
    }

    /**
     * Get feature flag labels.
     *
     * @return array<string,string>
     */
    private static function get_feature_flag_labels(): array {
        return [
            'use_db_access_table'   => __( 'Use dedicated DB table for access records', 'saw-wap' ),
            'use_db_points_tables'  => __( 'Use dedicated DB tables for points', 'saw-wap' ),
            'enable_events'         => __( 'Enable events engine', 'saw-wap' ),
            'enable_magic_link'     => __( 'Enable magic link authentication', 'saw-wap' ),
        ];
    }
}
