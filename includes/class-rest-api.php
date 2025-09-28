<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Rest_API')) {

    class ZC_DMT_Rest_API {

        /**
         * Register secure REST API routes
         */
        public function register_routes() {
            add_action('rest_api_init', function () {

                // POST /zc-dmt/v1/validate-key
                register_rest_route(ZC_DMT_REST_NS, '/validate-key', array(
                    array(
                        'methods'  => array('GET', 'POST'),
                        'callback' => array($this, 'validate_key'),
                        'permission_callback' => '__return_true',
                        'args' => array(
                            'access_key' => array(
                                'required' => false,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => array($this, 'validate_access_key_format'),
                            ),
                        ),
                    ),
                ));

                // GET /zc-dmt/v1/data/{slug}?access_key=KEY&start=&end=
                register_rest_route(ZC_DMT_REST_NS, '/data/(?P<slug>[\w\-]+)', array(
                    array(
                        'methods'  => 'GET',
                        'callback' => array($this, 'get_data_by_slug'),
                        'permission_callback' => '__return_true',
                        'args' => array(
                            'slug' => array(
                                'required' => true,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_title',
                                'validate_callback' => array($this, 'validate_slug_format'),
                            ),
                            'access_key' => array(
                                'required' => true,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => array($this, 'validate_access_key_format'),
                            ),
                            'start' => array(
                                'required' => false,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => array($this, 'validate_date_format'),
                            ),
                            'end' => array(
                                'required' => false,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => array($this, 'validate_date_format'),
                            ),
                        ),
                    ),
                ));

                // GET /zc-dmt/v1/search?q=QUERY&access_key=KEY&limit=20
                register_rest_route(ZC_DMT_REST_NS, '/search', array(
                    array(
                        'methods'  => 'GET',
                        'callback' => array($this, 'search_indicators'),
                        'permission_callback' => '__return_true',
                        'args' => array(
                            'q' => array(
                                'required' => true,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => array($this, 'validate_search_query'),
                            ),
                            'access_key' => array(
                                'required' => true,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => array($this, 'validate_access_key_format'),
                            ),
                            'limit' => array(
                                'required' => false,
                                'type' => 'integer',
                                'default' => 20,
                                'minimum' => 1,
                                'maximum' => 100,
                            ),
                        ),
                    ),
                ));

                // GET /zc-dmt/v1/indicators?access_key=KEY
                register_rest_route(ZC_DMT_REST_NS, '/indicators', array(
                    array(
                        'methods'  => 'GET',
                        'callback' => array($this, 'list_indicators'),
                        'permission_callback' => '__return_true',
                        'args' => array(
                            'access_key' => array(
                                'required' => true,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => array($this, 'validate_access_key_format'),
                            ),
                            'limit' => array(
                                'required' => false,
                                'type' => 'integer',
                                'default' => 50,
                                'minimum' => 1,
                                'maximum' => 200,
                            ),
                        ),
                    ),
                ));

                // GET /zc-dmt/v1/backup/{slug}?access_key=KEY (placeholder for future)
                register_rest_route(ZC_DMT_REST_NS, '/backup/(?P<slug>[\w\-]+)', array(
                    array(
                        'methods'  => 'GET',
                        'callback' => array($this, 'get_backup_by_slug'),
                        'permission_callback' => '__return_true',
                        'args' => array(
                            'slug' => array(
                                'required' => true,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_title',
                                'validate_callback' => array($this, 'validate_slug_format'),
                            ),
                            'access_key' => array(
                                'required' => true,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => array($this, 'validate_access_key_format'),
                            ),
                        ),
                    ),
                ));
            });
        }

        /**
         * Validate access key format
         */
        public function validate_access_key_format($param, $request, $key) {
            if (empty($param)) {
                return false;
            }
            
            // Access key should be alphanumeric with dashes/underscores, 16-64 characters
            if (!preg_match('/^[a-zA-Z0-9_\-]{16,64}$/', $param)) {
                return false;
            }
            
            return true;
        }

        /**
         * Validate slug format
         */
        public function validate_slug_format($param, $request, $key) {
            if (empty($param)) {
                return false;
            }
            
            // Slug should be lowercase alphanumeric with dashes/underscores, max 100 chars
            if (!preg_match('/^[a-z0-9_\-]{1,100}$/', $param)) {
                return false;
            }
            
            return true;
        }

        /**
         * Validate date format
         */
        public function validate_date_format($param, $request, $key) {
            if (empty($param)) {
                return true; // Optional parameter
            }
            
            // Accept Y-m-d or Y-m-d H:i:s formats
            $formats = array('Y-m-d', 'Y-m-d H:i:s');
            
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $param);
                if ($date && $date->format($format) === $param) {
                    return true;
                }
            }
            
            return false;
        }

        /**
         * Validate search query
         */
        public function validate_search_query($param, $request, $key) {
            if (empty($param)) {
                return false;
            }
            
            // Query should be 2-100 characters, no script tags
            if (strlen($param) < 2 || strlen($param) > 100) {
                return false;
            }
            
            // Prevent script injection
            if (preg_match('/<script|javascript:|data:/i', $param)) {
                return false;
            }
            
            return true;
        }

        /**
         * Enhanced rate limiting and security
         */
        private function check_security($access_key) {
            // Check rate limiting
            if (!$this->check_rate_limit()) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Rate limit exceeded'
                ), 429);
            }

            // Validate access key
            if (!ZC_DMT_Security::validate_key($access_key)) {
                // Log failed attempt
                $this->log_failed_access($access_key);
                
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Invalid or expired access key'
                ), 403);
            }

            return true;
        }

        /**
         * Rate limiting implementation
         */
        private function check_rate_limit() {
            $ip = $this->get_client_ip();
            $key = 'zc_dmt_api_rate_' . md5($ip);
            $requests = get_transient($key) ?: 0;
            
            // Allow 200 requests per hour per IP for REST API
            if ($requests >= 200) {
                return false;
            }
            
            set_transient($key, $requests + 1, HOUR_IN_SECONDS);
            return true;
        }

        /**
         * Get client IP address
         */
        private function get_client_ip() {
            $ip_headers = array(
                'HTTP_CF_CONNECTING_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'REMOTE_ADDR'
            );
            
            foreach ($ip_headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $ip = trim(explode(',', $_SERVER[$header])[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
            
            return '127.0.0.1';
        }

        /**
         * Log failed access attempts
         */
        private function log_failed_access($access_key) {
            $log_data = array(
                'ip' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'access_key' => substr($access_key, 0, 8) . '***', // Partial key for security
                'timestamp' => current_time('c'),
                'endpoint' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
            );

            if (class_exists('ZC_DMT_Error_Logger')) {
                ZC_DMT_Error_Logger::warning('REST API Security', 'Invalid access key attempt', $log_data);
            } else {
                error_log('ZC DMT: Invalid access key attempt - ' . wp_json_encode($log_data));
            }
        }

        /**
         * POST /validate-key with enhanced security
         */
        public function validate_key($request) {
            // Get access key from various sources
            $key = $this->extract_access_key($request);
            $key = sanitize_text_field($key);
            
            if (empty($key)) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Access key is required',
                    'valid' => false
                ), 400);
            }

            // Check basic rate limiting first
            if (!$this->check_rate_limit()) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Too many requests',
                    'valid' => false
                ), 429);
            }

            $valid = ZC_DMT_Security::validate_key($key);
            
            if (!$valid) {
                $this->log_failed_access($key);
            }

            return rest_ensure_response(array(
                'status' => $valid ? 'success' : 'error',
                'valid' => (bool) $valid,
                'message' => $valid ? 'Access key is valid' : 'Invalid access key'
            ));
        }

        /**
         * Extract access key from request
         */
        private function extract_access_key($request) {
            // Try query parameter first
            $key = $request->get_param('access_key');
            
            if (empty($key)) {
                // Try JSON body
                $json = $request->get_json_params();
                if (is_array($json) && isset($json['access_key'])) {
                    $key = $json['access_key'];
                }
            }

            if (empty($key)) {
                // Try form body
                $body = $request->get_body_params();
                if (is_array($body) && isset($body['access_key'])) {
                    $key = $body['access_key'];
                }
            }

            if (empty($key)) {
                // Try header
                $auth_header = $request->get_header('Authorization');
                if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
                    $key = substr($auth_header, 7);
                }
            }

            return $key;
        }

        /**
         * GET /data/{slug}?access_key=KEY&start=&end= (secured)
         */
        public function get_data_by_slug($request) {
            $access_key = sanitize_text_field($request->get_param('access_key'));
            $security_check = $this->check_security($access_key);
            
            if ($security_check !== true) {
                return $security_check; // Return error response
            }

            $slug = sanitize_title($request['slug']);
            $start = $request->get_param('start') ? sanitize_text_field($request->get_param('start')) : null;
            $end = $request->get_param('end') ? sanitize_text_field($request->get_param('end')) : null;

            // Validate date parameters if provided
            if ($start && !$this->is_valid_date($start)) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Invalid start date format. Use Y-m-d or Y-m-d H:i:s'
                ), 400);
            }

            if ($end && !$this->is_valid_date($end)) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Invalid end date format. Use Y-m-d or Y-m-d H:i:s'
                ), 400);
            }

            try {
                $result = ZC_DMT_Indicators::get_data_by_slug($slug, $start, $end);
                
                if (is_wp_error($result)) {
                    return new WP_REST_Response(array(
                        'status' => 'error',
                        'message' => $result->get_error_message()
                    ), 404);
                }

                // Sanitize response data
                $sanitized_result = $this->sanitize_indicator_response($result);

                return rest_ensure_response(array(
                    'status' => 'success',
                    'data' => $sanitized_result
                ));

            } catch (Exception $e) {
                error_log('ZC DMT REST API Error: ' . $e->getMessage());
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Failed to retrieve indicator data'
                ), 500);
            }
        }

        /**
         * Search indicators securely
         */
        public function search_indicators($request) {
            $access_key = sanitize_text_field($request->get_param('access_key'));
            $security_check = $this->check_security($access_key);
            
            if ($security_check !== true) {
                return $security_check;
            }

            $query = sanitize_text_field($request->get_param('q'));
            $limit = min(100, max(1, intval($request->get_param('limit')))) ?: 20;

            if (strlen($query) < 2) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Query must be at least 2 characters'
                ), 400);
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
                        // Intentionally exclude description and other sensitive data
                    );
                }

                return rest_ensure_response(array(
                    'status' => 'success',
                    'indicators' => $formatted,
                    'total' => count($formatted),
                    'query' => $query
                ));

            } catch (Exception $e) {
                error_log('ZC DMT Search Error: ' . $e->getMessage());
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Search failed'
                ), 500);
            }
        }

        /**
         * List indicators securely
         */
        public function list_indicators($request) {
            $access_key = sanitize_text_field($request->get_param('access_key'));
            $security_check = $this->check_security($access_key);
            
            if ($security_check !== true) {
                return $security_check;
            }

            $limit = min(200, max(1, intval($request->get_param('limit')))) ?: 50;

            try {
                global $wpdb;
                $table = $wpdb->prefix . 'zc_dmt_indicators';
                
                $indicators = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name, slug, source_type, is_active
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
                        'source_type' => sanitize_text_field($indicator->source_type)
                        // Exclude sensitive configuration data
                    );
                }

                return rest_ensure_response(array(
                    'status' => 'success',
                    'indicators' => $formatted,
                    'total' => count($formatted)
                ));

            } catch (Exception $e) {
                error_log('ZC DMT List Error: ' . $e->getMessage());
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Failed to list indicators'
                ), 500);
            }
        }

        /**
         * GET /backup/{slug}?access_key=KEY (secure placeholder)
         */
        public function get_backup_by_slug($request) {
            $access_key = sanitize_text_field($request->get_param('access_key'));
            $security_check = $this->check_security($access_key);
            
            if ($security_check !== true) {
                return $security_check;
            }

            $slug = sanitize_title($request['slug']);

            try {
                // For now, fallback to live data
                // In future, this will check Google Drive backups first
                $result = ZC_DMT_Indicators::get_data_by_slug($slug, null, null);
                
                if (is_wp_error($result)) {
                    return new WP_REST_Response(array(
                        'status' => 'success', // Don't expose 404s for backup endpoint
                        'data' => array(
                            'indicator' => array(
                                'name' => $slug,
                                'slug' => $slug,
                            ),
                            'series' => array(),
                            'note' => 'No backup data available',
                        ),
                    ));
                }

                $sanitized_result = $this->sanitize_indicator_response($result);

                return rest_ensure_response(array(
                    'status' => 'success',
                    'data' => $sanitized_result,
                    'source' => 'live_fallback'
                ));

            } catch (Exception $e) {
                error_log('ZC DMT Backup Error: ' . $e->getMessage());
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Backup service temporarily unavailable'
                ), 503);
            }
        }

        /**
         * Sanitize indicator response data
         */
        private function sanitize_indicator_response($result) {
            if (!is_array($result)) {
                return array();
            }

            $indicator = $result['indicator'] ?? null;
            $series = $result['series'] ?? array();

            $sanitized = array(
                'indicator' => array(
                    'id' => intval($indicator['id'] ?? $indicator->id ?? 0),
                    'name' => sanitize_text_field($indicator['name'] ?? $indicator->name ?? ''),
                    'slug' => sanitize_title($indicator['slug'] ?? $indicator->slug ?? ''),
                    'source_type' => sanitize_text_field($indicator['source_type'] ?? $indicator->source_type ?? '')
                    // Exclude sensitive fields like source_config, api_keys, etc.
                ),
                'series' => array(),
                'meta' => array(
                    'count' => 0,
                    'start_date' => null,
                    'end_date' => null
                )
            );

            // Sanitize series data
            if (is_array($series)) {
                foreach ($series as $point) {
                    if (is_array($point) && count($point) >= 2) {
                        $date = $point[0];
                        $value = $point[1];
                        
                        // Validate date and value
                        if (strtotime($date) !== false && is_numeric($value)) {
                            $sanitized_point = array(
                                gmdate('Y-m-d', strtotime($date)),
                                round(floatval($value), 6)
                            );
                            $sanitized['series'][] = $sanitized_point;
                        }
                    }
                }
            }

            // Update meta information
            $sanitized['meta']['count'] = count($sanitized['series']);
            if (!empty($sanitized['series'])) {
                $sanitized['meta']['start_date'] = $sanitized['series'][0][0];
                $sanitized['meta']['end_date'] = end($sanitized['series'])[0];
            }

            return $sanitized;
        }

        /**
         * Validate date format
         */
        private function is_valid_date($date) {
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
         * Add security headers
         */
        public function add_security_headers() {
            add_action('rest_api_init', function() {
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: DENY');
                header('X-XSS-Protection: 1; mode=block');
                header('Referrer-Policy: strict-origin-when-cross-origin');
            });
        }
    }
}