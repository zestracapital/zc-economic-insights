<?php
/**
 * Yahoo Finance Data Source Form
 * Path: admin/sources-forms/yahoo-finance.php
 *
 * Form for Yahoo Finance data source
 * Supports: Symbol+range+interval OR CSV URL OR JSON URL
 */
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

$notice='';
if(!empty($_POST['zc_source_action'])){
  if(!isset($_POST['zc_source_nonce'])||!wp_verify_nonce($_POST['zc_source_nonce'],'zc_source_action')){
    $notice='<div class="notice notice-error"><p>'.esc_html__('Security check failed.','zc-dmt').'</p></div>';
  } else {
    $act=sanitize_text_field($_POST['zc_source_action']);
    if($act==='add_indicator'){
      $n=sanitize_text_field($_POST['name']??'');
      $s=sanitize_title($_POST['slug']??'');
      $d=wp_kses_post($_POST['description']??'');
      $method=sanitize_text_field($_POST['yf_method']??'symbol');
      $symbol=sanitize_text_field($_POST['yf_symbol']??'');
      $range=sanitize_text_field($_POST['yf_range']??'1y');
      $interval=sanitize_text_field($_POST['yf_interval']??'1d');
      $csv=esc_url_raw($_POST['yf_csv_url']??'');
      $json=esc_url_raw($_POST['yf_json_url']??'');
      if(!$n||!$s){$notice='<div class="notice notice-error"><p>'.esc_html__('Name and Slug required.','zc-dmt').'</p></div>';} 
      elseif($method==='symbol'&&!$symbol){$notice='<div class="notice notice-error"><p>'.esc_html__('Symbol required.','zc-dmt').'</p></div>';} 
      elseif($method==='csv'&&!$csv){$notice='<div class="notice notice-error"><p>'.esc_html__('CSV URL required.','zc-dmt').'</p></div>';} 
      elseif($method==='json'&&!$json){$notice='<div class="notice notice-error"><p>'.esc_html__('JSON URL required.','zc-dmt').'</p></div>';} 
      else{
        $cfg=[];
        if($method==='symbol') $cfg=['symbol'=>$symbol,'range'=>$range,'interval'=>$interval];
        if($method==='csv') $cfg=['csv_url'=>$csv];
        if($method==='json') $cfg=['json_url'=>$json];
        $res=ZC_DMT_Indicators::create_indicator($n,$s,$d,'yahoo-finance',$cfg,1);
        if(is_wp_error($res)) $notice='<div class="notice notice-error"><p>'.esc_html($res->get_error_message()).'</p></div>';
        else{$notice='<div class="notice notice-success is-dismissible"><p>'.esc_html__('Created!','zc-dmt').'</p></div>';$_POST=[];}
      }
    }
    elseif($act==='test_connection'){
      $tm=sanitize_text_field($_POST['test_method']??'symbol');
      $cfg=[];
      if($tm==='symbol'){
        $cfg=['symbol'=>sanitize_text_field($_POST['test_symbol']??''),'range'=>sanitize_text_field($_POST['test_range']??'1y'),'interval'=>sanitize_text_field($_POST['test_interval']??'1d')];
      } elseif($tm==='csv') $cfg=['csv_url'=>esc_url_raw($_POST['test_csv_url']??'')];
      elseif($tm==='json') $cfg=['json_url'=>esc_url_raw($_POST['test_json_url']??'')];
      $ind=(object)['id'=>0,'name'=>'Test','slug'=>'test-yf','source_type'=>'yahoo-finance','source_config'=>wp_json_encode($cfg)];
      $r=ZC_DMT_DataSource_YahooFinance::get_series_for_indicator($ind);
      if(is_wp_error($r))$notice='<div class="notice notice-error"><p><strong>'.esc_html__('Failed:','zc-dmt').'</strong> '.esc_html($r->get_error_message()).'</p></div>';
      else{$c=count($r['series']??[]);$notice='<div class="notice notice-success"><p><strong>'.esc_html__('Success!','zc-dmt').'</strong> '.$c.' points retrieved.</p></div>';}    
    }
  }
}
// form vals
$v=['name'=>esc_attr($_POST['name']??''),'slug'=>esc_attr($_POST['slug']??''),'desc'=>esc_textarea($_POST['description']??''),'sym'=>esc_attr($_POST['yf_symbol']??''),'rng'=>esc_attr($_POST['yf_range']??'1y'),'int'=>esc_attr($_POST['yf_interval']??'1d'),'csv'=>esc_attr($_POST['yf_csv_url']??''),'json'=>esc_attr($_POST['yf_json_url']??'')];
?>
<div class="wrap zc-dmt-source-form">
<h1><?php esc_html_e('Yahoo Finance Source','zc-dmt');?></h1>
<?php echo $notice;?><a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources'));?>" class="page-title-action"><?php esc_html_e('← Back','zc-dmt');?></a><hr>
<div class="zc-source-info" style="background:#f8fafc;padding:16px;border:1px solid #e2e8f0;border-radius:6px;"><h3><?php esc_html_e('About Yahoo Finance','zc-dmt');?></h3><p><?php esc_html_e('Fetch financial data via unofficial Yahoo Finance CSV/JSON or using symbol, range, and interval.', 'zc-dmt');?></p></div>
<div class="zc-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
<div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;"><h2><?php esc_html_e('Add New Indicator','zc-dmt');?></h2><form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="add_indicator"><table class="form-table"><tbody>
<tr><th><?php esc_html_e('Name','zc-dmt');?></th><td><input name="name" class="regular-text" value="<?php echo $v['name'];?>" required></td></tr>
<tr><th><?php esc_html_e('Slug','zc-dmt');?></th><td><input name="slug" class="regular-text" value="<?php echo $v['slug'];?>" required></td></tr>
<tr><th><?php esc_html_e('Description','zc-dmt');?></th><td><textarea name="description" class="large-text" rows="3"><?php echo $v['desc'];?></textarea></td></tr>
<tr><th><?php esc_html_e('Method','zc-dmt');?></th><td>
<label><input type="radio" name="yf_method" value="symbol" checked> Symbol + Range/Interval</label><br>
<label><input type="radio" name="yf_method" value="csv"> CSV URL</label><br>
<label><input type="radio" name="yf_method" value="json"> JSON URL</label>
</td></tr>
<tr id="yf-symbol"><th><?php esc_html_e('Symbol','zc-dmt');?></th><td><input name="yf_symbol" class="regular-text" value="<?php echo $v['sym'];?>" placeholder="AAPL"></td></tr>
<tr id="yf-range"><th><?php esc_html_e('Range','zc-dmt');?></th><td><input name="yf_range" class="regular-text" value="<?php echo $v['rng'];?>" placeholder="1y"></td></tr>
<tr id="yf-interval"><th><?php esc_html_e('Interval','zc-dmt');?></th><td><input name="yf_interval" class="regular-text" value="<?php echo $v['int'];?>" placeholder="1d"></td></tr>
<tr id="yf-csv" style="display:none;"><th><?php esc_html_e('CSV URL','zc-dmt');?></th><td><input name="yf_csv_url" class="regular-text" value="<?php echo $v['csv'];?>"></td></tr>
<tr id="yf-json" style="display:none;"><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="yf_json_url" class="regular-text" value="<?php echo $v['json'];?>"></td></tr>
</tbody></table><p><button class="button button-primary"><?php esc_html_e('Create Indicator','zc-dmt');?></button></p></form></div>
<div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;"><h2><?php esc_html_e('Test Connection','zc-dmt');?></h2><form method="post"><?php wp_nonce_field('zc_source_action','zc_source_nonce');?><input type="hidden" name="zc_source_action" value="test_connection"><table class="form-table"><tbody>
<tr><th><?php esc_html_e('Symbol','zc-dmt');?></th><td><input name="test_symbol" class="regular-text"></td></tr>
<tr><th><?php esc_html_e('Range','zc-dmt');?></th><td><input name="test_range" class="regular-text" placeholder="1y"></td></tr>
<tr><th><?php esc_html_e('Interval','zc-dmt');?></th><td><input name="test_interval" class="regular-text" placeholder="1d"></td></tr>
<tr id="test-csv" style="display:none;"><th><?php esc_html_e('CSV URL','zc-dmt');?></th><td><input name="test_csv_url" class="regular-text"></td></tr>
<tr id="test-json" style="display:none;"><th><?php esc_html_e('JSON URL','zc-dmt');?></th><td><input name="test_json_url" class="regular-text"></td></tr>
</tbody></table><p><button class="button button-secondary"><?php esc_html_e('Test Connection','zc-dmt');?></button></p></form></div>
</div>
<script>jQuery(function($){$('input[name="yf_method"]').on('change',function(){$('#yf-symbol,#yf-range,#yf-interval,#yf-csv,#yf-json').hide();if(this.value==='symbol')$('#yf-symbol,#yf-range,#yf-interval').show();if(this.value==='csv')$('#yf-csv').show();if(this.value==='json')$('#yf-json').show();}).change();});</script>