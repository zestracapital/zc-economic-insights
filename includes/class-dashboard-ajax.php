<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Dashboard_Ajax')) {

    class ZC_DMT_Dashboard_Ajax {

        /**
         * Register secure AJAX endpoints
         */
        public static function register() {
            // Dashboard data endpoint (secured)
            add_action('wp_ajax_zc_dmt_get_dashboard_data', array(__CLASS__, 'get_dashboard_data'));
            add_action('wp_ajax_nopriv_zc_dmt_get_dashboard_data', array(__CLASS__, 'get_dashboard_data'));
            
            // Search indicators endpoint (secured)
            add_action('wp_ajax_zc_dmt_search_indicators', array(__CLASS__, 'search_indicators'));
            add_action('wp_ajax_nopriv_zc_dmt_search_indicators', array(__CLASS__, 'search_indicators'));

            // List all active indicators (secured)
            add_action('wp_ajax_zc_dmt_list_indicators', array(__CLASS__, 'list_indicators'));
            add_action('wp_ajax_nopriv_zc_dmt_list_indicators', array(__CLASS__, 'list_indicators'));

            // Test formula endpoint (admin only)
            add_action('wp_ajax_zc_dmt_test_formula', array(__CLASS__, 'test_formula'));

            // Get calculation result endpoint (secured)
            add_action('wp_ajax_zc_dmt_get_calculation_result', array(__CLASS__, 'get_calculation_result'));
            add_action('wp_ajax_nopriv_zc_dmt_get_calculation_result', array(__CLASS__, 'get_calculation_result'));
            
            // Validate access key endpoint (secured)
            add_action('wp_ajax_zc_dmt_validate_access_key', array(__CLASS__, 'validate_access_key'));
            add_action('wp_ajax_nopriv_zc_dmt_validate_access_key', array(__CLASS__, 'validate_access_key'));
        }

        /**
         * Enhanced security check
         */
        private static function security_check($required_capability = null) {
            // Rate limiting check
            if (!self::check_rate_limit()) {
                wp_send_json_error('Rate limit exceeded. Please try again later.');
                return false;
            }

            // Check user capability if required
            if ($required_capability && !current_user_can($required_capability)) {
                wp_send_json_error('Insufficient permissions');
                return false;
            }

            return true;
        }

        /**
         * Rate limiting implementation
         */
        private static function check_rate_limit() {
            $user_ip = self::get_user_ip();
            $transient_key = 'zc_dmt_rate_limit_' . md5($user_ip);
            $requests = get_transient($transient_key) ?: 0;
            
            // Allow 100 requests per hour per IP
            if ($requests >= 100) {
                return false;
            }
            
            set_transient($transient_key, $requests + 1, HOUR_IN_SECONDS);
            return true;
        }

        /**
         * Get user IP address securely
         */
        private static function get_user_ip() {
            $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
            
            foreach ($ip_keys as $key) {
                if (array_key_exists($key, $_SERVER) === true) {
                    $ip = $_SERVER[$key];
                    if (strpos($ip, ',') !== false) {
                        $ip = explode(',', $ip)[0];
                    }
                    if (filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return trim($ip);
                    }
                }
            }
            
            return '127.0.0.1'; // Fallback
        }

        /**
         * Validate access key
         */
        public static function validate_access_key() {
            // Enhanced nonce check with user ID
            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (!wp_verify_nonce($nonce, 'zc_dmt_dashboard_' . get_current_user_id())) {
                wp_send_json_error('Security check failed');
                return;
            }

            if (!self::security_check()) {
                return;
            }

            $access_key = sanitize_text_field($_POST['access_key'] ?? '');
            if (empty($access_key)) {
                wp_send_json_error('Access key required');
                return;
            }

            // Validate access key against stored keys
            $valid = self::is_valid_access_key($access_key);
            
            wp_send_json_success(array('valid' => $valid));
        }

        /**
         * Check if access key is valid
         */
        private static function is_valid_access_key($key) {
            if (empty($key)) {
                return false;
            }

            // Check against stored API keys
            $stored_keys = get_option('zc_dmt_api_keys', array());
            
            foreach ($stored_keys as $stored_key) {
                if (hash_equals($stored_key['key'], $key) && $stored_key['active']) {
                    return true;
                }
            }

            // Fallback: check against default key if exists
            $default_key = get_option('zc_dmt_default_api_key');
            if ($default_key && hash_equals($default_key, $key)) {
                return true;
            }

            return false;
        }

        /**
         * Get secure dashboard data for an indicator
         */
        public static function get_dashboard_data() {
            // Enhanced nonce check
            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (!wp_verify_nonce($nonce, 'zc_dmt_dashboard_' . get_current_user_id())) {
                wp_send_json_error('Security verification failed');
                return;
            }

            if (!self::security_check()) {
                return;
            }

            $slug = sanitize_title($_POST['slug'] ?? '');
            if (empty($slug)) {
                wp_send_json_error('Missing indicator slug');
                return;
            }

            // Validate access key if provided
            $access_key = sanitize_text_field($_POST['access_key'] ?? '');
            if (!empty($access_key) && !self::is_valid_access_key($access_key)) {
                wp_send_json_error('Invalid access key');
                return;
            }

            try {
                // Use secure data fetching
                $data = ZC_DMT_Indicators::get_data_by_slug($slug);

                if (is_wp_error($data)) {
                    wp_send_json_error($data->get_error_message());
                    return;
                }

                $indicator = $data['indicator'] ?? null;
                $series = $data['series'] ?? array();

                if (!$indicator) {
                    wp_send_json_error('Indicator data not available');
                    return;
                }

                if (empty($series)) {
                    wp_send_json_error('No data points available for this indicator');
                    return;
                }

                // Sanitize and format response
                $response_data = array(
                    'indicator' => array(
                        'id' => intval($indicator['id'] ?? $indicator->id ?? 0),
                        'name' => sanitize_text_field($indicator['name'] ?? $indicator->name ?? ''),
                        'slug' => sanitize_title($indicator['slug'] ?? $indicator->slug ?? $slug),
                        'description' => sanitize_text_field($indicator['description'] ?? $indicator->description ?? ''),
                        'source_type' => sanitize_text_field($indicator['source_type'] ?? $indicator->source_type ?? '')
                    ),
                    'series' => array_map(function($point) {
                        return array(
                            gmdate('Y-m-d', strtotime($point[0])), // Ensure consistent date format
                            floatval($point[1]) // Ensure numeric value
                        );
                    }, $series),
                    'count' => count($series),
                    'lastUpdate' => current_time('c')
                );

                wp_send_json_success($response_data);

            } catch (Exception $e) {
                error_log('ZC DMT Dashboard Error: ' . $e->getMessage());
                wp_send_json_error('Failed to load indicator data');
            }
        }

        /**
         * Secure search indicators
         */
        public static function search_indicators() {
            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (!wp_verify_nonce($nonce, 'zc_dmt_dashboard_' . get_current_user_id())) {
                wp_send_json_error('Security verification failed');
                return;
            }

            if (!self::security_check()) {
                return;
            }

            $query = sanitize_text_field($_POST['query'] ?? '');
            $limit = min(50, max(1, intval($_POST['limit'] ?? 20))); // Cap at 50 results
            
            if (strlen($query) < 2) {
                wp_send_json_success(array('indicators' => array()));
                return;
            }

            try {
                global $wpdb;
                $table = $wpdb->prefix . 'zc_dmt_indicators';
                
                $indicators = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name, slug, description, source_type 
                     FROM {$table} 
                     WHERE is_active = 1 
                     AND (name LIKE %s OR slug LIKE %s OR description LIKE %s)
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
                    '%' . $wpdb->esc_like($query) . '%',
                    $wpdb->esc_like($query) . '%', // Exact match priority
                    $wpdb->esc_like($query) . '%', // Exact match priority
                    $limit
                ));

                $formatted = array();
                foreach ($indicators as $indicator) {
                    $formatted[] = array(
                        'id' => intval($indicator->id),
                        'name' => sanitize_text_field($indicator->name),
                        'slug' => sanitize_title($indicator->slug),
                        'description' => sanitize_text_field($indicator->description),
                        'source_type' => sanitize_text_field($indicator->source_type)
                    );
                }

                wp_send_json_success(array(
                    'indicators' => $formatted,
                    'total' => count($formatted),
                    'query' => $query
                ));

            } catch (Exception $e) {
                error_log('ZC DMT Search Error: ' . $e->getMessage());
                wp_send_json_error('Search failed');
            }
        }

        /**
         * List all active indicators (secure)
         */
        public static function list_indicators() {
            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (!wp_verify_nonce($nonce, 'zc_dmt_dashboard_' . get_current_user_id())) {
                wp_send_json_error('Security verification failed');
                return;
            }

            if (!self::security_check()) {
                return;
            }

            $limit = min(100, max(1, intval($_POST['limit'] ?? 50))); // Cap at 100 indicators

            try {
                global $wpdb;
                $table = $wpdb->prefix . 'zc_dmt_indicators';
                
                $indicators = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name, slug, description, source_type, is_active, created_at
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
                        'description' => sanitize_text_field($indicator->description),
                        'source_type' => sanitize_text_field($indicator->source_type),
                        'is_active' => intval($indicator->is_active),
                        'created_at' => $indicator->created_at
                    );
                }

                wp_send_json_success(array(
                    'indicators' => $formatted,
                    'total' => count($formatted)
                ));

            } catch (Exception $e) {
                error_log('ZC DMT List Error: ' . $e->getMessage());
                wp_send_json_error('Failed to list indicators');
            }
        }

        /**
         * Test formula (admin only)
         */
        public static function test_formula() {
            // Admin only functionality
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (!wp_verify_nonce($nonce, 'zc_dmt_calculations')) {
                wp_send_json_error('Security verification failed');
                return;
            }

            if (!self::security_check('manage_options')) {
                return;
            }

            $formula = wp_kses_post($_POST['formula'] ?? '');
            $indicator_slugs = array();
            
            if (isset($_POST['indicators']) && is_array($_POST['indicators'])) {
                $indicator_slugs = array_map('sanitize_text_field', $_POST['indicators']);
            }

            if (empty($formula)) {
                wp_send_json_error('Formula cannot be empty');
                return;
            }

            try {
                // Check if calculations class exists
                if (!class_exists('ZC_DMT_Calculations')) {
                    wp_send_json_error('Calculations module not available');
                    return;
                }

                // Get sample data for indicators
                $data_context = array();
                foreach ($indicator_slugs as $slug) {
                    if (!empty($slug)) {
                        $indicator_data = ZC_DMT_Indicators::get_data_by_slug($slug);
                        if (!is_wp_error($indicator_data) && isset($indicator_data['series'])) {
                            // Use last 100 data points for testing
                            $series = array_slice($indicator_data['series'], -100);
                            $data_context[strtolower($slug)] = $series;
                        }
                    }
                }

                // Test the formula
                $result = ZC_DMT_Calculations::execute_formula($formula, $data_context);

                if (is_wp_error($result)) {
                    wp_send_json_error($result->get_error_message());
                    return;
                }

                wp_send_json_success($result);

            } catch (Exception $e) {
                error_log('ZC DMT Formula Test Error: ' . $e->getMessage());
                wp_send_json_error('Formula test failed');
            }
        }

        /**
         * Get calculation result securely
         */
        public static function get_calculation_result() {
            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (!wp_verify_nonce($nonce, 'zc_dmt_dashboard_' . get_current_user_id())) {
                wp_send_json_error('Security verification failed');
                return;
            }

            if (!self::security_check()) {
                return;
            }

            $slug = sanitize_title($_POST['slug'] ?? '');
            $start_date = sanitize_text_field($_POST['start_date'] ?? '');
            $end_date = sanitize_text_field($_POST['end_date'] ?? '');

            if (empty($slug)) {
                wp_send_json_error('Missing calculation slug');
                return;
            }

            // Validate date format if provided
            if (!empty($start_date) && !self::validate_date_format($start_date)) {
                wp_send_json_error('Invalid start date format');
                return;
            }

            if (!empty($end_date) && !self::validate_date_format($end_date)) {
                wp_send_json_error('Invalid end date format');
                return;
            }

            try {
                // Check if calculations class exists
                if (!class_exists('ZC_DMT_Calculations')) {
                    wp_send_json_error('Calculations module not available');
                    return;
                }

                $result = ZC_DMT_Calculations::get_calculation_result($slug, $start_date, $end_date);

                if (is_wp_error($result)) {
                    wp_send_json_error($result->get_error_message());
                    return;
                }

                wp_send_json_success($result);

            } catch (Exception $e) {
                error_log('ZC DMT Calculation Error: ' . $e->getMessage());
                wp_send_json_error('Failed to get calculation result');
            }
        }

        /**
         * Validate date format
         */
        private static function validate_date_format($date) {
            $formats = array('Y-m-d', 'Y-m-d H:i:s', 'c'); // ISO formats only
            
            foreach ($formats as $format) {
                $parsed = DateTime::createFromFormat($format, $date);
                if ($parsed && $parsed->format($format) === $date) {
                    return true;
                }
            }
            
            return false;
        }

        /**
         * Sanitize and validate indicator data
         */
        private static function sanitize_indicator_data($indicator) {
            if (!$indicator) {
                return null;
            }

            // Handle both object and array formats
            $sanitized = array(
                'id' => intval($indicator['id'] ?? $indicator->id ?? 0),
                'name' => sanitize_text_field($indicator['name'] ?? $indicator->name ?? ''),
                'slug' => sanitize_title($indicator['slug'] ?? $indicator->slug ?? ''),
                'description' => sanitize_text_field($indicator['description'] ?? $indicator->description ?? ''),
                'source_type' => sanitize_text_field($indicator['source_type'] ?? $indicator->source_type ?? '')
            );

            return $sanitized;
        }

        /**
         * Sanitize series data
         */
        private static function sanitize_series_data($series) {
            if (!is_array($series)) {
                return array();
            }

            $sanitized = array();
            foreach ($series as $point) {
                if (is_array($point) && count($point) >= 2) {
                    $date = sanitize_text_field($point[0]);
                    $value = floatval($point[1]);
                    
                    // Validate date
                    if (strtotime($date) !== false) {
                        $sanitized[] = array(
                            gmdate('Y-m-d', strtotime($date)),
                            round($value, 6) // Limit decimal precision
                        );
                    }
                }
            }

            return $sanitized;
        }

        /**
         * Log security events
         */
        private static function log_security_event($event_type, $details = array()) {
            if (class_exists('ZC_DMT_Error_Logger')) {
                ZC_DMT_Error_Logger::warning('Dashboard Security', $event_type, $details);
            } else {
                error_log("ZC DMT Security Event: {$event_type} - " . wp_json_encode($details));
            }
        }

        /**
         * Handle suspicious activity
         */
        private static function handle_suspicious_activity($reason) {
            self::log_security_event('Suspicious Activity', array(
                'reason' => $reason,
                'ip' => self::get_user_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'timestamp' => current_time('c')
            ));

            // Temporarily block IP for excessive requests
            $ip = self::get_user_ip();
            $block_key = 'zc_dmt_blocked_' . md5($ip);
            set_transient($block_key, true, 15 * MINUTE_IN_SECONDS); // 15 minute block
        }

        /**
         * Check if IP is blocked
         */
        private static function is_ip_blocked() {
            $ip = self::get_user_ip();
            $block_key = 'zc_dmt_blocked_' . md5($ip);
            return get_transient($block_key) === true;
        }
    }
}