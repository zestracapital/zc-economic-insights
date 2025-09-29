<?php
/**
 * Nasdaq Data Link (Quandl) Data Source Form
 * Path: admin/sources-forms/nasdaq-data-link.php
 *
 * Form for Nasdaq Data Link (formerly Quandl) data source
 * Supports: Dataset Code + API Key (optional), CSV URL, JSON URL
 */

if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

// Check if API key is configured
$nasdaq_api_key = get_option('zc_nasdaq_api_key', '');
$api_key_configured = !empty($nasdaq_api_key);

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
            $method = sanitize_text_field($_POST['nasdaq_method'] ?? 'dataset');
            $dataset_code = sanitize_text_field($_POST['nasdaq_dataset'] ?? '');
            $csv_url = esc_url_raw($_POST['nasdaq_csv_url'] ?? '');
            $json_url = esc_url_raw($_POST['nasdaq_json_url'] ?? '');
            
            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug required.', 'zc-dmt') . '</p></div>';
            } elseif ($method === 'dataset' && !$dataset_code) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Dataset code required.', 'zc-dmt') . '</p></div>';
            } elseif ($method === 'csv_url' && !$csv_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('CSV URL required.', 'zc-dmt') . '</p></div>';
            } elseif ($method === 'json_url' && !$json_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('JSON URL required.', 'zc-dmt') . '</p></div>';
            } else {
                $cfg = array();
                if ($method === 'dataset') {
                    $cfg['dataset_code'] = $dataset_code;
                } elseif ($method === 'csv_url') {
                    $cfg['csv_url'] = $csv_url;
                } elseif ($method === 'json_url') {
                    $cfg['json_url'] = $json_url;
                }
                
                $res = ZC_DMT_Indicators::create_indicator($name, $slug, $description, 'nasdaq-data-link', $cfg, 1);
                if (is_wp_error($res)) {
                    $notice = '<div class="notice notice-error"><p>' . esc_html($res->get_error_message()) . '</p></div>';
                } else {
                    $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Nasdaq Data Link indicator created!', 'zc-dmt') . '</p></div>';
                    $_POST = array();
                }
            }
        } elseif ($action === 'test_connection') {
            $test_method = sanitize_text_field($_POST['test_method'] ?? 'dataset');
            $test_dataset = sanitize_text_field($_POST['test_dataset'] ?? '');
            $test_csv_url = esc_url_raw($_POST['test_csv_url'] ?? '');
            $test_json_url = esc_url_raw($_POST['test_json_url'] ?? '');
            
            if ($test_method === 'dataset' && !$test_dataset) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Dataset code required to test.', 'zc-dmt') . '</p></div>';
            } else {
                $test_cfg = array();
                if ($test_method === 'dataset') {
                    $test_cfg['dataset_code'] = $test_dataset;
                } elseif ($test_method === 'csv_url') {
                    $test_cfg['csv_url'] = $test_csv_url;
                } elseif ($test_method === 'json_url') {
                    $test_cfg['json_url'] = $test_json_url;
                }
                
                $test_ind = (object)[
                    'id' => 0,
                    'name' => 'Test',
                    'slug' => 'test-nasdaq',
                    'source_type' => 'nasdaq-data-link',
                    'source_config' => wp_json_encode($test_cfg)
                ];
                
                if (class_exists('ZC_DMT_DataSource_NasdaqDataLink')) {
                    $test_result = ZC_DMT_DataSource_NasdaqDataLink::get_series_for_indicator($test_ind);
                    if (is_wp_error($test_result)) {
                        $notice = '<div class="notice notice-error"><p><strong>' . esc_html__('Test Failed:', 'zc-dmt') . '</strong> ' . esc_html($test_result->get_error_message()) . '</p></div>';
                    } else {
                        $count = count($test_result['series']);
                        $notice = '<div class="notice notice-success"><p><strong>' . esc_html__('Success!', 'zc-dmt') . '</strong> ' . $count . ' data points retrieved.</p></div>';
                    }
                } else {
                    $notice = '<div class="notice notice-error"><p>Nasdaq Data Link source class not found.</p></div>';
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
    'method' => sanitize_text_field($_POST['nasdaq_method'] ?? 'dataset'),
    'dataset' => esc_attr($_POST['nasdaq_dataset'] ?? ''),
    'csv_url' => esc_attr($_POST['nasdaq_csv_url'] ?? ''),
    'json_url' => esc_attr($_POST['nasdaq_json_url'] ?? '')
];
?>

<div class="wrap zc-dmt-source-form">
    <h1><?php esc_html_e('Nasdaq Data Link (formerly Quandl) Source', 'zc-dmt'); ?></h1>
    <?php echo $notice; ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action"><?php esc_html_e('← Back', 'zc-dmt'); ?></a>
    <hr>
    
    <!-- API Key Status Alert -->
    <?php if (!$api_key_configured) : ?>
        <div class="notice notice-info" style="margin: 20px 0;">
            <p>
                <strong><?php esc_html_e('API Key Optional:', 'zc-dmt'); ?></strong>
                <?php printf(
                    esc_html__('Nasdaq Data Link has a free tier. Configure your API key in %s for higher rate limits.', 'zc-dmt'),
                    '<a href="' . esc_url(admin_url('admin.php?page=zc-dmt-settings')) . '">' . esc_html__('Plugin Settings', 'zc-dmt') . '</a>'
                ); ?>
            </p>
            <p>
                <a href="https://data.nasdaq.com/sign-up" target="_blank" class="button button-secondary">
                    <?php esc_html_e('Get Free Nasdaq API Key', 'zc-dmt'); ?> ↗
                </a>
            </p>
        </div>
    <?php else : ?>
        <div class="notice notice-success" style="margin: 20px 0;">
            <p>
                <strong><?php esc_html_e('API Key Configured:', 'zc-dmt'); ?></strong>
                <?php esc_html_e('Nasdaq Data Link API key is configured for higher rate limits.', 'zc-dmt'); ?>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="zc-source-info" style="background:#f8fafc;padding:16px;border:1px solid #e2e8f0;border-radius:6px;margin:20px 0;">
        <h3><?php esc_html_e('About Nasdaq Data Link', 'zc-dmt'); ?></h3>
        <p><?php esc_html_e('Financial and economic data provider (formerly Quandl). Offers free tier with 50 API calls per day, premium datasets require subscription.', 'zc-dmt'); ?></p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 12px;">
            <div>
                <h4><?php esc_html_e('Free Tier Includes:', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><?php esc_html_e('50 API calls per day', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('Core financial datasets', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('CSV/JSON downloads', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('Historical data access', 'zc-dmt'); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php esc_html_e('Methods:', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><strong><?php esc_html_e('Dataset Code:', 'zc-dmt'); ?></strong> <?php esc_html_e('Official dataset identifier', 'zc-dmt'); ?></li>
                    <li><strong><?php esc_html_e('CSV URL:', 'zc-dmt'); ?></strong> <?php esc_html_e('Direct CSV download', 'zc-dmt'); ?></li>
                    <li><strong><?php esc_html_e('JSON URL:', 'zc-dmt'); ?></strong> <?php esc_html_e('Direct JSON API', 'zc-dmt'); ?></li>
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
                                <label><input type="radio" name="nasdaq_method" value="dataset" <?php checked($v['method'], 'dataset'); ?>> <?php esc_html_e('Dataset Code', 'zc-dmt'); ?></label><br>
                                <label><input type="radio" name="nasdaq_method" value="csv_url" <?php checked($v['method'], 'csv_url'); ?>> <?php esc_html_e('CSV URL', 'zc-dmt'); ?></label><br>
                                <label><input type="radio" name="nasdaq_method" value="json_url" <?php checked($v['method'], 'json_url'); ?>> <?php esc_html_e('JSON URL', 'zc-dmt'); ?></label>
                            </td>
                        </tr>
                        <tr id="nasdaq-dataset-row">
                            <th><?php esc_html_e('Dataset Code', 'zc-dmt'); ?></th>
                            <td>
                                <input name="nasdaq_dataset" class="regular-text" value="<?php echo $v['dataset']; ?>" placeholder="WIKI/AAPL">
                                <p class="description"><?php esc_html_e('Format: DATABASE/DATASET (e.g., WIKI/AAPL, FRED/GDP)', 'zc-dmt'); ?></p>
                            </td>
                        </tr>
                        <tr id="nasdaq-csv-row" style="display:none;">
                            <th><?php esc_html_e('CSV URL', 'zc-dmt'); ?></th>
                            <td><input name="nasdaq_csv_url" class="regular-text" style="min-width:400px;" value="<?php echo $v['csv_url']; ?>"></td>
                        </tr>
                        <tr id="nasdaq-json-row" style="display:none;">
                            <th><?php esc_html_e('JSON URL', 'zc-dmt'); ?></th>
                            <td><input name="nasdaq_json_url" class="regular-text" style="min-width:400px;" value="<?php echo $v['json_url']; ?>"></td>
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
                                    <option value="csv_url"><?php esc_html_e('CSV URL', 'zc-dmt'); ?></option>
                                    <option value="json_url"><?php esc_html_e('JSON URL', 'zc-dmt'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr id="test-dataset-row">
                            <th><?php esc_html_e('Dataset Code', 'zc-dmt'); ?></th>
                            <td><input name="test_dataset" class="regular-text" placeholder="WIKI/AAPL"></td>
                        </tr>
                        <tr id="test-csv-row" style="display:none;">
                            <th><?php esc_html_e('CSV URL', 'zc-dmt'); ?></th>
                            <td><input name="test_csv_url" class="regular-text"></td>
                        </tr>
                        <tr id="test-json-row" style="display:none;">
                            <th><?php esc_html_e('JSON URL', 'zc-dmt'); ?></th>
                            <td><input name="test_json_url" class="regular-text"></td>
                        </tr>
                    </tbody>
                </table>
                
                <p><button class="button button-secondary"><?php esc_html_e('Test Connection', 'zc-dmt'); ?></button></p>
            </form>
            
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <h3><?php esc_html_e('Popular Free Datasets:', 'zc-dmt'); ?></h3>
                <div style="margin: 12px 0;">
                    <strong>FRED/GDP:</strong> <?php esc_html_e('US Gross Domestic Product', 'zc-dmt'); ?><br>
                    <strong>FRED/UNRATE:</strong> <?php esc_html_e('US Unemployment Rate', 'zc-dmt'); ?><br>
                    <strong>WIKI/AAPL:</strong> <?php esc_html_e('Apple Stock Price (historical)', 'zc-dmt'); ?><br>
                    <strong>CURRFX/USDEUR:</strong> <?php esc_html_e('USD/EUR Exchange Rate', 'zc-dmt'); ?>
                </div>
                
                <div style="margin-top: 16px;">
                    <p class="description">
                        <strong><?php esc_html_e('Rate Limits:', 'zc-dmt'); ?></strong><br>
                        <?php esc_html_e('• Anonymous: 20 calls per 10 minutes', 'zc-dmt'); ?><br>
                        <?php esc_html_e('• Free account: 50 calls per day', 'zc-dmt'); ?><br>
                        <?php esc_html_e('• Premium: Higher limits + premium datasets', 'zc-dmt'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Method selection for main form
    $('input[name="nasdaq_method"]').on('change', function() {
        $('#nasdaq-dataset-row, #nasdaq-csv-row, #nasdaq-json-row').hide();
        if ($(this).val() === 'dataset') {
            $('#nasdaq-dataset-row').show();
        } else if ($(this).val() === 'csv_url') {
            $('#nasdaq-csv-row').show();
        } else if ($(this).val() === 'json_url') {
            $('#nasdaq-json-row').show();
        }
    }).change();
    
    // Test method selection
    $('#test_method').on('change', function() {
        $('#test-dataset-row, #test-csv-row, #test-json-row').hide();
        if ($(this).val() === 'dataset') {
            $('#test-dataset-row').show();
        } else if ($(this).val() === 'csv_url') {
            $('#test-csv-row').show();
        } else if ($(this).val() === 'json_url') {
            $('#test-json-row').show();
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
    $('input[name="nasdaq_dataset"]').on('input', function() {
        $('input[name="test_dataset"]').val($(this).val());
    });
    $('input[name="nasdaq_csv_url"]').on('input', function() {
        $('input[name="test_csv_url"]').val($(this).val());
    });
    $('input[name="nasdaq_json_url"]').on('input', function() {
        $('input[name="test_json_url"]').val($(this).val());
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