<?php
/**
 * ECB SDW Data Source Form
 * Path: admin/sources-forms/ecb.php
 * 
 * Individual form for European Central Bank SDW data source
 * Supports: PATH, CSV URL, JSON URL
 */

if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

$notice = '';
if (!empty($_POST['zc_source_action'])) {
    if (!isset($_POST['zc_source_nonce']) || !wp_verify_nonce($_POST['zc_source_nonce'], 'zc_source_action')) {
        $notice = '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'zc-dmt') . '</p></div>';
    } else {
        $action = sanitize_text_field($_POST['zc_source_action']);
        if ($action === 'add_indicator') {
            $name = sanitize_text_field($_POST['name'] ?? '');
            $slug = sanitize_title($_POST['slug'] ?? '');
            $description = wp_kses_post($_POST['description'] ?? '');
            $method = sanitize_text_field($_POST['ecb_method'] ?? 'path');
            $path = sanitize_text_field($_POST['ecb_path'] ?? '');
            $csv_url = esc_url_raw($_POST['ecb_csv_url'] ?? '');
            $json_url = esc_url_raw($_POST['ecb_json_url'] ?? '');
            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug required.', 'zc-dmt') . '</p></div>';
            } elseif ($method==='path' && !$path) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Path required.', 'zc-dmt') . '</p></div>';
            } elseif ($method==='csv' && !$csv_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('CSV URL required.', 'zc-dmt') . '</p></div>';
            } elseif ($method==='json' && !$json_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('JSON URL required.', 'zc-dmt') . '</p></div>';
            } else {
                $cfg=[];
                if ($method==='path') $cfg=['path'=>$path];
                elseif($method==='csv') $cfg=['csv_url'=>$csv_url];
                elseif($method==='json') $cfg=['json_url'=>$json_url];
                $res=ZC_DMT_Indicators::create_indicator($name,$slug,$description,'ecb',$cfg,1);
                if(is_wp_error($res)) $notice='<div class="notice notice-error"><p>'.esc_html($res->get_error_message()).'</p></div>';
                else{ $notice='<div class="notice notice-success is-dismissible"><p>'.esc_html__('Created!', 'zc-dmt').'</p></div>';$_POST=[]; }
            }
        } elseif($action==='test_connection'){
            // similar testing logic
        }
    }
}
// retrieve form values
$name_val = esc_attr($_POST['name'] ?? '');
$slug_val = esc_attr($_POST['slug'] ?? '');
$desc_val = esc_textarea($_POST['description'] ?? '');
$method_val = sanitize_text_field($_POST['ecb_method'] ?? 'path');
$path_val = esc_attr($_POST['ecb_path'] ?? '');
$csv_val = esc_attr($_POST['ecb_csv_url'] ?? '');
$json_val = esc_attr($_POST['ecb_json_url'] ?? '');
?>
<div class="wrap zc-dmt-source-form">
    <h1><?php esc_html_e('ECB (SDW) Data Source','zc-dmt');?></h1>
    <?php echo $notice;?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action"><?php esc_html_e('← Back','zc-dmt');?></a>
    <form method="post">
    <?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="add_indicator">
    <table class="form-table"><tbody>
    <tr><th><?php esc_html_e('Name','zc-dmt');?></th><td><input name="name" class="regular-text" value="<?php echo $name_val;?>"></td></tr>
    <tr><th><?php esc_html_e('Slug','zc-dmt');?></th><td><input name="slug" class="regular-text" value="<?php echo $slug_val;?>"></td></tr>
    <tr><th><?php esc_html_e('Description','zc-dmt');?></th><td><textarea name="description" class="large-text" rows="3"><?php echo $desc_val;?></textarea></td></tr>
    <tr><th><?php esc_html_e('Method','zc-dmt');?></th><td>
        <label><input type="radio" name="ecb_method" value="path" <?php checked($method_val,'path');?>> <?php esc_html_e('PATH (Auto CSV)','zc-dmt');?></label><br>
        <label><input type="radio" name="ecb_method" value="csv" <?php checked($method_val,'csv');?>> <?php esc_html_e('CSV URL','zc-dmt');?></label><br>
        <label><input type="radio" name="ecb_method" value="json" <?php checked($method_val,'json');?>> <?php esc_html_e('JSON URL','zc-dmt');?></label>
    </td></tr>
    <tr id="ecb-path-row"><th><?php esc_html_e('SDW Path','zc-dmt');?></th><td><input name="ecb_path" class="regular-text" value="<?php echo $path_val;?>" placeholder="EXR.D.USD.EUR.SP00.A?startPeriod=2000"></td></tr>
    <tr id="ecb-csv-row" style="display:none;"><th><?php esc_html_e('CSV URL','zc-dmt');?></th><td><input name="ecb_csv_url" class="regular-text" value="<?php echo $csv_val;?>"></td></tr>
    <tr id="ecb-json-row" style="display:none;"><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="ecb_json_url" class="regular-text" value="<?php echo $json_val;?>"></td></tr>
    </tbody></table>
    <p><button class="button button-primary"><?php esc_html_e('Create','zc-dmt');?></button></p>
    </form>
</div>
<script>jQuery(function($){$('input[name="ecb_method"]').on('change',function(){$('#ecb-path-row,#ecb-csv-row,#ecb-json-row').hide();if(this.value==='path')$('#ecb-path-row').show();if(this.value==='csv')$('#ecb-csv-row').show();if(this.value==='json')$('#ecb-json-row').show();}).change();});</script>