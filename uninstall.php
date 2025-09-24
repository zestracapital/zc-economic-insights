<?php
/**
 * Uninstall script for ZC DMT (Data Management Tool)
 *
 * This will remove plugin database objects and options.
 * Warning: This deletes ALL plugin data tables.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete plugin options
delete_option('zc_dmt_schema_version');

// Drop plugin tables (data will be permanently removed)
$tables = array(
    $wpdb->prefix . 'zc_dmt_data_points',
    $wpdb->prefix . 'zc_dmt_indicators',
    $wpdb->prefix . 'zc_dmt_api_keys',
    $wpdb->prefix . 'zc_dmt_error_logs',
);

foreach ($tables as $table) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
}
