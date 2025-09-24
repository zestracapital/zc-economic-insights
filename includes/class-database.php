<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Database')) {

    class ZC_DMT_Database {

        const OPTION_SCHEMA_VERSION = 'zc_dmt_schema_version';
        const SCHEMA_VERSION = '1.0.0';

        /**
         * Install or upgrade database schema.
         */
        public static function install() {
            global $wpdb;

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $charset_collate = $wpdb->get_charset_collate();

            $table_indicators = $wpdb->prefix . 'zc_dmt_indicators';
            $table_data_points = $wpdb->prefix . 'zc_dmt_data_points';
            $table_api_keys = $wpdb->prefix . 'zc_dmt_api_keys';
            $table_error_logs = $wpdb->prefix . 'zc_dmt_error_logs';

            // Indicators
            $sql_indicators = "CREATE TABLE {$table_indicators} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(128) NOT NULL,
                description TEXT NULL,
                source_type VARCHAR(64) DEFAULT 'manual',
                source_config LONGTEXT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY slug (slug),
                KEY is_active (is_active)
            ) {$charset_collate};";

            // Data Points
            $sql_data_points = "CREATE TABLE {$table_data_points} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                indicator_id BIGINT(20) UNSIGNED NOT NULL,
                obs_date DATE NOT NULL,
                value DECIMAL(20,6) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY unique_observation (indicator_id, obs_date),
                KEY obs_date (obs_date)
            ) {$charset_collate};";

            // API Keys
            $sql_api_keys = "CREATE TABLE {$table_api_keys} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                key_name VARCHAR(191) NOT NULL,
                key_hash VARCHAR(255) NOT NULL,
                key_preview VARCHAR(16) NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME NOT NULL,
                last_used DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY key_hash (key_hash),
                KEY is_active (is_active)
            ) {$charset_collate};";

            // Error Logs (minimal for now)
            $sql_error_logs = "CREATE TABLE {$table_error_logs} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                module VARCHAR(64) NOT NULL,
                action VARCHAR(128) NOT NULL,
                message TEXT NOT NULL,
                context LONGTEXT NULL,
                level ENUM('info','warning','error','critical') DEFAULT 'info',
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY module (module),
                KEY level (level),
                KEY created_at (created_at)
            ) {$charset_collate};";

            dbDelta($sql_indicators);
            dbDelta($sql_data_points);
            dbDelta($sql_api_keys);
            dbDelta($sql_error_logs);

            update_option(self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION);
        }

        /**
         * Helper to insert rows with timestamps.
         */
        public static function insert($table, $data, $format = null) {
            global $wpdb;
            if (!isset($data['created_at'])) {
                $data['created_at'] = current_time('mysql');
            }
            if (isset($data['updated_at'])) {
                // keep provided updated_at if present
            }
            return $wpdb->insert($table, $data, $format);
        }

        /**
         * Helper to update rows with timestamps.
         */
        public static function update($table, $data, $where, $format = null, $where_format = null) {
            global $wpdb;
            if (!isset($data['updated_at'])) {
                $data['updated_at'] = current_time('mysql');
            }
            return $wpdb->update($table, $data, $where, $format, $where_format);
        }

        /**
         * Minimal logger (DB only, no file fallback yet)
         */
        public static function log($module, $action, $message, $context = array(), $level = 'info') {
            global $wpdb;
            $table_error_logs = $wpdb->prefix . 'zc_dmt_error_logs';
            $wpdb->insert($table_error_logs, array(
                'module' => sanitize_text_field($module),
                'action' => sanitize_text_field($action),
                'message' => wp_kses_post($message),
                'context' => !empty($context) ? wp_json_encode($context) : null,
                'level' => in_array($level, array('info','warning','error','critical'), true) ? $level : 'info',
                'created_at' => current_time('mysql'),
            ));
        }
    }
}
