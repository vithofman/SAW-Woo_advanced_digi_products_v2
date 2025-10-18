<?php
/**
 * Product data tab and fields for digital products.
 *
 * @package SAW\WAP\Admin
 */

declare( strict_types=1 );

namespace SAW\WAP\Admin;

use WC_Product;

/**
 * Registers the "SAW – Digital Content" product tab in WooCommerce.
 */
class ProductFields {
    /**
     * Tab identifier.
     */
    private const TAB_ID = 'sawwap_digital';

    /**
     * Initialize hooks.
     */
    public static function init(): void {
        add_filter( 'woocommerce_product_data_tabs', [ self::class, 'register_tab' ] );
        add_action( 'woocommerce_product_data_panels', [ self::class, 'render_panel' ] );
        add_action( 'woocommerce_admin_process_product_object', [ self::class, 'save_product_fields' ] );
    }

    /**
     * Register the custom tab.
     *
     * @param array<string,mixed> $tabs Existing tabs.
     * @return array<string,mixed>
     */
    public static function register_tab( array $tabs ): array {
        $tabs[ self::TAB_ID ] = [
            'label'    => __( 'SAW – Digital Content', 'saw-wap' ),
            'target'   => 'sawwap_digital_product_data',
            'class'    => [ 'show_if_simple', 'show_if_variable', 'show_if_external', 'sawwap-digital-tab' ],
            'priority' => 65,
        ];

        return $tabs;
    }

