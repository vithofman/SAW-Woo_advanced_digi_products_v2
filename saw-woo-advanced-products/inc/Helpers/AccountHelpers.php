<?php
declare(strict_types=1);

namespace SAW\WAP\Helpers;

/**
 * Account Helper Functions
 * 
 * UI components and formatting helpers for My Account system.
 * 
 * @package SAW\WAP\Helpers
 * @since 1.0.0
 */
class AccountHelpers {

    /**
     * Get expiration status color based on days remaining
     * 
     * @param string $expiration_date Date string (Y-m-d H:i:s)
     * @return string 'green'|'orange'|'red'|'expired'
     */
    public static function get_expiration_status_color(string $expiration_date): string {
        $now = time();
        $expires = strtotime($expiration_date);
        $days_remaining = (int) (($expires - $now) / DAY_IN_SECONDS);
        
        if ($days_remaining < 0) {
            return 'expired';
        } elseif ($days_remaining <= 7) {
            return 'red';
        } elseif ($days_remaining <= 30) {
            return 'orange';
        } else {
            return 'green';
        }
    }

    /**
     * Format expiration date with color-coded status
     * 
     * @param string $expiration_date Date string
     * @return string HTML span with formatted date and status
     */
    public static function format_expiration_status(string $expiration_date): string {
        $now = time();
        $expires = strtotime($expiration_date);
        $days_remaining = (int) (($expires - $now) / DAY_IN_SECONDS);
        
        $color = self::get_expiration_status_color($expiration_date);
        
        if ($days_remaining < 0) {
            $text = 'Vypršelo';
            $badge_class = 'saw-badge--expired';
        } elseif ($days_remaining === 0) {
            $text = 'Vyprší dnes';
            $badge_class = 'saw-badge--critical';
        } elseif ($days_remaining === 1) {
            $text = 'Vyprší zítra';
            $badge_class = 'saw-badge--critical';
        } elseif ($days_remaining <= 7) {
            $text = sprintf('Zbývá %d dní', $days_remaining);
            $badge_class = 'saw-badge--warning';
        } elseif ($days_remaining <= 30) {
            $text = sprintf('Zbývá %d dní', $days_remaining);
            $badge_class = 'saw-badge--info';
        } else {
            $formatted_date = date_i18n('j.n.Y', $expires);
            $text = sprintf('Do %s', $formatted_date);
            $badge_class = 'saw-badge--success';
        }
        
        return sprintf(
            '<span class="saw-badge %s">⏱ %s</span>',
            esc_attr($badge_class),
            esc_html($text)
        );
    }

    /**
     * Generate SVG progress circle
     * 
     * @param float $percent Completion percentage (0-100)
     * @param int $size SVG size in pixels
     * @return string SVG HTML
     */
    public static function get_progress_circle_html(float $percent, int $size = 120): string {
        // Ensure percent is between 0 and 100
        $percent = max(0, min(100, $percent));
        
        // Calculate SVG circle properties
        $radius = 54;
        $circumference = 2 * M_PI * $radius;
        $offset = $circumference - ($percent / 100) * $circumference;
        
        // Determine color based on progress
        if ($percent < 30) {
            $color = '#ef4444'; // red
        } elseif ($percent < 70) {
            $color = '#f59e0b'; // orange
        } else {
            $color = '#10b981'; // green
        }
        
        $svg = sprintf(
            '<svg class="saw-progress-circle" width="%d" height="%d" viewBox="0 0 120 120">
                <circle class="saw-progress-circle__bg" cx="60" cy="60" r="%d" />
                <circle 
                    class="saw-progress-circle__fill" 
                    cx="60" 
                    cy="60" 
                    r="%d"
                    style="stroke: %s; stroke-dasharray: %s; stroke-dashoffset: %s;"
                />
                <text class="saw-progress-circle__text" x="60" y="60" text-anchor="middle" dominant-baseline="middle">
                    <tspan class="saw-progress-circle__percent">%d%%</tspan>
                </text>
            </svg>',
            $size,
            $size,
            $radius,
            $radius,
            esc_attr($color),
            esc_attr((string) $circumference),
            esc_attr((string) $offset),
            (int) $percent
        );
        
        return $svg;
    }

