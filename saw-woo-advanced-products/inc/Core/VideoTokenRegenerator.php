<?php
/**
 * Video Token Regenerator
 * 
 * Automaticky generuje chybějící tokeny pro existující zákazníky
 * když admin přidá nová videa do produktu.
 *
 * @package SAW\WAP
 * @since 1.0.0
 */

declare(strict_types=1);

namespace SAW\WAP\Core;

use SAW\WAP\Core\VideoTokenManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * VideoTokenRegenerator Class
 * 
 * Zajišťuje že všichni existující zákazníci dostanou přístup k novým videím
 * automaticky při jejich přidání do produktu.
 */
class VideoTokenRegenerator {
    
    /**
     * Inicializace hooks
     *
     * @return void
     */
    public static function init(): void {
        // Hook na uložení produktu - priority 20 aby videa byla už v DB
        add_action('save_post_product', [__CLASS__, 'auto_regenerate_on_save'], 20, 2);
        
        // Alternativní hook specifický pro WooCommerce
        add_action('woocommerce_update_product', [__CLASS__, 'auto_regenerate_on_wc_update'], 20, 1);
    }
    
    /**
     * Automatická regenerace při uložení produktu
     * 
     * Spouští se po uložení product fields a video meta boxu.
     *
     * @param int      $post_id Post ID produktu
     * @param \WP_Post $post    Post object
     * 
     * @return void
     */
    public static function auto_regenerate_on_save(int $post_id, \WP_Post $post): void {
        // Skip autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Skip revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Skip pokud není product
        if ($post->post_type !== 'product') {
            return;
        }
        
        // Capability check
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Získat WC produkt
        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }
        
        // Skip pokud není video produkt
        $is_video = (bool)(int)$product->get_meta('sawwap_is_video_product', true);
        if (!$is_video) {
            return;
        }
        
