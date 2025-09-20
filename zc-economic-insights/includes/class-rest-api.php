<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Rest_API')) {

    class ZC_DMT_Rest_API {

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
                            ),
                            'access_key' => array(
                                'required' => true,
                            ),
                            'start' => array(
                                'required' => false,
                            ),
                            'end' => array(
                                'required' => false,
                            ),
                        ),
                    ),
                ));

                // GET /zc-dmt/v1/indicators?access_key=KEY
                register_rest_route(ZC_DMT_REST_NS, '/indicators', array(
                    array(
                        'methods'  => 'GET',
                        'callback' => function ($request) {
                            $access_key = sanitize_text_field($request->get_param('access_key'));
                            if (!ZC_DMT_Security::validate_key($access_key)) {
                                return new WP_REST_Response(array(
                                    'status'  => 'error',
                                    'message' => __('Unauthorized access. Please provide a valid API key.', 'zc-dmt'),
                                ), 403);
                            }

                            $rows = ZC_DMT_Indicators::list_indicators(500, 0);
                            $list = array();
                            if (is_array($rows)) {
                                foreach ($rows as $ind) {
                                    $list[] = array(
                                        'id'   => (int) $ind->id,
                                        'name' => $ind->name,
                                        'slug' => $ind->slug,
                                    );
                                }
                            }

                            return rest_ensure_response(array(
                                'status' => 'success',
                                'data'   => $list,
                            ));
                        },
                        'permission_callback' => '__return_true',
                        'args' => array(
                            'access_key' => array(
                                'required' => true,
                            ),
                        ),
                    ),
                ));

                // GET /zc-dmt/v1/backup/{slug}?access_key=KEY
                // Stub for now, returns same as live data or empty if none found.
                register_rest_route(ZC_DMT_REST_NS, '/backup/(?P<slug>[\w\-]+)', array(
                    array(
                        'methods'  => 'GET',
                        'callback' => array($this, 'get_backup_by_slug'),
                        'permission_callback' => '__return_true',
                        'args' => array(
                            'slug' => array(
                                'required' => true,
                            ),
                            'access_key' => array(
                                'required' => true,
                            ),
                        ),
                    ),
                ));
            });
        }

        /**
         * POST /validate-key
         */
        public function validate_key($request) {
            // Accept access_key from query string, JSON body, form body, or $_POST
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

            if (empty($key) && isset($_POST['access_key'])) {
                $key = $_POST['access_key'];
            }

            $key = sanitize_text_field((string) $key);
            $valid = ZC_DMT_Security::validate_key($key);

            return rest_ensure_response(array(
                'valid' => (bool) $valid,
            ));
        }

        /**
         * GET /data/{slug}?access_key=KEY&start=&end=
         */
        public function get_data_by_slug($request) {
            $access_key = sanitize_text_field($request->get_param('access_key'));
            if (!ZC_DMT_Security::validate_key($access_key)) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => __('Unauthorized access. Please provide a valid API key.', 'zc-dmt'),
                ), 403);
            }

            $slug  = sanitize_title($request['slug']);
            $start = $request->get_param('start');
            $end   = $request->get_param('end');

            $result = ZC_DMT_Indicators::get_data_by_slug($slug, $start, $end);
            if (is_wp_error($result)) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => $result->get_error_message(),
                ), 404);
            }

            return rest_ensure_response(array(
                'status' => 'success',
                'data'   => $result,
            ));
        }

        /**
         * GET /backup/{slug}?access_key=KEY
         * Stubbed placeholder to enable Charts fallback during vertical slice testing.
         */
        public function get_backup_by_slug($request) {
            $access_key = sanitize_text_field($request->get_param('access_key'));
            if (!ZC_DMT_Security::validate_key($access_key)) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => __('Unauthorized access. Please provide a valid API key.', 'zc-dmt'),
                ), 403);
            }

            $slug  = sanitize_title($request['slug']);

            // Try live data first (as a placeholder for actual backup retrieval).
            $result = ZC_DMT_Indicators::get_data_by_slug($slug, null, null);
            if (is_wp_error($result)) {
                // Return empty with explicit notice for now.
                return rest_ensure_response(array(
                    'status' => 'success',
                    'data'   => array(
                        'indicator' => array(
                            'name' => $slug,
                            'slug' => $slug,
                        ),
                        'series' => array(),
                        'note' => 'Backup stub: no data available',
                    ),
                ));
            }

            return rest_ensure_response(array(
                'status' => 'success',
                'data'   => $result,
                'note'   => 'Backup stub: mirroring live data until Drive is implemented',
            ));
        }
    }
}
