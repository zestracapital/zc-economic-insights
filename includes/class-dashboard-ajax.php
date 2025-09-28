<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Dashboard_Ajax')) {

    class ZC_DMT_Dashboard_Ajax {

        /**
         * Register secure AJAX endpoints with enhanced protection
         */
        public static function register() {
            // Dashboard data endpoint (enhanced security)
            add_action('wp_ajax_zc_dmt_get_dashboard_data', array(__CLASS__, 'get_dashboard_data'));
            add_action('wp_ajax_nopriv_zc_dmt_get_dashboard_data', array(__CLASS__, 'get_dashboard_data_public'));
            
            // Search indicators endpoint (secured)
            add_action('wp_ajax_zc_dmt_search_indicators', array(__CLASS__, 'search_indicators'));
            add_action('wp_ajax_nopriv_zc_dmt_search_indicators', array(__CLASS__, 'search_indicators_public'));

            // List indicators endpoint (secured)
            add_action('wp_ajax_zc_dmt_list_indicators', array(__CLASS__, 'list_indicators'));
            add_action('wp_ajax_nopriv_zc_dmt_list_indicators', array(__CLASS__, 'list_indicators_public'));

            // Test formula endpoint (admin only)
            add_action('wp_ajax_zc_dmt_test_formula', array(__CLASS__, 'test_formula'));

            // Validate access key endpoint (public but secured)
            add_action('wp_ajax_zc_dmt_validate_access_key', array(__CLASS__, 'validate_access_key'));
            add_action('wp_ajax_nopriv_zc_dmt_validate_access_key', array(__CLASS__, 'validate_access_key_public'));
        }

        /**
         * Enhanced security checks with IP tracking and rate limiting
         */
        private static function enhanced_security_check($required_capability = null, $require_access_key = false) {
            // Check if IP is blocked
            $ip = self::get_user_ip();
            if (self::is_ip_blocked($ip)) {
                wp_send_json_error(array(
                    'message' => 'Access temporarily blocked',
                    'code' => 'BLOCKED_IP'
                ), 403);
                return false;
            }

            // Rate limiting with progressive blocking
            if (!self::check_rate_limit_enhanced()) {
                wp_send_json_error(array(
                    'message' => 'Rate limit exceeded. Please try again later.',
                    'code' => 'RATE_LIMIT'
                ), 429);
                return false;
            }

            // Check user capability if required
            if ($required_capability && !current_user_can($required_capability)) {
                self::log_security_event('Insufficient Permissions', array(
                    'required_capability' => $required_capability,
                    'user_id' => get_current_user_id()
                ));
                wp_send_json_error(array(
                    'message' => 'Insufficient permissions',
                    'code' => 'INSUFFICIENT_PERMISSIONS'
                ), 403);
                return false;
            }

            // Check access key if required
            if ($require_access_key) {
                $access_key = sanitize_text_field($_POST['access_key'] ?? '');
                if (empty($access_key)) {
                    wp_send_json_error(array(
                        'message' => 'Access key required',
                        'code' => 'ACCESS_KEY_REQUIRED'
                    ), 401);
                    return false;
                }

                if (!self::validate_access_key_secure($access_key)) {
                    wp_send_json_error(array(
                        'message' => 'Invalid access key',
                        'code' => 'INVALID_ACCESS_KEY'
                    ), 403);
                    return false;
                }
            }

            return true;
        }

        /**
         * Enhanced rate limiting with progressive blocking
         */
        private static function check_rate_limit_enhanced() {
            $ip = self::get_user_ip();
            $user_id = get_current_user_id();
            
            // Different limits for logged in users vs anonymous
            $hourly_limit = $user_id ? 300 : 100; // Higher limit for logged in users
            $daily_limit = $user_id ? 2000 : 500;
            
            // Check hourly limit
            $hourly_key = 'zc_dmt_rate_hourly_' . md5($ip . $user_id);
            $hourly_requests = get_transient($hourly_key) ?: 0;
            
            if ($hourly_requests >= $hourly_limit) {
                // Progressive blocking - block IP for increasing durations
                $violation_key = 'zc_dmt_violations_' . md5($ip);
                $violations = get_transient($violation_key) ?: 0;
                $block_duration = min(3600 * pow(2, $violations), 86400); // Max 24 hours
                
                self::block_ip_temporarily($ip, $block_duration);
                set_transient($violation_key, $violations + 1, DAY_IN_SECONDS);
                
                return false;
            }
            
            // Check daily limit
            $daily_key = 'zc_dmt_rate_daily_' . md5($ip . $user_id);
            $daily_requests = get_transient($daily_key) ?: 0;
            
            if ($daily_requests >= $daily_limit) {
                self::block_ip_temporarily($ip, HOUR_IN_SECONDS);
                return false;
            }
            
            // Update counters
            set_transient($hourly_key, $hourly_requests + 1, HOUR_IN_SECONDS);
            set_transient($daily_key, $daily_requests + 1, DAY_IN_SECONDS);
            
            return true;
        }

        /**
         * Block IP temporarily
         */
        private static function block_ip_temporarily($ip, $duration) {
            $block_key = 'zc_dmt_blocked_' . md5($ip);
            set_transient($block_key, true, $duration);
            
            self::log_security_event('IP Blocked', array(
                'ip' => $ip,
                'duration' => $duration,
                'reason' => 'Rate limit exceeded'
            ));
        }

        /**
         * Check if IP is blocked
         */
        private static function is_ip_blocked($ip) {
            $block_key = 'zc_dmt_blocked_' . md5($ip);
            return get_transient($block_key) === true;
        }

        /**
         * Enhanced nonce verification
         */
        private static function verify_dashboard_nonce($nonce) {
            $user_id = get_current_user_id();
            $expected_action = 'zc_dmt_dashboard_' . $user_id;
            
            if (!wp_verify_nonce($nonce, $expected_action)) {
                // Try fallback nonce for backward compatibility
                if (!wp_verify_nonce($nonce, 'zc_dmt_chart')) {
                    return false;
                }
            }
            
            return true;
        }

        /**
         * Validate access key with enhanced security
         */
        private static function validate_access_key_secure($key) {
            if (empty($key)) {
                return false;
            }

            // Use enhanced security validation
            $valid = ZC_DMT_Security::validate_key($key);
            
            if (!$valid) {
                self::log_security_event('Invalid Access Key', array(
                    'key_preview' => substr($key, 0, 8) . '***',
                    'ip' => self::get_user_ip()
                ));
            }
            
            return $valid;
        }

        /**
         * Get dashboard data for logged in users (enhanced security)
         */
        public static function get_dashboard_data() {
            // Enhanced nonce verification
            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (!self::verify_dashboard_nonce($nonce)) {
                wp_send_json_error(array(
                    'message' => 'Security verification failed',
                    'code' => 'NONCE_FAILED'
                ), 403);
                return;
            }

            if (!self::enhanced_security_check()) {
                return;
            }

            self::process_dashboard_data_request();
        }

        /**
         * Get dashboard data for public users (requires access key)
         */
        public static function get_dashboard_data_public() {
            if (!self::enhanced_security_check(null, true)) { // Require access key for public
                return;
            }

            self::process_dashboard_data_request();
        }

        /**
         * Process dashboard data request (unified logic)
         */
        private static function process_dashboard_data_request() {
            $slug = sanitize_title($_POST['slug'] ?? '');
            if (empty($slug)) {
                wp_send_json_error(array(
                    'message' => 'Missing indicator slug',
                    'code' => 'MISSING_SLUG'
                ), 400);
                return;
            }

            try {
                // Validate indicator exists and is accessible
                if (!self::validate_indicator_access($slug)) {
                    wp_send_json_error(array(
                        'message' => 'Indicator not found or access denied',
                        'code' => 'ACCESS_DENIED'
                    ), 404);
                    return;
                }

                // Get data securely
                $data = ZC_DMT_Indicators::get_data_by_slug($slug);

                if (is_wp_error($data)) {
                    wp_send_json_error(array(
                        'message' => 'Data not available',
                        'code' => 'DATA_ERROR'
                    ), 404);
                    return;
                }

                // Sanitize and limit response
                $response = self::sanitize_dashboard_response($data);
                
                wp_send_json_success($response);

            } catch (Exception $e) {
                error_log('ZC DMT Dashboard Data Error: ' . $e->getMessage());
                wp_send_json_error(array(
                    'message' => 'Service temporarily unavailable',
                    'code' => 'SERVICE_ERROR'
                ), 503);
            }
        }

        /**
         * Search indicators (logged in users)
         */
        public static function search_indicators() {
            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (!self::verify_dashboard_nonce($nonce)) {
                wp_send_json_error(array('message' => 'Security verification failed', 'code' => 'NONCE_FAILED'), 403);
                return;
            }

            if (!self::enhanced_security_check()) {
                return;
            }

            self::process_search_request();
        }

        /**
         * Search indicators (public with access key)
         */
        public static function search_indicators_public() {
            if (!self::enhanced_security_check(null, true)) {
                return;
            }

            self::process_search_request();
        }

        /**
         * Process search request (unified logic)
         */
        private static function process_search_request() {
            $query = sanitize_text_field($_POST['query'] ?? '');
            $limit = min(50, max(1, intval($_POST['limit'] ?? 20)));
            
            if (strlen($query) < 2) {
                wp_send_json_success(array(
                    'indicators' => array(),
                    'total' => 0,
                    'query' => $query
                ));
                return;
            }

            // Prevent dangerous queries
            if (preg_match('/<script|javascript:|data:|vbscript:|onload|onerror/i', $query)) {
                self::log_security_event('Malicious Search Query', array('query' => $query));
                wp_send_json_error(array(
                    'message' => 'Invalid search query',
                    'code' => 'INVALID_QUERY'
                ), 400);
                return;
            }

            try {
                global $wpdb;
                $table = $wpdb->prefix . 'zc_dmt_indicators';
                
                $indicators = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name, slug, source_type 
                     FROM {$table} 
                     WHERE is_active = 1 
                     AND (name LIKE %s OR slug LIKE %s)
                     ORDER BY 
                         CASE 
                             WHEN name LIKE %s THEN 1
                             WHEN slug LIKE %s THEN 2
                             ELSE 3
                         END,
                         name ASC 
                     LIMIT %d",
                    '%' . $wpdb->esc_like($query) . '%',
                    '%' . $wpdb->esc_like($query) . '%',
                    $wpdb->esc_like($query) . '%',
                    $wpdb->esc_like($query) . '%',
                    $limit
                ));

                $formatted = array();
                foreach ($indicators as $indicator) {
                    $formatted[] = array(
                        'id' => intval($indicator->id),
                        'name' => sanitize_text_field($indicator->name),
                        'slug' => sanitize_title($indicator->slug),
                        'source_type' => sanitize_text_field($indicator->source_type)
                        // Only essential data - no sensitive information
                    );
                }

                wp_send_json_success(array(
                    'indicators' => $formatted,
                    'total' => count($formatted),
                    'query' => $query
                ));

            } catch (Exception $e) {
                error_log('ZC DMT Search Error: ' . $e->getMessage());
                wp_send_json_error(array(
                    'message' => 'Search failed',
                    'code' => 'SEARCH_ERROR'
                ), 500);
            }
        }

        /**
         * List indicators (logged in users)
         */
        public static function list_indicators() {
            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (!self::verify_dashboard_nonce($nonce)) {
                wp_send_json_error(array('message' => 'Security verification failed', 'code' => 'NONCE_FAILED'), 403);
                return;
            }

            if (!self::enhanced_security_check()) {
                return;
            }

            self::process_list_request();
        }

        /**
         * List indicators (public with access key)
         */
        public static function list_indicators_public() {
            if (!self::enhanced_security_check(null, true)) {
                return;
            }

            self::process_list_request();
        }

        /**
         * Process list indicators request
         */
        private static function process_list_request() {
            $limit = min(100, max(1, intval($_POST['limit'] ?? 50)));

            try {
                global $wpdb;
                $table = $wpdb->prefix . 'zc_dmt_indicators';
                
                $indicators = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name, slug, source_type, is_active, created_at
                     FROM {$table}
                     WHERE is_active = 1
                     ORDER BY name ASC
                     LIMIT %d",
                    $limit
                ));

                $formatted = array();
                foreach ($indicators as $indicator) {
                    $formatted[] = array(
                        'id' => intval($indicator->id),
                        'name' => sanitize_text_field($indicator->name),
                        'slug' => sanitize_title($indicator->slug),
                        'source_type' => sanitize_text_field($indicator->source_type),
                        'is_active' => intval($indicator->is_active),
                        'created_at' => sanitize_text_field($indicator->created_at)
                    );
                }

                wp_send_json_success(array(
                    'indicators' => $formatted,
                    'total' => count($formatted)
                ));

            } catch (Exception $e) {
                error_log('ZC DMT List Error: ' . $e->getMessage());
                wp_send_json_error(array(
                    'message' => 'Failed to list indicators',
                    'code' => 'LIST_ERROR'
                ), 500);
            }
        }

        /**
         * Validate access key (logged in)
         */
        public static function validate_access_key() {
            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (!self::verify_dashboard_nonce($nonce)) {
                wp_send_json_error(array('message' => 'Security verification failed', 'code' => 'NONCE_FAILED'), 403);
                return;
            }

            if (!self::enhanced_security_check()) {
                return;
            }

            self::process_key_validation();
        }

        /**
         * Validate access key (public)
         */
        public static function validate_access_key_public() {
            if (!self::enhanced_security_check()) {
                return;
            }

            self::process_key_validation();
        }

        /**
         * Process key validation request
         */
        private static function process_key_validation() {
            $access_key = sanitize_text_field($_POST['access_key'] ?? '');
            if (empty($access_key)) {
                wp_send_json_error(array(
                    'message' => 'Access key required',
                    'code' => 'MISSING_KEY'
                ), 400);
                return;
            }

            $valid = self::validate_access_key_secure($access_key);
            
            wp_send_json_success(array(
                'valid' => $valid,
                'message' => $valid ? 'Valid access key' : 'Invalid access key'
            ));
        }

        /**
         * Test formula (admin only - enhanced security)
         */
        public static function test_formula() {
            if (!self::enhanced_security_check('manage_options')) {
                return;
            }

            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (!wp_verify_nonce($nonce, 'zc_dmt_calculations')) {
                wp_send_json_error(array('message' => 'Security verification failed', 'code' => 'NONCE_FAILED'), 403);
                return;
            }

            $formula = wp_kses_post($_POST['formula'] ?? '');
            $indicator_slugs = array();
            
            if (isset($_POST['indicators']) && is_array($_POST['indicators'])) {
                $indicator_slugs = array_map('sanitize_title', $_POST['indicators']);
            }

            if (empty($formula)) {
                wp_send_json_error(array(
                    'message' => 'Formula cannot be empty',
                    'code' => 'EMPTY_FORMULA'
                ), 400);
                return;
            }

            // Validate formula for dangerous functions
            if (self::contains_dangerous_formula($formula)) {
                wp_send_json_error(array(
                    'message' => 'Formula contains potentially dangerous operations',
                    'code' => 'DANGEROUS_FORMULA'
                ), 400);
                return;
            }

            try {
                if (!class_exists('ZC_DMT_Calculations')) {
                    wp_send_json_error(array(
                        'message' => 'Calculations module not available',
                        'code' => 'MODULE_UNAVAILABLE'
                    ), 503);
                    return;
                }

                // Get sample data for indicators (limited for security)
                $data_context = array();
                foreach (array_slice($indicator_slugs, 0, 5) as $slug) { // Limit to 5 indicators max
                    if (!empty($slug) && self::validate_indicator_access($slug)) {
                        $indicator_data = ZC_DMT_Indicators::get_data_by_slug($slug);
                        if (!is_wp_error($indicator_data) && isset($indicator_data['series'])) {
                            // Use last 100 data points for testing (security limit)
                            $series = array_slice($indicator_data['series'], -100);
                            $data_context[strtolower($slug)] = $series;
                        }
                    }
                }

                $result = ZC_DMT_Calculations::execute_formula($formula, $data_context);

                if (is_wp_error($result)) {
                    wp_send_json_error(array(
                        'message' => $result->get_error_message(),
                        'code' => 'FORMULA_ERROR'
                    ), 400);
                    return;
                }

                wp_send_json_success($result);

            } catch (Exception $e) {
                error_log('ZC DMT Formula Test Error: ' . $e->getMessage());
                wp_send_json_error(array(
                    'message' => 'Formula test failed',
                    'code' => 'TEST_FAILED'
                ), 500);
            }
        }

        /**
         * Check for dangerous formula content
         */
        private static function contains_dangerous_formula($formula) {
            // List of potentially dangerous functions or patterns
            $dangerous_patterns = array(
                '/\bexec\b/i', '/\bsystem\b/i', '/\bshell_exec\b/i',
                '/\bpassthru\b/i', '/\bfile_get_contents\b/i', '/\bfile_put_contents\b/i',
                '/\bmysql_query\b/i', '/\bmysqli_query\b/i', '/\beval\b/i',
                '/\bcurl_exec\b/i', '/\bbase64_decode\b/i', '/\bunlink\b/i',
                '/<\?php/i', '/<%/i', '/\$_GET\b/i', '/\$_POST\b/i',
                '/\$GLOBALS\b/i', '/\$_SERVER\b/i'
            );

            foreach ($dangerous_patterns as $pattern) {
                if (preg_match($pattern, $formula)) {
                    self::log_security_event('Dangerous Formula Detected', array(
                        'formula' => substr($formula, 0, 100) . '...',
                        'pattern' => $pattern
                    ));
                    return true;
                }
            }

            return false;
        }

        /**
         * Validate indicator access
         */
        private static function validate_indicator_access($slug) {
            if (empty($slug)) {
                return false;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_indicators';
            
            $indicator = $wpdb->get_row($wpdb->prepare(
                "SELECT id, is_active FROM {$table} WHERE slug = %s LIMIT 1",
                $slug
            ));

            return ($indicator && intval($indicator->is_active) === 1);
        }

        /**
         * Sanitize dashboard response data
         */
        private static function sanitize_dashboard_response($data) {
            if (!is_array($data)) {
                return array();
            }

            $indicator = $data['indicator'] ?? null;
            $series = $data['series'] ?? array();

            $response = array(
                'indicator' => array(
                    'id' => intval($indicator['id'] ?? $indicator->id ?? 0),
                    'name' => sanitize_text_field($indicator['name'] ?? $indicator->name ?? ''),
                    'slug' => sanitize_title($indicator['slug'] ?? $indicator->slug ?? ''),
                    'source_type' => sanitize_text_field($indicator['source_type'] ?? $indicator->source_type ?? '')
                    // Exclude sensitive data like source_config, API keys, etc.
                ),
                'series' => array(),
                'meta' => array(
                    'count' => 0,
                    'generated_at' => current_time('c'),
                    'cache_expires' => current_time('timestamp') + (5 * MINUTE_IN_SECONDS)
                )
            );

            // Sanitize and limit series data
            if (is_array($series)) {
                $count = 0;
                $max_points = 5000; // Security limit on data points
                
                foreach ($series as $point) {
                    if ($count >= $max_points) break;
                    
                    if (is_array($point) && count($point) >= 2) {
                        $date = $point[0];
                        $value = $point[1];
                        
                        if (strtotime($date) !== false && is_numeric($value)) {
                            $response['series'][] = array(
                                gmdate('Y-m-d', strtotime($date)),
                                round(floatval($value), 6) // Limit precision
                            );
                            $count++;
                        }
                    }
                }
            }

            $response['meta']['count'] = count($response['series']);
            return $response;
        }

        /**
         * Security helper methods
         */
        private static function get_user_ip() {
            return ZC_DMT_Security::get_client_ip();
        }

        private static function log_security_event($event_type, $details = array()) {
            ZC_DMT_Security::log_security_event($event_type, $details);
        }

        /**
         * Add security headers for AJAX responses
         */
        public static function add_security_headers() {
            if (defined('DOING_AJAX') && DOING_AJAX) {
                $action = $_POST['action'] ?? $_GET['action'] ?? '';
                if (strpos($action, 'zc_dmt_') === 0) {
                    header('X-Content-Type-Options: nosniff');
                    header('X-Frame-Options: DENY');
                    header('X-XSS-Protection: 1; mode=block');
                    header('Referrer-Policy: strict-origin-when-cross-origin');
                    header('X-Robots-Tag: noindex, nofollow');
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    header('Pragma: no-cache');
                    header('Expires: 0');
                }
            }
        }

        /**
         * Clean expired security data (runs on WordPress cron)
         */
        public static function cleanup_expired_data() {
            global $wpdb;
            
            // Clean up old rate limit transients
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_zc_dmt_rate_%' 
                 OR option_name LIKE '_transient_zc_dmt_blocked_%'"
            );

            // Clean up old security logs if table exists
            $security_table = $wpdb->prefix . 'zc_dmt_security_logs';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$security_table}'") == $security_table) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$security_table} WHERE created_at < %s",
                    date('Y-m-d H:i:s', strtotime('-30 days'))
                ));
            }
        }

        /**
         * Get security statistics (admin only)
         */
        public static function get_security_stats() {
            if (!current_user_can('manage_options')) {
                return array();
            }

            return ZC_DMT_Security::get_security_stats();
        }

        /**
         * Emergency security lockdown (admin only)
         */
        public static function emergency_lockdown() {
            if (!current_user_can('manage_options')) {
                return false;
            }

            // Revoke all API keys
            $revoked = ZC_DMT_Security::emergency_revoke_all();
            
            // Enable strict mode
            update_option('zc_dmt_require_api_key', true);
            update_option('zc_dmt_strict_mode', true);
            
            self::log_security_event('Emergency Lockdown Activated', array(
                'revoked_keys' => $revoked,
                'triggered_by' => get_current_user_id(),
                'timestamp' => current_time('c')
            ));

            return true;
        }

        /**
         * Disable emergency lockdown (admin only)
         */
        public static function disable_lockdown() {
            if (!current_user_can('manage_options')) {
                return false;
            }

            update_option('zc_dmt_strict_mode', false);
            
            self::log_security_event('Emergency Lockdown Disabled', array(
                'disabled_by' => get_current_user_id(),
                'timestamp' => current_time('c')
            ));

            return true;
        }
    }

    // Initialize security cleanup cron
    if (!wp_next_scheduled('zc_dmt_security_cleanup')) {
        wp_schedule_event(time(), 'daily', 'zc_dmt_security_cleanup');
    }
    add_action('zc_dmt_security_cleanup', array('ZC_DMT_Dashboard_Ajax', 'cleanup_expired_data'));
}