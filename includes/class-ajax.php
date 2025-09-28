<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Ajax')) {

    class ZC_DMT_Ajax {

        public static function register() {
            // Secure, nonce-based AJAX (works for logged-in and public)
            add_action('wp_ajax_zc_dmt_get_data', array(__CLASS__, 'get_data'));
            add_action('wp_ajax_nopriv_zc_dmt_get_data', array(__CLASS__, 'get_data'));
 
            add_action('wp_ajax_zc_dmt_get_backup', array(__CLASS__, 'get_backup'));
            add_action('wp_ajax_nopriv_zc_dmt_get_backup', array(__CLASS__, 'get_backup'));
 
            // Admin-only: list indicators for Shortcode Builder
            add_action('wp_ajax_zc_dmt_list_indicators', array(__CLASS__, 'list_indicators'));
        }

        /**
         * AJAX handler: get live data for a slug
         * Expects: POST nonce, slug, optional start, end (YYYY-MM-DD)
         * Returns: { status: 'success', data: { indicator, series[] } } or { status: 'error', message }
         */
        public static function get_data() {
            // Security: nonce required
            $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : '';
            if (!wp_verify_nonce($nonce, 'zc_dmt_chart')) {
                self::json_respond(array(
                    'status'  => 'error',
                    'message' => __('Unauthorized request (invalid nonce).', 'zc-dmt'),
                ), 403);
            }

            $slug  = isset($_REQUEST['slug']) ? sanitize_title(wp_unslash($_REQUEST['slug'])) : '';
            $start = isset($_REQUEST['start']) ? sanitize_text_field(wp_unslash($_REQUEST['start'])) : null;
            $end   = isset($_REQUEST['end']) ? sanitize_text_field(wp_unslash($_REQUEST['end'])) : null;

            if (empty($slug)) {
                self::json_respond(array(
                    'status'  => 'error',
                    'message' => __('Missing slug', 'zc-dmt'),
                ), 400);
            }

            $result = ZC_DMT_Indicators::get_data_by_slug($slug, $start, $end);
            if (is_wp_error($result)) {
                self::json_respond(array(
                    'status'  => 'error',
                    'message' => $result->get_error_message(),
                ), 404);
            }

            self::json_respond(array(
                'status' => 'success',
                'data'   => $result,
            ));
        }

        /**
         * AJAX handler: get backup data (stub mirrors live until Drive integration exists)
         */
        public static function get_backup() {
            $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : '';
            if (!wp_verify_nonce($nonce, 'zc_dmt_chart')) {
                self::json_respond(array(
                    'status'  => 'error',
                    'message' => __('Unauthorized request (invalid nonce).', 'zc-dmt'),
                ), 403);
            }

            $slug  = isset($_REQUEST['slug']) ? sanitize_title(wp_unslash($_REQUEST['slug'])) : '';
            if (empty($slug)) {
                self::json_respond(array(
                    'status'  => 'error',
                    'message' => __('Missing slug', 'zc-dmt'),
                ), 400);
            }

            $result = ZC_DMT_Indicators::get_data_by_slug($slug, null, null);
            if (is_wp_error($result)) {
                self::json_respond(array(
                    'status' => 'success',
                    'data'   => array(
                        'indicator' => array(
                            'name' => $slug,
                            'slug' => $slug,
                        ),
                        'series' => array(),
                        'note'   => 'Backup stub: no data available',
                    ),
                ));
            }

            self::json_respond(array(
                'status' => 'success',
                'data'   => $result,
                'note'   => 'Backup stub: mirroring live data until Drive is implemented',
            ));
        }

        /**
         * Admin-only listing of indicators for the Shortcode Builder.
         * Expects: POST nonce
         * Returns: { status: 'success', data: [ { id, name, slug } ] }
         */
        public static function list_indicators() {
            // Must be admin or valid nonce within wp-admin
            $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : '';
            if (!current_user_can('manage_options') && !wp_verify_nonce($nonce, 'zc_dmt_chart')) {
                self::json_respond(array(
                    'status'  => 'error',
                    'message' => __('Unauthorized request', 'zc-dmt'),
                ), 403);
            }
            $rows = ZC_DMT_Indicators::list_indicators(500, 0);
            $out = array();
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $out[] = array(
                        'id'   => (int) $r->id,
                        'name' => (string) $r->name,
                        'slug' => (string) $r->slug,
                    );
                }
            }
            self::json_respond(array(
                'status' => 'success',
                'data'   => $out,
            ));
        }
 
        private static function json_respond($payload, $http_status = 200) {
            $code = intval($http_status);
            if (function_exists('status_header')) {
                status_header($code);
            }
            wp_send_json($payload);
        }
    }
}
