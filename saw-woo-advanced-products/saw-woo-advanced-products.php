<?php
/**
 * Plugin Name: SAW – Woo Advanced Products
 * Description: Digital courses for WooCommerce: discounts, points, secure video access, account UX, and PDP enhancements.
 * Version: 1.0.0
 * Author: 307P s.r.o.
 * Text Domain: saw-wap
 */

declare( strict_types=1 );

namespace SAW\WAP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin bootstrap class.
 */
final class Plugin {
    /**
     * Singleton instance.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Plugin version.
     */
    public const VERSION = '1.0.0';

    /**
     * Plugin slug.
     */
    public const SLUG = 'saw-woo-advanced-products';

    /**
     * Get instance.
     */
    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Plugin constructor.
     */
    private function __construct() {
        $this->maybe_load_autoloader();
        $this->define_constants();
        $this->bootstrap();
    }

    /**
     * Load Composer autoloader if present.
     */
    private function maybe_load_autoloader(): void {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if ( is_readable( $autoload ) ) {
            require_once $autoload;
        } else {
            spl_autoload_register( [ $this, 'psr4_autoload' ] );
        }
    }

    /**
     * PSR-4 autoloader as a fallback when Composer is not available.
     *
     * @param string $class Class name.
     */
    private function psr4_autoload( string $class ): void {
        $prefix = __NAMESPACE__ . '\\';
        if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
            return;
        }

        $relative = substr( $class, strlen( $prefix ) );
        $relative = str_replace( '\\', '/', $relative );
        $file     = __DIR__ . '/inc/' . $relative . '.php';

        if ( is_readable( $file ) ) {
            require_once $file;
        }
    }

    /**
     * Define plugin constants.
     */
    private function define_constants(): void {
        if ( ! defined( 'SAW_WAP_FILE' ) ) {
            define( 'SAW_WAP_FILE', __FILE__ );
        }

        if ( ! defined( 'SAW_WAP_PATH' ) ) {
            define( 'SAW_WAP_PATH', __DIR__ . '/' );
        }

        if ( ! defined( 'SAW_WAP_URL' ) ) {
            define( 'SAW_WAP_URL', plugin_dir_url( __FILE__ ) );
        }
    }

    /**
     * Bootstrap the plugin components.
     */
    private function bootstrap(): void {
        add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
        add_action( 'plugins_loaded', [ $this, 'init_modules' ] );
    }

    /**
     * Declare HPOS compatibility for WooCommerce 8+.
     */
    public function declare_hpos_compatibility(): void {
        if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SAW_WAP_FILE, true );
        }
    }

    /**
     * Initialize plugin modules.
     */
    public function init_modules(): void {
        load_plugin_textdomain( 'saw-wap', false, dirname( plugin_basename( SAW_WAP_FILE ) ) . '/languages' );

        if ( class_exists( Helpers\Cache::class ) ) {
            Helpers\Cache::init();
        }

        if ( class_exists( Helpers\FeatureFlags::class ) ) {
            Helpers\FeatureFlags::init();
        }

        if ( class_exists( Core\Access::class ) ) {
            Core\Access::init();
        }

        if ( class_exists( Core\Pricing::class ) ) {
            Core\Pricing::init();
        }

        if ( class_exists( Core\Points::class ) ) {
            Core\Points::init();
        }

        if ( class_exists( Core\Bundles::class ) ) {
            Core\Bundles::init();
        }

        if ( class_exists( Core\Memberships::class ) ) {
            Core\Memberships::init();
        }

        if ( class_exists( Core\Account::class ) ) {
            Core\Account::init();
        }

        if ( class_exists( Core\Events::class ) ) {
            Core\Events::init();
        }

        if ( class_exists( Core\Upgrades::class ) ) {
            Core\Upgrades::maybe_upgrade();
        }

        if ( class_exists( Admin\ProductFields::class ) ) {
            Admin\ProductFields::init();
        }

        if ( class_exists( Admin\Settings::class ) ) {
            Admin\Settings::init();
        }

        if ( class_exists( Frontend\Assets::class ) ) {
            Frontend\Assets::init();
        }

        if ( class_exists( Frontend\Shortcodes::class ) ) {
            Frontend\Shortcodes::init();
        }

        if ( class_exists( Frontend\Templates::class ) ) {
            Frontend\Templates::init();
        }

        if ( class_exists( WC\Hooks_Product::class ) ) {
            WC\Hooks_Product::init();
        }

        if ( class_exists( WC\Hooks_Cart::class ) ) {
            WC\Hooks_Cart::init();
        }

        if ( class_exists( WC\Hooks_Checkout::class ) ) {
            WC\Hooks_Checkout::init();
        }

        if ( class_exists( WC\Hooks_Account::class ) ) {
            WC\Hooks_Account::init();
        }

        if ( class_exists( WC\Data_Sources::class ) ) {
            WC\Data_Sources::init();
        }

        if ( class_exists( REST\Routes::class ) ) {
            REST\Routes::init();
        }

        if ( class_exists( Legal\DigitalContent::class ) ) {
            Legal\DigitalContent::init();
        }

        if ( class_exists( Legal\Privacy::class ) ) {
            Legal\Privacy::init();
        }

        if ( class_exists( Cron\Schedules::class ) ) {
            Cron\Schedules::init();
        }

        if ( class_exists( Integrations\Toret_Idoklad::class ) ) {
            Integrations\Toret_Idoklad::init();
        }

        if ( class_exists( Integrations\ThePay::class ) ) {
            Integrations\ThePay::init();
        }

        if ( class_exists( Integrations\GoPay::class ) ) {
            Integrations\GoPay::init();
        }
    }
}

// Initialize plugin
Plugin::instance();

/**
 * Activation hook - create database tables and flush rewrite rules.
 */
register_activation_hook( __FILE__, function() {
    // Require Database class
    require_once __DIR__ . '/inc/Core/Database.php';
    
    // Create tables
    \SAW\WAP\Core\Database::create_tables();
    
    // NOVĚ PŘIDÁNO: Flush rewrite rules pro /watch/ endpoint

    
    // Flush rules
    flush_rewrite_rules();
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'SAW-WAP: Plugin activated, rewrite rules flushed' );
    }
} );


/**
 * Deactivation hook - flush rewrite rules.
 * NOTE: We do NOT drop tables on deactivation!
 */
register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
} );