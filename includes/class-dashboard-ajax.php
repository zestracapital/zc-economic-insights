<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Secure Dashboard AJAX Endpoints
 * 
 * Enhanced version with proper security, rate limiting, and access control
 * to prevent data exposure in browser developer tools and unauthorized access
 */
if (!class_exists('ZC_DMT_Dashboard_Ajax')) {

    class ZC_DMT_Dashboard_Ajax {

        /**
         * Register AJAX endpoints
         */
        public static function register() {
            // Secure dashboard data endpoint (main chart data)
            add_action('wp_ajax_zc_dmt_get_chart_data', array(__CLASS__, 'get_chart_data'));
            add_action('wp_ajax_nopriv_zc_dmt_get_chart_data', array(__CLASS__, 'get_chart_data'));
            
            // Secure search endpoint
            add_action('wp_ajax_zc_dmt_search_indicators', array(__CLASS__, 'search_indicators'));
            add_action('wp_ajax_nopriv_zc_dmt_search_indicators', array(__CLASS__, 'search_indicators'));
            
            // Basic indicator info endpoint
            add_action('wp_ajax_zc_dmt_get_indicator_info', array(__CLASS__, 'get_indicator_info'));
            add_action('wp_ajax_nopriv_zc_dmt_get_indicator_info', array(__CLASS__, 'get_indicator_info'));
            
            // Admin-only endpoints
            add_action('wp_ajax_zc_dmt_refresh_indicators', array(__CLASS__, 'refresh_indicators'));
            add_action('wp_ajax_zc_dmt_test_indicator', array(__CLASS__, 'test_indicator'));
            
            // Legacy compatibility endpoints
            add_action('wp_ajax_zc_dmt_get_dashboard_data', array(__CLASS__, 'get_dashboard_data_legacy'));
            add_action('wp_ajax_nopriv_zc_dmt_get_dashboard_data', array(__CLASS__, 'get_dashboard_data_legacy'));
            
            add_action('wp_ajax_zc_dmt_list_indicators', array(__CLASS__, 'list_indicators'));
            add_action('wp_ajax_nopriv_zc_dmt_list_indicators', array(__CLASS__, 'list_indicators'));
        }

        /**
         * Main secure chart data endpoint
         * This replaces the old vulnerable endpoint with proper security
         */
        public static function get_chart_data() {
            try {
                // Step 1: Verify nonce for security
                if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zc_dmt_dashboard')) {
                    wp_send_json_error('Security verification failed', 403);
                    return;
                }

                // Step 2: Validate and sanitize inputs
                $slug = sanitize_text_field($_POST['slug'] ?? '');
                $time_range = sanitize_text_field($_POST['time_range'] ?? '5Y');
                $access_key = sanitize_text_field($_POST['access_key'] ?? '');

                if (empty($slug)) {
                    wp_send_json_error('Missing required parameter: slug', 400);
                    return;
                }

                // Step 3: Check access permissions
                if (!self::verify_access($access_key)) {
                    wp_send_json_error('Access denied. This endpoint requires authentication.', 403);
                    return;
                }

                // Step 4: Rate limiting to prevent abuse
                if (!self::check_rate_limit('chart_data')) {
                    wp_send_json_error('Rate limit exceeded. Please try again later.', 429);
                    return;
                }

                // Step 5: Get and validate indicator
                $indicator = ZC_DMT_Indicators::get_indicator_by_slug($slug);
                if (!$indicator) {
                    wp_send_json_error('Indicator not found', 404);
                    return;
                }

                if (!$indicator->is_active) {
                    wp_send_json_error('Indicator is not active', 400);
                    return;
                }

                // Step 6: Fetch data with time range filtering
                $data = ZC_DMT_Indicators::get_data_by_slug($slug, array(
                    'time_range' => $time_range,
                    'format' => 'chart_ready'
                ));

                if (is_wp_error($data)) {
                    wp_send_json_error('Data fetch failed: ' . $data->get_error_message(), 500);
                    return;
                }

                if (empty($data) || empty($data['series'])) {
                    wp_send_json_error('No data available for this indicator', 404);
                    return;
                }

                // Step 7: Sanitize and prepare secure response
                $response_data = array(
                    'indicator' => array(
                        'id' => intval($indicator->id),
                        'name' => sanitize_text_field($indicator->name),
                        'slug' => sanitize_text_field($indicator->slug),
                        'description' => sanitize_text_field($indicator->description ?? ''),
                        // Removed: source_config, api_keys, file_paths, etc.
                    ),
                    'series' => self::sanitize_series_data($data['series']),
                    'meta' => array(
                        'time_range' => $time_range,
                        'total_points' => count($data['series']),
                        'last_updated' => $indicator->updated_at,
                        'data_source' => 'secure_endpoint', // Don't expose actual source details
                    )
                );

                // Step 8: Log successful access for monitoring
                self::log_access('chart_data', $slug, true);

                wp_send_json_success($response_data);

            } catch (Exception $e) {
                // Log error securely without exposing sensitive details
                error_log('ZC DMT Chart Data Error: ' . $e->getMessage());
                self::log_access('chart_data', $slug ?? 'unknown', false, 'Exception occurred');
                
                wp_send_json_error('Failed to load chart data. Please try again.', 500);
            }
        }

        /**
         * Secure indicator search endpoint
         */
        public static function search_indicators() {
            try {
                // Verify nonce
                if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zc_dmt_dashboard')) {
                    wp_send_json_error('Security verification failed', 403);
                    return;
                }

                $query = sanitize_text_field($_POST['query'] ?? '');
                $access_key = sanitize_text_field($_POST['access_key'] ?? '');

                // Validate query length
                if (empty($query) || strlen($query) < 2) {
                    wp_send_json_success(array()); // Return empty for short queries
                    return;
                }

                // Check access permissions
                if (!self::verify_access($access_key)) {
                    wp_send_json_error('Access denied', 403);
                    return;
                }

                // Rate limiting for search
                if (!self::check_rate_limit('search')) {
                    wp_send_json_error('Search rate limit exceeded', 429);
                    return;
                }

                // Search with limits and sanitization
                global $wpdb;
                $table = $wpdb->prefix . 'zc_dmt_indicators';
                
                $indicators = $wpdb->get_results($wpdb->prepare(
                    "SELECT name, slug FROM {$table} 
                     WHERE is_active = 1 
                     AND (name LIKE %s OR slug LIKE %s)
                     ORDER BY name ASC 
                     LIMIT 15", // Limit to 15 results
                    '%' . $wpdb->esc_like($query) . '%',
                    '%' . $wpdb->esc_like($query) . '%'
                ));

                // Return minimal data only
                $results = array();
                foreach ($indicators as $indicator) {
                    $results[] = array(
                        'slug' => sanitize_text_field($indicator->slug),
                        'name' => sanitize_text_field($indicator->name)
                        // Removed: id, source_type, description, etc.
                    );
                }

                self::log_access('search', $query, true);
                wp_send_json_success($results);

            } catch (Exception $e) {
                error_log('ZC DMT Search Error: ' . $e->getMessage());
                wp_send_json_error('Search failed', 500);
            }
        }

        /**
         * Get basic indicator information (minimal data)
         */
        public static function get_indicator_info() {
            try {
                if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zc_dmt_dashboard')) {
                    wp_send_json_error('Security verification failed', 403);
                    return;
                }

                $slug = sanitize_text_field($_POST['slug'] ?? '');
                $access_key = sanitize_text_field($_POST['access_key'] ?? '');

                if (empty($slug)) {
                    wp_send_json_error('Missing slug parameter', 400);
                    return;
                }

                if (!self::verify_access($access_key)) {
                    wp_send_json_error('Access denied', 403);
                    return;
                }

                $indicator = ZC_DMT_Indicators::get_indicator_by_slug($slug);
                if (!$indicator || !$indicator->is_active) {
                    wp_send_json_error('Indicator not found', 404);
                    return;
                }

                // Return only basic info (no sensitive data)
                $info = array(
                    'name' => sanitize_text_field($indicator->name),
                    'slug' => sanitize_text_field($indicator->slug),
                    'description' => sanitize_text_field($indicator->description ?? ''),
                    'last_updated' => $indicator->updated_at
                );

                wp_send_json_success($info);

            } catch (Exception $e) {
                error_log('ZC DMT Indicator Info Error: ' . $e->getMessage());
                wp_send_json_error('Failed to get indicator info', 500);
            }
        }

        /**
         * Admin-only: Refresh indicators list
         */
        public static function refresh_indicators() {
            try {
                // Check admin capabilities
                if (!current_user_can('manage_options')) {
                    wp_send_json_error('Insufficient permissions', 403);
                    return;
                }

                if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zc_dmt_indicators_action')) {
                    wp_send_json_error('Security verification failed', 403);
                    return;
                }

                // Get indicators for admin use
                $indicators = ZC_DMT_Indicators::list_indicators(array(
                    'active_only' => false,
                    'limit' => 100
                ));

                $formatted = array();
                foreach ($indicators as $indicator) {
                    // Admin can see more details but still limit sensitive data
                    $formatted[] = array(
                        'id' => intval($indicator->id),
                        'name' => sanitize_text_field($indicator->name),
                        'slug' => sanitize_text_field($indicator->slug),
                        'source_type' => sanitize_text_field($indicator->source_type),
                        'is_active' => (bool) $indicator->is_active,
                        'updated_at' => $indicator->updated_at
                        // Still no source_config, api_keys, file_paths
                    );
                }

                wp_send_json_success($formatted);

            } catch (Exception $e) {
                error_log('ZC DMT Refresh Indicators Error: ' . $e->getMessage());
                wp_send_json_error('Failed to refresh indicators', 500);
            }
        }

        /**
         * Admin-only: Test indicator data
         */
        public static function test_indicator() {
            try {
                if (!current_user_can('manage_options')) {
                    wp_send_json_error('Insufficient permissions', 403);
                    return;
                }

                if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zc_dmt_indicators_action')) {
                    wp_send_json_error('Security verification failed', 403);
                    return;
                }

                $slug = sanitize_text_field($_POST['slug'] ?? '');
                if (empty($slug)) {
                    wp_send_json_error('Missing slug parameter', 400);
                    return;
                }

                // Test with limited data
                $data = ZC_DMT_Indicators::get_data_by_slug($slug, array(
                    'limit' => 10,
                    'format' => 'test'
                ));

                $result = array(
                    'success' => !is_wp_error($data) && !empty($data['series']),
                    'data_points' => !is_wp_error($data) && !empty($data['series']) ? count($data['series']) : 0,
                    'sample_data' => !is_wp_error($data) && !empty($data['series']) ? array_slice($data['series'], 0, 3) : array(),
                    'error' => is_wp_error($data) ? $data->get_error_message() : null
                );

                wp_send_json_success($result);

            } catch (Exception $e) {
                error_log('ZC DMT Test Indicator Error: ' . $e->getMessage());
                wp_send_json_error('Test failed', 500);
            }
        }

        /**
         * Legacy compatibility endpoint
         */
        public static function get_dashboard_data_legacy() {
            // Redirect to secure endpoint
            return self::get_chart_data();
        }

        /**
         * List indicators with security
         */
        public static function list_indicators() {
            try {
                if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zc_dmt_dashboard')) {
                    wp_send_json_error('Security verification failed', 403);
                    return;
                }

                $access_key = sanitize_text_field($_POST['access_key'] ?? '');
                if (!self::verify_access($access_key)) {
                    wp_send_json_error('Access denied', 403);
                    return;
                }

                global $wpdb;
                $table = $wpdb->prefix . 'zc_dmt_indicators';
                
                $rows = $wpdb->get_results(
                    "SELECT name, slug FROM {$table} 
                     WHERE is_active = 1 
                     ORDER BY name ASC 
                     LIMIT 50" // Limit results
                );

                $indicators = array();
                foreach ($rows as $r) {
                    $indicators[] = array(
                        'name' => sanitize_text_field($r->name),
                        'slug' => sanitize_text_field($r->slug)
                        // Minimal data only
                    );
                }

                wp_send_json_success(array('indicators' => $indicators));

            } catch (Exception $e) {
                error_log('ZC DMT List Indicators Error: ' . $e->getMessage());
                wp_send_json_error('Failed to list indicators', 500);
            }
        }

        /**
         * Verify access permissions with multiple security layers
         */
        private static function verify_access($access_key = '') {
            // Layer 1: Check if this is an internal WordPress request
            if (self::is_internal_request()) {
                return true;
            }

            // Layer 2: Check if user is logged in
            if (is_user_logged_in()) {
                return true;
            }

            // Layer 3: Validate provided access key
            if (!empty($access_key) && self::validate_access_key($access_key)) {
                return true;
            }

            // Layer 4: Development mode (REMOVE IN PRODUCTION)
            if (defined('ZC_DMT_DEBUG') && ZC_DMT_DEBUG && current_user_can('administrator')) {
                return true;
            }

            return false;
        }

        /**
         * Check if request is from same origin
         */
        private static function is_internal_request() {
            $referer = wp_get_referer();
            $current_host = $_SERVER['HTTP_HOST'] ?? '';

            if (!empty($referer) && !empty($current_host)) {
                $referer_host = parse_url($referer, PHP_URL_HOST);
                return $referer_host === $current_host;
            }

            return false;
        }

        /**
         * Validate access key against stored keys
         */
        private static function validate_access_key($access_key) {
            $valid_keys = get_option('zc_dmt_access_keys', array());
            
            if (empty($valid_keys) || !is_array($valid_keys)) {
                return false;
            }

            foreach ($valid_keys as $key_data) {
                if (isset($key_data['key']) && 
                    isset($key_data['active']) && 
                    $key_data['active'] && 
                    hash_equals($key_data['key'], $access_key)) {
                    
                    // Check expiration if set
                    if (isset($key_data['expires']) && 
                        !empty($key_data['expires']) && 
                        strtotime($key_data['expires']) <= time()) {
                        continue; // Key expired
                    }
                    
                    return true;
                }
            }

            return false;
        }

        /**
         * Rate limiting system
         */
        private static function check_rate_limit($action = 'default') {
            $user_ip = self::get_client_ip();
            $user_id = get_current_user_id();
            
            // Use user ID if logged in, otherwise IP
            $identifier = $user_id > 0 ? 'user_' . $user_id : 'ip_' . md5($user_ip);
            $cache_key = "zc_dmt_rate_{$action}_{$identifier}";
            
            $current_requests = get_transient($cache_key);
            
            // Set limits per action
            $limits = array(
                'default' => 100,     // 100 requests per hour
                'search' => 50,       // 50 searches per hour
                'chart_data' => 200   // 200 chart loads per hour (higher for dashboards)
            );
            
            $limit = $limits[$action] ?? $limits['default'];
            
            if ($current_requests === false) {
                set_transient($cache_key, 1, HOUR_IN_SECONDS);
                return true;
            }
            
            if ($current_requests >= $limit) {
                return false;
            }
            
            set_transient($cache_key, $current_requests + 1, HOUR_IN_SECONDS);
            return true;
        }

        /**
         * Get client IP with proxy support
         */
        private static function get_client_ip() {
            $ip_headers = array(
                'HTTP_CF_CONNECTING_IP',     // Cloudflare
                'HTTP_X_FORWARDED_FOR',      // Standard proxy header
                'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'REMOTE_ADDR'                // Direct connection
            );

            foreach ($ip_headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $ip = trim($_SERVER[$header]);
                    
                    // Handle comma-separated IPs from proxies
                    if (strpos($ip, ',') !== false) {
                        $ip = trim(explode(',', $ip)[0]);
                    }
                    
                    // Validate IP
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }

            return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }

        /**
         * Sanitize series data for safe client consumption
         */
        private static function sanitize_series_data($series) {
            if (!is_array($series)) {
                return array();
            }

            $sanitized = array();
            foreach ($series as $point) {
                if (is_array($point) && count($point) >= 2) {
                    $sanitized[] = array(
                        sanitize_text_field($point[0]), // date
                        floatval($point[1])              // value
                    );
                }
            }

            return $sanitized;
        }

        /**
         * Log access for security monitoring
         */
        private static function log_access($action, $resource, $success, $error = '') {
            // Only log if enabled and table exists
            if (!get_option('zc_dmt_enable_access_logging', false)) {
                return;
            }

            $log_entry = array(
                'timestamp' => current_time('mysql'),
                'action' => sanitize_text_field($action),
                'resource' => sanitize_text_field($resource),
                'ip' => self::get_client_ip(),
                'user_id' => get_current_user_id(),
                'success' => (bool) $success,
                'error' => sanitize_text_field($error),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '')
            );

            // Store in database if table exists
            global $wpdb;
            $table_name = $wpdb->prefix . 'zc_dmt_access_logs';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
                $wpdb->insert($table_name, $log_entry);
            }
        }

        /**
         * Cleanup function for expired rate limits
         */
        public static function cleanup_expired_rate_limits() {
            global $wpdb;
            
            // Clean up expired transients
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_timeout_zc_dmt_rate_%' 
                 AND option_value < UNIX_TIMESTAMP()"
            );
            
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_zc_dmt_rate_%' 
                 AND option_name NOT IN (
                     SELECT DISTINCT REPLACE(option_name, '_timeout', '') 
                     FROM {$wpdb->options} 
                     WHERE option_name LIKE '_transient_timeout_zc_dmt_rate_%'
                 )"
            );
        }
    }

    // Schedule daily cleanup of expired rate limits
    if (!wp_next_scheduled('zc_dmt_cleanup_rate_limits')) {
        wp_schedule_event(time(), 'daily', 'zc_dmt_cleanup_rate_limits');
    }
    add_action('zc_dmt_cleanup_rate_limits', array('ZC_DMT_Dashboard_Ajax', 'cleanup_expired_rate_limits'));
}