    /**
     * Generate progress bar HTML
     * 
     * @param float $percent Completion percentage (0-100)
     * @return string HTML
     */
    public static function get_progress_bar_html(float $percent): string {
        $percent = max(0, min(100, $percent));
        
        // Determine color class
        if ($percent < 30) {
            $color_class = 'saw-progress-bar--low';
        } elseif ($percent < 70) {
            $color_class = 'saw-progress-bar--medium';
        } else {
            $color_class = 'saw-progress-bar--high';
        }
        
        return sprintf(
            '<div class="saw-progress-bar">
                <div class="saw-progress-bar__fill %s" style="width: %s%%" role="progressbar" aria-valuenow="%d" aria-valuemin="0" aria-valuemax="100">
                    <span class="saw-progress-bar__label">%d%%</span>
                </div>
            </div>',
            esc_attr($color_class),
            esc_attr((string) $percent),
            (int) $percent,
            (int) $percent
        );
    }

    /**
     * Get course completion badge
     * 
     * @param bool $is_completed Is course completed
     * @param bool $is_expired Is access expired
     * @param bool $is_active Is course active
     * @return string HTML badge
     */
    public static function get_course_completion_badge(bool $is_completed, bool $is_expired, bool $is_active): string {
        if ($is_expired) {
            return '<span class="saw-badge saw-badge--expired">Vypršelo</span>';
        }
        
        if ($is_completed) {
            return '<span class="saw-badge saw-badge--success">✓ Dokončeno</span>';
        }
        
        if ($is_active) {
            return '<span class="saw-badge saw-badge--info">V pokračování</span>';
        }
        
        return '<span class="saw-badge saw-badge--default">Nový</span>';
    }

    /**
     * Format WooCommerce order status as badge
     * 
     * @param string $status Order status (without 'wc-' prefix)
     * @return string HTML badge
     */
    public static function format_order_status(string $status): string {
        // Map WooCommerce statuses to readable text
        $status_labels = [
            'pending'    => 'Čeká na platbu',
            'processing' => 'Zpracovává se',
            'on-hold'    => 'Pozastaveno',
            'completed'  => 'Dokončeno',
            'cancelled'  => 'Zrušeno',
            'refunded'   => 'Vráceno',
            'failed'     => 'Selhalo',
        ];
        
        // Map statuses to badge classes
        $status_classes = [
            'pending'    => 'saw-badge--warning',
            'processing' => 'saw-badge--info',
            'on-hold'    => 'saw-badge--warning',
            'completed'  => 'saw-badge--success',
            'cancelled'  => 'saw-badge--default',
            'refunded'   => 'saw-badge--default',
            'failed'     => 'saw-badge--expired',
        ];
        
        $label = $status_labels[$status] ?? ucfirst($status);
        $class = $status_classes[$status] ?? 'saw-badge--default';
        
        return sprintf(
            '<span class="saw-badge %s">%s</span>',
            esc_attr($class),
            esc_html($label)
        );
    }

    /**
     * Format price with currency
     * 
     * @param float $amount Price amount
     * @return string Formatted price
     */
    public static function format_price(float $amount): string {
        return wc_price($amount);
    }

    /**
     * Format date in Czech format
     * 
     * @param string $date Date string
     * @param string $format Date format (default: j.n.Y)
     * @return string Formatted date
     */
    public static function format_date(string $date, string $format = 'j.n.Y'): string {
        $timestamp = strtotime($date);
        
        if ($timestamp === false) {
            return '';
        }
        
        return date_i18n($format, $timestamp);
    }

    /**
     * Format datetime in Czech format
     * 
     * @param string $datetime Datetime string
     * @return string Formatted datetime
     */
    public static function format_datetime(string $datetime): string {
        return self::format_date($datetime, 'j.n.Y H:i');
    }

    /**
     * Get relative time string (e.g. "před 2 dny")
     * 
     * @param string $date Date string
     * @return string Relative time
     */
    public static function get_relative_time(string $date): string {
        $timestamp = strtotime($date);
        
        if ($timestamp === false) {
            return '';
        }
        
        $diff = time() - $timestamp;
        
        if ($diff < MINUTE_IN_SECONDS) {
            return 'Právě teď';
        }
        
        if ($diff < HOUR_IN_SECONDS) {
            $minutes = (int) ($diff / MINUTE_IN_SECONDS);
            return sprintf('Před %d min', $minutes);
        }
        
        if ($diff < DAY_IN_SECONDS) {
            $hours = (int) ($diff / HOUR_IN_SECONDS);
            return sprintf('Před %d h', $hours);
        }
        
        if ($diff < WEEK_IN_SECONDS) {
            $days = (int) ($diff / DAY_IN_SECONDS);
            return sprintf('Před %d dny', $days);
        }
        
        if ($diff < MONTH_IN_SECONDS) {
            $weeks = (int) ($diff / WEEK_IN_SECONDS);
            return sprintf('Před %d týdny', $weeks);
        }
        
        // For older dates, show full date
        return self::format_date($date);
    }

