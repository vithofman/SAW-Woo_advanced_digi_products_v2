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
 * Registers the "SAW – Digital Content" product tab and video repeater meta box.
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
        // Registrujeme custom tab v WooCommerce product data
        add_filter( 'woocommerce_product_data_tabs', [ self::class, 'register_tab' ] );
        
        // Vykreslíme obsah tabu
        add_action( 'woocommerce_product_data_panels', [ self::class, 'render_panel' ] );
        
        // Uložíme data při save produktu
        add_action( 'woocommerce_admin_process_product_object', [ self::class, 'save_product_fields' ] );
        
        // Přidáme meta box pro video repeater
        add_action( 'add_meta_boxes', [ self::class, 'add_video_meta_box' ] );
        
        // Save handler pro video meta box
        add_action( 'save_post', [ self::class, 'save_video_meta_box' ], 10, 2 );
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
     * Render the product data panel (základní nastavení).
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
                // Checkbox: Je toto video produkt?
                woocommerce_wp_checkbox(
                    [
                        'id'          => 'sawwap_is_video_product',
                        'label'       => __( 'Je toto video produkt', 'saw-wap' ),
                        'value'       => $meta['sawwap_is_video_product'] ? 'yes' : 'no',
                        'description' => __( 'Aktivuje video systém a access control pro tento produkt.', 'saw-wap' ),
                    ]
                );

                // Text input: Výchozí délka přístupu (dny)
                woocommerce_wp_text_input(
                    [
                        'id'                => 'sawwap_access_days',
                        'label'             => __( 'Délka přístupu (dny)', 'saw-wap' ),
                        'value'             => (string) $meta['sawwap_access_days'],
                        'type'              => 'number',
                        'description'       => __( 'Počet dní po zakoupení. Výchozí: 365.', 'saw-wap' ),
                        'custom_attributes' => [
                            'min'  => '0',
                            'step' => '1',
                        ],
                    ]
                );

                // Textarea: Poznámky k produktu (interní)
                woocommerce_wp_textarea_input(
                    [
                        'id'          => 'sawwap_internal_notes',
                        'label'       => __( 'Interní poznámky', 'saw-wap' ),
                        'value'       => $meta['sawwap_internal_notes'],
                        'description' => __( 'Poznámky viditelné pouze v adminu.', 'saw-wap' ),
                    ]
                );
                ?>
            </div>

            <div class="options_group">
                <p class="form-field">
                    <strong><?php esc_html_e( 'Video lekce spravujte v meta boxu níže na stránce.', 'saw-wap' ); ?></strong>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Add video repeater meta box.
     */
    public static function add_video_meta_box(): void {
        add_meta_box(
            'sawwap_video_lessons',
            __( 'SAW – Video lekce', 'saw-wap' ),
            [ self::class, 'render_video_meta_box' ],
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render video repeater meta box.
     *
     * @param \WP_Post $post Current post object.
     */
    public static function render_video_meta_box( \WP_Post $post ): void {
        // Nonce pro bezpečnost
        wp_nonce_field( 'sawwap_save_videos', 'sawwap_videos_nonce' );

        // Získáme existující videa z DB
        global $wpdb;
        $table_name = $wpdb->prefix . 'saw_video_metadata';
        
        $videos = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} 
                 WHERE product_id = %d 
                 ORDER BY lesson_order ASC",
                $post->ID
            ),
            ARRAY_A
        );

        // Pokud nejsou videa, vytvoříme prázdný template
        if ( empty( $videos ) ) {
            $videos = [ self::get_empty_video_template() ];
        }

        ?>
        <div class="sawwap-video-repeater-wrapper">
            <div class="sawwap-video-repeater-header">
                <p class="description">
                    <?php esc_html_e( 'Přidejte video lekce pro tento kurz. První video může být označeno jako ZDARMA (preview).', 'saw-wap' ); ?>
                </p>
            </div>

            <div class="sawwap-video-repeater-container" id="sawwap-video-repeater">
                <?php foreach ( $videos as $index => $video ) : ?>
                    <?php self::render_video_row( $video, $index ); ?>
                <?php endforeach; ?>
            </div>

            <div class="sawwap-video-repeater-footer">
                <button type="button" class="button button-primary sawwap-add-video">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e( 'Přidat video', 'saw-wap' ); ?>
                </button>
                
                <?php
                // ✅✅✅ NOVÝ: Regenerate button (pouze pokud produkt má objednávky) ✅✅✅
                $has_orders = self::product_has_orders( $post->ID );
                if ( $has_orders ) :
                    $regenerate_url = wp_nonce_url(
                        admin_url( 'admin-post.php?action=saw_regenerate_tokens&product_id=' . $post->ID ),
                        'saw_regenerate_tokens',
                        'saw_nonce'
                    );
                ?>
                    <button type="button" 
                            class="button button-secondary sawwap-regenerate-tokens"
                            onclick="if(confirm('<?php echo esc_js( __( 'Vygenerovat chybějící tokeny pro všechny zákazníky tohoto produktu?\n\nToto vytvoří přístupové tokeny pro všechna nová videa pro všechny existující zákazníky s aktivním přístupem.', 'saw-wap' ) ); ?>')) { window.location.href='<?php echo esc_url( $regenerate_url ); ?>'; }"
                            style="margin-left: 10px;">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Regenerovat tokeny pro zákazníky', 'saw-wap' ); ?>
                    </button>
                    <p class="description" style="margin-top: 8px; color: #646970;">
                        <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                        <?php esc_html_e( 'Pokud jste přidali nová videa, klikněte zde pro automatické vytvoření přístupových tokenů pro všechny existující zákazníky s aktivním přístupem.', 'saw-wap' ); ?>
                    </p>
                <?php endif; ?>
                <?php // ✅✅✅ KONEC NOVÉHO KÓDU ✅✅✅ ?>
            </div>

            <!-- Template pro nové video (hidden) -->
            <script type="text/template" id="sawwap-video-row-template">
                <?php self::render_video_row( self::get_empty_video_template(), '__INDEX__' ); ?>
            </script>
        </div>
        <?php
    }

    /**
     * Render single video row.
     *
     * @param array<string,mixed> $video Video data.
     * @param int|string          $index Row index.
     */
    private static function render_video_row( array $video, $index ): void {
        ?>
        <div class="sawwap-video-row" data-index="<?php echo esc_attr( (string) $index ); ?>">
            <div class="sawwap-video-row-header">
                <span class="sawwap-video-handle dashicons dashicons-menu"></span>
                <span class="sawwap-video-title-preview">
                    <?php echo esc_html( $video['video_title'] ?: __( 'Nová lekce', 'saw-wap' ) ); ?>
                </span>
                <div class="sawwap-video-actions">
                    <button type="button" class="button sawwap-toggle-video" aria-label="<?php esc_attr_e( 'Rozbalit/sbalit', 'saw-wap' ); ?>">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <button type="button" class="button sawwap-remove-video" aria-label="<?php esc_attr_e( 'Odstranit', 'saw-wap' ); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>

            <div class="sawwap-video-row-content" style="display: <?php echo 0 === $index ? 'block' : 'none'; ?>;">
                <!-- Hidden ID field (pokud už video existuje v DB) -->
                <input type="hidden" 
                       name="sawwap_videos[<?php echo esc_attr( (string) $index ); ?>][id]" 
                       value="<?php echo esc_attr( (string) ( $video['id'] ?? '' ) ); ?>" />

                <!-- Video index (pro propojení s tokeny) -->
                <input type="hidden" 
                       name="sawwap_videos[<?php echo esc_attr( (string) $index ); ?>][video_index]" 
                       value="<?php echo esc_attr( (string) ( $video['video_index'] ?? $index ) ); ?>" />

                <!-- Lesson order (aktualizuje se při drag & drop) -->
                <input type="hidden" 
                       class="sawwap-lesson-order"
                       name="sawwap_videos[<?php echo esc_attr( (string) $index ); ?>][lesson_order]" 
                       value="<?php echo esc_attr( (string) ( $video['lesson_order'] ?? $index ) ); ?>" />

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="sawwap_video_title_<?php echo esc_attr( (string) $index ); ?>">
                                    <?php esc_html_e( 'Název lekce', 'saw-wap' ); ?>
                                    <span class="required">*</span>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="sawwap_video_title_<?php echo esc_attr( (string) $index ); ?>"
                                       name="sawwap_videos[<?php echo esc_attr( (string) $index ); ?>][video_title]"
                                       value="<?php echo esc_attr( $video['video_title'] ?? '' ); ?>"
                                       class="regular-text sawwap-video-title-input"
                                       required />
                                <p class="description">
                                    <?php esc_html_e( 'Název videa který uvidí uživatelé.', 'saw-wap' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sawwap_video_url_<?php echo esc_attr( (string) $index ); ?>">
                                    <?php esc_html_e( 'URL videa', 'saw-wap' ); ?>
                                    <span class="required">*</span>
                                </label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="sawwap_video_url_<?php echo esc_attr( (string) $index ); ?>"
                                       name="sawwap_videos[<?php echo esc_attr( (string) $index ); ?>][video_url]"
                                       value="<?php echo esc_attr( $video['video_url'] ?? '' ); ?>"
                                       class="large-text"
                                       placeholder="https://youtube.com/watch?v=... nebo https://vimeo.com/..."
                                       required />
                                <p class="description">
                                    <?php esc_html_e( 'YouTube nebo Vimeo URL. Poskytovatel se detekuje automaticky.', 'saw-wap' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sawwap_video_provider_<?php echo esc_attr( (string) $index ); ?>">
                                    <?php esc_html_e( 'Poskytovatel', 'saw-wap' ); ?>
                                </label>
                            </th>
                            <td>
                                <select id="sawwap_video_provider_<?php echo esc_attr( (string) $index ); ?>"
                                        name="sawwap_videos[<?php echo esc_attr( (string) $index ); ?>][video_provider]">
                                    <option value="" <?php selected( $video['video_provider'] ?? '', '' ); ?>>
                                        <?php esc_html_e( 'Automaticky', 'saw-wap' ); ?>
                                    </option>
                                    <option value="youtube" <?php selected( $video['video_provider'] ?? '', 'youtube' ); ?>>
                                        YouTube
                                    </option>
                                    <option value="vimeo" <?php selected( $video['video_provider'] ?? '', 'vimeo' ); ?>>
                                        Vimeo
                                    </option>
                                    <option value="custom" <?php selected( $video['video_provider'] ?? '', 'custom' ); ?>>
                                        <?php esc_html_e( 'Vlastní', 'saw-wap' ); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Ponechte "Automaticky" pro auto-detekci z URL.', 'saw-wap' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sawwap_video_duration_<?php echo esc_attr( (string) $index ); ?>">
                                    <?php esc_html_e( 'Délka videa (minuty)', 'saw-wap' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="sawwap_video_duration_<?php echo esc_attr( (string) $index ); ?>"
                                       name="sawwap_videos[<?php echo esc_attr( (string) $index ); ?>][video_duration]"
                                       value="<?php echo esc_attr( (string) ( $video['video_duration'] ?? 0 ) ); ?>"
                                       min="0"
                                       step="1"
                                       class="small-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Přibližná délka v minutách (pro informaci uživatelů).', 'saw-wap' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sawwap_video_description_<?php echo esc_attr( (string) $index ); ?>">
                                    <?php esc_html_e( 'Popis lekce', 'saw-wap' ); ?>
                                </label>
                            </th>
                            <td>
                                <textarea id="sawwap_video_description_<?php echo esc_attr( (string) $index ); ?>"
                                          name="sawwap_videos[<?php echo esc_attr( (string) $index ); ?>][video_description]"
                                          rows="3"
                                          class="large-text"><?php echo esc_textarea( $video['video_description'] ?? '' ); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Krátký popis co se uživatel naučí v této lekci.', 'saw-wap' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php esc_html_e( 'Možnosti', 'saw-wap' ); ?>
                            </th>
                            <td>
                                <label for="sawwap_is_free_<?php echo esc_attr( (string) $index ); ?>">
                                    <input type="checkbox" 
                                           id="sawwap_is_free_<?php echo esc_attr( (string) $index ); ?>"
                                           name="sawwap_videos[<?php echo esc_attr( (string) $index ); ?>][is_free]"
                                           value="1"
                                           <?php checked( ! empty( $video['is_free'] ) ); ?> />
                                    <?php esc_html_e( 'Toto video je ZDARMA (preview)', 'saw-wap' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'Zdarma videa jsou přístupná bez nákupu (ideální pro první lekci jako ukázka).', 'saw-wap' ); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Get empty video template.
     *
     * @return array<string,mixed>
     */
    private static function get_empty_video_template(): array {
        return [
            'id'                => '',
            'video_index'       => 0,
            'video_title'       => '',
            'video_url'         => '',
            'video_provider'    => '',
            'video_duration'    => 0,
            'video_description' => '',
            'lesson_order'      => 0,
            'is_free'           => 0,
        ];
    }

    /**
     * Save video meta box data.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public static function save_video_meta_box( int $post_id, \WP_Post $post ): void {
        // Ověříme že jde o produkt
        if ( 'product' !== $post->post_type ) {
            return;
        }

        // Ověříme nonce
        if ( ! isset( $_POST['sawwap_videos_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sawwap_videos_nonce'] ) ), 'sawwap_save_videos' ) ) {
            return;
        }

        // Ověříme oprávnění
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Prevence autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Získáme videa z POST
        $videos_raw = isset( $_POST['sawwap_videos'] ) && is_array( $_POST['sawwap_videos'] ) 
            ? wp_unslash( $_POST['sawwap_videos'] ) 
            : [];

        // Validace a sanitizace
        $videos = [];
        foreach ( $videos_raw as $index => $video_raw ) {
            if ( ! is_array( $video_raw ) ) {
                continue;
            }

            $video = self::sanitize_video_data( $video_raw );

            // Přeskočit pokud chybí povinná pole
            if ( empty( $video['video_title'] ) || empty( $video['video_url'] ) ) {
                continue;
            }

            $videos[] = $video;
        }

        // Uložit do DB
        global $wpdb;
        $table_name = $wpdb->prefix . 'saw_video_metadata';

        // Nejdříve smažeme všechna stará videa pro tento produkt
        $wpdb->delete( $table_name, [ 'product_id' => $post_id ], [ '%d' ] );

        // Pak vložíme nová/aktualizovaná videa
        foreach ( $videos as $index => $video ) {
            $wpdb->insert(
                $table_name,
                [
                    'product_id'        => $post_id,
                    'video_index'       => $index,
                    'video_title'       => $video['video_title'],
                    'video_url'         => $video['video_url'],
                    'video_provider'    => $video['video_provider'],
                    'video_duration'    => $video['video_duration'],
                    'video_description' => $video['video_description'],
                    'lesson_order'      => $video['lesson_order'],
                    'is_free'           => $video['is_free'],
                ],
                [ '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d' ]
            );
        }

        // Log pro debug (pokud je WP_DEBUG zapnuté)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( 'SAW-WAP: Saved %d videos for product ID %d', count( $videos ), $post_id ) );
        }
    }

    /**
     * Sanitize video data.
     *
     * @param array<string,mixed> $video_raw Raw video data.
     * @return array<string,mixed> Sanitized video data.
     */
    private static function sanitize_video_data( array $video_raw ): array {
        // Sanitizace video providera
        $provider = isset( $video_raw['video_provider'] ) ? sanitize_text_field( (string) $video_raw['video_provider'] ) : '';
        $allowed_providers = [ 'youtube', 'vimeo', 'custom', '' ];
        if ( ! in_array( $provider, $allowed_providers, true ) ) {
            $provider = '';
        }

        // Auto-detekce providera z URL pokud není nastaven
        $url = isset( $video_raw['video_url'] ) ? esc_url_raw( (string) $video_raw['video_url'] ) : '';
        if ( empty( $provider ) && ! empty( $url ) ) {
            if ( strpos( $url, 'youtube.com' ) !== false || strpos( $url, 'youtu.be' ) !== false ) {
                $provider = 'youtube';
            } elseif ( strpos( $url, 'vimeo.com' ) !== false ) {
                $provider = 'vimeo';
            }
        }

        return [
            'video_title'       => isset( $video_raw['video_title'] ) ? sanitize_text_field( (string) $video_raw['video_title'] ) : '',
            'video_url'         => $url,
            'video_provider'    => $provider,
            'video_duration'    => isset( $video_raw['video_duration'] ) ? absint( $video_raw['video_duration'] ) : 0,
            'video_description' => isset( $video_raw['video_description'] ) ? sanitize_textarea_field( (string) $video_raw['video_description'] ) : '',
            'lesson_order'      => isset( $video_raw['lesson_order'] ) ? absint( $video_raw['lesson_order'] ) : 0,
            'is_free'           => ! empty( $video_raw['is_free'] ) ? 1 : 0,
        ];
    }

    /**
     * Retrieve product meta values with defaults (pro základní tab).
     *
     * @param WC_Product $product Product.
     * @return array<string,mixed>
     */
    private static function get_product_meta( WC_Product $product ): array {
        $meta = [
            'sawwap_is_video_product' => (bool) (int) $product->get_meta( 'sawwap_is_video_product', true ),
            'sawwap_access_days'      => (int) $product->get_meta( 'sawwap_access_days', true ),
            'sawwap_internal_notes'   => (string) $product->get_meta( 'sawwap_internal_notes', true ),
        ];

        if ( 0 === $meta['sawwap_access_days'] ) {
            $meta['sawwap_access_days'] = 365;
        }

        return $meta;
    }

    /**
     * Persist product meta when saved (pro základní tab).
     *
     * @param WC_Product $product Product being saved.
     */
    public static function save_product_fields( WC_Product $product ): void {
        $is_video = isset( $_POST['sawwap_is_video_product'] ) ? 'yes' === wp_unslash( $_POST['sawwap_is_video_product'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $product->update_meta_data( 'sawwap_is_video_product', $is_video ? 1 : 0 );

        $access_days_raw = isset( $_POST['sawwap_access_days'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sawwap_access_days'] ) ) : '';
        $access_days     = '' === $access_days_raw ? 365 : (int) $access_days_raw;
        $product->update_meta_data( 'sawwap_access_days', max( 0, $access_days ) );

        $internal_notes = isset( $_POST['sawwap_internal_notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['sawwap_internal_notes'] ) ) : '';
        $product->update_meta_data( 'sawwap_internal_notes', $internal_notes );
    }

    /**
     * ✅✅✅ NOVÁ HELPER METODA ✅✅✅
     * 
     * Check if product has any completed orders.
     * Used to determine whether to show the "Regenerate tokens" button.
     * 
     * @param int $product_id Product ID.
     * @return bool True if product has at least one order with tokens.
     */
    private static function product_has_orders( int $product_id ): bool {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT order_id) 
                 FROM {$wpdb->prefix}saw_video_access_tokens 
                 WHERE product_id = %d",
                $product_id
            )
        );

        return $count > 0;
    }
}