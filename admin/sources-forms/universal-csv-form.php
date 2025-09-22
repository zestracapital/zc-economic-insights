<?php
/**
 * Universal CSV Data Source Form
 * Path: admin/sources-forms/universal-csv.php
 *
 * Form for any CSV URL data source
 * Supports: CSV URL, Date Column, Value Column, Delimiter, Skip Rows
 */

if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

$notice = '';
if (!empty($_POST['zc_source_action'])) {
    if (!isset($_POST['zc_source_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['zc_source_nonce']), 'zc_source_action')) {
        $notice = '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'zc-dmt') . '</p></div>';
    } else {
        $action = sanitize_text_field($_POST['zc_source_action']);
        if ($action === 'add_indicator') {
            $name = sanitize_text_field($_POST['name'] ?? '');
            $slug = sanitize_title($_POST['slug'] ?? '');
            $description = wp_kses_post($_POST['description'] ?? '');
            $csv_url = esc_url_raw($_POST['ucsv_url'] ?? '');
            $date_col = sanitize_text_field($_POST['ucsv_date_col'] ?? '');
            $value_col = sanitize_text_field($_POST['ucsv_value_col'] ?? '');
            $delimiter = sanitize_text_field($_POST['ucsv_delimiter'] ?? ',');
            $skiprows = intval($_POST['ucsv_skiprows'] ?? 0);

            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug required.', 'zc-dmt') . '</p></div>';
            } elseif (!$csv_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('CSV URL is required.', 'zc-dmt') . '</p></div>';
            } else {
                $cfg = array(
                    'csv_url' => $csv_url,
                    'date_col' => $date_col,
                    'value_col' => $value_col,
                    'delimiter' => $delimiter,
                    'skiprows' => $skiprows
                );
                $res = ZC_DMT_Indicators::create_indicator($name, $slug, $description, 'universal-csv', $cfg, 1);
                if (is_wp_error($res)) {
                    $notice = '<div class="notice notice-error"><p>' . esc_html($res->get_error_message()) . '</p></div>';
                } else {
                    $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Universal CSV indicator created!', 'zc-dmt') . '</p></div>';
                    $_POST = array();
                }
            }
        } elseif ($action === 'test_connection') {
            $test_csv = esc_url_raw($_POST['test_csv_url'] ?? '');
            $test_date = sanitize_text_field($_POST['test_date_col'] ?? '');
            $test_value = sanitize_text_field($_POST['test_value_col'] ?? '');
            if (!$test_csv) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('CSV URL is required to test.', 'zc-dmt') . '</p></div>';
            } else {
                $test_cfg = array(
                    'csv_url' => $test_csv,
                    'date_col' => $test_date,
                    'value_col' => $test_value
                );
                $test_ind = (object)[
                    'id' => 0,
                    'name' => 'Test',
                    'slug' => 'test-ucsv',
                    'source_type' => 'universal-csv',
                    'source_config' => wp_json_encode($test_cfg)
                ];
                $res = ZC_DMT_DataSource_UniversalCSV::get_series_for_indicator($test_ind);
                if (is_wp_error($res)) {
                    $notice = '<div class="notice notice-error"><p><strong>' . esc_html__('Test Failed:', 'zc-dmt') . '</strong> ' . esc_html($res->get_error_message()) . '</p></div>';
                } else {
                    $count = count($res);
                    $notice = '<div class="notice notice-success"><p><strong>' . esc_html__('Success!', 'zc-dmt') . '</strong> ' . esc_html($count) . ' rows retrieved.</p></div>';
                }
            }
        }
    }
}
// retrieve form values
$f = [
    'name' => esc_attr($_POST['name'] ?? ''),
    'slug' => esc_attr($_POST['slug'] ?? ''),
    'desc' => esc_textarea($_POST['description'] ?? ''),
    'csv' => esc_attr($_POST['ucsv_url'] ?? ''),
    'date' => esc_attr($_POST['ucsv_date_col'] ?? ''),
    'value' => esc_attr($_POST['ucsv_value_col'] ?? ''),
    'delimiter' => esc_attr($_POST['ucsv_delimiter'] ?? ','),
    'skiprows' => esc_attr($_POST['ucsv_skiprows'] ?? '0')
];
?>
<div class="wrap zc-dmt-source-form">
    <h1><?php esc_html_e('Universal CSV Data Source','zc-dmt');?></h1>
    <?php echo $notice;?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action"><?php esc_html_e('← Back','zc-dmt');?></a>
    <hr>
    <div class="zc-source-info" style="background:#f8fafc;padding:16px;border:1px solid #e2e8f0;border-radius:6px;"><h3><?php esc_html_e('About Universal CSV','zc-dmt');?></h3><p><?php esc_html_e('Fetch time-series data from any CSV URL. Configure date/value columns, delimiter, and skip rows.', 'zc-dmt');?></p></div>
    <div class="zc-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;"><h2><?php esc_html_e('Add New Indicator','zc-dmt');?></h2>
            <form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="add_indicator">
            <table class="form-table"><tbody>
                <tr><th><?php esc_html_e('Name','zc-dmt'); ?></th><td><input name="name" class="regular-text" value="<?php echo $f['name']; ?>" required></td></tr>
                <tr><th><?php esc_html_e('Slug','zc-dmt'); ?></th><td><input name="slug" class="regular-text" value="<?php echo $f['slug']; ?>" required></td></tr>
                <tr><th><?php esc_html_e('Description','zc-dmt'); ?></th><td><textarea name="description" class="large-text" rows="3"><?php echo $f['desc']; ?></textarea></td></tr>
                <tr><th><?php esc_html_e('CSV URL','zc-dmt'); ?></th><td><input name="ucsv_url" class="regular-text" value="<?php echo $f['csv']; ?>" placeholder="https://example.com/data.csv"></td></tr>
                <tr><th><?php esc_html_e('Date Column','zc-dmt'); ?></th><td><input name="ucsv_date_col" class="regular-text" value="<?php echo $f['date']; ?>" placeholder="date or 0"></td></tr>
                <tr><th><?php esc_html_e('Value Column','zc-dmt'); ?></th><td><input name="ucsv_value_col" class="regular-text" value="<?php echo $f['value']; ?>" placeholder="value or 1"></td></tr>
                <tr><th><?php esc_html_e('Delimiter','zc-dmt'); ?></th><td><input name="ucsv_delimiter" class="regular-text" value="<?php echo $f['delimiter']; ?>" placeholder=","></td></tr>
                <tr><th><?php esc_html_e('Skip Rows','zc-dmt'); ?></th><td><input name="ucsv_skiprows" type="number" class="small-text" value="<?php echo $f['skiprows']; ?>" min="0"></td></tr>
            </tbody></table>
            <p><button class="button button-primary"><?php esc_html_e('Create Indicator','zc-dmt'); ?></button></p>
            </form>
        </div>
        <div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;"><h2><?php esc_html_e('Test Connection','zc-dmt');?></h2>
            <form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="test_connection">
            <table class="form-table"><tbody>
                <tr><th><?php esc_html_e('CSV URL','zc-dmt'); ?></th><td><input name="test_csv_url" class="regular-text"></td></tr>
                <tr><th><?php esc_html_e('Date Column','zc-dmt'); ?></th><td><input name="test_date_col" class="regular-text" placeholder="date or 0"></td></tr>
                <tr><th><?php esc_html_e('Value Column','zc-dmt'); ?></th><td><input name="test_value_col" class="regular-text" placeholder="value or 1"></td></tr>
            </tbody></table>
            <p><button class="button button-secondary"><?php esc_html_e('Test Connection','zc-dmt'); ?></button></p>
            </form>
        </div>
    </div>
</div>
<script>
jQuery(function($){$('input[name="ucsv_url"]').on('input',function(){$('input[name="test_csv_url"]').val($(this).val());});});
</script>