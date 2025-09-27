<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Dashboard_Ajax')) {

    class ZC_DMT_Dashboard_Ajax {

        /**
         * Register AJAX endpoints
         */
        public static function register() {
            // Dashboard data endpoint (for new Zestra dashboard)
            add_action('wp_ajax_zc_dmt_get_dashboard_data', array(__CLASS__, 'get_dashboard_data'));
            add_action('wp_ajax_nopriv_zc_dmt_get_dashboard_data', array(__CLASS__, 'get_dashboard_data'));
            
            // Search indicators endpoint
            add_action('wp_ajax_zc_dmt_search_indicators', array(__CLASS__, 'search_indicators'));
            add_action('wp_ajax_nopriv_zc_dmt_search_indicators', array(__CLASS__, 'search_indicators'));

            // List all active indicators (builder/dashboard refresh)
            add_action('wp_ajax_zc_dmt_list_indicators', array(__CLASS__, 'list_indicators'));
            add_action('wp_ajax_nopriv_zc_dmt_list_indicators', array(__CLASS__, 'list_indicators'));

            // Test formula endpoint (calculations)
            add_action('wp_ajax_zc_dmt_test_formula', array(__CLASS__, 'test_formula'));
            add_action('wp_ajax_nopriv_zc_dmt_test_formula', array(__CLASS__, 'test_formula'));

            // Get calculation result endpoint
            add_action('wp_ajax_zc_dmt_get_calculation_result', array(__CLASS__, 'get_calculation_result'));
            add_action('wp_ajax_nopriv_zc_dmt_get_calculation_result', array(__CLASS__, 'get_calculation_result'));
        }

        /**
         * Get dashboard data for an indicator
         */
        public static function get_dashboard_data() {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zc_dmt_dashboard')) {
                wp_die('Security check failed');
            }

            $slug = sanitize_title($_POST['slug'] ?? '');
            if (empty($slug)) {
                wp_send_json_error('Missing indicator slug');
                return;
            }

            // Prefer unified fetch that supports both DB and live adapters
            $data = ZC_DMT_Indicators::get_data_by_slug($slug);

            if (is_wp_error($data)) {
                wp_send_json_error($data->get_error_message());
                return;
            }

            $indicator = isset($data['indicator']) ? $data['indicator'] : null;
            $series    = isset($data['series']) && is_array($data['series']) ? $data['series'] : array();

            if (!$indicator) {
                wp_send_json_error('Indicator not found');
                return;
            }

            if (empty($series)) {
                wp_send_json_error('No data available for this indicator');
                return;
            }

            // Normalize indicator payload
            $indicator_payload = array(
                'id'          => isset($indicator['id']) ? intval($indicator['id']) : (isset($indicator->id) ? intval($indicator->id) : 0),
                'name'        => isset($indicator['name']) ? $indicator['name'] : (isset($indicator->name) ? $indicator->name : ''),
                'slug'        => isset($indicator['slug']) ? $indicator['slug'] : (isset($indicator->slug) ? $indicator->slug : $slug),
                'description' => isset($indicator['description']) ? $indicator['description'] : (isset($indicator->description) ? $indicator->description : ''),
                'source_type' => isset($indicator['source_type']) ? $indicator['source_type'] : (isset($indicator->source_type) ? $indicator->source_type : ''),
            );

            wp_send_json_success(array(
                'indicator' => $indicator_payload,
                'series'    => $series,
                'count'     => count($series),
            ));
        }

        /**
         * Search indicators
         */
        public static function search_indicators() {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zc_dmt_dashboard')) {
                wp_die('Security check failed');
            }

            $query = sanitize_text_field($_POST['query'] ?? '');
            $limit = intval($_POST['limit'] ?? 20);
            
            if (strlen($query) < 2) {
                wp_send_json_success(array('indicators' => array()));
                return;
            }

            // Search indicators
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_indicators';
            
            $indicators = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, slug, description, source_type 
                 FROM {$table} 
                 WHERE is_active = 1 
                 AND (name LIKE %s OR slug LIKE %s OR source_type LIKE %s)
                 ORDER BY name ASC 
                 LIMIT %d",
                '%' . $wpdb->esc_like($query) . '%',
                '%' . $wpdb->esc_like($query) . '%',
                '%' . $wpdb->esc_like($query) . '%',
                $limit
            ));

            $formatted = array();
            foreach ($indicators as $indicator) {
                $formatted[] = array(
                    'id' => intval($indicator->id),
                    'name' => $indicator->name,
                    'slug' => $indicator->slug,
                    'description' => $indicator->description,
                    'source_type' => $indicator->source_type
                );
            }

            wp_send_json_success(array('indicators' => $formatted));
        }

        /**
         * List all active indicators (id, name, slug, description, source_type)
         */
        public static function list_indicators() {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zc_dmt_dashboard')) {
                wp_die('Security check failed');
            }

            $limit = intval($_POST['limit'] ?? 500);

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_indicators';
            
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, slug, description, source_type, is_active
                 FROM {$table}
                 WHERE is_active = 1
                 ORDER BY name ASC
                 LIMIT %d",
                max(1, $limit)
            ));

            $indicators = array();
            foreach ($rows as $r) {
                $indicators[] = array(
                    'id' => intval($r->id),
                    'name' => $r->name,
                    'slug' => $r->slug,
                    'description' => $r->description,
                    'source_type' => $r->source_type,
                    'is_active' => intval($r->is_active),
                );
            }

            wp_send_json_success(array('indicators' => $indicators));
        }

        /**
         * Test a formula with sample data
         */
        public static function test_formula() {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zc_dmt_calculations')) {
                wp_die('Security check failed');
            }

            $formula = wp_kses_post($_POST['formula'] ?? '');
            $indicator_slugs = array_map('sanitize_text_field', $_POST['indicators'] ?? array());

            if (empty($formula)) {
                wp_send_json_error('Formula cannot be empty');
                return;
            }

            // Get sample data for indicators
            $data_context = array();
            foreach ($indicator_slugs as $slug) {
                $indicator_data = ZC_DMT_Indicators::get_data_by_slug($slug);
                if (!is_wp_error($indicator_data) && isset($indicator_data['series'])) {
                    // Use last 100 data points for testing
                    $series = array_slice($indicator_data['series'], -100);
                    $data_context[strtolower($slug)] = $series;
                }
            }

            // Test the formula
            $result = ZC_DMT_Calculations::execute_formula($formula, $data_context);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }

            wp_send_json_success($result);
        }

        /**
         * Get calculation result by slug
         */
        public static function get_calculation_result() {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zc_dmt_calculations')) {
                wp_die('Security check failed');
            }

            $slug = sanitize_title($_POST['slug'] ?? '');
            $start_date = sanitize_text_field($_POST['start_date'] ?? '');
            $end_date = sanitize_text_field($_POST['end_date'] ?? '');

            if (empty($slug)) {
                wp_send_json_error('Missing calculation slug');
                return;
            }

            $result = ZC_DMT_Calculations::get_calculation_result($slug, $start_date, $end_date);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }

            wp_send_json_success($result);
        }
    }
}