        // Spustit regeneraci
        self::regenerate_missing_tokens($post_id);
    }
    
    /**
     * Automatická regenerace při WC update
     * 
     * Alternative hook pro WooCommerce specific updates.
     *
     * @param int $product_id Product ID
     * 
     * @return void
     */
    public static function auto_regenerate_on_wc_update(int $product_id): void {
        // Získat produkt
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        
        // Skip pokud není video produkt
        $is_video = (bool)(int)$product->get_meta('sawwap_is_video_product', true);
        if (!$is_video) {
            return;
        }
        
        // Spustit regeneraci
        self::regenerate_missing_tokens($product_id);
    }
    
    /**
     * Regeneruje chybějící tokeny pro existující zákazníky
     * 
     * Algoritmus:
     * 1. Získá všechna videa produktu
     * 2. Najde všechny zákazníky s aktivním přístupem
     * 3. Pro každého zákazníka zjistí chybějící videa
     * 4. Vygeneruje tokeny pro chybějící videa
     *
     * @param int $product_id Product ID
     * 
     * @return array {
     *     @type int   $tokens_created      Počet vytvořených tokenů
     *     @type int   $affected_customers  Počet zákazníků
     *     @type array $errors              Chyby které nastaly
     * }
     */
    public static function regenerate_missing_tokens(int $product_id): array {
        global $wpdb;
        
        $result = [
            'tokens_created' => 0,
            'affected_customers' => 0,
            'errors' => [],
        ];
        
        // Debug log start
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'SAW-WAP [INFO] VideoTokenRegenerator: Starting auto-regeneration for product_id=%d',
                $product_id
            ));
        }
        
        // 1. Získat všechna PLACENÁ videa produktu
        $all_videos = $wpdb->get_results($wpdb->prepare(
            "SELECT video_index, video_title 
             FROM {$wpdb->prefix}saw_video_metadata 
             WHERE product_id = %d 
             AND is_free = 0 
             ORDER BY lesson_order ASC",
            $product_id
        ));
        
        if (empty($all_videos)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SAW-WAP [INFO] VideoTokenRegenerator: No paid videos found');
            }
            return $result;
        }
        
        // Vytvoř array video_index pro snadnější práci
        $all_video_indices = array_map(function($v) {
            return (int)$v->video_index;
        }, $all_videos);
        
        // 2. Získat všechny zákazníky s aktivním přístupem k tomuto produktu
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT 
                user_id, 
                order_id, 
                access_expires,
                DATEDIFF(access_expires, NOW()) as days_remaining
             FROM {$wpdb->prefix}saw_video_access_tokens 
             WHERE product_id = %d 
             AND is_active = 1 
             AND access_expires > NOW()
             ORDER BY user_id ASC",
            $product_id
        ));
        
        if (empty($customers)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SAW-WAP [INFO] VideoTokenRegenerator: No active customers found');
            }
            return $result;
        }
        
        $result['affected_customers'] = count($customers);
        
        // 3. Pro každého zákazníka najít chybějící tokeny
        foreach ($customers as $customer) {
            $user_id = (int)$customer->user_id;
            $order_id = (int)$customer->order_id;
            $days_remaining = (int)$customer->days_remaining;
            
            // Získat které video_index už zákazník má
            $existing_indices = $wpdb->get_col($wpdb->prepare(
                "SELECT video_index 
                 FROM {$wpdb->prefix}saw_video_access_tokens 
                 WHERE user_id = %d 
                 AND product_id = %d 
                 AND is_active = 1",
                $user_id,
                $product_id
            ));
            
            $existing_indices = array_map('intval', $existing_indices);
            
            // Najít rozdíl (chybějící videa)
            $missing_indices = array_diff($all_video_indices, $existing_indices);
            
            if (empty($missing_indices)) {
                continue; // Tento zákazník má všechna videa
            }
            
            // 4. Vygenerovat tokeny pro chybějící videa
            foreach ($missing_indices as $video_index) {
                try {
                    $token_manager = new VideoTokenManager();
                    
                    $token = $token_manager->generateToken(
                        $user_id,
                        $product_id,
                        $video_index,
                        $order_id,
                        $days_remaining // Použij stejnou expiraci jako existující tokeny
                    );
                    
                    if ($token) {
                        $result['tokens_created']++;
                        
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log(sprintf(
                                'SAW-WAP [SUCCESS] Created token for user_id=%d, product_id=%d, video_index=%d',
                                $user_id,
                                $product_id,
                                $video_index
                            ));
                        }
                    }
                    
                } catch (\Exception $e) {
                    $error_msg = sprintf(
                        'Failed to generate token for user_id=%d, video_index=%d: %s',
                        $user_id,
                        $video_index,
                        $e->getMessage()
                    );
                    
                    $result['errors'][] = $error_msg;
                    
                    error_log('SAW-WAP [ERROR] VideoTokenRegenerator: ' . $error_msg);
                    
                    // Pokračuj s dalším videem
                    continue;
                }
            }
        }
        
        // Debug log summary
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'SAW-WAP [INFO] VideoTokenRegenerator: Completed | tokens_created=%d, affected_customers=%d, errors=%d',
                $result['tokens_created'],
                $result['affected_customers'],
                count($result['errors'])
            ));
        }
        
        return $result;
    }
    
    /**
     * Manuální regenerace pro konkrétního zákazníka
     * 
     * Užitečné pro support nebo testing.
     *
     * @param int $user_id    User ID zákazníka
     * @param int $product_id Product ID
     * 
     * @return array Stejný formát jako regenerate_missing_tokens()
     */
    public static function regenerate_for_customer(int $user_id, int $product_id): array {
        global $wpdb;
        
        $result = [
            'tokens_created' => 0,
            'affected_customers' => 0,
            'errors' => [],
        ];
        
        // Ověř že zákazník má aktivní přístup
        $access = $wpdb->get_row($wpdb->prepare(
            "SELECT order_id, access_expires, DATEDIFF(access_expires, NOW()) as days_remaining
             FROM {$wpdb->prefix}saw_video_access_tokens 
             WHERE user_id = %d 
             AND product_id = %d 
             AND is_active = 1 
             AND access_expires > NOW()
             LIMIT 1",
            $user_id,
            $product_id
        ));
        
        if (!$access) {
            $result['errors'][] = 'Customer does not have active access to this product';
            return $result;
        }
        
        // Získat všechna PLACENÁ videa
        $all_video_indices = $wpdb->get_col($wpdb->prepare(
            "SELECT video_index 
             FROM {$wpdb->prefix}saw_video_metadata 
             WHERE product_id = %d 
             AND is_free = 0 
             ORDER BY lesson_order ASC",
            $product_id
        ));
        
        if (empty($all_video_indices)) {
            $result['errors'][] = 'No paid videos found for this product';
            return $result;
        }
        
        $all_video_indices = array_map('intval', $all_video_indices);
        
        // Získat existující video_index
        $existing_indices = $wpdb->get_col($wpdb->prepare(
            "SELECT video_index 
             FROM {$wpdb->prefix}saw_video_access_tokens 
             WHERE user_id = %d 
             AND product_id = %d 
             AND is_active = 1",
            $user_id,
            $product_id
        ));
        
        $existing_indices = array_map('intval', $existing_indices);
        
        // Najít chybějící
        $missing_indices = array_diff($all_video_indices, $existing_indices);
        
        if (empty($missing_indices)) {
            $result['errors'][] = 'Customer already has all videos';
            return $result;
        }
        
        $result['affected_customers'] = 1;
        
        // Generovat tokeny
        foreach ($missing_indices as $video_index) {
            try {
                $token_manager = new VideoTokenManager();
                
                $token = $token_manager->generateToken(
                    $user_id,
                    $product_id,
                    $video_index,
                    (int)$access->order_id,
                    (int)$access->days_remaining
                );
                
                if ($token) {
                    $result['tokens_created']++;
                }
                
            } catch (\Exception $e) {
                $result['errors'][] = sprintf(
                    'Failed video_index=%d: %s',
                    $video_index,
                    $e->getMessage()
                );
            }
        }
        
        return $result;
    }
}