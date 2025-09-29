<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Calculations')) {

    class ZC_DMT_Calculations {

        /**
         * Create a new calculation
         * @param string $name Calculation name
         * @param string $formula Formula string
         * @param array $indicators Array of indicator slugs used in formula
         * @param string $output_type Type of output (indicator, value, series)
         * @return int|WP_Error Calculation ID or error
         */
        public static function create_calculation($name, $formula, $indicators = array(), $output_type = 'series') {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', __('Insufficient permissions', 'zc-dmt'));
            }

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_calculations';

            $name = sanitize_text_field($name);
            $slug = sanitize_title($name);
            $formula = wp_kses_post($formula);
            $output_type = sanitize_text_field($output_type);

            // Ensure unique slug
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = %s", $slug));
            if ($exists) {
                $slug = $slug . '-' . time();
            }

            $data = array(
                'name' => $name,
                'slug' => $slug,
                'formula' => $formula,
                'indicators' => wp_json_encode($indicators),
                'output_type' => $output_type,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            );

            $inserted = $wpdb->insert($table, $data);
            if (!$inserted) {
                return new WP_Error('db_insert_failed', __('Failed to create calculation', 'zc-dmt'));
            }

            return (int) $wpdb->insert_id;
        }

        /**
         * Execute a calculation formula
         * @param string $formula Formula to execute
         * @param array $data_context Array of indicator data [slug => series_data]
         * @return array|WP_Error Calculated result or error
         */
        public static function execute_formula($formula, $data_context = array()) {
            try {
                $parser = new ZC_DMT_Formula_Parser();
                return $parser->parse_and_execute($formula, $data_context);
            } catch (Exception $e) {
                return new WP_Error('formula_error', $e->getMessage());
            }
        }

        /**
         * Get calculation by slug
         * @param string $slug Calculation slug
         * @return object|null Calculation object or null
         */
        public static function get_calculation_by_slug($slug) {
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_calculations';
            $slug = sanitize_title($slug);
            
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE slug = %s AND is_active = 1 LIMIT 1", 
                $slug
            ));
        }

        /**
         * List all calculations
         * @param int $limit Number of calculations to return
         * @param int $offset Offset for pagination
         * @return array Array of calculation objects
         */
        public static function list_calculations($limit = 100, $offset = 0) {
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_calculations';
            $limit = max(1, intval($limit));
            $offset = max(0, intval($offset));
            
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY created_at DESC LIMIT %d OFFSET %d", 
                $limit, $offset
            ));
        }

        /**
         * Execute calculation and return result
         * @param string $slug Calculation slug
         * @param string $start_date Optional start date filter
         * @param string $end_date Optional end date filter
         * @return array|WP_Error Calculation result or error
         */
        public static function get_calculation_result($slug, $start_date = null, $end_date = null) {
            $calculation = self::get_calculation_by_slug($slug);
            if (!$calculation) {
                return new WP_Error('not_found', __('Calculation not found', 'zc-dmt'));
            }

            // Get required indicator data
            $indicators = json_decode($calculation->indicators, true);
            if (!is_array($indicators)) {
                $indicators = array();
            }

            $data_context = array();
            foreach ($indicators as $indicator_slug) {
                $indicator_data = ZC_DMT_Indicators::get_data_by_slug($indicator_slug, $start_date, $end_date);
                if (!is_wp_error($indicator_data) && isset($indicator_data['series'])) {
                    $data_context[$indicator_slug] = $indicator_data['series'];
                }
            }

            // Execute the formula
            $result = self::execute_formula($calculation->formula, $data_context);
            if (is_wp_error($result)) {
                return $result;
            }

            return array(
                'calculation' => array(
                    'id' => intval($calculation->id),
                    'name' => $calculation->name,
                    'slug' => $calculation->slug,
                    'formula' => $calculation->formula,
                    'output_type' => $calculation->output_type,
                ),
                'result' => $result,
                'indicators_used' => $indicators,
            );
        }

        /**
         * Update calculation
         * @param int $id Calculation ID
         * @param array $fields Fields to update
         * @return bool|WP_Error Success or error
         */
        public static function update_calculation($id, $fields = array()) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', __('Insufficient permissions', 'zc-dmt'));
            }

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_calculations';
            $id = intval($id);

            if ($id <= 0) {
                return new WP_Error('invalid_id', __('Invalid calculation ID', 'zc-dmt'));
            }

            $data = array();
            $format = array();

            if (isset($fields['name'])) {
                $data['name'] = sanitize_text_field($fields['name']);
                $format[] = '%s';
            }
            if (isset($fields['formula'])) {
                $data['formula'] = wp_kses_post($fields['formula']);
                $format[] = '%s';
            }
            if (isset($fields['indicators'])) {
                $data['indicators'] = wp_json_encode($fields['indicators']);
                $format[] = '%s';
            }
            if (isset($fields['output_type'])) {
                $data['output_type'] = sanitize_text_field($fields['output_type']);
                $format[] = '%s';
            }
            if (isset($fields['is_active'])) {
                $data['is_active'] = intval($fields['is_active']) ? 1 : 0;
                $format[] = '%d';
            }

            // Always update updated_at
            $data['updated_at'] = current_time('mysql');
            $format[] = '%s';

            if (empty($data)) {
                return new WP_Error('no_changes', __('No changes to update', 'zc-dmt'));
            }

            $updated = $wpdb->update($table, $data, array('id' => $id), $format, array('%d'));
            if ($updated === false) {
                return new WP_Error('db_update_failed', __('Failed to update calculation', 'zc-dmt'));
            }

            return true;
        }

        /**
         * Delete calculation
         * @param int $id Calculation ID
         * @return bool|WP_Error Success or error
         */
        public static function delete_calculation($id) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', __('Insufficient permissions', 'zc-dmt'));
            }

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_calculations';
            $id = intval($id);

            if ($id <= 0) {
                return new WP_Error('invalid_id', __('Invalid calculation ID', 'zc-dmt'));
            }

            // Soft delete by setting is_active = 0
            $updated = $wpdb->update(
                $table, 
                array('is_active' => 0, 'updated_at' => current_time('mysql')), 
                array('id' => $id), 
                array('%d', '%s'), 
                array('%d')
            );

            if ($updated === false) {
                return new WP_Error('db_update_failed', __('Failed to delete calculation', 'zc-dmt'));
            }

            return true;
        }

        /**
         * Get available functions for formula builder
         * @return array Array of available functions with descriptions
         */
        public static function get_available_functions() {
            return array(
                'basic' => array(
                    'SUM' => array(
                        'name' => 'SUM',
                        'description' => 'Sum of all values in a series',
                        'syntax' => 'SUM(series)',
                        'example' => 'SUM(GDP_US)',
                    ),
                    'AVG' => array(
                        'name' => 'AVG',
                        'description' => 'Average of all values in a series',
                        'syntax' => 'AVG(series)',
                        'example' => 'AVG(GDP_US)',
                    ),
                    'MIN' => array(
                        'name' => 'MIN',
                        'description' => 'Minimum value in a series',
                        'syntax' => 'MIN(series)',
                        'example' => 'MIN(GDP_US)',
                    ),
                    'MAX' => array(
                        'name' => 'MAX',
                        'description' => 'Maximum value in a series',
                        'syntax' => 'MAX(series)',
                        'example' => 'MAX(GDP_US)',
                    ),
                    'COUNT' => array(
                        'name' => 'COUNT',
                        'description' => 'Count of non-null values in a series',
                        'syntax' => 'COUNT(series)',
                        'example' => 'COUNT(GDP_US)',
                    ),
                ),
                'technical' => array(
                    'ROC' => array(
                        'name' => 'ROC',
                        'description' => 'Rate of Change over specified periods',
                        'syntax' => 'ROC(series, periods)',
                        'example' => 'ROC(GDP_US, 4)',
                    ),
                    'MA' => array(
                        'name' => 'MA',
                        'description' => 'Moving Average over specified periods',
                        'syntax' => 'MA(series, periods)',
                        'example' => 'MA(GDP_US, 12)',
                    ),
                    'RSI' => array(
                        'name' => 'RSI',
                        'description' => 'Relative Strength Index',
                        'syntax' => 'RSI(series, periods)',
                        'example' => 'RSI(GDP_US, 14)',
                    ),
                    'MOMENTUM' => array(
                        'name' => 'MOMENTUM',
                        'description' => 'Momentum indicator',
                        'syntax' => 'MOMENTUM(series, periods)',
                        'example' => 'MOMENTUM(GDP_US, 10)',
                    ),
                ),
                'advanced' => array(
                    'CORRELATION' => array(
                        'name' => 'CORRELATION',
                        'description' => 'Correlation between two series',
                        'syntax' => 'CORRELATION(series1, series2)',
                        'example' => 'CORRELATION(GDP_US, UNEMPLOYMENT_US)',
                    ),
                    'REGRESSION' => array(
                        'name' => 'REGRESSION',
                        'description' => 'Linear regression of series',
                        'syntax' => 'REGRESSION(series)',
                        'example' => 'REGRESSION(GDP_US)',
                    ),
                ),
            );
        }
    }
}
