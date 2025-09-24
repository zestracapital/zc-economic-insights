<?php
/**
 * Universal JSON Data Source Form
 * Path: admin/sources-forms/universal-json.php
 *
 * Form for any JSON URL data source
 * Supports: JSON URL, Root Path, Date Key, Value Key
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
      $url=esc_url_raw($_POST['uj_json_url']??'');
      $root=sanitize_text_field($_POST['uj_root']??'');
      $date=sanitize_text_field($_POST['uj_date_key']??'');
      $val=sanitize_text_field($_POST['uj_value_key']??'');
      if(!$n||!$s){$notice='<div class="notice notice-error"><p>'.esc_html__('Name/slug required.','zc-dmt').'</p></div>';} 
      elseif(!$url){$notice='<div class="notice notice-error"><p>'.esc_html__('JSON URL required.','zc-dmt').'</p></div>';} 
      else{
        $cfg=['json_url'=>$url];
        if($root)$cfg['root']=$root;
        if($date)$cfg['date_key']=$date;
        if($val)$cfg['value_key']=$val;
        $res=ZC_DMT_Indicators::create_indicator($n,$s,$d,'universal-json',$cfg,1);
        if(is_wp_error($res))$notice='<div class="notice notice-error"><p>'.esc_html($res->get_error_message()).'</p></div>';
        else{$notice='<div class="notice notice-success is-dismissible"><p>'.esc_html__('Created!','zc-dmt').'</p></div>';$_POST=[];}
      }
    }
    elseif($act==='test_connection'){
      $tj=esc_url_raw($_POST['test_json_url']??'');
      $tr=sanitize_text_field($_POST['test_root']??'');
      $td=sanitize_text_field($_POST['test_date_key']??'');
      $tv=sanitize_text_field($_POST['test_value_key']??'');
      if(!$tj){$notice='<div class="notice notice-error"><p>'.esc_html__('JSON URL required.','zc-dmt').'</p></div>';} else{
        $cfg=['json_url'=>$tj];if($tr)$cfg['root']=$tr;if($td)$cfg['date_key']=$td;if($tv)$cfg['value_key']=$tv;
        $ind=(object)['id'=>0,'name'=>'Test','slug'=>'test-uj','source_type'=>'universal-json','source_config'=>wp_json_encode($cfg)];
        $res=ZC_DMT_DataSource_UniversalJSON::get_series_for_indicator($ind);
        if(is_wp_error($res))$notice='<div class="notice notice-error"><p><strong>'.esc_html__('Failed:','zc-dmt').'</strong> '.esc_html($res->get_error_message()).'</p></div>';
        else{$count=count($res);$notice='<div class="notice notice-success"><p><strong>'.esc_html__('Success!','zc-dmt').'</strong> '.$count.' points.</p></div>';}      }
    }
  }
}
$v=['name'=>esc_attr($_POST['name']??''),'slug'=>esc_attr($_POST['slug']??''),'desc'=>esc_textarea($_POST['description']??''),'url'=>esc_attr($_POST['uj_json_url']??''),'root'=>esc_attr($_POST['uj_root']??''),'date'=>esc_attr($_POST['uj_date_key']??''),'val'=>esc_attr($_POST['uj_value_key']??'')];
?>
<div class="wrap zc-dmt-source-form">
<h1><?php esc_html_e('Universal JSON Data Source','zc-dmt');?></h1>
<?php echo $notice;?><a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources'));?>" class="page-title-action"><?php esc_html_e('← Back','zc-dmt');?></a><hr>
<div class="zc-source-info" style="background:#f8fafc;padding:16px;border:1px solid #e2e8f0;border-radius:6px;"><h3><?php esc_html_e('About Universal JSON','zc-dmt');?></h3><p><?php esc_html_e('Fetch time-series from any JSON endpoint by specifying root path, date and value keys.', 'zc-dmt');?></p></div>
<div class="zc-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
<div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;"><h2><?php esc_html_e('Add New Indicator','zc-dmt');?></h2><form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="add_indicator"><table class="form-table"><tbody>
<tr><th><?php esc_html_e('Name','zc-dmt');?></th><td><input name="name" class="regular-text" value="<?php echo $v['name'];?>" required></td></tr>
<tr><th><?php esc_html_e('Slug','zc-dmt');?></th><td><input name="slug" class="regular-text" value="<?php echo $v['slug'];?>" required></td></tr>
<tr><th><?php esc_html_e('Description','zc-dmt');?></th><td><textarea name="description" class="large-text" rows="3"><?php echo $v['desc'];?></textarea></td></tr>
<tr><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="uj_json_url" class="regular-text" value="<?php echo $v['url'];?>" placeholder="https://api.example.com/data.json"></td></tr>
<tr><th><?php esc_html_e('Root Path','zc-dmt');?></th><td><input name="uj_root" class="regular-text" value="<?php echo $v['root'];?>" placeholder="data.items"></td></tr>
<tr><th><?php esc_html_e('Date Key','zc-dmt');?></th><td><input name="uj_date_key" class="regular-text" value="<?php echo $v['date'];?>" placeholder="date"></td></tr>
<tr><th><?php esc_html_e('Value Key','zc-dmt');?></th><td><input name="uj_value_key" class="regular-text" value="<?php echo $v['val'];?>" placeholder="value"></td></tr>
</tbody></table><p><button class="button button-primary"><?php esc_html_e('Create Indicator','zc-dmt');?></button></p></form></div>
<div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;"><h2><?php esc_html_e('Test Connection','zc-dmt');?></h2><form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="test_connection"><table class="form-table"><tbody>
<tr><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="test_json_url" class="regular-text"></td></tr>
<tr><th><?php esc_html_e('Root Path','zc-dmt');?></th><td><input name="test_root" class="regular-text" placeholder="data.items"></td></tr>
<tr><th><?php esc_html_e('Date Key','zc-dmt');?></th><td><input name="test_date_key" class="regular-text" placeholder="date"></td></tr>
<tr><th><?php esc_html_e('Value Key','zc-dmt');?></th><td><input name="test_value_key" class="regular-text" placeholder="value"></td></tr>
</tbody></table><p><button class="button button-secondary"><?php esc_html_e('Test Connection','zc-dmt');?></button></p></form></div>
</div>
<script>jQuery(function($){$('input[name="uj_json_url"]').on('input',function(){$('input[name="test_json_url"]').val($(this).val());});});</script>