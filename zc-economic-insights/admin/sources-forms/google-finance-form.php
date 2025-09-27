<?php
/**
 * Google Finance Data Source Form
 * Path: admin/sources-forms/google-finance.php
 *
 * Individual form for Google Finance data source
 * Supports: CSV URL, JSON URL
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
            $csv_url = esc_url_raw($_POST['gf_csv_url'] ?? '');
            $json_url = esc_url_raw($_POST['gf_json_url'] ?? '');
            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug required.', 'zc-dmt') . '</p></div>';
            } elseif (!$csv_url && !$json_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Provide CSV URL or JSON URL.', 'zc-dmt') . '</p></div>';
            } else {
                $cfg = [];
                if ($csv_url) $cfg['csv_url'] = $csv_url;
                if ($json_url) $cfg['json_url'] = $json_url;
                $res = ZC_DMT_Indicators::create_indicator($name,$slug,$description,'google-finance',$cfg,1);
                if (is_wp_error($res)) $notice = '<div class="notice notice-error"><p>'.esc_html($res->get_error_message()).'</p></div>';
                else { $notice = '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Google Finance indicator created!', 'zc-dmt').'</p></div>'; $_POST=[]; }
            }
        } elseif ($action === 'test_connection') {
            $test_csv = esc_url_raw($_POST['test_csv_url'] ?? '');
            $test_json = esc_url_raw($_POST['test_json_url'] ?? '');
            if (!$test_csv && !$test_json) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Provide URL to test.', 'zc-dmt') . '</p></div>';
            } else {
                $test_cfg = [];
                if ($test_csv) $test_cfg['csv_url'] = $test_csv;
                elseif ($test_json) $test_cfg['json_url'] = $test_json;
                $test_indicator = (object)['id'=>0,'name'=>'Test','slug'=>'test-gf','source_type'=>'google-finance','source_config'=>wp_json_encode($test_cfg)];
                $test_res = ZC_DMT_DataSource_GoogleFinance::get_series_for_indicator($test_indicator);
                if (is_wp_error($test_res)) $notice = '<div class="notice notice-error"><p><strong>Test Failed:</strong> '.esc_html($test_res->get_error_message()).'</p></div>';
                else { $count = isset($test_res['series'])?count($test_res['series']):0; $notice = '<div class="notice notice-success"><p><strong>Success!</strong> '.$count.' data points retrieved.</p></div>'; }
            }
        }
    }
}
// Retrieve form values
$f_name = esc_attr($_POST['name']??'');
$f_slug = esc_attr($_POST['slug']??'');
$f_desc = esc_textarea($_POST['description']??'');
$f_csv = esc_attr($_POST['gf_csv_url']??'');
$f_json = esc_attr($_POST['gf_json_url']??'');
?>
<div class="wrap zc-dmt-source-form">
    <h1><?php esc_html_e('Google Finance Data Source','zc-dmt');?></h1>
    <?php echo $notice;?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action"><?php esc_html_e('← Back','zc-dmt');?></a>
    <hr>
    <div class="zc-source-info" style="background:#f8fafc;border:1px solid #e2e8f0;padding:16px;border-radius:6px;">
        <h3><?php esc_html_e('About Google Finance Source','zc-dmt');?></h3>
        <p><?php esc_html_e('Supports fetching data via published CSV from Google Sheets using GOOGLEFINANCE or any JSON endpoint returning period/value.', 'zc-dmt'); ?></p>
    </div>
    <div class="zc-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <div class="zc-card" style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:6px;">
            <h2><?php esc_html_e('Add New Google Finance Indicator','zc-dmt');?></h2>
            <form method="post">
                <?php wp_nonce_field('zc_source_action','zc_source_nonce');?>
                <input type="hidden" name="zc_source_action" value="add_indicator">
                <table class="form-table"><tbody>
                    <tr><th><?php esc_html_e('Name','zc-dmt');?></th><td><input name="name" class="regular-text" value="<?php echo $f_name;?>" required></td></tr>
                    <tr><th><?php esc_html_e('Slug','zc-dmt');?></th><td><input name="slug" class="regular-text" value="<?php echo $f_slug;?>" required></td></tr>
                    <tr><th><?php esc_html_e('Description','zc-dmt');?></th><td><textarea name="description" class="large-text" rows="3"><?php echo $f_desc;?></textarea></td></tr>
                    <tr><th><?php esc_html_e('CSV URL','zc-dmt');?></th><td><input name="gf_csv_url" class="regular-text" value="<?php echo $f_csv;?>" placeholder="https://docs.google.com/.../pub?output=csv"></td></tr>
                    <tr><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="gf_json_url" class="regular-text" value="<?php echo $f_json;?>" placeholder="https://api.yoursite.com/data.json"></td></tr>
                </tbody></table>
                <p><button class="button button-primary"><?php esc_html_e('Create Indicator','zc-dmt');?></button></p>
            </form>
        </div>
        <div class="zc-card" style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:6px;">
            <h2><?php esc_html_e('Test Connection','zc-dmt');?></h2>
            <form method="post">
                <?php wp_nonce_field('zc_source_action','zc_source_nonce');?>
                <input type="hidden" name="zc_source_action" value="test_connection">
                <table class="form-table"><tbody>
                    <tr><th><?php esc_html_e('CSV URL Test','zc-dmt');?></th><td><input name="test_csv_url" class="regular-text"></td></tr>
                    <tr><th><?php esc_html_e('JSON URL Test','zc-dmt');?></th><td><input name="test_json_url" class="regular-text"></td></tr>
                </tbody></table>
                <p><button class="button button-secondary"><?php esc_html_e('Test Connection','zc-dmt');?></button></p>
            </form>
        </div>
    </div>
</div>
<script>
jQuery(function($){/* copy logic*/$('input[name="gf_csv_url"]').on('input',function(){$('input[name="test_csv_url"]').val($(this).val());$('input[name="test_json_url"]').val('');});$('input[name="gf_json_url"]').on('input',function(){$('input[name="test_json_url"]').val($(this).val());$('input[name="test_csv_url"]').val('');});});
</script>