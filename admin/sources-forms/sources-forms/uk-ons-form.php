<?php
/**
 * UK ONS Data Source Form
 * Path: admin/sources-forms/uk-ons.php
 *
 * Form for UK Office for National Statistics data source
 * Supports: JSON URL, CSV URL, Timeseries (dataset+series)
 */
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

$notice='';
if(!empty($_POST['zc_source_action'])){
  if(!isset($_POST['zc_source_nonce'])||!wp_verify_nonce($_POST['zc_source_nonce'],'zc_source_action')){
    $notice='<div class="notice notice-error"><p>'.esc_html__('Security check failed.','zc-dmt').'</p></div>';
  }else{
    $act=sanitize_text_field($_POST['zc_source_action']);
    if($act==='add_indicator'){
      $n=sanitize_text_field($_POST['name']??'');
      $s=sanitize_title($_POST['slug']??'');
      $d=wp_kses_post($_POST['description']??'');
      $method=sanitize_text_field($_POST['ons_method']??'json');
      $json=esc_url_raw($_POST['ons_json_url']??'');
      $csv=esc_url_raw($_POST['ons_csv_url']??'');
      $ts_code=sanitize_text_field($_POST['ons_timeseries']??'');
      if(!$n||!$s){$notice='<div class="notice notice-error"><p>'.esc_html__('Name/Slug required.','zc-dmt').'</p></div>';} 
      elseif($method==='timeseries'&&!$ts_code){$notice='<div class="notice notice-error"><p>'.esc_html__('Timeseries code required.','zc-dmt').'</p></div>';} 
      elseif($method==='json'&&!$json){$notice='<div class="notice notice-error"><p>'.esc_html__('JSON URL required.','zc-dmt').'</p></div>';} 
      elseif($method==='csv'&&!$csv){$notice='<div class="notice notice-error"><p>'.esc_html__('CSV URL required.','zc-dmt').'</p></div>';} 
      else{
        $cfg=[];
        if($method==='timeseries') $cfg=['timeseries'=>$ts_code];
        if($method==='json') $cfg=['json_url'=>$json];
        if($method==='csv') $cfg=['csv_url'=>$csv];
        $res=ZC_DMT_Indicators::create_indicator($n,$s,$d,'uk-ons',$cfg,1);
        if(is_wp_error($res)) $notice='<div class="notice notice-error"><p>'.esc_html($res->get_error_message()).'</p></div>';
        else{$notice='<div class="notice notice-success is-dismissible"><p>'.esc_html__('Created!','zc-dmt').'</p></div>';$_POST=[];}
      }
    }
  }
}
// values
$v=['name'=>esc_attr($_POST['name']??''),'slug'=>esc_attr($_POST['slug']??''),'desc'=>esc_textarea($_POST['description']??''),'json'=>esc_attr($_POST['ons_json_url']??''),'csv'=>esc_attr($_POST['ons_csv_url']??''),'ts'=>esc_attr($_POST['ons_timeseries']??'')];
?>
<div class="wrap zc-dmt-source-form">
<h1><?php esc_html_e('UK ONS Source','zc-dmt');?></h1>
<?php echo $notice;?><a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources'));?>" class="page-title-action"><?php esc_html_e('← Back','zc-dmt');?></a><hr>
<div class="zc-source-info" style="background:#f8fafc;padding:16px;border:1px solid #e2e8f0;border-radius:6px;"><h3><?php esc_html_e('About UK ONS','zc-dmt');?></h3><p><?php esc_html_e('Fetch UK data via ONS API JSON, CSV, or timeseries dataset+series code.', 'zc-dmt');?></p></div>
<div class="zc-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
<div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;"><h2><?php esc_html_e('Add New Indicator','zc-dmt');?></h2><form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="add_indicator"><table class="form-table"><tbody>
<tr><th><?php esc_html_e('Name','zc-dmt');?></th><td><input name="name" class="regular-text" value="<?php echo $v['name'];?>" required></td></tr>
<tr><th><?php esc_html_e('Slug','zc-dmt');?></th><td><input name="slug" class="regular-text" value="<?php echo $v['slug'];?>" required></td></tr>
<tr><th><?php esc_html_e('Description','zc-dmt');?></th><td><textarea name="description" class="large-text" rows="3"><?php echo $v['desc'];?></textarea></td></tr>
<tr><th><?php esc_html_e('Method','zc-dmt');?></th><td>
<label><input type="radio" name="ons_method" value="json" <?php checked($v['method']??'json','json');?>> JSON URL</label><br>
<label><input type="radio" name="ons_method" value="csv" <?php checked($v['method'],'csv');?>> CSV URL</label><br>
<label><input type="radio" name="ons_method" value="timeseries" <?php checked($v['method'],'timeseries');?>> Timeseries Code</label>
</td></tr>
<tr id="ons-json-row"><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="ons_json_url" class="regular-text" value="<?php echo $v['json'];?>"></td></tr>
<tr id="ons-csv-row" style="display:none;"><th><?php esc_html_e('CSV URL','zc-dmt');?></th><td><input name="ons_csv_url" class="regular-text" value="<?php echo $v['csv'];?>"></td></tr>
<tr id="ons-ts-row" style="display:none;"><th><?php esc_html_e('Timeseries Code','zc-dmt');?></th><td><input name="ons_timeseries" class="regular-text" value="<?php echo $v['ts'];?>" placeholder="dataset=pn2|series=mgsx"></td></tr>
</tbody></table><p><button class="button button-primary"><?php esc_html_e('Create Indicator','zc-dmt');?></button></p></form></div>
<div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;"><h2><?php esc_html_e('Test Connection','zc-dmt');?></h2><form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="test_connection"><table class="form-table"><tbody>
<tr id="test-json"><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="test_json_url" class="regular-text"></td></tr>
<tr id="test-csv" style="display:none;"><th><?php esc_html_e('CSV URL','zc-dmt');?></th><td><input name="test_csv_url" class="regular-text"></td></tr>
<tr id="test-ts" style="display:none;"><th><?php esc_html_e('Timeseries Code','zc-dmt');?></th><td><input name="test_timeseries" class="regular-text"></td></tr>
</tbody></table><p><button class="button button-secondary"><?php esc_html_e('Test Connection','zc-dmt');?></button></p></form></div>
</div>
<script>jQuery(function($){$('input[name="ons_method"]').on('change',function(){$('#ons-json-row,#ons-csv-row,#ons-ts-row').hide();if(this.value==='json')$('#ons-json-row').show();if(this.value==='csv')$('#ons-csv-row').show();if(this.value==='timeseries')$('#ons-ts-row').show();}).change();});</script>