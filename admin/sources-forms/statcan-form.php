<?php
/**
 * Statistics Canada Data Source Form
 * Path: admin/sources-forms/statcan.php
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
      $json=esc_url_raw($_POST['statcan_json_url']??'');
      $csv=esc_url_raw($_POST['statcan_csv_url']??'');
      if(!$n||!$s){$notice='<div class="notice notice-error"><p>'.esc_html__('Name/slug required.','zc-dmt').'</p></div>';} 
      elseif(!$json&&!$csv){$notice='<div class="notice notice-error"><p>'.esc_html__('Provide JSON or CSV URL.','zc-dmt').'</p></div>';} 
      else{
        $cfg=[]; if($json)$cfg['json_url']=$json; if($csv)$cfg['csv_url']=$csv;
        $res=ZC_DMT_Indicators::create_indicator($n,$s,$d,'statcan',$cfg,1);
        if(is_wp_error($res))$notice='<div class="notice notice-error"><p>'.esc_html($res->get_error_message()).'</p></div>';
        else{$notice='<div class="notice notice-success is-dismissible"><p>'.esc_html__('Created!','zc-dmt').'</p></div>';$_POST=[];}
      }
    }
    elseif($act==='test_connection'){
      $tj=esc_url_raw($_POST['test_json_url']??'');
      $tc=esc_url_raw($_POST['test_csv_url']??'');
      if(!$tj&&!$tc){$notice='<div class="notice notice-error"><p>'.esc_html__('Provide URL to test.','zc-dmt').'</p></div>';} else{
        $cfg=[]; if($tj)$cfg['json_url']=$tj; else $cfg['csv_url']=$tc;
        $ind=(object)['id'=>0,'name'=>'Test','slug'=>'test-sc','source_type'=>'statcan','source_config'=>wp_json_encode($cfg)];
        $r=ZC_DMT_DataSource_StatCan::get_series_for_indicator($ind);
        if(is_wp_error($r))$notice='<div class="notice notice-error"><p><strong>'.esc_html__('Failed:','zc-dmt').'</strong> '.esc_html($r->get_error_message()).'</p></div>';
        else{$c=count($r['series']??[]);$notice='<div class="notice notice-success"><p><strong>'.esc_html__('Success!','zc-dmt').'</strong> '.$c.' points.</p></div>';}      }
    }
  }
}
// form vals
$fv=['name'=>esc_attr($_POST['name']??''),'slug'=>esc_attr($_POST['slug']??''),'desc'=>esc_textarea($_POST['description']??''),'json'=>esc_attr($_POST['statcan_json_url']??''),'csv'=>esc_attr($_POST['statcan_csv_url']??'')];
?>
<div class="wrap zc-dmt-source-form">
  <h1><?php esc_html_e('Statistics Canada Source','zc-dmt');?></h1>
  <?php echo $notice;?><a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources'));?>" class="page-title-action"><?php esc_html_e('← Back','zc-dmt');?></a><hr>
  <div class="zc-source-info" style="background:#f8fafc;padding:16px;border:1px solid #e2e8f0;border-radius:6px;"><h3><?php esc_html_e('About StatCan','zc-dmt');?></h3><p><?php esc_html_e('Fetch data via JSON CSV from Statistics Canada Web Data Service or Open Data portal.', 'zc-dmt');?></p></div>
  <div class="zc-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
    <div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;"><h2><?php esc_html_e('Add New Indicator','zc-dmt');?></h2>
      <form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="add_indicator"><table class="form-table"><tbody>
        <tr><th><?php esc_html_e('Name','zc-dmt');?></th><td><input name="name" class="regular-text" value="<?php echo $fv['name'];?>" required></td></tr>
        <tr><th><?php esc_html_e('Slug','zc-dmt');?></th><td><input name="slug" class="regular-text" value="<?php echo $fv['slug'];?>" required></td></tr>
        <tr><th><?php esc_html_e('Description','zc-dmt');?></th><td><textarea name="description" class="large-text" rows="3"><?php echo $fv['desc'];?></textarea></td></tr>
        <tr><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="statcan_json_url" class="regular-text" value="<?php echo $fv['json'];?>" placeholder="https://www150.statcan.gc.ca/.../getDataObject.json"></td></tr>
        <tr><th><?php esc_html_e('CSV URL','zc-dmt');?></th><td><input name="statcan_csv_url" class="regular-text" value="<?php echo $fv['csv'];?>" placeholder="https://www150.statcan.gc.ca/.../download.csv"></td></tr>
      </tbody></table><p><button class="button button-primary"><?php esc_html_e('Create Indicator','zc-dmt');?></button></p></form></div>
    <div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;"><h2><?php esc_html_e('Test Connection','zc-dmt');?></h2>
      <form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="test_connection"><table class="form-table"><tbody>
        <tr><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="test_json_url" class="regular-text"></td></tr>
        <tr><th><?php esc_html_e('CSV URL','zc-dmt');?></th><td><input name="test_csv_url" class="regular-text"></td></tr>
      </tbody></table><p><button class="button button-secondary"><?php esc_html_e('Test Connection','zc-dmt');?></button></p></form></div>
  </div>
</div>
<script>jQuery(function($){$('input[name="statcan_json_url"]').on('input',function(){$('input[name="test_json_url"]').val($(this).val());});$('input[name="statcan_csv_url"]').on('input',function(){$('input[name="test_csv_url"]').val($(this).val());});});</script>