<?php
/**
 * World Bank Data Source Form
 * Path: admin/sources-forms/world-bank.php
 *
 * Form for World Bank Open Data API
 * Supports: Country Code + Indicator Code
 */
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

$notice='';
if(!empty($_POST['zc_source_action'])){
  if(!isset($_POST['zc_source_nonce'])||!wp_verify_nonce($_POST['zc_source_nonce'],'zc_source_action')){
    $notice='<div class="notice notice-error"><p>'.esc_html__('Security check failed.','zc-dmt').'</p></div>';
  } else{
    $act=sanitize_text_field($_POST['zc_source_action']);
    if($act==='add_indicator'){
      $n=sanitize_text_field($_POST['name']??'');
      $s=sanitize_title($_POST['slug']??'');
      $d=wp_kses_post($_POST['description']??'');
      $country=sanitize_text_field($_POST['wb_country']??'');
      $indicator=sanitize_text_field($_POST['wb_indicator']??'');
      if(!$n||!$s){$notice='<div class="notice notice-error"><p>'.esc_html__('Name/Slug required.','zc-dmt').'</p></div>';} 
      elseif(!$country||!$indicator){$notice='<div class="notice notice-error"><p>'.esc_html__('Country and Indicator codes required.','zc-dmt').'</p></div>';} 
      else{
        $cfg=['country_code'=>$country,'indicator_code'=>$indicator];
        $res=ZC_DMT_Indicators::create_indicator($n,$s,$d,'world-bank',$cfg,1);
        if(is_wp_error($res))$notice='<div class="notice notice-error"><p>'.esc_html($res->get_error_message()).'</p></div>';
        else{$notice='<div class="notice notice-success is-dismissible"><p>'.esc_html__('Created!','zc-dmt').'</p></div>';$_POST=[];}
      }
    }
  }
}
$v=['name'=>esc_attr($_POST['name']??''),'slug'=>esc_attr($_POST['slug']??''),'desc'=>esc_textarea($_POST['description']??''),'country'=>esc_attr($_POST['wb_country']??''),'indicator'=>esc_attr($_POST['wb_indicator']??'')];
?>
<div class="wrap zc-dmt-source-form">
<h1><?php esc_html_e('World Bank Data Source','zc-dmt');?></h1>
<?php echo $notice;?><a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources'));?>" class="page-title-action"><?php esc_html_e('← Back','zc-dmt');?></a><hr>
<div class="zc-source-info" style="background:#f8fafc;padding:16px;border:1px solid #e2e8f0;border-radius:6px;"><h3><?php esc_html_e('About World Bank','zc-dmt');?></h3><p><?php esc_html_e('Fetch global development data using World Bank Open Data API. Specify country and indicator codes.', 'zc-dmt');?></p></div>
<div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;width:50%;">
<h2><?php esc_html_e('Add New Indicator','zc-dmt');?></h2>
<form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="add_indicator">
<table class="form-table"><tbody>
<tr><th><?php esc_html_e('Name','zc-dmt');?></th><td><input name="name" class="regular-text" value="<?php echo $v['name'];?>" required></td></tr>
<tr><th><?php esc_html_e('Slug','zc-dmt');?></th><td><input name="slug" class="regular-text" value="<?php echo $v['slug'];?>" required></td></tr>
<tr><th><?php esc_html_e('Description','zc-dmt');?></th><td><textarea name="description" class="large-text" rows="3"><?php echo $v['desc'];?></textarea></td></tr>
<tr><th><?php esc_html_e('Country Code','zc-dmt');?></th><td><input name="wb_country" class="regular-text" value="<?php echo $v['country'];?>" placeholder="USA"></td></tr>
<tr><th><?php esc_html_e('Indicator Code','zc-dmt');?></th><td><input name="wb_indicator" class="regular-text" value="<?php echo $v['indicator'];?>" placeholder="NY.GDP.MKTP.CD"></td></tr>
</tbody></table>
<p><button class="button button-primary"><?php esc_html_e('Create Indicator','zc-dmt');?></button></p>
</form></div>
</div>