    /**
     * Render the product data panel.
     */
    public static function render_panel(): void {
        $product = wc_get_product();

        if ( ! $product instanceof WC_Product ) {
            return;
        }

        $meta = self::get_product_meta( $product );
        ?>
        <div id="sawwap_digital_product_data" class="panel woocommerce_options_panel hidden">
            <div class="options_group">
                <?php
                woocommerce_wp_checkbox(
                    [
                        'id'          => 'sawwap_is_video_product',
                        'label'       => __( 'Is digital video product', 'saw-wap' ),
                        'value'       => $meta['sawwap_is_video_product'] ? 'yes' : 'no',
                        'description' => __( 'Enable access control and PDP enhancements for this product.', 'saw-wap' ),
                    ]
                );

                woocommerce_wp_select(
                    [
                        'id'          => 'sawwap_video_provider',
                        'label'       => __( 'Video provider', 'saw-wap' ),
                        'options'     => [
                            ''         => __( 'Select provider', 'saw-wap' ),
                            'youtube'  => __( 'YouTube', 'saw-wap' ),
                            'vimeo'    => __( 'Vimeo', 'saw-wap' ),
                        ],
                        'value'       => $meta['sawwap_video_provider'],
                        'description' => __( 'Choose the streaming provider for embeds.', 'saw-wap' ),
                    ]
                );

                woocommerce_wp_text_input(
                    [
                        'id'          => 'sawwap_video_url',
                        'label'       => __( 'Primary video URL', 'saw-wap' ),
                        'value'       => $meta['sawwap_video_url'],
                        'placeholder' => 'https://',
                        'description' => __( 'Single lesson URL. Leave empty when using multi-lesson JSON below.', 'saw-wap' ),
                    ]
                );

                woocommerce_wp_textarea_input(
                    [
                        'id'          => 'sawwap_video_urls',
                        'label'       => __( 'Lesson URLs (JSON)', 'saw-wap' ),
                        'value'       => $meta['sawwap_video_urls'],
                        'description' => __( 'JSON array of lesson URLs. Example: ["https://..."]', 'saw-wap' ),
                    ]
                );

                woocommerce_wp_text_input(
                    [
                        'id'                => 'sawwap_video_duration',
                        'label'             => __( 'Video duration (minutes)', 'saw-wap' ),
                        'value'             => $meta['sawwap_video_duration'] > 0 ? (string) $meta['sawwap_video_duration'] : '',
                        'type'              => 'number',
                        'description'       => __( 'Approximate running time in minutes.', 'saw-wap' ),
                        'custom_attributes' => [
                            'min' => '0',
                            'step' => '1',
                        ],
                    ]
                );

                woocommerce_wp_text_input(
                    [
                        'id'                => 'sawwap_access_days',
                        'label'             => __( 'Access duration (days)', 'saw-wap' ),
                        'value'             => (string) $meta['sawwap_access_days'],
                        'type'              => 'number',
                        'description'       => __( 'Number of days the customer keeps access (default 365).', 'saw-wap' ),
                        'custom_attributes' => [
                            'min' => '0',
                            'step' => '1',
                        ],
                    ]
                );

                woocommerce_wp_textarea_input(
                    [
                        'id'          => 'sawwap_promo_tiers',
                        'label'       => __( 'Promo tiers (JSON)', 'saw-wap' ),
                        'value'       => $meta['sawwap_promo_tiers'],
                        'description' => __( 'JSON array of objects {"limit":int,"price":float}. TODO: Replace with repeater UI.', 'saw-wap' ),
                    ]
                );

                woocommerce_wp_text_input(
                    [
                        'id'          => 'sawwap_promo_until',
                        'label'       => __( 'Promo valid until', 'saw-wap' ),
                        'value'       => $meta['sawwap_promo_until'],
                        'description' => __( 'Date in Y-m-d H:i:s. Leave blank for no expiry.', 'saw-wap' ),
                    ]
                );

                woocommerce_wp_textarea_input(
                    [
                        'id'          => 'sawwap_bundle_items',
                        'label'       => __( 'Bundle product IDs (JSON)', 'saw-wap' ),
                        'value'       => $meta['sawwap_bundle_items'],
                        'description' => __( 'JSON array of related product IDs. TODO: Replace with selector UI.', 'saw-wap' ),
                    ]
                );

                woocommerce_wp_text_input(
                    [
                        'id'                => 'sawwap_points_award',
                        'label'             => __( 'Points award override', 'saw-wap' ),
                        'value'             => $meta['sawwap_points_award'] > 0 ? (string) $meta['sawwap_points_award'] : '',
                        'type'              => 'number',
                        'description'       => __( 'Override default points reward. Leave empty for automatic calculation.', 'saw-wap' ),
                        'custom_attributes' => [
                            'min' => '0',
                            'step' => '1',
                        ],
                    ]
                );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Retrieve product meta values with defaults.
     *
     * @param WC_Product $product Product.
     * @return array<string,mixed>
     */
    private static function get_product_meta( WC_Product $product ): array {
        $meta = [
            'sawwap_is_video_product' => (bool) (int) $product->get_meta( 'sawwap_is_video_product', true ),
            'sawwap_video_provider'   => (string) $product->get_meta( 'sawwap_video_provider', true ),
            'sawwap_video_url'        => (string) $product->get_meta( 'sawwap_video_url', true ),
            'sawwap_video_urls'       => (string) $product->get_meta( 'sawwap_video_urls', true ),
            'sawwap_video_duration'   => (int) $product->get_meta( 'sawwap_video_duration', true ),
            'sawwap_access_days'      => (int) $product->get_meta( 'sawwap_access_days', true ),
            'sawwap_promo_tiers'      => (string) $product->get_meta( 'sawwap_promo_tiers', true ),
            'sawwap_promo_until'      => (string) $product->get_meta( 'sawwap_promo_until', true ),
            'sawwap_bundle_items'     => (string) $product->get_meta( 'sawwap_bundle_items', true ),
            'sawwap_points_award'     => (int) $product->get_meta( 'sawwap_points_award', true ),
        ];

        if ( 0 === $meta['sawwap_access_days'] ) {
            $meta['sawwap_access_days'] = 365;
        }

        if ( '' === $meta['sawwap_video_urls'] ) {
            $meta['sawwap_video_urls'] = '[]';
        }

        if ( '' === $meta['sawwap_promo_tiers'] ) {
            $meta['sawwap_promo_tiers'] = '[]';
        }

        if ( '' === $meta['sawwap_bundle_items'] ) {
            $meta['sawwap_bundle_items'] = '[]';
        }

        return $meta;
    }

    /**
     * Persist product meta when saved.
     *
     * @param WC_Product $product Product being saved.
     */
    public static function save_product_fields( WC_Product $product ): void {
        $is_video = isset( $_POST['sawwap_is_video_product'] ) ? 'yes' === wp_unslash( $_POST['sawwap_is_video_product'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $product->update_meta_data( 'sawwap_is_video_product', $is_video ? 1 : 0 );

        $provider = isset( $_POST['sawwap_video_provider'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sawwap_video_provider'] ) ) : '';
        $product->update_meta_data( 'sawwap_video_provider', self::sanitize_provider( $provider ) );

        $video_url = isset( $_POST['sawwap_video_url'] ) ? (string) wp_unslash( $_POST['sawwap_video_url'] ) : '';
        $product->update_meta_data( 'sawwap_video_url', esc_url_raw( $video_url ) );

        $video_urls_raw = isset( $_POST['sawwap_video_urls'] ) ? (string) wp_unslash( $_POST['sawwap_video_urls'] ) : '';
        $product->update_meta_data( 'sawwap_video_urls', self::sanitize_json_urls( $video_urls_raw ) );

        $duration_raw = isset( $_POST['sawwap_video_duration'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sawwap_video_duration'] ) ) : '';
        $duration     = '' === $duration_raw ? 0 : (int) $duration_raw;
        $product->update_meta_data( 'sawwap_video_duration', max( 0, $duration ) );

        $access_days_raw = isset( $_POST['sawwap_access_days'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sawwap_access_days'] ) ) : '';
        $access_days     = '' === $access_days_raw ? 365 : (int) $access_days_raw;
        $product->update_meta_data( 'sawwap_access_days', max( 0, $access_days ) );

        $promo_tiers = isset( $_POST['sawwap_promo_tiers'] ) ? (string) wp_unslash( $_POST['sawwap_promo_tiers'] ) : '';
        $product->update_meta_data( 'sawwap_promo_tiers', self::sanitize_promo_tiers( $promo_tiers ) );

        $promo_until = isset( $_POST['sawwap_promo_until'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sawwap_promo_until'] ) ) : '';
        $product->update_meta_data( 'sawwap_promo_until', self::sanitize_datetime( $promo_until ) );

        $bundle_items = isset( $_POST['sawwap_bundle_items'] ) ? (string) wp_unslash( $_POST['sawwap_bundle_items'] ) : '';
        $product->update_meta_data( 'sawwap_bundle_items', self::sanitize_bundle_items( $bundle_items ) );

        $points_award_raw = isset( $_POST['sawwap_points_award'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sawwap_points_award'] ) ) : '';
        $points_award     = '' === $points_award_raw ? 0 : (int) $points_award_raw;
        $product->update_meta_data( 'sawwap_points_award', max( 0, $points_award ) );
    }

    /**
     * Sanitize provider value.
     */
    private static function sanitize_provider( string $provider ): string {
        $allowed = [ 'youtube', 'vimeo' ];
        return in_array( $provider, $allowed, true ) ? $provider : '';
    }

    /**
     * Sanitize JSON array of URLs.
     */
    private static function sanitize_json_urls( string $raw ): string {
        $raw = trim( $raw );
        if ( '' === $raw ) {
            return '[]';
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return '[]';
        }

        $sanitized = [];
        foreach ( $decoded as $url ) {
            if ( ! is_scalar( $url ) ) {
                continue;
            }
            $clean = esc_url_raw( (string) $url );
            if ( '' !== $clean ) {
                $sanitized[] = $clean;
            }
        }

        return wp_json_encode( $sanitized );
    }

    /**
     * Sanitize promo tiers.
     */
    private static function sanitize_promo_tiers( string $raw ): string {
        $raw = trim( $raw );
        if ( '' === $raw ) {
            return '[]';
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return '[]';
        }

        $sanitized = [];
        foreach ( $decoded as $tier ) {
            if ( ! is_array( $tier ) ) {
                continue;
            }

            $limit = isset( $tier['limit'] ) ? (int) $tier['limit'] : 0;
            $price = isset( $tier['price'] ) ? (float) $tier['price'] : 0.0;

            if ( $limit < 0 || $price < 0 ) {
                continue;
            }

            $sanitized[] = [
                'limit' => $limit,
                'price' => (float) wc_format_decimal( $price ),
            ];
        }

        return wp_json_encode( $sanitized );
    }

    /**
     * Sanitize datetime string.
     */
    private static function sanitize_datetime( string $datetime ): string {
        $datetime = trim( $datetime );
        if ( '' === $datetime ) {
            return '';
        }

        $timestamp = strtotime( $datetime );
        if ( false === $timestamp ) {
            return '';
        }

        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Sanitize bundle items JSON array.
     */
    private static function sanitize_bundle_items( string $raw ): string {
        $raw = trim( $raw );
        if ( '' === $raw ) {
            return '[]';
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return '[]';
        }

        $sanitized = [];
        foreach ( $decoded as $product_id ) {
            $product_id = (int) $product_id;
            if ( $product_id > 0 ) {
                $sanitized[] = $product_id;
            }
        }

        return wp_json_encode( $sanitized );
    }
}
