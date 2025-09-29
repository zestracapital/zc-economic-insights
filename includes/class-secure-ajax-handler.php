<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Secure AJAX Handler for Dashboard Data
 * 
 * This class handles all AJAX requests for the secure dashboard
 * with enhanced security measures to prevent data exposure.
 */
class ZC_DMT_Secure_Ajax_Handler {

    /**
     * Initialize secure AJAX handlers
     */
    public static function init() {
        // Secure dashboard data endpoint
        add_action('wp_ajax_zc_dmt_get_secure_dashboard_data', array(__CLASS__, 'get_secure_dashboard_data'));
        add_action('wp_ajax_nopriv_zc_dmt_get_secure_dashboard_data', array(__CLASS__, 'get_secure_dashboard_data'));
        
        // Rate limiting cleanup
        add_action('wp_ajax_zc_dmt_cleanup_rate_limits', array(__CLASS__, 'cleanup_rate_limits'));
        
        // Security logging
        add_action('zc_dmt_log_security_event', array(__CLASS__, 'log_security_event'), 10, 2);
    }

    /**
     * Secure dashboard data endpoint
     * Enhanced security with multiple validation layers
     */
    public static function get_secure_dashboard_data() {
        // CSRF protection
        if (!self::verify_nonce()) {
            self::send_error('Invalid security token', 403);
            return;
        }

        // Rate limiting
        if (!self::check_rate_limit()) {
            self::send_error('Too many requests. Please wait before trying again.', 429);
            return;
        }

        // Input validation and sanitization
        $slug = self::validate_and_sanitize_slug();
        if (!$slug) {
            self::send_error('Invalid indicator identifier', 400);
            return;
        }

        // Timestamp validation (prevent replay attacks)
        if (!self::validate_timestamp()) {
            self::send_error('Request expired', 400);
            return;
        }

        // Signature validation
        if (!self::validate_signature($slug)) {
            self::send_error('Invalid request signature', 400);
            return;
        }

        // Access key validation (if provided)
        $access_key = sanitize_text_field($_POST['access_key'] ?? '');
        if (!empty($access_key) && !self::validate_access_key($access_key)) {
            self::send_error('Invalid access credentials', 403);
            return;
        }

        // Check if indicator exists and is accessible
        if (!self::is_indicator_accessible($slug)) {
            self::send_error('Indicator not found or access denied', 404);
            return;
        }

        try {
            // Get sanitized indicator data
            $data = self::get_sanitized_indicator_data($slug);
            
            if (!$data) {
                self::send_error('Data not available', 404);
                return;
            }

            // Log successful access (for monitoring)
            self::log_access_event($slug, 'success');

            // Send secure response
            self::send_success($data);

        } catch (Exception $e) {
            error_log('ZC DMT Secure AJAX Error: ' . $e->getMessage());
            self::log_access_event($slug, 'error', $e->getMessage());
            self::send_error('Data retrieval failed', 500);
        }
    }

    /**
     * Verify WordPress nonce with additional security
     */
    private static function verify_nonce() {
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        $user_id = get_current_user_id();
        
        // Verify nonce
        if (!wp_verify_nonce($nonce, 'zc_dmt_dashboard_' . $user_id)) {
            return false;
        }

        // Additional time-based validation
        $nonce_age = wp_nonce_tick() - intval(substr($nonce, -12, 10));
        if ($nonce_age > 2) { // Only allow nonces from last 2 tick periods
            return false;
        }

        return true;
    }

    /**
     * Rate limiting implementation
     */
    private static function check_rate_limit() {
        $ip = self::get_client_ip();
        $user_id = get_current_user_id();
        $key = 'zc_dmt_ajax_rate_' . md5($ip . '_' . $user_id);
        
        $requests = get_transient($key) ?: array();
        $now = time();
        $window = 300; // 5 minutes
        $limit = 30; // 30 requests per 5 minutes
        
        // Clean old requests
        $requests = array_filter($requests, function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        if (count($requests) >= $limit) {
            self::log_security_event('rate_limit_exceeded', array(
                'ip' => $ip,
                'user_id' => $user_id,
                'requests' => count($requests)
            ));
            return false;
        }
        
        $requests[] = $now;
        set_transient($key, $requests, $window);
        
        return true;
    }

    /**
     * Validate and sanitize indicator slug
     */
    private static function validate_and_sanitize_slug() {
        $slug = sanitize_text_field($_POST['slug'] ?? '');
        
        // Basic validation
        if (empty($slug)) {
            return false;
        }
        
        // Sanitize slug (only allow alphanumeric, hyphens, underscores)
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
        
        // Length validation
        if (strlen($slug) < 2 || strlen($slug) > 50) {
            return false;
        }
        
        return strtolower($slug);
    }

    /**
     * Validate request timestamp (prevent replay attacks)
     */
    private static function validate_timestamp() {
        $timestamp = intval($_POST['timestamp'] ?? 0);
        $now = time();
        $max_age = 300; // 5 minutes
        
        if ($timestamp < ($now - $max_age) || $timestamp > ($now + 60)) {
            return false;
        }
        
        return true;
    }

    /**
     * Validate request signature
     */
    private static function validate_signature($slug) {
        $provided_signature = sanitize_text_field($_POST['signature'] ?? '');
        $timestamp = intval($_POST['timestamp'] ?? 0);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        
        // Generate expected signature
        $base_string = $slug . '_' . $timestamp . '_' . $nonce;
        $expected_signature = substr(base64_encode($base_string), 0, 16);
        
        return hash_equals($expected_signature, $provided_signature);
    }

    /**
     * Validate access key if provided
     */
    private static function validate_access_key($access_key) {
        if (class_exists('ZC_DMT_Security')) {
            return ZC_DMT_Security::validate_key($access_key);
        }
        
        // Fallback validation
        return !empty($access_key) && strlen($access_key) >= 16;
    }

    /**
     * Check if indicator is accessible
     */
    private static function is_indicator_accessible($slug) {
        global $wpdb;
        $table = $wpdb->prefix . 'zc_dmt_indicators';
        
        $indicator = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_active FROM {$table} WHERE slug = %s LIMIT 1",
            $slug
        ));
        
