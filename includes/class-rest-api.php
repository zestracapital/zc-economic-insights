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

                // Security headers for all API endpoints
                add_action('send_headers', array($this, 'add_security_headers'));

                // POST/GET /validate-key - Enhanced security
                register_rest_route(ZC_DMT_REST_NS, '/validate-key', array(
                    array(
                        'methods'  => array('GET', 'POST'),
                        'callback' => array($this, 'validate_key'),
                        'permission_callback' => array($this, 'public_permission_callback'),
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

                // GET /data/{slug} - Enhanced with security checks
                register_rest_route(ZC_DMT_REST_NS, '/data/(?P<slug>[\w\-]+)', array(
                    array(
                        'methods'  => 'GET',
                        'callback' => array($this, 'get_data_by_slug'),
                        'permission_callback' => array($this, 'data_permission_callback'),
                        'args' => array(
                            'slug' => array(
                                'required' => true,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_title',
                                'validate_callback' => array($this, 'validate_slug_format'),
                            ),
                            'access_key' => array(
                                'required' => false, // Make optional for internal requests
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

                // GET /search - Enhanced security
                register_rest_route(ZC_DMT_REST_NS, '/search', array(
                    array(
                        'methods'  => 'GET',
                        'callback' => array($this, 'search_indicators'),
                        'permission_callback' => array($this, 'search_permission_callback'),
                        'args' => array(
                            'q' => array(
                                'required' => true,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => array($this, 'validate_search_query'),
                            ),
                            'access_key' => array(
                                'required' => false,
                                'type' => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => array($this, 'validate_access_key_format'),
                            ),
                            'limit' => array(
                                'required' => false,
                                'type' => 'integer',
                                'default' => 20,
                                'minimum' => 1,
                                'maximum' => 50, // Reduced max limit for security
                            ),
                        ),
                    ),
                ));

                // GET /indicators - Restricted access
                register_rest_route(ZC_DMT_REST_NS, '/indicators', array(
                    array(
                        'methods'  => 'GET',
                        'callback' => array($this, 'list_indicators'),
                        'permission_callback' => array($this, 'admin_permission_callback'),
                        'args' => array(
                            'limit' => array(
                                'required' => false,
                                'type' => 'integer',
                                'default' => 50,
                                'minimum' => 1,
                                'maximum' => 100, // Reduced for security
                            ),
                        ),
                    ),
                ));

                // Admin-only endpoint for getting full indicator details
                register_rest_route(ZC_DMT_REST_NS, '/indicators/(?P<id>\\d+)', array(
                    array(
                        'methods'  => 'GET',
                        'callback' => array($this, 'get_indicator_details'),
                        'permission_callback' => array($this, 'admin_permission_callback'),
                        'args' => array(
                            'id' => array(
                                'required' => true,
                                'type' => 'integer',
                                'sanitize_callback' => 'absint',
                                'validate_callback' => array($this, 'validate_positive_integer'),
                            ),
                        ),
                    ),
                ));
            });
        }

        /**
         * Enhanced permission callbacks with security
         */
        public function public_permission_callback($request) {
            // Basic rate limiting for public endpoints
            return $this->check_rate_limit('public', 100);
        }

        public function data_permission_callback($request) {
            if (!$this->check_rate_limit('data', 150)) {
                return new WP_Error('rate_limit', 'Rate limit exceeded', array('status' => 429));
            }

            // Check if this is an internal request (from same domain)
            if ($this->is_internal_request()) {
                return true;
            }

            // For external requests, check access key requirement
            $require_key = get_option('zc_dmt_require_api_key', false);
            $access_key = $request->get_param('access_key');

            if ($require_key && empty($access_key)) {
                return new WP_Error('access_key_required', 'Access key required for external requests', array('status' => 401));
            }

            if (!empty($access_key)) {
                if (!ZC_DMT_Security::validate_key($access_key)) {
                    return new WP_Error('invalid_access_key', 'Invalid access key', array('status' => 403));
                }
            }

            return true;
        }

        public function search_permission_callback($request) {
            if (!$this->check_rate_limit('search', 50)) {
                return new WP_Error('rate_limit', 'Search rate limit exceeded', array('status' => 429));
            }

            // More restrictive for search
            $access_key = $request->get_param('access_key');
            if (!$this->is_internal_request() && empty($access_key)) {
                return new WP_Error('access_key_required', 'Access key required for search', array('status' => 401));
            }

            if (!empty($access_key) && !ZC_DMT_Security::validate_key($access_key)) {
                return new WP_Error('invalid_access_key', 'Invalid access key', array('status' => 403));
            }

            return true;
        }

        public function admin_permission_callback($request) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('insufficient_permissions', 'Admin access required', array('status' => 403));
            }

            return $this->check_rate_limit('admin', 200);
        }

        /**
         * Check if request is from internal source
         */
        private function is_internal_request() {
            $referer = wp_get_referer();
            $home_url = home_url();
            
            // Check if referer is from same domain
            if ($referer && strpos($referer, $home_url) === 0) {
                return true;
            }

            // Check if request has valid WordPress nonce for internal requests
            $nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? $_GET['_wpnonce'] ?? $_POST['_wpnonce'] ?? '';
            if (!empty($nonce) && wp_verify_nonce($nonce, 'wp_rest')) {
                return true;
            }

            return false;
        }

        /**
         * Enhanced rate limiting
         */
        private function check_rate_limit($action, $limit = 100) {
            return ZC_DMT_Security::check_rate_limit($action, $limit);
        }

        /**
         * Validation callbacks
         */
        public function validate_access_key_format($param, $request, $key) {
            if (empty($param)) {
                return true; // Optional parameter
            }
            return preg_match('/^[a-zA-Z0-9_\-]{16,64}$/', $param);
        }

        public function validate_slug_format($param, $request, $key) {
            if (empty($param)) {
                return false;
            }
            return preg_match('/^[a-z0-9_\-]{1,100}$/', strtolower($param));
        }

        public function validate_date_format($param, $request, $key) {
            if (empty($param)) {
                return true;
            }
            $formats = array('Y-m-d', 'Y-m-d H:i:s', 'c');
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $param);
                if ($date && $date->format($format) === $param) {
                    return true;
                }
            }
            return false;
        }

        public function validate_search_query($param, $request, $key) {
            if (empty($param)) {
                return false;
            }
            if (strlen($param) < 2 || strlen($param) > 100) {
                return false;
            }
            // Prevent script injection
            return !preg_match('/<script|javascript:|data:|vbscript:/i', $param);
        }

        public function validate_positive_integer($param, $request, $key) {
            return is_numeric($param) && intval($param) > 0;
        }

        /**
         * API Endpoints with enhanced security
         */
        public function validate_key($request) {
            $key = $this->extract_access_key($request);
            $key = sanitize_text_field($key);
            
            if (empty($key)) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Access key is required',
                    'valid' => false
                ), 400);
            }

            $valid = ZC_DMT_Security::validate_key($key);
            
            if (!$valid) {
                $this->log_security_event('Invalid key validation attempt', array(
                    'key_preview' => substr($key, 0, 8) . '***'
                ));
            }

            return rest_ensure_response(array(
                'status' => $valid ? 'success' : 'error',
                'valid' => (bool) $valid,
                'message' => $valid ? 'Valid access key' : 'Invalid access key'
            ));
        }

        public function get_data_by_slug($request) {
            try {
                $slug = sanitize_title($request['slug']);
                $start = $request->get_param('start') ? sanitize_text_field($request->get_param('start')) : null;
                $end = $request->get_param('end') ? sanitize_text_field($request->get_param('end')) : null;

                // Use secure data fetching method
                $result = ZC_DMT_Indicators::get_data_by_slug($slug, $start, $end);
                
                if (is_wp_error($result)) {
                    return new WP_REST_Response(array(
                        'status' => 'error',
                        'message' => 'Data not available'
                    ), 404);
                }

                // Sanitize and limit response data
                $sanitized_result = $this->sanitize_indicator_response($result);

                $response = array(
                    'status' => 'success',
                    'data' => $sanitized_result
                );

                // Add security headers
                $wp_response = rest_ensure_response($response);
                $wp_response->header('Cache-Control', 'private, max-age=300'); // 5 minutes
                $wp_response->header('X-Content-Type-Options', 'nosniff');
                
                return $wp_response;

            } catch (Exception $e) {
                error_log('ZC DMT REST API Error: ' . $e->getMessage());
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Service temporarily unavailable'
                ), 503);
            }
        }

        public function search_indicators($request) {
            try {
                $query = sanitize_text_field($request->get_param('q'));
                $limit = min(50, max(1, intval($request->get_param('limit')))) ?: 20;

                if (strlen($query) < 2) {
                    return rest_ensure_response(array(
                        'status' => 'success',
                        'indicators' => array(),
                        'total' => 0
                    ));
                }

                global $wpdb;
                $table = $wpdb->prefix . 'zc_dmt_indicators';
                
                $indicators = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name, slug, source_type 
                     FROM {$table} 
                     WHERE is_active = 1 
                     AND (name LIKE %s OR slug LIKE %s)
                     ORDER BY 
                         CASE WHEN name LIKE %s THEN 1 ELSE 2 END,
                         name ASC 
                     LIMIT %d",
                    '%' . $wpdb->esc_like($query) . '%',
                    '%' . $wpdb->esc_like($query) . '%',
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
                    );
                }

                return rest_ensure_response(array(
                    'status' => 'success',
                    'indicators' => $formatted,
                    'total' => count($formatted)
                ));

            } catch (Exception $e) {
                error_log('ZC DMT Search Error: ' . $e->getMessage());
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Search failed'
                ), 500);
            }
        }

        public function list_indicators($request) {
            try {
                $limit = min(100, max(1, intval($request->get_param('limit')))) ?: 50;

                global $wpdb;
                $table = $wpdb->prefix . 'zc_dmt_indicators';
                
                $indicators = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name, slug, source_type, is_active, created_at
                     FROM {$table}
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
                        'created_at' => $indicator->created_at
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

        public function get_indicator_details($request) {
            try {
                $id = absint($request->get_param('id'));

                global $wpdb;
                $table = $wpdb->prefix . 'zc_dmt_indicators';

                $indicator = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE id = %d",
                    $id
                ));

                if (!$indicator) {
                    return new WP_REST_Response(array(
                        'status' => 'error',
                        'message' => 'Indicator not found'
                    ), 404);
                }

                $formatted = array(
                    'id' => intval($indicator->id),
                    'name' => sanitize_text_field($indicator->name),
                    'slug' => sanitize_title($indicator->slug),
                    'description' => sanitize_text_field($indicator->description),
                    'source_type' => sanitize_text_field($indicator->source_type),
                    'is_active' => intval($indicator->is_active),
                    'created_at' => $indicator->created_at,
                    'updated_at' => $indicator->updated_at
                    // Exclude sensitive source_config for security
                );

                return rest_ensure_response(array(
                    'status' => 'success',
                    'indicator' => $formatted
                ));

            } catch (Exception $e) {
                error_log('ZC DMT Get Details Error: ' . $e->getMessage());
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Failed to get indicator details'
                ), 500);
            }
        }

        /**
         * Helper methods
         */
        private function extract_access_key($request) {
            // Try multiple sources for access key
            $key = $request->get_param('access_key');
            
            if (empty($key)) {
                $json = $request->get_json_params();
                if (is_array($json) && isset($json['access_key'])) {
                    $key = $json['access_key'];
                }
            }

            if (empty($key)) {
                $body = $request->get_body_params();
                if (is_array($body) && isset($body['access_key'])) {
                    $key = $body['access_key'];
                }
            }

            if (empty($key)) {
                $auth_header = $request->get_header('Authorization');
                if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
                    $key = substr($auth_header, 7);
                }
            }

            return $key;
        }

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
                ),
                'series' => array(),
                'meta' => array(
                    'count' => 0,
                    'generated_at' => current_time('c')
                )
            );

            // Sanitize series data with limits
            if (is_array($series)) {
                $count = 0;
                foreach ($series as $point) {
                    if ($count >= 10000) break; // Limit data points for security
                    
                    if (is_array($point) && count($point) >= 2) {
                        $date = $point[0];
                        $value = $point[1];
                        
                        if (strtotime($date) !== false && is_numeric($value)) {
                            $sanitized['series'][] = array(
                                gmdate('Y-m-d', strtotime($date)),
                                round(floatval($value), 6)
                            );
                            $count++;
                        }
                    }
                }
            }

            $sanitized['meta']['count'] = count($sanitized['series']);
            return $sanitized;
        }

        private function log_security_event($event, $details = array()) {
            $log_data = array_merge($details, array(
                'ip' => ZC_DMT_Security::get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'timestamp' => current_time('c'),
                'endpoint' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
            ));

            ZC_DMT_Security::log_security_event($event, $log_data);
        }

        /**
         * Add comprehensive security headers
         */
        public function add_security_headers() {
            if (strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-json/zc-dmt/') !== false) {
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: DENY');
                header('X-XSS-Protection: 1; mode=block');
                header('Referrer-Policy: strict-origin-when-cross-origin');
                header('X-Robots-Tag: noindex, nofollow'); // Prevent search engine indexing
                header('Cache-Control: private, no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
            }
        }
    }
}