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
            __( 'SAW – Pokročilé produkty', 'saw-wap' ),
            __( 'SAW – Pokročilé produkty', 'saw-wap' ),
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
            'sanitize_callback' => [ self::class, 'sanitize_discount_rules' ],
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

        add_settings_section( 'sawwap_general', __( 'Obecné nastavení', 'saw-wap' ), null, self::MENU_SLUG );
        add_settings_field( 'sawwap_points_rate', __( 'Přepočet CZK → body', 'saw-wap' ), [ self::class, 'render_points_rate_field' ], self::MENU_SLUG, 'sawwap_general' );
        add_settings_field( 'sawwap_default_access_days', __( 'Výchozí délka přístupu (dny)', 'saw-wap' ), [ self::class, 'render_access_days_field' ], self::MENU_SLUG, 'sawwap_general' );
        add_settings_field( 'sawwap_max_points_discount_pct', __( 'Maximální sleva z bodů (%)', 'saw-wap' ), [ self::class, 'render_max_points_field' ], self::MENU_SLUG, 'sawwap_general' );
        add_settings_field( 'sawwap_bundles_enabled', __( 'Automatické balíčky', 'saw-wap' ), [ self::class, 'render_bundles_field' ], self::MENU_SLUG, 'sawwap_general' );

        add_settings_section( 'sawwap_legal', __( 'Právní texty', 'saw-wap' ), null, self::MENU_SLUG );
        add_settings_field( 'sawwap_licence_url', __( 'Odkaz na licenční podmínky', 'saw-wap' ), [ self::class, 'render_licence_url_field' ], self::MENU_SLUG, 'sawwap_legal' );
        add_settings_field( 'sawwap_digital_consent_text', __( 'Text souhlasu s okamžitým plněním', 'saw-wap' ), [ self::class, 'render_consent_text_field' ], self::MENU_SLUG, 'sawwap_legal' );

        add_settings_section( 'sawwap_discounts', __( 'Slevy a akce', 'saw-wap' ), null, self::MENU_SLUG );
        add_settings_field( 'sawwap_discount_mode', __( 'Způsob promítnutí slevy do objednávky', 'saw-wap' ), [ self::class, 'render_discount_mode_field' ], self::MENU_SLUG, 'sawwap_discounts' );
        add_settings_field( 'sawwap_discount_rules', __( 'Pravidla slev', 'saw-wap' ), [ self::class, 'render_discount_rules_field' ], self::MENU_SLUG, 'sawwap_discounts' );

        add_settings_section( 'sawwap_performance', __( 'Výkon a cache', 'saw-wap' ), null, self::MENU_SLUG );
        add_settings_field( 'sawwap_cache_ttl', __( 'Životnost cache (sekundy)', 'saw-wap' ), [ self::class, 'render_cache_ttl_field' ], self::MENU_SLUG, 'sawwap_performance' );
        add_settings_field( 'sawwap_cache_button', __( 'Invalidace cache', 'saw-wap' ), [ self::class, 'render_cache_button_field' ], self::MENU_SLUG, 'sawwap_performance' );

        add_settings_section( 'sawwap_features', __( 'Experimentální funkce', 'saw-wap' ), null, self::MENU_SLUG );
        add_settings_field( 'sawwap_feature_flags', __( 'Přepínače funkcí', 'saw-wap' ), [ self::class, 'render_feature_flags_field' ], self::MENU_SLUG, 'sawwap_features' );
    }

    /**
     * Render settings page.
     */
    public static function render_settings_page(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Pro zobrazení této stránky nemáte oprávnění.', 'saw-wap' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'SAW – Pokročilé produkty', 'saw-wap' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'sawwap_options' );
                do_settings_sections( self::MENU_SLUG );
                submit_button( __( 'Uložit nastavení', 'saw-wap' ) );
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
            wp_die( esc_html__( 'Nemáte dostatečná oprávnění.', 'saw-wap' ) );
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
            esc_html__( 'Např. 0,05 znamená 5 bodů za každých 100 Kč.', 'saw-wap' )
        );
    }

    /**
     * Render default access days field.
     */
    public static function render_access_days_field(): void {
        $value = (int) get_option( 'sawwap_default_access_days', 365 );
        printf(
            '<input type="number" name="sawwap_default_access_days" id="sawwap_default_access_days" min="0" step="1" value="%d" class="small-text" /> <span class="description">%s</span>',
            $value,
            esc_html__( 'Použije se automaticky u všech nových digitálních produktů.', 'saw-wap' )
        );
    }

    /**
     * Render max points field.
     */
    public static function render_max_points_field(): void {
        $value = (int) get_option( 'sawwap_max_points_discount_pct', 20 );
        printf(
            '<input type="number" name="sawwap_max_points_discount_pct" id="sawwap_max_points_discount_pct" min="0" max="100" step="1" value="%d" class="small-text" /> <span class="description">%s</span>',
            $value,
            esc_html__( 'Limit, kolik procent z ceny lze uplatnit z věrnostních bodů.', 'saw-wap' )
        );
    }

    /**
     * Render bundles toggle.
     */
    public static function render_bundles_field(): void {
        $value = (bool) get_option( 'sawwap_bundles_enabled', false );
        printf(
            '<label><input type="checkbox" name="sawwap_bundles_enabled" value="1" %s /> %s</label><p class="description">%s</p>',
            checked( $value, true, false ),
            esc_html__( 'Automaticky nabídnout propojené kurzy v detailu produktu.', 'saw-wap' ),
            esc_html__( 'Po aktivaci se v editoru produktů zobrazí doporučené kurzy dle kategorií.', 'saw-wap' )
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
        $value       = (string) get_option( 'sawwap_digital_consent_text', '' );
        $placeholder = __( 'Souhlasím s okamžitým zpřístupněním digitálního obsahu a beru na vědomí, že tím ztrácím možnost odstoupit od smlouvy.', 'saw-wap' );
        printf(
            '<textarea name="sawwap_digital_consent_text" id="sawwap_digital_consent_text" class="large-text" rows="4" placeholder="%s">%s</textarea>',
            esc_attr( $placeholder ),
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
            <option value="itemized" <?php selected( $value, 'itemized' ); ?>><?php esc_html_e( 'Přepočítat ceny položek (bezpečné pro DPH)', 'saw-wap' ); ?></option>
            <option value="fee" <?php selected( $value, 'fee' ); ?>><?php esc_html_e( 'Odečíst zápornou položku „Sleva SAW“', 'saw-wap' ); ?></option>
        </select>
        <?php
    }

    /**
     * Render discount rules builder.
     */
    public static function render_discount_rules_field(): void {
        $value   = (string) get_option( 'sawwap_discount_rules', '[]' );
        $config  = self::parse_discount_rules_option( $value );
        $quantity_suggestions = esc_attr( wp_json_encode( self::get_default_quantity_suggestions() ) );
        ?>
        <input type="hidden" name="sawwap_discount_rules" id="sawwap_discount_rules" value="<?php echo esc_attr( $value ); ?>" />
        <div class="sawwap-discount-builder" data-quantity-suggestions="<?php echo $quantity_suggestions; ?>">
            <fieldset class="sawwap-settings-card">
                <legend><?php esc_html_e( 'Časově omezená akce', 'saw-wap' ); ?></legend>
                <label>
                    <input type="checkbox" class="sawwap-discount-toggle" name="sawwap_discount_builder[promo][enabled]" value="1" data-toggle-target="promo" <?php checked( $config['promo']['enabled'], true ); ?> />
                    <?php esc_html_e( 'Aktivovat akci se slevou', 'saw-wap' ); ?>
                </label>
                <div class="sawwap-settings-card__body <?php echo $config['promo']['enabled'] ? '' : 'is-hidden'; ?>" data-toggle-block="promo">
                    <p>
                        <label for="sawwap_discount_builder_promo_percent"><?php esc_html_e( 'Sleva v %', 'saw-wap' ); ?></label>
                        <input type="number" min="0" step="1" class="small-text" id="sawwap_discount_builder_promo_percent" name="sawwap_discount_builder[promo][percent]" value="<?php echo esc_attr( $config['promo']['percent'] > 0 ? (string) $config['promo']['percent'] : '' ); ?>" />
                    </p>
                    <p>
                        <label for="sawwap_discount_builder_promo_until"><?php esc_html_e( 'Platnost do', 'saw-wap' ); ?></label>
                        <input type="datetime-local" id="sawwap_discount_builder_promo_until" name="sawwap_discount_builder[promo][until]" value="<?php echo esc_attr( self::format_builder_datetime( $config['promo']['until'] ) ); ?>" />
                    </p>
                    <p class="description"><?php esc_html_e( 'Po skončení akce se cena vrátí na standardní úroveň.', 'saw-wap' ); ?></p>
                    <button type="button" class="button button-secondary sawwap-promo-preset" data-percent="20" data-days="14"><?php esc_html_e( 'Nastavit -20 % na 14 dní', 'saw-wap' ); ?></button>
                </div>
            </fieldset>

            <fieldset class="sawwap-settings-card">
                <legend><?php esc_html_e( 'Množstevní sleva', 'saw-wap' ); ?></legend>
                <label>
                    <input type="checkbox" class="sawwap-discount-toggle" name="sawwap_discount_builder[quantity][enabled]" value="1" data-toggle-target="quantity" <?php checked( $config['quantity']['enabled'], true ); ?> />
                    <?php esc_html_e( 'Nabídnout lepší cenu při nákupu více kusů', 'saw-wap' ); ?>
                </label>
                <div class="sawwap-settings-card__body <?php echo $config['quantity']['enabled'] ? '' : 'is-hidden'; ?>" data-toggle-block="quantity">
                    <table class="widefat sawwap-discount-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Od počtu kusů', 'saw-wap' ); ?></th>
                                <th><?php esc_html_e( 'Sleva (%)', 'saw-wap' ); ?></th>
                                <th class="column-actions">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody class="sawwap-discount-quantity-rows">
                            <?php
                            if ( ! empty( $config['quantity']['tiers'] ) ) {
                                foreach ( $config['quantity']['tiers'] as $tier ) {
                                    self::render_quantity_row( (int) $tier['min_qty'], (float) $tier['percent'] );
                                }
                            } else {
                                self::render_quantity_row( 0, 0.0 );
                            }
                            self::render_quantity_row( 0, 0.0, true );
                            ?>
                        </tbody>
                    </table>
                    <div class="sawwap-repeater__actions">
                        <button type="button" class="button button-secondary sawwap-discount-add-quantity"><?php esc_html_e( 'Přidat úroveň', 'saw-wap' ); ?></button>
                        <button type="button" class="button sawwap-discount-suggest"><?php esc_html_e( 'Doplnit doporučené úrovně', 'saw-wap' ); ?></button>
                    </div>
                </div>
            </fieldset>

            <fieldset class="sawwap-settings-card">
                <legend><?php esc_html_e( 'Členské ceny', 'saw-wap' ); ?></legend>
                <label>
                    <input type="checkbox" class="sawwap-discount-toggle" name="sawwap_discount_builder[membership][enabled]" value="1" data-toggle-target="membership" <?php checked( $config['membership']['enabled'], true ); ?> />
                    <?php esc_html_e( 'Členové věrnostního klubu mají lepší cenu', 'saw-wap' ); ?>
                </label>
                <div class="sawwap-settings-card__body <?php echo $config['membership']['enabled'] ? '' : 'is-hidden'; ?>" data-toggle-block="membership">
                    <p>
                        <label for="sawwap_discount_builder_membership_percent"><?php esc_html_e( 'Sleva v %', 'saw-wap' ); ?></label>
                        <input type="number" min="0" step="1" class="small-text" id="sawwap_discount_builder_membership_percent" name="sawwap_discount_builder[membership][percent]" value="<?php echo esc_attr( $config['membership']['percent'] > 0 ? (string) $config['membership']['percent'] : '' ); ?>" />
                    </p>
                    <button type="button" class="button button-secondary sawwap-membership-preset" data-percent="15"><?php esc_html_e( 'Doporučit 15 %', 'saw-wap' ); ?></button>
                    <p class="description"><?php esc_html_e( 'Sleva se uplatní jen pro přihlášené členy dle jejich stavu.', 'saw-wap' ); ?></p>
                </div>
            </fieldset>
        </div>
        <?php
    }

    /**
     * Render quantity row for builder.
     *
     * @param int   $limit Minimum quantity.
     * @param float $percent Discount percent.
     * @param bool  $template Whether this is a template row.
     */
    private static function render_quantity_row( int $limit, float $percent, bool $template = false ): void {
        $classes = $template ? 'sawwap-discount-row--template' : '';
        $style   = $template ? ' style="display:none;"' : '';
        ?>
        <tr class="<?php echo esc_attr( $classes ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <td><input type="number" min="1" step="1" name="sawwap_discount_builder[quantity][limit][]" value="<?php echo esc_attr( $limit > 0 ? (string) $limit : '' ); ?>" /></td>
            <td><input type="number" min="0" step="1" name="sawwap_discount_builder[quantity][percent][]" value="<?php echo esc_attr( $percent > 0 ? (string) $percent : '' ); ?>" /></td>
            <td class="column-actions"><button type="button" class="button-link-delete sawwap-discount-remove-row" aria-label="<?php esc_attr_e( 'Odebrat úroveň', 'saw-wap' ); ?>">&times;</button></td>
        </tr>
        <?php
    }

    /**
     * Render cache TTL field.
     */
    public static function render_cache_ttl_field(): void {
        $value = (int) get_option( 'sawwap_cache_ttl', 600 );
        printf(
            '<input type="number" name="sawwap_cache_ttl" id="sawwap_cache_ttl" min="0" step="1" value="%d" class="small-text" /> <span class="description">%s</span>',
            $value,
            esc_html__( 'Doporučeno 600 vteřin (10 minut) pro vyvážení výkonu a aktuálnosti.', 'saw-wap' )
        );
    }

    /**
     * Render cache button field.
     */
    public static function render_cache_button_field(): void {
        $url = wp_nonce_url( admin_url( 'admin-post.php?action=sawwap_invalidate_cache' ), 'sawwap_invalidate_cache' );
        echo '<a href="' . esc_url( $url ) . '" class="button">' . esc_html__( 'Vymazat cache nyní', 'saw-wap' ) . '</a>';
        if ( isset( $_GET['sawwap_cache_flushed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<p class="description">' . esc_html__( 'Cache byla úspěšně zneplatněna.', 'saw-wap' ) . '</p>';
        }
    }

    /**
     * Render feature flags checkboxes.
     */
    public static function render_feature_flags_field(): void {
        $value = (array) get_option( 'sawwap_feature_flags', [] );
        $flags = self::get_feature_flag_labels();
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
     * Sanitize discount rules builder.
     */
    public static function sanitize_discount_rules( string $value ): string {
        $builder = isset( $_POST['sawwap_discount_builder'] ) ? wp_unslash( $_POST['sawwap_discount_builder'] ) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! is_array( $builder ) ) {
            return '[]';
        }

        $rules = [];

        if ( isset( $builder['promo'] ) && is_array( $builder['promo'] ) && ! empty( $builder['promo']['enabled'] ) ) {
            $percent = Utils::sanitize_positive_float( $builder['promo']['percent'] ?? 0, 0.0 );
            $until   = isset( $builder['promo']['until'] ) ? self::sanitize_datetime_field( (string) $builder['promo']['until'] ) : '';
            if ( $percent > 0 && '' !== $until ) {
                $rules[] = [
                    'type'     => 'promo_until',
                    'priority' => 5,
                    'percent'  => $percent,
                    'until'    => $until,
                ];
            }
        }

        if ( isset( $builder['quantity'] ) && is_array( $builder['quantity'] ) && ! empty( $builder['quantity']['enabled'] ) ) {
            $limits   = isset( $builder['quantity']['limit'] ) ? (array) $builder['quantity']['limit'] : [];
            $percents = isset( $builder['quantity']['percent'] ) ? (array) $builder['quantity']['percent'] : [];
            $tiers    = [];
            foreach ( $limits as $index => $limit_raw ) {
                $limit   = Utils::sanitize_non_negative_int( $limit_raw );
                $percent = Utils::sanitize_positive_float( $percents[ $index ] ?? 0, 0.0 );
                if ( $limit > 0 && $percent > 0 ) {
                    $tiers[] = [
                        'min_qty' => $limit,
                        'percent' => $percent,
                    ];
                }
            }

            if ( ! empty( $tiers ) ) {
                usort(
                    $tiers,
                    static function ( array $a, array $b ): int {
                        return $a['min_qty'] <=> $b['min_qty'];
                    }
                );

                $rules[] = [
                    'type'     => 'quantity',
                    'priority' => 10,
                    'tiers'    => $tiers,
                ];
            }
        }

        if ( isset( $builder['membership'] ) && is_array( $builder['membership'] ) && ! empty( $builder['membership']['enabled'] ) ) {
            $percent = Utils::sanitize_positive_float( $builder['membership']['percent'] ?? 0, 0.0 );
            if ( $percent > 0 ) {
                $rules[] = [
                    'type'     => 'membership',
                    'priority' => 40,
                    'percent'  => $percent,
                ];
            }
        }

        return wp_json_encode( $rules );
    }

    /**
     * Parse stored discount rules for the UI.
     *
     * @param string $json JSON rules.
     * @return array<string,mixed>
     */
    private static function parse_discount_rules_option( string $json ): array {
        $config = [
            'promo'      => [
                'enabled' => false,
                'percent' => 0.0,
                'until'   => '',
            ],
            'quantity'   => [
                'enabled' => false,
                'tiers'   => [],
            ],
            'membership' => [
                'enabled' => false,
                'percent' => 0.0,
            ],
        ];

        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) {
            return $config;
        }

        foreach ( $decoded as $rule ) {
            if ( ! is_array( $rule ) || empty( $rule['type'] ) ) {
                continue;
            }

            switch ( $rule['type'] ) {
                case 'promo_until':
                    $config['promo']['enabled'] = true;
                    $config['promo']['percent'] = isset( $rule['percent'] ) ? (float) $rule['percent'] : 0.0;
                    $config['promo']['until']   = isset( $rule['until'] ) ? (string) $rule['until'] : '';
                    break;
                case 'quantity':
                    if ( isset( $rule['tiers'] ) && is_array( $rule['tiers'] ) ) {
                        $tiers = [];
                        foreach ( $rule['tiers'] as $tier ) {
                            if ( ! is_array( $tier ) ) {
                                continue;
                            }
                            $limit   = isset( $tier['min_qty'] ) ? (int) $tier['min_qty'] : 0;
                            $percent = isset( $tier['percent'] ) ? (float) $tier['percent'] : 0.0;
                            if ( $limit > 0 && $percent > 0 ) {
                                $tiers[] = [
                                    'min_qty' => $limit,
                                    'percent' => $percent,
                                ];
                            }
                        }
                        if ( ! empty( $tiers ) ) {
                            $config['quantity']['enabled'] = true;
                            $config['quantity']['tiers']   = $tiers;
                        }
                    }
                    break;
                case 'membership':
                    $config['membership']['enabled'] = true;
                    $config['membership']['percent'] = isset( $rule['percent'] ) ? (float) $rule['percent'] : 0.0;
                    break;
            }
        }

        return $config;
    }

    /**
     * Format stored datetime for datetime-local input.
     */
    private static function format_builder_datetime( string $datetime ): string {
        if ( '' === $datetime ) {
            return '';
        }

        try {
            $date = new \DateTime( $datetime, new \DateTimeZone( 'UTC' ) );
            $date->setTimezone( function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' ) );
            return $date->format( 'Y-m-d\TH:i' );
        } catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }

        return '';
    }

    /**
     * Sanitize datetime from datetime-local input.
     */
    private static function sanitize_datetime_field( string $value ): string {
        $value = trim( str_replace( 'T', ' ', $value ) );
        if ( '' === $value ) {
            return '';
        }

        try {
            $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
            $date     = new \DateTime( $value, $timezone );
            $date->setTimezone( new \DateTimeZone( 'UTC' ) );
            return $date->format( 'Y-m-d H:i:s' );
        } catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }

        return '';
    }

    /**
     * Default quantity tier suggestions.
     *
     * @return array<int,array<string,int>>
     */
    private static function get_default_quantity_suggestions(): array {
        return [
            [ 'limit' => 3, 'percent' => 10 ],
            [ 'limit' => 5, 'percent' => 15 ],
            [ 'limit' => 10, 'percent' => 20 ],
        ];
    }

    /**
     * Feature flag labels in Czech.
     *
     * @return array<string,string>
     */
    private static function get_feature_flag_labels(): array {
        return [
            'use_db_access_table'  => __( 'Použít vlastní databázovou tabulku pro přístupy', 'saw-wap' ),
            'use_db_points_tables' => __( 'Použít vlastní tabulky pro věrnostní body', 'saw-wap' ),
            'enable_events'        => __( 'Zapnout plánovač událostí', 'saw-wap' ),
            'enable_magic_link'    => __( 'Povolit magický odkaz pro přihlášení', 'saw-wap' ),
        ];
    }
}