        return ($indicator && intval($indicator->is_active) === 1);
    }

    /**
     * Get sanitized indicator data with minimal exposure
     */
    private static function get_sanitized_indicator_data($slug) {
        global $wpdb;
        
        // Get indicator info (safe fields only)
        $indicator_table = $wpdb->prefix . 'zc_dmt_indicators';
        $indicator = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, slug, description, source_type 
             FROM {$indicator_table} 
             WHERE slug = %s AND is_active = 1 
             LIMIT 1",
            $slug
        ));
        
        if (!$indicator) {
            return null;
        }
        
        // Get data points (with limit for security)
        $data_table = $wpdb->prefix . 'zc_dmt_data_points';
        $data_points = $wpdb->get_results($wpdb->prepare(
            "SELECT date, value 
             FROM {$data_table} 
             WHERE indicator_id = %d 
             ORDER BY date ASC 
             LIMIT 5000", // Security limit
            $indicator->id
        ));
        
        // Process data points securely
        $series = array();
        foreach ($data_points as $point) {
            $date = sanitize_text_field($point->date);
            $value = floatval($point->value);
            
            // Validate date format
            if (self::is_valid_date($date)) {
                $series[] = array($date, $value);
            }
        }
        
        // Ensure we have data
        if (empty($series)) {
            return null;
        }
        
        // Return sanitized data structure
        return array(
            'indicator' => array(
                'id' => intval($indicator->id),
                'name' => sanitize_text_field($indicator->name),
                'slug' => sanitize_title($indicator->slug),
                'description' => sanitize_text_field($indicator->description),
                'source_type' => sanitize_text_field($indicator->source_type)
            ),
            'series' => $series,
            'meta' => array(
                'count' => count($series),
                'last_update' => !empty($series) ? $series[count($series) - 1][0] : null,
                'generated_at' => current_time('c')
            )
        );
    }

    /**
     * Validate date format
     */
    private static function is_valid_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Get client IP address safely
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field($_SERVER[$key]);
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0'; // Fallback
    }

    /**
     * Log access events for monitoring
     */
    private static function log_access_event($slug, $status, $error_message = '') {
        $log_data = array(
            'slug' => $slug,
            'status' => $status,
            'ip' => self::get_client_ip(),
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('c'),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown')
        );
        
        if (!empty($error_message)) {
            $log_data['error'] = $error_message;
        }
        
        // Use WordPress logging or custom error logger
        if (class_exists('ZC_DMT_Error_Logger')) {
            ZC_DMT_Error_Logger::log_event('dashboard_access', $log_data);
        } else {
            error_log('ZC DMT Dashboard Access: ' . json_encode($log_data));
        }
    }

    /**
     * Log security events
     */
    private static function log_security_event($event_type, $details = array()) {
        $log_data = array_merge(array(
            'event' => $event_type,
            'ip' => self::get_client_ip(),
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('c')
        ), $details);
        
        // Store in WordPress options or custom logging
        $security_log = get_option('zc_dmt_security_log', array());
        $security_log[] = $log_data;
        
        // Keep only last 1000 entries
        if (count($security_log) > 1000) {
            $security_log = array_slice($security_log, -1000);
        }
        
        update_option('zc_dmt_security_log', $security_log, false);
        
        // Also log to error log for immediate attention
        error_log('ZC DMT Security Event: ' . json_encode($log_data));
    }

    /**
     * Send success response
     */
    private static function send_success($data) {
        // Add security headers
        self::add_security_headers();
        
        wp_send_json_success($data);
    }

    /**
     * Send error response
     */
    private static function send_error($message, $status_code = 400) {
        // Add security headers
        self::add_security_headers();
        
        http_response_code($status_code);
        wp_send_json_error($message);
    }

    /**
     * Add security headers to response
     */
    private static function add_security_headers() {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }

    /**
     * Cleanup rate limiting data
     */
    public static function cleanup_rate_limits() {
        // Only allow this for administrators
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        
        global $wpdb;
        
        // Clean up expired transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_zc_dmt_ajax_rate_%' 
             OR option_name LIKE '_transient_timeout_zc_dmt_ajax_rate_%'"
        );
        
        wp_send_json_success('Rate limit data cleaned up');
    }

    /**
     * Get security statistics for admin
     */
    public static function get_security_stats() {
        if (!current_user_can('manage_options')) {
            return array();
        }
        
        $security_log = get_option('zc_dmt_security_log', array());
        $recent_events = array_slice($security_log, -100);
        
        $stats = array(
            'total_events' => count($security_log),
            'recent_events' => count($recent_events),
            'rate_limit_violations' => count(array_filter($recent_events, function($event) {
                return $event['event'] === 'rate_limit_exceeded';
            })),
            'invalid_access_attempts' => count(array_filter($recent_events, function($event) {
                return in_array($event['event'], array('invalid_nonce', 'invalid_signature', 'invalid_access_key'));
            }))
        );
        
        return $stats;
    }
}

// Initialize the secure AJAX handler
ZC_DMT_Secure_Ajax_Handler::init();