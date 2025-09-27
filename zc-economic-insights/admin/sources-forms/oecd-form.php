<?php
/**
 * OECD Data Source Form
 * Path: admin/sources-forms/oecd.php
 * 
 * Form for OECD SDMX-JSON data source
 * Supports: JSON URL, CSV URL, Dataset Key (path)
 */
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

$notice='';
if(!empty($_POST['zc_source_action'])){
  if(!isset($_POST['zc_source_nonce'])||!wp_verify_nonce($_POST['zc_source_nonce'],'zc_source_action')){
    $notice='<div class="notice notice-error"><p>'.esc_html__('Security check failed.','zc-dmt').'</p></div>';
  } else {
    $action=sanitize_text_field($_POST['zc_source_action']);
    if($action==='add_indicator'){
      $name=sanitize_text_field($_POST['name']??'');
      $slug=sanitize_title($_POST['slug']??'');
      $description=wp_kses_post($_POST['description']??'');
      $method=sanitize_text_field($_POST['oecd_method']??'path');
      $path=sanitize_text_field($_POST['oecd_path']??'');
      $csv_url=esc_url_raw($_POST['oecd_csv_url']??'');
      $json_url=esc_url_raw($_POST['oecd_json_url']??'');
      if(!$name||!$slug){
        $notice='<div class="notice notice-error"><p>'.esc_html__('Name and Slug required.','zc-dmt').'</p></div>';
      } elseif($method==='path'&&!$path){
        $notice='<div class="notice notice-error"><p>'.esc_html__('Path required.','zc-dmt').'</p></div>';
      } elseif($method==='csv'&&!$csv_url){
        $notice='<div class="notice notice-error"><p>'.esc_html__('CSV URL required.','zc-dmt').'</p></div>';
      } elseif($method==='json'&&!$json_url){
        $notice='<div class="notice notice-error"><p>'.esc_html__('JSON URL required.','zc-dmt').'</p></div>';
      } else {
        $cfg=[];
        if($method==='path')$cfg=['path'=>$path];
        elseif($method==='csv')$cfg=['csv_url'=>$csv_url];
        elseif($method==='json')$cfg=['json_url'=>$json_url];
        $res=ZC_DMT_Indicators::create_indicator($name,$slug,$description,'oecd_sdmx_json',$cfg,1);
        if(is_wp_error($res))$notice='<div class="notice notice-error"><p>'.esc_html($res->get_error_message()).'</p></div>';
        else{$notice='<div class="notice notice-success is-dismissible"><p>'.esc_html__('OECD indicator created!','zc-dmt').'</p></div>';$_POST=[];}
      }
    }
    elseif($action==='test_connection'){
      $test_method=sanitize_text_field($_POST['test_method']??'path');
      $test_cfg=[];
      if($test_method==='path')$test_cfg=['path'=>sanitize_text_field($_POST['test_path']??'')];
      elseif($test_method==='csv')$test_cfg=['csv_url'=>esc_url_raw($_POST['test_csv_url']??'')];
      elseif($test_method==='json')$test_cfg=['json_url'=>esc_url_raw($_POST['test_json_url']??'')];
      $test_ind=(object)['id'=>0,'name'=>'Test','slug'=>'test-oecd','source_type'=>'oecd','source_config'=>wp_json_encode($test_cfg)];
      $res=ZC_DMT_DataSource_OECD::get_series_for_indicator($test_ind);
      if(is_wp_error($res))$notice='<div class="notice notice-error"><p><strong>'.esc_html__('Test Failed:','zc-dmt').'</strong> '.esc_html($res->get_error_message()).'</p></div>';
      else{$count=count($res['series']??[]);$notice='<div class="notice notice-success"><p><strong>'.esc_html__('Success!','zc-dmt').'</strong> '.$count.' data points retrieved.</p></div>';}    
    }
  }
}
// form vals
$name_v=esc_attr($_POST['name']??'');
$slug_v=esc_attr($_POST['slug']??'');
desc_v=esc_textarea($_POST['description']??'');
$method_v=sanitize_text_field($_POST['oecd_method']??'path');
$path_v=esc_attr($_POST['oecd_path']??'');
csv_v=esc_attr($_POST['oecd_csv_url']??'');
json_v=esc_attr($_POST['oecd_json_url']??'');
?>
<div class="wrap zc-dmt-source-form">
<h1><?php esc_html_e('OECD SDMX-JSON Source','zc-dmt');?></h1>
<?php echo $notice;?><a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources'));?>" class="page-title-action"><?php esc_html_e('← Back','zc-dmt');?></a><hr>
<div class="zc-source-info" style="background:#f8fafc;padding:16px;border:1px solid #e2e8f0;border-radius:6px;"><h3><?php esc_html_e('About OECD SDMX-JSON','zc-dmt');?></h3><p><?php esc_html_e('Fetch OECD time series via SDMX-JSON path or direct CSV/JSON URL. Auto-detects time dimension.', 'zc-dmt');?></p></div>
<div class="zc-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
<div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;"><h2><?php esc_html_e('Add New Indicator','zc-dmt');?></h2><form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="add_indicator"><table class="form-table"><tbody><tr><th><?php esc_html_e('Name','zc-dmt');?></th><td><input name="name" class="regular-text" value="<?php echo $name_v;?>" required></td></tr><tr><th><?php esc_html_e('Slug','zc-dmt');?></th><td><input name="slug" class="regular-text" value="<?php echo $slug_v;?>" required></td></tr><tr><th><?php esc_html_e('Description','zc-dmt');?></th><td><textarea name="description" class="large-text" rows="3"><?php echo $desc_v;?></textarea></td></tr><tr><th><?php esc_html_e('Method','zc-dmt');?></th><td><label><input type="radio" name="oecd_method" value="path" <?php checked($method_v,'path');?>> <?php esc_html_e('Dataset Path','zc-dmt');?></label><br><label><input type="radio" name="oecd_method" value="csv" <?php checked($method_v,'csv');?>> <?php esc_html_e('CSV URL','zc-dmt');?></label><br><label><input type="radio" name="oecd_method" value="json" <?php checked($method_v,'json');?>> <?php esc_html_e('JSON URL','zc-dmt');?></label></td></tr><tr id="oecd-path-row"><th><?php esc_html_e('SDMX Path','zc-dmt');?></th><td><input name="oecd_path" class="regular-text" style="min-width:400px;" value="<?php echo $path_v;?>" placeholder="QNA.USA.B1GE.CQRSA.ALL"></td></tr><tr id="oecd-csv-row" style="display:none;"><th><?php esc_html_e('CSV URL','zc-dmt');?></th><td><input name="oecd_csv_url" class="regular-text" value="<?php echo $csv_v;?>"></td></tr><tr id="oecd-json-row" style="display:none;"><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="oecd_json_url" class="regular-text" value="<?php echo $json_v;?>"></td></tr></tbody></table><p><button class="button button-primary"><?php esc_html_e('Create','zc-dmt');?></button></p></form></div>
<div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;"><h2><?php esc_html_e('Test Connection','zc-dmt');?></h2><form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="test_connection"><table class="form-table"><tbody><tr><th><?php esc_html_e('Path','zc-dmt');?></th><td><input name="test_path" class="regular-text"></td></tr><tr id="test-csv-row" style="display:none;"><th><?php esc_html_e('CSV URL','zc-dmt');?></th><td><input name="test_csv_url" class="regular-text"></td></tr><tr id="test-json-row" style="display:none;"><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="test_json_url" class="regular-text"></td></tr></tbody></table><p><button class="button button-secondary"><?php esc_html_e('Test','zc-dmt');?></button></p></form></div>
</div>
<script>jQuery(function($){$('input[name="oecd_method"]').on('change',function(){$('#oecd-path-row,#oecd-csv-row,#oecd-json-row').hide();if(this.value==='path')$('#oecd-path-row').show();if(this.value==='csv')$('#oecd-csv-row').show();if(this.value==='json')$('#oecd-json-row').show();}).change();$('input[name="test_path"]').on('input',function(){$('#test-csv-row,#test-json-row').hide();}).change();});</script>