    /**
     * Generate action button HTML
     * 
     * @param string $url Button URL
     * @param string $text Button text
     * @param string $type Button type: 'primary'|'secondary'|'danger'
     * @param string $icon Optional icon (emoji or HTML)
     * @return string Button HTML
     */
    public static function get_action_button(string $url, string $text, string $type = 'primary', string $icon = ''): string {
        $class = 'saw-btn saw-btn--' . esc_attr($type);
        
        $icon_html = $icon ? '<span class="saw-btn__icon">' . $icon . '</span>' : '';
        
        return sprintf(
            '<a href="%s" class="%s">%s<span class="saw-btn__text">%s</span></a>',
            esc_url($url),
            $class,
            $icon_html,
            esc_html($text)
        );
    }

    /**
     * Get empty state message HTML
     * 
     * @param string $icon Emoji icon
     * @param string $title Message title
     * @param string $description Message description
     * @param string $action_url Optional action button URL
     * @param string $action_text Optional action button text
     * @return string HTML
     */
    public static function get_empty_state(string $icon, string $title, string $description, string $action_url = '', string $action_text = ''): string {
        $action_html = '';
        
        if ($action_url && $action_text) {
            $action_html = sprintf(
                '<div class="saw-empty-state__action">%s</div>',
                self::get_action_button($action_url, $action_text, 'primary')
            );
        }
        
        return sprintf(
            '<div class="saw-empty-state">
                <div class="saw-empty-state__icon">%s</div>
                <h3 class="saw-empty-state__title">%s</h3>
                <p class="saw-empty-state__description">%s</p>
                %s
            </div>',
            esc_html($icon),
            esc_html($title),
            esc_html($description),
            $action_html
        );
    }

    /**
     * Sanitize endpoint parameter
     * 
     * @param string $endpoint Endpoint from URL
     * @return string Sanitized endpoint
     */
    public static function sanitize_endpoint(string $endpoint): string {
        return sanitize_key($endpoint);
    }

    /**
     * Get video duration formatted as human readable
     * 
     * @param int $seconds Duration in seconds
     * @return string Formatted duration (e.g. "15 min" or "1 h 30 min")
     */
    public static function format_video_duration(int $seconds): string {
        if ($seconds < 60) {
            return sprintf('%d s', $seconds);
        }
        
        $minutes = (int) ($seconds / 60);
        
        if ($minutes < 60) {
            return sprintf('%d min', $minutes);
        }
        
        $hours = (int) ($minutes / 60);
        $remaining_minutes = $minutes % 60;
        
        if ($remaining_minutes === 0) {
            return sprintf('%d h', $hours);
        }
        
        return sprintf('%d h %d min', $hours, $remaining_minutes);
    }

    /**
     * Get course total duration
     * 
     * @param array $videos Array of video objects with duration property
     * @return string Formatted total duration
     */
    public static function get_course_total_duration(array $videos): string {
        $total_seconds = 0;
        
        foreach ($videos as $video) {
            if (isset($video->video_duration)) {
                $total_seconds += (int) $video->video_duration;
            }
        }
        
        return self::format_video_duration($total_seconds);
    }

    /**
     * Check if course is expiring soon (within 7 days)
     * 
     * @param string $expiration_date Expiration date
     * @return bool True if expiring soon
     */
    public static function is_expiring_soon(string $expiration_date): bool {
        $expires = strtotime($expiration_date);
        $days_remaining = (int) (($expires - time()) / DAY_IN_SECONDS);
        
        return $days_remaining > 0 && $days_remaining <= 7;
    }

    /**
     * Get user greeting based on time of day
     * 
     * @param string $user_name User's first name
     * @return string Greeting message
     */
    public static function get_greeting(string $user_name): string {
        $hour = (int) date('G');
        
        if ($hour >= 5 && $hour < 12) {
            $greeting = 'Dobré ráno';
        } elseif ($hour >= 12 && $hour < 18) {
            $greeting = 'Dobré odpoledne';
        } else {
            $greeting = 'Dobrý večer';
        }
        
        return sprintf('%s, %s!', $greeting, esc_html($user_name));
    }
}