<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Indicators')) {

    class ZC_DMT_Indicators {

        /**
         * Create an indicator (name, slug, description).
         * @return int|WP_Error Inserted ID or error.
         */
        public static function create_indicator($name, $slug, $description = '', $source_type = 'manual', $source_config = null, $is_active = 1) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', __('Insufficient permissions', 'zc-dmt'));
            }
            global $wpdb;

            $table = $wpdb->prefix . 'zc_dmt_indicators';
            $name = sanitize_text_field($name);
            $slug = sanitize_title($slug);
            $description = wp_kses_post($description);
            $source_type = sanitize_text_field($source_type);
            $is_active = intval($is_active) ? 1 : 0;

            // Ensure unique slug
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = %s", $slug));
            if ($exists) {
                return new WP_Error('duplicate_slug', __('Slug already exists', 'zc-dmt'));
            }

            $data = array(
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'source_type' => $source_type,
                'source_config' => $source_config ? wp_json_encode($source_config) : null,
                'is_active' => $is_active,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            );

            $inserted = $wpdb->insert($table, $data);
            if (!$inserted) {
                return new WP_Error('db_insert_failed', __('Failed to create indicator', 'zc-dmt'));
            }
            return (int) $wpdb->insert_id;
        }

        /**
         * Get indicator row by slug.
         */
        public static function get_indicator_by_slug($slug) {
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_indicators';
            $slug = sanitize_title($slug);
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s LIMIT 1", $slug));
        }

        /**
         * List indicators (basic).
         */
        public static function list_indicators($limit = 100, $offset = 0) {
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_indicators';
            $limit = max(1, intval($limit));
            $offset = max(0, intval($offset));
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset));
        }

        /**
         * Add or replace a data point for an indicator (id, date, value).
         * Idempotent via unique (indicator_id, obs_date).
         */
        public static function add_data_point($indicator_id, $date, $value) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', __('Insufficient permissions', 'zc-dmt'));
            }

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_data_points';

            // Normalize date to Y-m-d
            $ts = strtotime($date);
            if ($ts === false) {
                return new WP_Error('invalid_date', __('Invalid date format', 'zc-dmt'));
            }
            $ymd = gmdate('Y-m-d', $ts);

            $value = is_numeric($value) ? (float) $value : null;

            // Use REPLACE to ensure idempotency on (indicator_id, obs_date) unique key
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} (indicator_id, obs_date, value, created_at)
                 VALUES (%d, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)",
                intval($indicator_id),
                $ymd,
                isset($value) ? (string)$value : null,
                current_time('mysql')
            );

            $result = $wpdb->query($sql);
            if ($result === false) {
                return new WP_Error('db_write_failed', __('Failed to write data point', 'zc-dmt'));
            }
            return true;
        }

        /**
         * Fetch data points by indicator slug with optional date range.
         * Returns array of [date, value] sorted ascending by date.
         */
        public static function get_data_by_slug($slug, $start = null, $end = null) {
            global $wpdb;
            $table_i = $wpdb->prefix . 'zc_dmt_indicators';
            $table_d = $wpdb->prefix . 'zc_dmt_data_points';
            $slug = sanitize_title($slug);

            $indicator = self::get_indicator_by_slug($slug);
            if (!$indicator) {
                return new WP_Error('not_found', __('Indicator not found', 'zc-dmt'));
            }
            // Route to appropriate data source adapter based on source_type
            if (isset($indicator->source_type)) {
                if ($indicator->source_type === 'google_sheets' && class_exists('ZC_DMT_DataSource_Google_Sheets')) {
                    return ZC_DMT_DataSource_Google_Sheets::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'fred' && class_exists('ZC_DMT_DataSource_FRED')) {
                    return ZC_DMT_DataSource_FRED::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'world_bank' && class_exists('ZC_DMT_DataSource_WorldBank')) {
                    return ZC_DMT_DataSource_WorldBank::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'dbnomics' && class_exists('ZC_DMT_DataSource_DBnomics')) {
                    return ZC_DMT_DataSource_DBnomics::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'eurostat' && class_exists('ZC_DMT_DataSource_Eurostat')) {
                    return ZC_DMT_DataSource_Eurostat::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'oecd' && class_exists('ZC_DMT_DataSource_OECD')) {
                    return ZC_DMT_DataSource_OECD::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'uk_ons' && class_exists('ZC_DMT_DataSource_UK_ONS')) {
                    return ZC_DMT_DataSource_UK_ONS::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'yahoo_finance' && class_exists('ZC_DMT_DataSource_YahooFinance')) {
                    return ZC_DMT_DataSource_YahooFinance::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'google_finance' && class_exists('ZC_DMT_DataSource_GoogleFinance')) {
                    return ZC_DMT_DataSource_GoogleFinance::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'quandl' && class_exists('ZC_DMT_DataSource_Quandl')) {
                    return ZC_DMT_DataSource_Quandl::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'bank_of_canada' && class_exists('ZC_DMT_DataSource_BankOfCanada')) {
                    return ZC_DMT_DataSource_BankOfCanada::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'statcan' && class_exists('ZC_DMT_DataSource_StatCan')) {
                    return ZC_DMT_DataSource_StatCan::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'australia_rba' && class_exists('ZC_DMT_DataSource_Australia_RBA')) {
                    return ZC_DMT_DataSource_Australia_RBA::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'ecb' && class_exists('ZC_DMT_DataSource_ECB')) {
                    return ZC_DMT_DataSource_ECB::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'universal_csv' && class_exists('ZC_DMT_DataSource_Universal_CSV')) {
                    return ZC_DMT_DataSource_Universal_CSV::get_series_for_indicator($indicator, $start, $end);
                } elseif ($indicator->source_type === 'universal_json' && class_exists('ZC_DMT_DataSource_Universal_JSON')) {
                    return ZC_DMT_DataSource_Universal_JSON::get_series_for_indicator($indicator, $start, $end);
                }
            }

            $where = "WHERE d.indicator_id = %d";
            $params = array($indicator->id);

            if (!empty($start)) {
                $ts = strtotime($start);
                if ($ts !== false) {
                    $where .= " AND d.obs_date >= %s";
                    $params[] = gmdate('Y-m-d', $ts);
                }
            }
            if (!empty($end)) {
                $ts2 = strtotime($end);
                if ($ts2 !== false) {
                    $where .= " AND d.obs_date <= %s";
                    $params[] = gmdate('Y-m-d', $ts2);
                }
            }

            array_unshift($params, "SELECT d.obs_date, d.value
                                    FROM {$table_d} d
                                    {$where}
                                    ORDER BY d.obs_date ASC");

            $sql = call_user_func_array(array($wpdb, 'prepare'), $params);
            $rows = $wpdb->get_results($sql);

            $data = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $data[] = array($row->obs_date, is_null($row->value) ? null : (0 + $row->value));
                }
            }
            return array(
                'indicator' => array(
                    'id' => (int) $indicator->id,
                    'name' => $indicator->name,
                    'slug' => $indicator->slug,
                    'description' => $indicator->description,
                    'source_type' => $indicator->source_type,
                    'is_active' => (int) $indicator->is_active,
                ),
                'series' => $data,
            );
        }

        /**
         * Update an existing indicator.
         * Allows changing: name, slug (unique), description, source_type, source_config, is_active.
         * @param int $id
         * @param array $fields keys: name, slug, description, source_type, source_config(array|null), is_active(0|1)
         * @return bool|WP_Error
         */
        public static function update_indicator($id, $fields = array()) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', __('Insufficient permissions', 'zc-dmt'));
            }
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_indicators';

            $id = intval($id);
            if ($id <= 0) {
                return new WP_Error('invalid_id', __('Invalid indicator id', 'zc-dmt'));
            }

            $data = array();
            $where = array('id' => $id);
            $format = array();
            $where_format = array('%d');

            if (isset($fields['name'])) {
                $data['name'] = sanitize_text_field($fields['name']);
                $format[] = '%s';
            }
            if (isset($fields['slug'])) {
                $new_slug = sanitize_title($fields['slug']);
                // Ensure unique slug excluding current id
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id <> %d", $new_slug, $id));
                if ($exists) {
                    return new WP_Error('duplicate_slug', __('Slug already exists', 'zc-dmt'));
                }
                $data['slug'] = $new_slug;
                $format[] = '%s';
            }
            if (isset($fields['description'])) {
                $data['description'] = wp_kses_post($fields['description']);
                $format[] = '%s';
            }
            if (isset($fields['source_type'])) {
                $data['source_type'] = sanitize_text_field($fields['source_type']);
                $format[] = '%s';
            }
            if (array_key_exists('source_config', $fields)) {
                // Accept null or array
                $cfg = $fields['source_config'];
                if (is_array($cfg)) {
                    $data['source_config'] = wp_json_encode($cfg);
                } else {
                    $data['source_config'] = null;
                }
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

            $updated = $wpdb->update($table, $data, $where, $format, $where_format);
            if ($updated === false) {
                return new WP_Error('db_update_failed', __('Failed to update indicator', 'zc-dmt'));
            }
            return true;
        }
    }
}
