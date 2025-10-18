<?php
declare(strict_types=1);

namespace SAW\WAP\Core;

use WC_Customer;
use WC_Order;

/**
 * My Account Core Class
 * 
 * Handles all logic for the custom My Account system.
 * Replaces WooCommerce My Account with our own implementation.
 * 
 * @package SAW\WAP\Core
 * @since 1.0.0
 */
class MyAccount {

    /**
     * Available endpoints (tabs)
     * 
     * @var array<string, array{title: string, icon: string}>
     */
    private const ENDPOINTS = [
        'dashboard'       => ['title' => 'Přehled', 'icon' => '📊'],
        'courses'         => ['title' => 'Moje kurzy', 'icon' => '🎓'],
        'orders'          => ['title' => 'Objednávky', 'icon' => '📦'],
        'addresses'       => ['title' => 'Adresy', 'icon' => '📍'],
        'account-details' => ['title' => 'Můj účet', 'icon' => '👤'],
        'payment-methods' => ['title' => 'Platba', 'icon' => '💳'],
        'downloads'       => ['title' => 'Ke stažení', 'icon' => '📥'],
        'certificates'    => ['title' => 'Certifikáty', 'icon' => '📜'],
    ];

    /**
     * Check if user is logged in, redirect to login if not
     * 
     * @return void
     */
    public static function require_login(): void {
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink());
            wp_safe_redirect($login_url);
            exit;
        }
    }

    /**
     * Get current endpoint from URL
     * 
     * @return string Default: 'dashboard'
     */
    public static function get_current_endpoint(): string {
        // Get from query string (?endpoint=courses)
        $endpoint = isset($_GET['endpoint']) ? sanitize_text_field($_GET['endpoint']) : 'dashboard';
        
        // Validate endpoint exists
        if (!array_key_exists($endpoint, self::ENDPOINTS)) {
            $endpoint = 'dashboard';
        }
        
        return $endpoint;
    }

    /**
     * Get navigation menu items
     * 
     * @param string $current_endpoint Currently active endpoint
     * @return array<string, array{title: string, icon: string, url: string, active: bool}>
     */
    public static function get_menu_items(string $current_endpoint): array {
        $items = [];
        $base_url = get_permalink();
        
        foreach (self::ENDPOINTS as $key => $data) {
            $items[$key] = [
                'title'  => $data['title'],
                'icon'   => $data['icon'],
                'url'    => add_query_arg('endpoint', $key, $base_url),
                'active' => ($key === $current_endpoint),
            ];
        }
        
        // Add logout at the end
        $items['logout'] = [
            'title'  => 'Odhlásit se',
            'icon'   => '🚪',
            'url'    => wp_logout_url(home_url()),
            'active' => false,
        ];
        
        return $items;
    }

    /**
     * Get dashboard statistics
     * 
     * @param int $user_id WordPress user ID
     * @return array{active_courses: int, completed_courses: int, total_orders: int, expiring_soon: int}
     */
    public static function get_dashboard_stats(int $user_id): array {
        global $wpdb;
        
        $stats = [
            'active_courses'     => 0,
            'completed_courses'  => 0,
            'total_orders'       => 0,
            'expiring_soon'      => 0,
        ];
        
        // Get active courses (not completed, not expired)
        $active_courses = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT product_id) 
                 FROM {$wpdb->prefix}saw_video_access_tokens 
                 WHERE user_id = %d 
                 AND is_active = 1 
                 AND access_expires > NOW()",
                $user_id
            )
        );
        $stats['active_courses'] = (int) $active_courses;
        
        // Get completed courses
        $completed_courses = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT vat.product_id)
                 FROM {$wpdb->prefix}saw_video_access_tokens vat
                 INNER JOIN {$wpdb->prefix}saw_video_metadata vm ON vat.product_id = vm.product_id
                 WHERE vat.user_id = %d
                 AND vat.is_active = 1
                 GROUP BY vat.product_id
                 HAVING COUNT(DISTINCT vat.video_index) = (
                     SELECT COUNT(*) 
                     FROM {$wpdb->prefix}saw_video_metadata 
                     WHERE product_id = vat.product_id 
                     AND is_free = 0
                 )",
                $user_id
            )
        );
        $stats['completed_courses'] = (int) $completed_courses;
        
        // Get total orders
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => -1,
            'return'      => 'ids',
        ]);
        $stats['total_orders'] = count($orders);
        
        // Get courses expiring soon (within 7 days)
        $expiring_soon = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT product_id) 
                 FROM {$wpdb->prefix}saw_video_access_tokens 
                 WHERE user_id = %d 
                 AND is_active = 1 
                 AND access_expires > NOW() 
                 AND access_expires <= DATE_ADD(NOW(), INTERVAL 7 DAY)",
                $user_id
            )
        );
        $stats['expiring_soon'] = (int) $expiring_soon;
        
        return $stats;
    }

    /**
     * Get user courses with detailed info
     * 
     * @param int $user_id WordPress user ID
     * @return array Array of course objects
     */
    public static function get_user_courses(int $user_id): array {
        global $wpdb;
        
        // Get all products with tokens for this user
        $products = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT product_id, 
                        MIN(access_expires) as nearest_expiration,
                        MAX(is_active) as has_active_token
                 FROM {$wpdb->prefix}saw_video_access_tokens 
                 WHERE user_id = %d 
                 GROUP BY product_id
                 ORDER BY access_expires DESC",
                $user_id
            )
        );
        
        if (empty($products)) {
            return [];
        }
        
        $courses = [];
        
        foreach ($products as $product_row) {
            $product_id = (int) $product_row->product_id;
            $product = wc_get_product($product_id);
            
            if (!$product) {
                continue;
            }
            
            // Get all videos for this product
            $videos = Database::get_all_product_videos($product_id);
            $total_videos = count($videos);
            
            // Get completed videos count
            $completed_videos = self::get_completed_videos_count($user_id, $product_id);
            
            // Calculate progress percentage
            $progress_percent = $total_videos > 0 ? round(($completed_videos / $total_videos) * 100) : 0;
            
            // Get next unwatched video
            $next_video = self::get_next_unwatched_video($user_id, $product_id);
            
            // Determine status
            $is_expired = strtotime($product_row->nearest_expiration) < time();
            $is_completed = $completed_videos === $total_videos && $total_videos > 0;
            
            $courses[] = [
                'product_id'         => $product_id,
                'product'            => $product,
                'title'              => $product->get_name(),
                'image_url'          => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
                'total_videos'       => $total_videos,
                'completed_videos'   => $completed_videos,
                'progress_percent'   => $progress_percent,
                'expiration_date'    => $product_row->nearest_expiration,
                'is_expired'         => $is_expired,
                'is_completed'       => $is_completed,
                'is_active'          => (bool) $product_row->has_active_token && !$is_expired,
                'next_video'         => $next_video,
                'videos'             => $videos,
            ];
        }
        
        return $courses;
    }

    /**
     * Get count of completed videos for a product
     * 
     * @param int $user_id User ID
     * @param int $product_id Product ID
     * @return int Number of completed videos
     */
    private static function get_completed_videos_count(int $user_id, int $product_id): int {
        global $wpdb;
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT vat.video_index)
                 FROM {$wpdb->prefix}saw_video_access_tokens vat
                 INNER JOIN {$wpdb->prefix}saw_video_watch_sessions vws ON vat.id = vws.token_id
                 WHERE vat.user_id = %d 
                 AND vat.product_id = %d 
                 AND vws.completed = 1",
                $user_id,
                $product_id
            )
        );
        
        return (int) $count;
    }

    /**
     * Get next unwatched video for a course
     * 
     * @param int $user_id User ID
     * @param int $product_id Product ID
     * @return object|null Video object or null
     */
    public static function get_next_unwatched_video(int $user_id, int $product_id): ?object {
        global $wpdb;
        
        // Get first video that is not completed
        $video = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT vm.*, vat.access_token
                 FROM {$wpdb->prefix}saw_video_metadata vm
                 INNER JOIN {$wpdb->prefix}saw_video_access_tokens vat 
                    ON vm.product_id = vat.product_id 
                    AND vm.video_index = vat.video_index
                 LEFT JOIN {$wpdb->prefix}saw_video_watch_sessions vws 
                    ON vat.id = vws.token_id 
                    AND vws.completed = 1
                 WHERE vat.user_id = %d 
                 AND vm.product_id = %d 
                 AND vm.is_free = 0
                 AND vat.is_active = 1
                 AND vat.access_expires > NOW()
                 AND vws.id IS NULL
                 ORDER BY vm.lesson_order ASC
                 LIMIT 1",
                $user_id,
                $product_id
            )
        );
        
        return $video ?: null;
    }

    /**
     * Get user orders
     * 
     * @param int $user_id User ID
     * @param int $limit Number of orders to return (-1 for all)
     * @return array Array of WC_Order objects
     */
    public static function get_user_orders(int $user_id, int $limit = -1): array {
        $args = [
            'customer_id' => $user_id,
            'limit'       => $limit,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ];
        
        return wc_get_orders($args);
    }

    /**
     * Get user addresses
     * 
     * @param int $user_id User ID
     * @return array{billing: array, shipping: array}
     */
    public static function get_user_addresses(int $user_id): array {
        $customer = new WC_Customer($user_id);
        
        return [
            'billing' => [
                'first_name' => $customer->get_billing_first_name(),
                'last_name'  => $customer->get_billing_last_name(),
                'company'    => $customer->get_billing_company(),
                'address_1'  => $customer->get_billing_address_1(),
                'address_2'  => $customer->get_billing_address_2(),
                'city'       => $customer->get_billing_city(),
                'postcode'   => $customer->get_billing_postcode(),
                'country'    => $customer->get_billing_country(),
                'state'      => $customer->get_billing_state(),
                'email'      => $customer->get_billing_email(),
                'phone'      => $customer->get_billing_phone(),
            ],
            'shipping' => [
                'first_name' => $customer->get_shipping_first_name(),
                'last_name'  => $customer->get_shipping_last_name(),
                'company'    => $customer->get_shipping_company(),
                'address_1'  => $customer->get_shipping_address_1(),
                'address_2'  => $customer->get_shipping_address_2(),
                'city'       => $customer->get_shipping_city(),
                'postcode'   => $customer->get_shipping_postcode(),
                'country'    => $customer->get_shipping_country(),
                'state'      => $customer->get_shipping_state(),
            ],
        ];
    }

    /**
     * Save account details
     * 
     * @param int $user_id User ID
     * @param array $data Form data
     * @return array{success: bool, message: string}
     */
    public static function save_account_details(int $user_id, array $data): array {
        // Verify nonce
        if (!isset($data['saw_account_nonce']) || 
            !wp_verify_nonce($data['saw_account_nonce'], 'saw_save_account_details')) {
            return [
                'success' => false,
                'message' => 'Bezpečnostní kontrola selhala.',
            ];
        }
        
        $customer = new WC_Customer($user_id);
        
        // Update basic info
        if (isset($data['first_name'])) {
            $customer->set_first_name(sanitize_text_field($data['first_name']));
        }
        
        if (isset($data['last_name'])) {
            $customer->set_last_name(sanitize_text_field($data['last_name']));
        }
        
        if (isset($data['display_name'])) {
            wp_update_user([
                'ID'           => $user_id,
                'display_name' => sanitize_text_field($data['display_name']),
            ]);
        }
        
        // Update email
        if (isset($data['email'])) {
            $email = sanitize_email($data['email']);
            
            if (!is_email($email)) {
                return [
                    'success' => false,
                    'message' => 'Neplatná emailová adresa.',
                ];
            }
            
            if (email_exists($email) && $email !== $customer->get_email()) {
                return [
                    'success' => false,
                    'message' => 'Tento email už používá jiný účet.',
                ];
            }
            
            $customer->set_email($email);
        }
        
        // Save customer data
        $customer->save();
        
        // Change password if provided
        if (!empty($data['password_current']) && !empty($data['password_new'])) {
            $current_user = wp_get_current_user();
            
            if (!wp_check_password($data['password_current'], $current_user->user_pass, $user_id)) {
                return [
                    'success' => false,
                    'message' => 'Současné heslo je nesprávné.',
                ];
            }
            
            if (strlen($data['password_new']) < 8) {
                return [
                    'success' => false,
                    'message' => 'Nové heslo musí mít alespoň 8 znaků.',
                ];
            }
            
            if ($data['password_new'] !== $data['password_confirm']) {
                return [
                    'success' => false,
                    'message' => 'Hesla se neshodují.',
                ];
            }
            
            wp_set_password($data['password_new'], $user_id);
        }
        
        return [
            'success' => true,
            'message' => 'Údaje účtu byly úspěšně uloženy.',
        ];
    }

    /**
     * Save address (billing or shipping)
     * 
     * @param int $user_id User ID
     * @param string $type 'billing' or 'shipping'
     * @param array $data Form data
     * @return array{success: bool, message: string}
     */
    public static function save_address(int $user_id, string $type, array $data): array {
        // Verify nonce
        if (!isset($data['saw_address_nonce']) || 
            !wp_verify_nonce($data['saw_address_nonce'], 'saw_save_address')) {
            return [
                'success' => false,
                'message' => 'Bezpečnostní kontrola selhala.',
            ];
        }
        
        if (!in_array($type, ['billing', 'shipping'], true)) {
            return [
                'success' => false,
                'message' => 'Neplatný typ adresy.',
            ];
        }
        
        $customer = new WC_Customer($user_id);
        
        // Map of fields
        $fields = [
            'first_name', 'last_name', 'company', 'address_1', 'address_2',
            'city', 'postcode', 'country', 'state',
        ];
        
        // Add email/phone for billing
        if ($type === 'billing') {
            $fields[] = 'email';
            $fields[] = 'phone';
        }
        
        // Update each field
        foreach ($fields as $field) {
            $key = "{$type}_{$field}";
            
            if (isset($data[$key])) {
                $value = sanitize_text_field($data[$key]);
                $method = "set_{$key}";
                
                if (method_exists($customer, $method)) {
                    $customer->$method($value);
                }
            }
        }
        
        $customer->save();
        
        return [
            'success' => true,
            'message' => 'Adresa byla úspěšně uložena.',
        ];
    }
}