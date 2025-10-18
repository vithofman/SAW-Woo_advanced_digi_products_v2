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
            'label'    => __( 'SAW – Digitální obsah', 'saw-wap' ),
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

        $meta               = self::get_product_meta( $product );
        $base_price         = self::get_base_price( $product );
        $bundle_suggestions = self::get_bundle_suggestions( $product );
        ?>
        <div id="sawwap_digital_product_data" class="panel woocommerce_options_panel hidden">
            <div class="options_group sawwap-digital-panel">
                <?php
                woocommerce_wp_checkbox(
                    [
                        'id'          => 'sawwap_is_video_product',
                        'label'       => __( 'Digitální video produkt', 'saw-wap' ),
                        'value'       => $meta['sawwap_is_video_product'] ? 'yes' : 'no',
                        'description' => __( 'Po aktivaci se zákazníkům automaticky nastaví přístupy, speciální šablona detailu a body za nákup.', 'saw-wap' ),
                    ]
                );

                woocommerce_wp_select(
                    [
                        'id'          => 'sawwap_video_provider',
                        'label'       => __( 'Poskytovatel videa', 'saw-wap' ),
                        'options'     => [
                            ''         => __( 'Vyberte poskytovatele', 'saw-wap' ),
                            'youtube'  => __( 'YouTube', 'saw-wap' ),
                            'vimeo'    => __( 'Vimeo', 'saw-wap' ),
                        ],
                        'value'       => $meta['sawwap_video_provider'],
                        'description' => __( 'Dle poskytovatele se přizpůsobí vložený přehrávač.', 'saw-wap' ),
                    ]
                );

                woocommerce_wp_text_input(
                    [
                        'id'          => 'sawwap_video_url',
                        'label'       => __( 'Jedno video – odkaz', 'saw-wap' ),
                        'value'       => $meta['sawwap_video_url'],
                        'placeholder' => 'https://',
                        'description' => __( 'Pokud má kurz jen jedno video, vložte jeho adresu sem. Pro více lekcí použijte seznam níže.', 'saw-wap' ),
                    ]
                );

                self::render_lessons_field( $meta['sawwap_video_urls'] );

                woocommerce_wp_text_input(
                    [
                        'id'                => 'sawwap_video_duration',
                        'label'             => __( 'Délka videa (minuty)', 'saw-wap' ),
                        'value'             => $meta['sawwap_video_duration'] > 0 ? (string) $meta['sawwap_video_duration'] : '',
                        'type'              => 'number',
                        'description'       => __( 'Uveďte orientační délku kurzu. Pokud necháte prázdné, zobrazí se automatické odhadnutí.', 'saw-wap' ),
                        'custom_attributes' => [
                            'min'  => '0',
                            'step' => '1',
                        ],
                    ]
                );

                woocommerce_wp_text_input(
                    [
                        'id'                => 'sawwap_access_days',
                        'label'             => __( 'Dostupnost pro zákazníka (dny)', 'saw-wap' ),
                        'value'             => (string) $meta['sawwap_access_days'],
                        'type'              => 'number',
                        'description'       => __( 'Výchozí hodnota se doplní z nastavení (365 dní). Zde můžete případně přepsat.', 'saw-wap' ),
                        'custom_attributes' => [
                            'min'  => '0',
                            'step' => '1',
                        ],
                    ]
                );

                self::render_promo_tiers_field( $meta['sawwap_promo_tiers'], $base_price );

                woocommerce_wp_text_input(
                    [
                        'id'                => 'sawwap_promo_until',
                        'label'             => __( 'Akce platí do', 'saw-wap' ),
                        'value'             => self::format_datetime_local( $meta['sawwap_promo_until'] ),
                        'type'              => 'datetime-local',
                        'description'       => __( 'Datum konce akce. Automaticky se přepne na běžnou cenu. Čas se řídí nastavením WordPressu.', 'saw-wap' ),
                    ]
                );

                self::render_bundle_field( $meta['sawwap_bundle_items'], $bundle_suggestions );

                woocommerce_wp_text_input(
                    [
                        'id'                => 'sawwap_points_award',
                        'label'             => __( 'Bonusové body (volitelné)', 'saw-wap' ),
                        'value'             => $meta['sawwap_points_award'] > 0 ? (string) $meta['sawwap_points_award'] : '',
                        'type'              => 'number',
                        'description'       => __( 'Necháte-li prázdné, systém dopočítá body automaticky podle ceny a nastaveného poměru.', 'saw-wap' ),
                        'custom_attributes' => [
                            'min'  => '0',
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
            'sawwap_video_urls'       => self::decode_json_list( (string) $product->get_meta( 'sawwap_video_urls', true ) ),
            'sawwap_video_duration'   => (int) $product->get_meta( 'sawwap_video_duration', true ),
            'sawwap_access_days'      => (int) $product->get_meta( 'sawwap_access_days', true ),
            'sawwap_promo_tiers'      => self::decode_promo_tiers( (string) $product->get_meta( 'sawwap_promo_tiers', true ) ),
            'sawwap_promo_until'      => (string) $product->get_meta( 'sawwap_promo_until', true ),
            'sawwap_bundle_items'     => self::decode_bundle_items( (string) $product->get_meta( 'sawwap_bundle_items', true ) ),
            'sawwap_points_award'     => (int) $product->get_meta( 'sawwap_points_award', true ),
        ];

        if ( 0 === $meta['sawwap_access_days'] ) {
            $meta['sawwap_access_days'] = (int) get_option( 'sawwap_default_access_days', 365 );
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

        $video_urls_input = isset( $_POST['sawwap_video_urls_list'] ) ? (array) wp_unslash( $_POST['sawwap_video_urls_list'] ) : [];
        $product->update_meta_data( 'sawwap_video_urls', self::sanitize_video_urls( $video_urls_input ) );

        $duration_raw = isset( $_POST['sawwap_video_duration'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sawwap_video_duration'] ) ) : '';
        $duration     = '' === $duration_raw ? 0 : (int) $duration_raw;
        $product->update_meta_data( 'sawwap_video_duration', max( 0, $duration ) );

        $access_days_raw = isset( $_POST['sawwap_access_days'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sawwap_access_days'] ) ) : '';
        $access_days     = '' === $access_days_raw ? (int) get_option( 'sawwap_default_access_days', 365 ) : (int) $access_days_raw;
        $product->update_meta_data( 'sawwap_access_days', max( 0, $access_days ) );

        $promo_limits = isset( $_POST['sawwap_promo_tiers_limit'] ) ? (array) wp_unslash( $_POST['sawwap_promo_tiers_limit'] ) : [];
        $promo_prices = isset( $_POST['sawwap_promo_tiers_price'] ) ? (array) wp_unslash( $_POST['sawwap_promo_tiers_price'] ) : [];
        $product->update_meta_data( 'sawwap_promo_tiers', self::sanitize_promo_tiers_inputs( $promo_limits, $promo_prices ) );

        $promo_until = isset( $_POST['sawwap_promo_until'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sawwap_promo_until'] ) ) : '';
        $product->update_meta_data( 'sawwap_promo_until', self::sanitize_datetime( $promo_until ) );

        $bundle_items = isset( $_POST['sawwap_bundle_items'] ) ? (array) wp_unslash( $_POST['sawwap_bundle_items'] ) : [];
        $product->update_meta_data( 'sawwap_bundle_items', self::sanitize_bundle_items_inputs( $bundle_items ) );

        $points_award_raw = isset( $_POST['sawwap_points_award'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sawwap_points_award'] ) ) : '';
        $points_award     = '' === $points_award_raw ? 0 : (int) $points_award_raw;
        $product->update_meta_data( 'sawwap_points_award', max( 0, $points_award ) );
    }

    /**
     * Render the multi-lesson field.
     *
     * @param array<int,string> $lessons Lesson URLs.
     */
    private static function render_lessons_field( array $lessons ): void {
        ?>
        <div class="sawwap-admin-card sawwap-lessons" data-component="lessons">
            <h4><?php esc_html_e( 'Více lekcí videa', 'saw-wap' ); ?></h4>
            <p class="description"><?php esc_html_e( 'Každý řádek představuje jednu lekci. Odkazy lze vložit hromadně, stačí je zkopírovat ze seznamu.', 'saw-wap' ); ?></p>
            <ul class="sawwap-repeater__list">
                <?php
                if ( empty( $lessons ) ) {
                    self::render_lessons_row( '' );
                } else {
                    foreach ( $lessons as $url ) {
                        self::render_lessons_row( $url );
                    }
                }
                self::render_lessons_row( '', true );
                ?>
            </ul>
            <div class="sawwap-repeater__actions">
                <button type="button" class="button button-secondary sawwap-lessons-add"><?php esc_html_e( 'Přidat lekci', 'saw-wap' ); ?></button>
                <button type="button" class="button sawwap-lessons-bulk" data-prompt="<?php echo esc_attr__( 'Vložte odkazy (každý na samostatném řádku).', 'saw-wap' ); ?>"><?php esc_html_e( 'Vložit více odkazů najednou', 'saw-wap' ); ?></button>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single lesson row.
     *
     * @param string $value URL value.
     * @param bool   $is_template Whether the row is a template.
     */
    private static function render_lessons_row( string $value, bool $is_template = false ): void {
        $classes = [ 'sawwap-repeater__item' ];
        if ( $is_template ) {
            $classes[] = 'sawwap-repeater__item--template';
        }

        $style = $is_template ? ' style="display:none;"' : '';
        ?>
        <li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <span class="sawwap-repeater__handle" aria-hidden="true">☰</span>
            <input type="url" name="sawwap_video_urls_list[]" value="<?php echo esc_attr( $value ); ?>" placeholder="https://" class="sawwap-repeater__input" />
            <button type="button" class="button-link-delete sawwap-repeater__remove" aria-label="<?php esc_attr_e( 'Odebrat lekci', 'saw-wap' ); ?>">&times;</button>
        </li>
        <?php
    }

    /**
     * Render promo tiers field.
     *
     * @param array<int,array<string,float|int>> $tiers Promo tiers.
     * @param float                              $base_price Base price suggestion.
     */
    private static function render_promo_tiers_field( array $tiers, float $base_price ): void {
        ?>
        <div class="sawwap-admin-card sawwap-promo" data-component="promo" data-base-price="<?php echo esc_attr( (string) $base_price ); ?>">
            <h4><?php esc_html_e( 'Množstevní ceny', 'saw-wap' ); ?></h4>
            <p class="description"><?php esc_html_e( 'Definujte vlastní cenu při nákupu více licencí najednou. Cena se automaticky přepočítá při vložení do košíku.', 'saw-wap' ); ?></p>
            <div class="sawwap-table-wrapper">
                <table class="sawwap-repeater-table widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Od počtu kusů', 'saw-wap' ); ?></th>
                            <th><?php esc_html_e( 'Cena za kus (CZK)', 'saw-wap' ); ?></th>
                            <th class="column-actions">&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody class="sawwap-repeater__list">
                        <?php
                        if ( empty( $tiers ) ) {
                            self::render_promo_tier_row( 2, $base_price );
                        } else {
                            foreach ( $tiers as $tier ) {
                                $limit = isset( $tier['limit'] ) ? (int) $tier['limit'] : 0;
                                $price = isset( $tier['price'] ) ? (float) $tier['price'] : 0.0;
                                self::render_promo_tier_row( $limit, $price );
                            }
                        }
                        self::render_promo_tier_row( 0, 0.0, true );
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="sawwap-repeater__actions">
                <button type="button" class="button button-secondary sawwap-promo-add"><?php esc_html_e( 'Přidat úroveň', 'saw-wap' ); ?></button>
                <button type="button" class="button sawwap-promo-suggest" data-suggestion="<?php echo esc_attr( wp_json_encode( self::get_default_promo_suggestions() ) ); ?>"><?php esc_html_e( 'Navrhnout automaticky', 'saw-wap' ); ?></button>
            </div>
        </div>
        <?php
    }

    /**
     * Render a promo tier row.
     *
     * @param int   $limit Minimum quantity.
     * @param float $price Unit price.
     * @param bool  $is_template Whether this is a template row.
     */
    private static function render_promo_tier_row( int $limit, float $price, bool $is_template = false ): void {
        $classes = [ 'sawwap-repeater__item' ];
        if ( $is_template ) {
            $classes[] = 'sawwap-repeater__item--template';
        }

        $style          = $is_template ? ' style="display:none;"' : '';
        $price_display  = '';
        if ( $price > 0 ) {
            $price_display = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $price ) : (string) round( $price, 2 );
        }
        ?>
        <tr class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <td>
                <input type="number" min="2" step="1" name="sawwap_promo_tiers_limit[]" value="<?php echo esc_attr( $limit > 0 ? (string) $limit : '' ); ?>" class="small-text" />
            </td>
            <td>
                <input type="number" min="0" step="0.01" name="sawwap_promo_tiers_price[]" value="<?php echo esc_attr( $price_display ); ?>" class="regular-text" />
            </td>
            <td class="column-actions">
                <button type="button" class="button-link-delete sawwap-repeater__remove" aria-label="<?php esc_attr_e( 'Odebrat úroveň', 'saw-wap' ); ?>">&times;</button>
            </td>
        </tr>
        <?php
    }

    /**
     * Render bundle field with suggestions.
     *
     * @param array<int,int>                $selected Selected product IDs.
     * @param array<int,array<string,mixed>> $suggestions Suggested products.
     */
    private static function render_bundle_field( array $selected, array $suggestions ): void {
        ?>
        <div class="sawwap-admin-card sawwap-bundle-field" data-component="bundles" data-suggestions="<?php echo esc_attr( wp_json_encode( $suggestions ) ); ?>">
            <h4><?php esc_html_e( 'Doporučené kurzy do balíčku', 'saw-wap' ); ?></h4>
            <p class="description"><?php esc_html_e( 'Vyberte další kurzy, ke kterým zákazník získá přístup. Nabízíme automatické návrhy podle kategorie.', 'saw-wap' ); ?></p>
            <select class="wc-product-search sawwap-bundle-select" style="width:100%;" name="sawwap_bundle_items[]" data-placeholder="<?php echo esc_attr__( 'Začněte psát název kurzu…', 'saw-wap' ); ?>" data-action="woocommerce_json_search_products" multiple="multiple">
                <?php
                foreach ( $selected as $product_id ) {
                    $product = wc_get_product( $product_id );
                    if ( ! $product ) {
                        continue;
                    }
                    printf(
                        '<option value="%1$d" selected="selected">%2$s</option>',
                        (int) $product_id,
                        esc_html( $product->get_formatted_name() )
                    );
                }
                ?>
            </select>
            <?php if ( ! empty( $suggestions ) ) : ?>
                <div class="sawwap-repeater__actions">
                    <button type="button" class="button sawwap-bundle-apply"><?php esc_html_e( 'Přidat doporučené kurzy', 'saw-wap' ); ?></button>
                </div>
            <?php else : ?>
                <p class="description sawwap-bundle-empty"><?php esc_html_e( 'Jakmile produkt zařadíte do kategorie, zobrazíme zde vhodné návrhy.', 'saw-wap' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Sanitize provider value.
     */
    private static function sanitize_provider( string $provider ): string {
        $allowed = [ 'youtube', 'vimeo' ];
        return in_array( $provider, $allowed, true ) ? $provider : '';
    }

    /**
     * Sanitize array of video URLs.
     *
     * @param array<int,mixed> $urls Raw URLs.
     */
    private static function sanitize_video_urls( array $urls ): string {
        $sanitized = [];
        foreach ( $urls as $url ) {
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
     * Sanitize promo tiers input arrays.
     *
     * @param array<int,mixed> $limits Limits.
     * @param array<int,mixed> $prices Prices.
     */
    private static function sanitize_promo_tiers_inputs( array $limits, array $prices ): string {
        $sanitized = [];
        foreach ( $limits as $index => $limit_raw ) {
            $limit = max( 0, (int) $limit_raw );
            $price = $prices[ $index ] ?? '';
            $price = is_scalar( $price ) ? (float) str_replace( ',', '.', (string) $price ) : 0.0;
            $price = $price < 0 ? 0.0 : $price;

            if ( $limit <= 0 || $price <= 0 ) {
                continue;
            }

            $sanitized[] = [
                'limit' => $limit,
                'price' => self::format_decimal( $price ),
            ];
        }

        return wp_json_encode( $sanitized );
    }

    /**
     * Sanitize datetime string using site timezone.
     */
    private static function sanitize_datetime( string $datetime ): string {
        $datetime = trim( str_replace( 'T', ' ', $datetime ) );
        if ( '' === $datetime ) {
            return '';
        }

        try {
            $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
            $date     = new \DateTime( $datetime, $timezone );
            $date->setTimezone( new \DateTimeZone( 'UTC' ) );
            return $date->format( 'Y-m-d H:i:s' );
        } catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }

        return '';
    }

    /**
     * Sanitize bundle items array.
     *
     * @param array<int,mixed> $items Bundle items.
     */
    private static function sanitize_bundle_items_inputs( array $items ): string {
        $sanitized = [];
        foreach ( $items as $item ) {
            $product_id = absint( $item );
            if ( $product_id > 0 ) {
                $sanitized[] = $product_id;
            }
        }

        return wp_json_encode( array_values( array_unique( $sanitized ) ) );
    }

    /**
     * Decode JSON list of strings.
     *
     * @param string $raw Raw JSON.
     * @return array<int,string>
     */
    private static function decode_json_list( string $raw ): array {
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }

        $list = [];
        foreach ( $decoded as $value ) {
            if ( is_string( $value ) && '' !== $value ) {
                $list[] = $value;
            }
        }

        return $list;
    }

    /**
     * Decode promo tiers from JSON string.
     *
     * @param string $raw Raw JSON.
     * @return array<int,array<string,float|int>>
     */
    private static function decode_promo_tiers( string $raw ): array {
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }

        $tiers = [];
        foreach ( $decoded as $tier ) {
            if ( ! is_array( $tier ) ) {
                continue;
            }

            $limit = isset( $tier['limit'] ) ? (int) $tier['limit'] : 0;
            $price = isset( $tier['price'] ) ? (float) $tier['price'] : 0.0;

            if ( $limit > 0 && $price > 0 ) {
                $tiers[] = [
                    'limit' => $limit,
                    'price' => $price,
                ];
            }
        }

        return $tiers;
    }

    /**
     * Decode bundle items JSON.
     *
     * @param string $raw Raw JSON.
     * @return array<int,int>
     */
    private static function decode_bundle_items( string $raw ): array {
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }

        $items = [];
        foreach ( $decoded as $value ) {
            $product_id = absint( $value );
            if ( $product_id > 0 ) {
                $items[] = $product_id;
            }
        }

        return $items;
    }

    /**
     * Format UTC datetime to datetime-local input.
     *
     * @param string $datetime UTC datetime string.
     */
    private static function format_datetime_local( string $datetime ): string {
        if ( '' === $datetime ) {
            return '';
        }

        try {
            $utc  = new \DateTimeZone( 'UTC' );
            $date = new \DateTime( $datetime, $utc );
            $date->setTimezone( function_exists( 'wp_timezone' ) ? wp_timezone() : $utc );
            return $date->format( 'Y-m-d\TH:i' );
        } catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }

        return '';
    }

    /**
     * Get current product price for automatic suggestions.
     */
    private static function get_base_price( WC_Product $product ): float {
        if ( function_exists( 'wc_get_price_to_display' ) ) {
            return (float) wc_get_price_to_display( $product );
        }

        return (float) $product->get_price();
    }

    /**
     * Provide default promo tier suggestions.
     *
     * @return array<int,array<string,float|int>>
     */
    private static function get_default_promo_suggestions(): array {
        return [
            [
                'limit'    => 3,
                'discount' => 10,
            ],
            [
                'limit'    => 5,
                'discount' => 15,
            ],
            [
                'limit'    => 10,
                'discount' => 20,
            ],
        ];
    }

    /**
     * Format decimal numbers for storage.
     */
    private static function format_decimal( float $value ): float {
        if ( function_exists( 'wc_format_decimal' ) ) {
            return (float) wc_format_decimal( $value );
        }

        return round( $value, 2 );
    }

    /**
     * Suggest bundle items from related categories.
     *
     * @param WC_Product $product Current product.
     * @return array<int,array<string,mixed>>
     */
    private static function get_bundle_suggestions( WC_Product $product ): array {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return [];
        }

        $terms = get_the_terms( $product->get_id(), 'product_cat' );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return [];
        }

        $slugs = [];
        foreach ( $terms as $term ) {
            $slugs[] = $term->slug;
        }

        if ( empty( $slugs ) ) {
            return [];
        }

        $found = wc_get_products(
            [
                'status'     => 'publish',
                'limit'      => 6,
                'exclude'    => [ $product->get_id() ],
                'category'   => $slugs,
                'meta_query' => [
                    [
                        'key'   => 'sawwap_is_video_product',
                        'value' => 1,
                    ],
                ],
            ]
        );

        $suggestions = [];
        foreach ( $found as $item ) {
            if ( ! $item instanceof WC_Product ) {
                continue;
            }

            $suggestions[] = [
                'id'    => $item->get_id(),
                'name'  => $item->get_name(),
            ];
        }

        return $suggestions;
    }
}
