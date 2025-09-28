<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Security')) {

    class ZC_DMT_Security {

        /**
         * Initialize security measures
         */
        public static function init() {
            // Add security headers
            add_action('init', array(__CLASS__, 'add_security_headers'));
            
            // Block suspicious requests
            add_action('init', array(__CLASS__, 'block_suspicious_requests'));
            
            // Clean up expired rate limits
            add_action('wp_scheduled_delete', array(__CLASS__, 'cleanup_expired_limits'));
        }

        /**
         * Add security headers to prevent data exposure
         */
        public static function add_security_headers() {
            if (self::is_dashboard_request()) {
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: SAMEORIGIN');
                header('X-XSS-Protection: 1; mode=block');
                header('Referrer-Policy: strict-origin-when-cross-origin');
                header('X-Robots-Tag: noindex, nofollow');
                
                // Prevent caching of sensitive data
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
            }
        }

        /**
         * Check if current request is for dashboard
         */
        private static function is_dashboard_request() {
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            return (
                strpos($request_uri, '/wp-json/zc-dmt/') !== false ||
                strpos($request_uri, 'action=zc_dmt_') !== false ||
                (isset($_POST['action']) && strpos($_POST['action'], 'zc_dmt_') === 0)
            );
        }

        /**
         * Block suspicious requests
         */
        public static function block_suspicious_requests() {
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            
            // Block known malicious user agents
            $blocked_agents = array(
                'sqlmap', 'nikto', 'havij', 'netsparker', 'acunetix',
                'masscan', 'nmap', 'w3af', 'burpsuite'
            );
            
            foreach ($blocked_agents as $agent) {
                if (stripos($user_agent, $agent) !== false) {
                    self::block_request('Malicious user agent detected');
                    return;
                }
            }
            
            // Block directory traversal attempts
            if (preg_match('/\.\.[\/\\]|\.\.%/', $request_uri)) {
                self::block_request('Directory traversal attempt');
                return;
            }
            
            // Block SQL injection patterns
            $sql_patterns = array(
                '/union.*select/i', '/select.*from/i', '/insert.*into/i',
                '/delete.*from/i', '/drop.*table/i', '/exec(\s|\+)+(s|x)p\w+/i'
            );
            
            foreach ($sql_patterns as $pattern) {
                if (preg_match($pattern, $request_uri)) {
                    self::block_request('SQL injection attempt');
                    return;
                }
            }
        }

        /**
         * Block malicious request
         */
        private static function block_request($reason) {
            self::log_security_event('Blocked Request', array(
                'reason' => $reason,
                'ip' => self::get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
                'timestamp' => current_time('c')
            ));
            
            // Block IP for 1 hour
            $ip = self::get_client_ip();
            $block_key = 'zc_dmt_security_block_' . md5($ip);
            set_transient($block_key, true, HOUR_IN_SECONDS);
            
            wp_die('Access denied', 'Security Error', array('response' => 403));
        }

        /**
         * Generate a new API key with enhanced security
         */
        public static function generate_key($name = '') {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', __('Insufficient permissions', 'zc-dmt'));
            }

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_api_keys';

            try {
                // Generate secure random key
                $raw_key = bin2hex(random_bytes(24)); // 48 character hex string
                $key = 'zc_' . $raw_key;

                // Create secure hash
                $hash = hash('sha256', $key . wp_salt('nonce'));
                $preview = substr($key, 0, 12) . '***' . substr($key, -6);

                $insert_result = $wpdb->insert($table, array(
                    'key_name' => sanitize_text_field($name ?: 'API Key ' . date('Y-m-d H:i')),
                    'key_hash' => $hash,
                    'key_preview' => $preview,
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'created_by' => get_current_user_id(),
                    'last_used' => null,
                    'usage_count' => 0,
                    'permissions' => wp_json_encode(array('read_data', 'search_indicators'))
                ));

                if ($insert_result === false) {
                    throw new Exception('Database insert failed');
                }

                // Log key generation
                self::log_security_event('API Key Generated', array(
                    'key_id' => $wpdb->insert_id,
                    'key_name' => $name,
                    'created_by' => get_current_user_id()
                ));

                return $key;
                
            } catch (Exception $e) {
                error_log('ZC DMT: Key generation failed - ' . $e->getMessage());
                return new WP_Error('key_generation_failed', 'Failed to generate API key');
            }
        }

        /**
         * Validate API key with enhanced security
         */
        public static function validate_key($key) {
            if (empty($key) || !is_string($key)) {
                return false;
            }

            // Check if key format is valid
            if (!preg_match('/^zc_[a-f0-9]{48}$/', $key)) {
                self::log_security_event('Invalid Key Format', array('key_preview' => substr($key, 0, 10) . '***'));
                return false;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_api_keys';
            $hash = hash('sha256', $key . wp_salt('nonce'));

            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, key_name, is_active, usage_count, permissions FROM {$table} WHERE key_hash = %s AND is_active = 1 LIMIT 1",
                $hash
            ));

            if (!$row) {
                self::log_security_event('Invalid Key Attempt', array(
                    'key_preview' => substr($key, 0, 10) . '***',
                    'ip' => self::get_client_ip()
                ));
                return false;
            }

            // Update usage statistics
            $wpdb->update(
                $table,
                array(
                    'last_used' => current_time('mysql'),
                    'usage_count' => intval($row->usage_count) + 1
                ),
                array('id' => $row->id),
                array('%s', '%d'),
                array('%d')
            );

            return true;
        }

        /**
         * Revoke API key
         */
        public static function revoke_key($id) {
            if (!current_user_can('manage_options')) {
                return false;
            }
            
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_api_keys';
            
            $result = $wpdb->update(
                $table,
                array('is_active' => 0, 'revoked_at' => current_time('mysql')),
                array('id' => intval($id)),
                array('%d', '%s'),
                array('%d')
            );

            if ($result !== false) {
                self::log_security_event('API Key Revoked', array(
                    'key_id' => $id,
                    'revoked_by' => get_current_user_id()
                ));
            }

            return $result !== false;
        }

        /**
         * List all API keys (admin only)
         */
        public static function list_keys() {
            if (!current_user_can('manage_options')) {
                return array();
            }
            
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_api_keys';
            
            $rows = $wpdb->get_results(
                "SELECT id, key_name, key_preview, is_active, created_at, created_by, 
                        last_used, usage_count, revoked_at 
                 FROM {$table} 
                 ORDER BY created_at DESC"
            );

            $formatted = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $creator = get_userdata($row->created_by);
                    $formatted[] = array(
                        'id' => intval($row->id),
                        'name' => $row->key_name,
                        'preview' => $row->key_preview,
                        'is_active' => intval($row->is_active),
                        'created_at' => $row->created_at,
                        'created_by' => $creator ? $creator->display_name : 'Unknown',
                        'last_used' => $row->last_used,
                        'usage_count' => intval($row->usage_count),
                        'revoked_at' => $row->revoked_at
                    );
                }
            }
            
            return $formatted;
        }

        /**
         * Get client IP address securely
         */
        public static function get_client_ip() {
            $ip_headers = array(
                'HTTP_CF_CONNECTING_IP',     // Cloudflare
                'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
                'HTTP_X_FORWARDED',          // Proxy
                'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
                'HTTP_FORWARDED_FOR',        // Proxy
                'HTTP_FORWARDED',            // Proxy
                'REMOTE_ADDR'                // Standard
            );
            
            foreach ($ip_headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $ips = explode(',', $_SERVER[$header]);
                    $ip = trim($ips[0]);
                    
                    // Validate IP and exclude private ranges for security
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
            
            return '127.0.0.1'; // Fallback
        }

        /**
         * Rate limiting with enhanced protection
         */
        public static function check_rate_limit($action = 'general', $limit = 100) {
            $ip = self::get_client_ip();
            $key = 'zc_dmt_rate_' . $action . '_' . md5($ip);
            $requests = get_transient($key) ?: 0;
            
            if ($requests >= $limit) {
                self::log_security_event('Rate Limit Exceeded', array(
                    'action' => $action,
                    'ip' => $ip,
                    'requests' => $requests,
                    'limit' => $limit
                ));
                
                // Temporary block for repeated violations
                if ($requests >= ($limit * 1.5)) {
                    $block_key = 'zc_dmt_blocked_' . md5($ip);
                    set_transient($block_key, true, HOUR_IN_SECONDS);
                }
                
                return false;
            }
            
            set_transient($key, $requests + 1, HOUR_IN_SECONDS);
            return true;
        }

        /**
         * Check if IP is temporarily blocked
         */
        public static function is_ip_blocked($ip = null) {
            if (!$ip) {
                $ip = self::get_client_ip();
            }
            
            $block_key = 'zc_dmt_blocked_' . md5($ip);
            return get_transient($block_key) === true;
        }

        /**
         * Validate and sanitize user input
         */
        public static function sanitize_input($input, $type = 'text') {
            switch ($type) {
                case 'slug':
                    return sanitize_title($input);
                    
                case 'key':
                    $sanitized = sanitize_text_field($input);
                    // Additional validation for key format
                    if (!preg_match('/^[a-zA-Z0-9_\-]{16,64}$/', $sanitized)) {
                        return '';
                    }
                    return $sanitized;
                    
                case 'date':
                    $sanitized = sanitize_text_field($input);
                    // Validate date format
                    if (!self::is_valid_date($sanitized)) {
                        return '';
                    }
                    return $sanitized;
                    
                case 'number':
                    return is_numeric($input) ? floatval($input) : 0;
                    
                case 'integer':
                    return intval($input);
                    
                case 'email':
                    return sanitize_email($input);
                    
                case 'url':
                    return esc_url_raw($input);
                    
                default:
                    return sanitize_text_field($input);
            }
        }

        /**
         * Validate date format
         */
        private static function is_valid_date($date) {
            if (empty($date)) {
                return true; // Empty is valid for optional dates
            }
            
            $formats = array('Y-m-d', 'Y-m-d H:i:s', 'c');
            
            foreach ($formats as $format) {
                $parsed = DateTime::createFromFormat($format, $date);
                if ($parsed && $parsed->format($format) === $date) {
                    return true;
                }
            }
            
            return false;
        }

        /**
         * Secure nonce generation with user context
         */
        public static function create_secure_nonce($action) {
            $user_id = get_current_user_id();
            $session_id = session_id() ?: 'no_session';
            
            return wp_create_nonce($action . '_' . $user_id . '_' . $session_id);
        }

        /**
         * Verify secure nonce with user context
         */
        public static function verify_secure_nonce($nonce, $action) {
            $user_id = get_current_user_id();
            $session_id = session_id() ?: 'no_session';
            
            return wp_verify_nonce($nonce, $action . '_' . $user_id . '_' . $session_id);
        }

        /**
         * Generate secure access key
         */
        public static function generate_key($name = '') {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', __('Insufficient permissions', 'zc-dmt'));
            }

            // Check if table exists
            if (!self::ensure_api_keys_table()) {
                return new WP_Error('database_error', __('API keys table not available', 'zc-dmt'));
            }

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_api_keys';

            try {
                // Generate cryptographically secure key
                $raw_key = bin2hex(random_bytes(24)); // 48 character hex
                $key = 'zc_' . $raw_key; // Total: 51 characters

                // Create secure hash with salt
                $salt = wp_salt('nonce');
                $hash = hash('sha256', $key . $salt);
                $preview = substr($key, 0, 12) . '***' . substr($key, -6);

                $insert_data = array(
                    'key_name' => sanitize_text_field($name ?: 'API Key ' . current_time('Y-m-d H:i')),
                    'key_hash' => $hash,
                    'key_preview' => $preview,
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'created_by' => get_current_user_id(),
                    'last_used' => null,
                    'usage_count' => 0,
                    'permissions' => wp_json_encode(array('read_data', 'search_indicators')),
                    'rate_limit' => 200 // Requests per hour
                );

                $result = $wpdb->insert($table, $insert_data);

                if ($result === false) {
                    throw new Exception('Failed to insert API key: ' . $wpdb->last_error);
                }

                // Log successful generation
                self::log_security_event('API Key Generated', array(
                    'key_id' => $wpdb->insert_id,
                    'key_name' => $name,
                    'created_by' => get_current_user_id()
                ));

                return $key;
                
            } catch (Exception $e) {
                self::log_security_event('Key Generation Failed', array(
                    'error' => $e->getMessage(),
                    'user_id' => get_current_user_id()
                ));
                
                return new WP_Error('key_generation_failed', $e->getMessage());
            }
        }

        /**
         * Enhanced key validation with usage tracking
         */
        public static function validate_key($key) {
            if (empty($key) || !is_string($key)) {
                return false;
            }

            // Check IP blocking first
            if (self::is_ip_blocked()) {
                return false;
            }

            // Validate key format
            if (!preg_match('/^zc_[a-f0-9]{48}$/', $key)) {
                self::log_security_event('Invalid Key Format', array(
                    'key_preview' => substr($key, 0, 10) . '***',
                    'ip' => self::get_client_ip()
                ));
                return false;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_api_keys';
            $salt = wp_salt('nonce');
            $hash = hash('sha256', $key . $salt);

            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, key_name, is_active, usage_count, rate_limit, permissions FROM {$table} 
                 WHERE key_hash = %s AND is_active = 1 LIMIT 1",
                $hash
            ));

            if (!$row) {
                self::log_security_event('Key Not Found', array(
                    'key_preview' => substr($key, 0, 10) . '***',
                    'ip' => self::get_client_ip()
                ));
                return false;
            }

            // Check individual key rate limit
            $key_rate_key = 'zc_dmt_key_rate_' . $row->id;
            $key_requests = get_transient($key_rate_key) ?: 0;
            $key_limit = intval($row->rate_limit) ?: 200;
            
            if ($key_requests >= $key_limit) {
                self::log_security_event('Key Rate Limit Exceeded', array(
                    'key_id' => $row->id,
                    'key_name' => $row->key_name,
                    'requests' => $key_requests,
                    'limit' => $key_limit
                ));
                return false;
            }

            // Update usage statistics
            $wpdb->update(
                $table,
                array(
                    'last_used' => current_time('mysql'),
                    'usage_count' => intval($row->usage_count) + 1
                ),
                array('id' => $row->id),
                array('%s', '%d'),
                array('%d')
            );
            
            // Update key rate limit counter
            set_transient($key_rate_key, $key_requests + 1, HOUR_IN_SECONDS);

            return true;
        }

        /**
         * Ensure API keys table exists
         */
        private static function ensure_api_keys_table() {
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_api_keys';
            
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") == $table;
            
            if (!$table_exists) {
                // Create table
                $charset_collate = $wpdb->get_charset_collate();
                
                $sql = "CREATE TABLE {$table} (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    key_name varchar(255) NOT NULL,
                    key_hash varchar(64) NOT NULL,
                    key_preview varchar(32) NOT NULL,
                    is_active tinyint(1) NOT NULL DEFAULT 1,
                    created_at datetime NOT NULL,
                    created_by bigint(20) DEFAULT NULL,
                    last_used datetime DEFAULT NULL,
                    usage_count bigint(20) DEFAULT 0,
                    permissions longtext DEFAULT NULL,
                    rate_limit int(11) DEFAULT 200,
                    revoked_at datetime DEFAULT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY key_hash (key_hash),
                    KEY is_active (is_active),
                    KEY created_by (created_by)
                ) {$charset_collate};";
                
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
                
                // Verify table was created
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") == $table;
            }
            
            return $table_exists;
        }

        /**
         * Log security events
         */
        public static function log_security_event($event_type, $details = array()) {
            $log_entry = array(
                'timestamp' => current_time('c'),
                'event_type' => $event_type,
                'ip' => self::get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'user_id' => get_current_user_id(),
                'details' => $details
            );

            // Use error logger if available
            if (class_exists('ZC_DMT_Error_Logger')) {
                ZC_DMT_Error_Logger::warning('Security', $event_type, $log_entry);
            } else {
                // Fallback to error log
                error_log('ZC DMT Security Event: ' . wp_json_encode($log_entry));
            }

            // Store in database for admin review
            self::store_security_log($log_entry);
        }

        /**
         * Store security log in database
         */
        private static function store_security_log($log_entry) {
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_security_logs';
            
            // Create table if it doesn't exist
            if (!self::ensure_security_logs_table()) {
                return;
            }
            
            $wpdb->insert($table, array(
                'event_type' => sanitize_text_field($log_entry['event_type']),
                'ip_address' => sanitize_text_field($log_entry['ip']),
                'user_agent' => sanitize_text_field($log_entry['user_agent']),
                'user_id' => intval($log_entry['user_id']),
                'details' => wp_json_encode($log_entry['details']),
                'created_at' => current_time('mysql')
            ));
        }

        /**
         * Ensure security logs table exists
         */
        private static function ensure_security_logs_table() {
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_security_logs';
            
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") == $table;
            
            if (!$table_exists) {
                $charset_collate = $wpdb->get_charset_collate();
                
                $sql = "CREATE TABLE {$table} (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    event_type varchar(100) NOT NULL,
                    ip_address varchar(45) NOT NULL,
                    user_agent text DEFAULT NULL,
                    user_id bigint(20) DEFAULT NULL,
                    details longtext DEFAULT NULL,
                    created_at datetime NOT NULL,
                    PRIMARY KEY (id),
                    KEY event_type (event_type),
                    KEY ip_address (ip_address),
                    KEY created_at (created_at)
                ) {$charset_collate};";
                
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
                
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") == $table;
            }
            
            return $table_exists;
        }

        /**
         * Clean up expired security data
         */
        public static function cleanup_expired_limits() {
            global $wpdb;
            
            // Clean up old security logs (keep 30 days)
            $security_table = $wpdb->prefix . 'zc_dmt_security_logs';
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$security_table} WHERE created_at < %s",
                date('Y-m-d H:i:s', strtotime('-30 days'))
            ));
            
            // Clean up old rate limit transients
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_zc_dmt_rate_%' 
                 AND option_value = ''"
            );
        }

        /**
         * Get security statistics (admin only)
         */
        public static function get_security_stats() {
            if (!current_user_can('manage_options')) {
                return array();
            }
            
            global $wpdb;
            $logs_table = $wpdb->prefix . 'zc_dmt_security_logs';
            $keys_table = $wpdb->prefix . 'zc_dmt_api_keys';
            
            $stats = array();
            
            // Total API keys
            $stats['total_keys'] = $wpdb->get_var("SELECT COUNT(*) FROM {$keys_table}");
            $stats['active_keys'] = $wpdb->get_var("SELECT COUNT(*) FROM {$keys_table} WHERE is_active = 1");
            
            // Security events (last 7 days)
            $stats['recent_events'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$logs_table} WHERE created_at >= %s",
                date('Y-m-d H:i:s', strtotime('-7 days'))
            ));
            
            // Failed access attempts (last 24 hours)
            $stats['failed_attempts'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$logs_table} 
                 WHERE event_type LIKE '%Invalid%' 
                 AND created_at >= %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            ));
            
            // Most active IPs (last 7 days)
            $stats['top_ips'] = $wpdb->get_results($wpdb->prepare(
                "SELECT ip_address, COUNT(*) as requests 
                 FROM {$logs_table} 
                 WHERE created_at >= %s 
                 GROUP BY ip_address 
                 ORDER BY requests DESC 
                 LIMIT 10",
                date('Y-m-d H:i:s', strtotime('-7 days'))
            ));
            
            return $stats;
        }

        /**
         * Emergency revoke all keys (admin only)
         */
        public static function emergency_revoke_all() {
            if (!current_user_can('manage_options')) {
                return false;
            }
            
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_api_keys';
            
            $result = $wpdb->update(
                $table,
                array(
                    'is_active' => 0,
                    'revoked_at' => current_time('mysql')
                ),
                array('is_active' => 1),
                array('%d', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                self::log_security_event('Emergency Revoke All', array(
                    'revoked_count' => $result,
                    'triggered_by' => get_current_user_id()
                ));
            }
            
            return $result;
        }

        /**
         * Export security logs (admin only)
         */
        public static function export_security_logs($days = 30) {
            if (!current_user_can('manage_options')) {
                return false;
            }
            
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_security_logs';
            
            $logs = $wpdb->get_results($wpdb->prepare(
                "SELECT event_type, ip_address, user_agent, user_id, details, created_at
                 FROM {$table}
                 WHERE created_at >= %s
                 ORDER BY created_at DESC",
                date('Y-m-d H:i:s', strtotime("-{$days} days"))
            ));
            
            return $logs;
        }
    }

    // Initialize security measures
    ZC_DMT_Security::init();
}