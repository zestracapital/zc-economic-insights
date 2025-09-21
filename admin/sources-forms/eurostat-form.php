<?php
/**
 * Eurostat Data Source Form
 * Path: admin/sources-forms/eurostat.php
 *
 * Form for Eurostat data source
 * Supports: Dataset Code + Query, JSON URL, CSV URL
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
            $method = sanitize_text_field($_POST['euro_method'] ?? 'dataset');
            $dataset = sanitize_text_field($_POST['euro_dataset'] ?? '');
            $query = sanitize_text_field($_POST['euro_query'] ?? '');
            $json_url = esc_url_raw($_POST['euro_json_url'] ?? '');
            $csv_url = esc_url_raw($_POST['euro_csv_url'] ?? '');
            
            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug required.', 'zc-dmt') . '</p></div>';
            } elseif ($method === 'dataset' && !$dataset) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Dataset code required.', 'zc-dmt') . '</p></div>';
            } elseif ($method === 'json_url' && !$json_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('JSON URL required.', 'zc-dmt') . '</p></div>';
            } elseif ($method === 'csv_url' && !$csv_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('CSV URL required.', 'zc-dmt') . '</p></div>';
            } else {
                $cfg = array();
                if ($method === 'dataset') {
                    $cfg['dataset_code'] = $dataset;
                    if ($query) $cfg['query'] = $query;
                } elseif ($method === 'json_url') {
                    $cfg['json_url'] = $json_url;
                } elseif ($method === 'csv_url') {
                    $cfg['csv_url'] = $csv_url;
                }
                
                $res = ZC_DMT_Indicators::create_indicator($name, $slug, $description, 'eurostat', $cfg, 1);
                if (is_wp_error($res)) {
                    $notice = '<div class="notice notice-error"><p>' . esc_html($res->get_error_message()) . '</p></div>';
                } else {
                    $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Eurostat indicator created!', 'zc-dmt') . '</p></div>';
                    $_POST = array();
                }
            }
        } elseif ($action === 'test_connection') {
            $test_method = sanitize_text_field($_POST['test_method'] ?? 'dataset');
            $test_dataset = sanitize_text_field($_POST['test_dataset'] ?? '');
            $test_query = sanitize_text_field($_POST['test_query'] ?? '');
            $test_json_url = esc_url_raw($_POST['test_json_url'] ?? '');
            $test_csv_url = esc_url_raw($_POST['test_csv_url'] ?? '');
            
            if ($test_method === 'dataset' && !$test_dataset) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Dataset code required to test.', 'zc-dmt') . '</p></div>';
            } else {
                $test_cfg = array();
                if ($test_method === 'dataset') {
                    $test_cfg['dataset_code'] = $test_dataset;
                    if ($test_query) $test_cfg['query'] = $test_query;
                } elseif ($test_method === 'json_url') {
                    $test_cfg['json_url'] = $test_json_url;
                } elseif ($test_method === 'csv_url') {
                    $test_cfg['csv_url'] = $test_csv_url;
                }
                
                $test_ind = (object)[
                    'id' => 0,
                    'name' => 'Test',
                    'slug' => 'test-eurostat',
                    'source_type' => 'eurostat',
                    'source_config' => wp_json_encode($test_cfg)
                ];
                
                if (class_exists('ZC_DMT_DataSource_Eurostat')) {
                    $test_result = ZC_DMT_DataSource_Eurostat::get_series_for_indicator($test_ind);
                    if (is_wp_error($test_result)) {
                        $notice = '<div class="notice notice-error"><p><strong>' . esc_html__('Test Failed:', 'zc-dmt') . '</strong> ' . esc_html($test_result->get_error_message()) . '</p></div>';
                    } else {
                        $count = count($test_result['series']);
                        $notice = '<div class="notice notice-success"><p><strong>' . esc_html__('Success!', 'zc-dmt') . '</strong> ' . $count . ' data points retrieved.</p></div>';
                    }
                } else {
                    $notice = '<div class="notice notice-error"><p>Eurostat data source class not found.</p></div>';
                }
            }
        }
    }
}

// Get form values
$v = [
    'name' => esc_attr($_POST['name'] ?? ''),
    'slug' => esc_attr($_POST['slug'] ?? ''),
    'desc' => esc_textarea($_POST['description'] ?? ''),
    'method' => sanitize_text_field($_POST['euro_method'] ?? 'dataset'),
    'dataset' => esc_attr($_POST['euro_dataset'] ?? ''),
    'query' => esc_attr($_POST['euro_query'] ?? ''),
    'json_url' => esc_attr($_POST['euro_json_url'] ?? ''),
    'csv_url' => esc_attr($_POST['euro_csv_url'] ?? '')
];
?>

<div class="wrap zc-dmt-source-form">
    <h1><?php esc_html_e('Eurostat Data Source', 'zc-dmt'); ?></h1>
    <?php echo $notice; ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action"><?php esc_html_e('← Back', 'zc-dmt'); ?></a>
    <hr>
    
    <div class="zc-source-info" style="background:#f8fafc;padding:16px;border:1px solid #e2e8f0;border-radius:6px;margin:20px 0;">
        <h3><?php esc_html_e('About Eurostat Source', 'zc-dmt'); ?></h3>
        <p><?php esc_html_e('European Statistical Office data via JSON-stat API. Supports dataset codes with queries, direct JSON URLs, or CSV/TSV downloads.', 'zc-dmt'); ?></p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 12px;">
            <div>
                <h4><?php esc_html_e('Methods:', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><strong><?php esc_html_e('Dataset Code:', 'zc-dmt'); ?></strong> <?php esc_html_e('Official Eurostat dataset + optional query', 'zc-dmt'); ?></li>
                    <li><strong><?php esc_html_e('JSON URL:', 'zc-dmt'); ?></strong> <?php esc_html_e('Direct JSON-stat URL', 'zc-dmt'); ?></li>
                    <li><strong><?php esc_html_e('CSV URL:', 'zc-dmt'); ?></strong> <?php esc_html_e('Direct CSV/TSV URL', 'zc-dmt'); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php esc_html_e('Features:', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><?php esc_html_e('JSON-stat format parsing', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('Time dimension detection', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('20 minutes caching', 'zc-dmt'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="zc-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;">
            <h2><?php esc_html_e('Add New Indicator', 'zc-dmt'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('zc_source_action', 'zc_source_nonce'); ?>
                <input type="hidden" name="zc_source_action" value="add_indicator">
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e('Name', 'zc-dmt'); ?></th>
                            <td><input name="name" class="regular-text" value="<?php echo $v['name']; ?>" required></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Slug', 'zc-dmt'); ?></th>
                            <td><input name="slug" class="regular-text" value="<?php echo $v['slug']; ?>" required></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Description', 'zc-dmt'); ?></th>
                            <td><textarea name="description" class="large-text" rows="3"><?php echo $v['desc']; ?></textarea></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Method', 'zc-dmt'); ?></th>
                            <td>
                                <label><input type="radio" name="euro_method" value="dataset" <?php checked($v['method'], 'dataset'); ?>> <?php esc_html_e('Dataset Code', 'zc-dmt'); ?></label><br>
                                <label><input type="radio" name="euro_method" value="json_url" <?php checked($v['method'], 'json_url'); ?>> <?php esc_html_e('JSON URL', 'zc-dmt'); ?></label><br>
                                <label><input type="radio" name="euro_method" value="csv_url" <?php checked($v['method'], 'csv_url'); ?>> <?php esc_html_e('CSV/TSV URL', 'zc-dmt'); ?></label>
                            </td>
                        </tr>
                        <tr id="euro-dataset-row">
                            <th><?php esc_html_e('Dataset Code', 'zc-dmt'); ?></th>
                            <td>
                                <input name="euro_dataset" class="regular-text" value="<?php echo $v['dataset']; ?>" placeholder="nama_10_gdp">
                                <p class="description"><?php esc_html_e('Official Eurostat dataset code', 'zc-dmt'); ?></p>
                            </td>
                        </tr>
                        <tr id="euro-query-row">
                            <th><?php esc_html_e('Query (Optional)', 'zc-dmt'); ?></th>
                            <td>
                                <input name="euro_query" class="regular-text" value="<?php echo $v['query']; ?>" placeholder="na_item=B1GQ&geo=DE&unit=CP_MEUR&time=2019:2021">
                                <p class="description"><?php esc_html_e('Optional query parameters for filtering', 'zc-dmt'); ?></p>
                            </td>
                        </tr>
                        <tr id="euro-json-row" style="display:none;">
                            <th><?php esc_html_e('JSON URL', 'zc-dmt'); ?></th>
                            <td><input name="euro_json_url" class="regular-text" style="min-width:400px;" value="<?php echo $v['json_url']; ?>"></td>
                        </tr>
                        <tr id="euro-csv-row" style="display:none;">
                            <th><?php esc_html_e('CSV/TSV URL', 'zc-dmt'); ?></th>
                            <td><input name="euro_csv_url" class="regular-text" style="min-width:400px;" value="<?php echo $v['csv_url']; ?>"></td>
                        </tr>
                    </tbody>
                </table>
                
                <p><button class="button button-primary"><?php esc_html_e('Create Indicator', 'zc-dmt'); ?></button></p>
            </form>
        </div>
        
        <div class="zc-card" style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:6px;">
            <h2><?php esc_html_e('Test Connection', 'zc-dmt'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('zc_source_action', 'zc_source_nonce'); ?>
                <input type="hidden" name="zc_source_action" value="test_connection">
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e('Method', 'zc-dmt'); ?></th>
                            <td>
                                <select name="test_method" id="test_method">
                                    <option value="dataset"><?php esc_html_e('Dataset Code', 'zc-dmt'); ?></option>
                                    <option value="json_url"><?php esc_html_e('JSON URL', 'zc-dmt'); ?></option>
                                    <option value="csv_url"><?php esc_html_e('CSV/TSV URL', 'zc-dmt'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr id="test-dataset-row">
                            <th><?php esc_html_e('Dataset Code', 'zc-dmt'); ?></th>
                            <td><input name="test_dataset" class="regular-text" placeholder="nama_10_gdp"></td>
                        </tr>
                        <tr id="test-query-row">
                            <th><?php esc_html_e('Query', 'zc-dmt'); ?></th>
                            <td><input name="test_query" class="regular-text" placeholder="na_item=B1GQ&geo=DE"></td>
                        </tr>
                        <tr id="test-json-row" style="display:none;">
                            <th><?php esc_html_e('JSON URL', 'zc-dmt'); ?></th>
                            <td><input name="test_json_url" class="regular-text"></td>
                        </tr>
                        <tr id="test-csv-row" style="display:none;">
                            <th><?php esc_html_e('CSV/TSV URL', 'zc-dmt'); ?></th>
                            <td><input name="test_csv_url" class="regular-text"></td>
                        </tr>
                    </tbody>
                </table>
                
                <p><button class="button button-secondary"><?php esc_html_e('Test Connection', 'zc-dmt'); ?></button></p>
            </form>
            
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <h3><?php esc_html_e('Example Dataset Codes:', 'zc-dmt'); ?></h3>
                <div style="margin: 12px 0;">
                    <strong>nama_10_gdp:</strong> <?php esc_html_e('GDP and main components', 'zc-dmt'); ?><br>
                    <strong>prc_hicp_midx:</strong> <?php esc_html_e('HICP inflation rates', 'zc-dmt'); ?><br>
                    <strong>une_rt_m:</strong> <?php esc_html_e('Unemployment rates', 'zc-dmt'); ?><br>
                    <strong>demo_pjan:</strong> <?php esc_html_e('Population statistics', 'zc-dmt'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Method selection for main form
    $('input[name="euro_method"]').on('change', function() {
        $('#euro-dataset-row, #euro-query-row, #euro-json-row, #euro-csv-row').hide();
        if ($(this).val() === 'dataset') {
            $('#euro-dataset-row, #euro-query-row').show();
        } else if ($(this).val() === 'json_url') {
            $('#euro-json-row').show();
        } else if ($(this).val() === 'csv_url') {
            $('#euro-csv-row').show();
        }
    }).change();
    
    // Test method selection
    $('#test_method').on('change', function() {
        $('#test-dataset-row, #test-query-row, #test-json-row, #test-csv-row').hide();
        if ($(this).val() === 'dataset') {
            $('#test-dataset-row, #test-query-row').show();
        } else if ($(this).val() === 'json_url') {
            $('#test-json-row').show();
        } else if ($(this).val() === 'csv_url') {
            $('#test-csv-row').show();
        }
    }).change();
    
    // Auto-generate slug from name
    $('input[name="name"]').on('input', function() {
        var name = $(this).val();
        var slug = name.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
        $('input[name="slug"]').val(slug);
    });
    
    // Copy from main form to test form
    $('input[name="euro_dataset"]').on('input', function() {
        $('input[name="test_dataset"]').val($(this).val());
    });
    $('input[name="euro_query"]').on('input', function() {
        $('input[name="test_query"]').val($(this).val());
    });
    $('input[name="euro_json_url"]').on('input', function() {
        $('input[name="test_json_url"]').val($(this).val());
    });
    $('input[name="euro_csv_url"]').on('input', function() {
        $('input[name="test_csv_url"]').val($(this).val());
    });
});
</script>

<style>
.zc-dmt-source-form .zc-card {
    transition: box-shadow 0.2s ease;
}

.zc-dmt-source-form .zc-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.zc-source-info h3 {
    color: #0f172a;
    margin-top: 0;
}

.zc-source-info h4 {
    color: #334155;
    margin: 8px 0 4px 0;
}

.zc-source-info ul {
    list-style-type: disc;
}

.zc-source-info li {
    margin-bottom: 4px;
}

@media (max-width: 768px) {
    .zc-grid {
        grid-template-columns: 1fr !important;
    }
    
    .zc-source-info > div {
        grid-template-columns: 1fr !important;
    }
}
</style>