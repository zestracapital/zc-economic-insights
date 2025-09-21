<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Security')) {

    class ZC_DMT_Security {

        /**
         * Generate a new API key, store hash + metadata in DB, and return the plain key.
         *
         * @param string $name Label for the key (for admin reference).
         * @return string|WP_Error Plain API key or WP_Error on failure.
         */
        public static function generate_key($name = '') {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', __('Insufficient permissions', 'zc-dmt'));
            }

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_api_keys';

            try {
                // Key format: "zc_" + 32 hex characters
                $raw = bin2hex(random_bytes(16));
                $key = 'zc_' . $raw;

                $hash = hash('sha256', $key);
                $preview = substr($key, 0, 8) . '...' . substr($key, -4);

                $wpdb->insert($table, array(
                    'key_name'  => sanitize_text_field($name ?: 'Default Key'),
                    'key_hash'  => $hash,
                    'key_preview' => $preview,
                    'is_active' => 1,
                    'created_at'=> current_time('mysql'),
                    'last_used' => null,
                ));

                return $key;
            } catch (Exception $e) {
                return new WP_Error('key_generation_failed', $e->getMessage());
            }
        }

        /**
         * Validate a provided API key against the stored hashes.
         *
         * @param string $key
         * @return bool
         */
        public static function validate_key($key) {
            if (empty($key) || !is_string($key)) {
                return false;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_api_keys';
            $hash = hash('sha256', $key);

            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT id, is_active FROM {$table} WHERE key_hash = %s LIMIT 1", $hash)
            );

            $valid = $row && intval($row->is_active) === 1;

            if ($valid) {
                // Update last_used timestamp
                $wpdb->update($table, array('last_used' => current_time('mysql')), array('id' => $row->id), array('%s'), array('%d'));
            }

            return (bool) $valid;
        }

        /**
         * Revoke a key by ID (set is_active=0).
         *
         * @param int $id
         * @return bool
         */
        public static function revoke_key($id) {
            if (!current_user_can('manage_options')) {
                return false;
            }
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_api_keys';
            return false !== $wpdb->update($table, array('is_active' => 0), array('id' => intval($id)), array('%d'), array('%d'));
        }

        /**
         * List all keys (admin).
         *
         * @return array
         */
        public static function list_keys() {
            if (!current_user_can('manage_options')) {
                return array();
            }
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_api_keys';
            $rows = $wpdb->get_results("SELECT id, key_name, key_preview, is_active, created_at, last_used FROM {$table} ORDER BY id DESC");
            return is_array($rows) ? $rows : array();
        }
    }
}
