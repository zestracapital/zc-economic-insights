<?php
/**
 * DBnomics Data Source Form
 * Path: admin/sources-forms/dbnomics.php
 * 
 * Individual form for DBnomics data source
 * Supports: dataset+series config, JSON URL, CSV URL
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
            $method = sanitize_text_field($_POST['dbn_method'] ?? '');
            $dataset = sanitize_text_field($_POST['dbn_dataset'] ?? '');
            $series = sanitize_text_field($_POST['dbn_series'] ?? '');
            $json_url = esc_url_raw($_POST['dbn_json_url'] ?? '');
            $csv_url = esc_url_raw($_POST['dbn_csv_url'] ?? '');
            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug are required.', 'zc-dmt') . '</p></div>';
            } elseif ($method === 'config' && (!$dataset||!$series)) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Dataset and Series required.', 'zc-dmt') . '</p></div>';
            } elseif ($method==='json'&& !$json_url||($method==='csv'&&!$csv_url)) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('URL required.', 'zc-dmt') . '</p></div>';
            } else {
                $source_config = [];
                if ($method==='config') {
                    $source_config=['database'=>sanitize_text_field($_POST['dbn_database']),'dataset'=>$dataset,'series'=>$series];
                } elseif ($method==='json') $source_config=['json_url'=>$json_url];
                elseif ($method==='csv') $source_config=['csv_url'=>$csv_url];
                $res=ZC_DMT_Indicators::create_indicator($name,$slug,$description,'dbnomics',$source_config,1);
                if (is_wp_error($res)) $notice='<div class="notice notice-error"><p>'.esc_html($res->get_error_message()).'</p></div>';
                else { $notice='<div class="notice notice-success is-dismissible"><p>'.esc_html__('Created!', 'zc-dmt').'</p></div>'; $_POST=[]; }
            }
        } elseif ($action==='test_connection') {
            // similar test logic
        }
    }
}
// ... form context retrieval ... (similar to above)
?><div class="wrap">
<?php echo $notice;?>
<h1><?php esc_html_e('DBnomics Data Source','zc-dmt');?></h1>
<form method="post">
<?php wp_nonce_field('zc_source_action','zc_source_nonce');?>
<input type="hidden" name="zc_source_action" value="add_indicator">
<table class="form-table"><tbody>
<tr><th><?php esc_html_e('Name','zc-dmt');?></th><td><input name="name" required></td></tr>
<tr><th><?php esc_html_e('Slug','zc-dmt');?></th><td><input name="slug" required></td></tr>
<tr><th><?php esc_html_e('Desc','zc-dmt');?></th><td><textarea name="description"></textarea></td></tr>
<tr><th><?php esc_html_e('Method','zc-dmt');?></th><td>
<select name="dbn_method" id="dbn_method">
<option value="config"><?php esc_html_e('Dataset+Series','zc-dmt');?></option>
<option value="json"><?php esc_html_e('JSON URL','zc-dmt');?></option>
<option value="csv"><?php esc_html_e('CSV URL','zc-dmt');?></option>
</select></td></tr>
<tr id="dbn-config"><th><?php esc_html_e('Database','zc-dmt');?></th><td><input name="dbn_database" value="<?php echo esc_attr($_POST['dbn_database']??'');?>"></td></tr>
<tr id="dbn-config-series"><th><?php esc_html_e('Series','zc-dmt');?></th><td><input name="dbn_series"></td></tr>
<tr id="dbn-json" style="display:none;"><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="dbn_json_url"></td></tr>
<tr id="dbn-csv" style="display:none;"><th><?php esc_html_e('CSV URL','zc-dmt');?></th><td><input name="dbn_csv_url"></td></tr>
</tbody></table>
<p><button class="button button-primary"><?php esc_html_e('Create','zc-dmt');?></button></p>
</form>
<script>jQuery(function($){$('#dbn_method').change(function(){var v=$(this).val();$('#dbn-config,tr[id*=json],#dbn-csv').hide();if(v==='config')$('#dbn-config,#dbn-config-series').show();if(v==='json')$('#dbn-json').show();if(v==='csv')$('#dbn-csv').show();}).change();});</script>
</div>