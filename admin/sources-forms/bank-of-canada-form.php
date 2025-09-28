<?php
/**
 * Bank of Canada (Valet) Data Source Form
 * Path: admin/sources-forms/bank-of-canada.php
 * 
 * Individual form for Bank of Canada Valet data source
 * Supports: JSON URL, CSV URL, Series Code (with optional date range)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_options')) {
    return;
}

// Handle form submission
$notice = '';
if (!empty($_POST['zc_source_action'])) {
    if (!isset($_POST['zc_source_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['zc_source_nonce']), 'zc_source_action')) {
        $notice = '<div class="notice notice-error"><p>' . esc_html__('Security check failed. Please try again.', 'zc-dmt') . '</p></div>';
    } else {
        $action = sanitize_text_field($_POST['zc_source_action']);
        
        if ($action === 'add_indicator') {
            // Get form data
            $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
            $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
            $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
            
            // Bank of Canada specific fields
            $method = isset($_POST['boc_method']) ? sanitize_text_field($_POST['boc_method']) : '';
            $json_url = isset($_POST['boc_json_url']) ? esc_url_raw($_POST['boc_json_url']) : '';
            $csv_url = isset($_POST['boc_csv_url']) ? esc_url_raw($_POST['boc_csv_url']) : '';
            $series_code = isset($_POST['boc_series_code']) ? sanitize_text_field($_POST['boc_series_code']) : '';
            $start_date = isset($_POST['boc_start_date']) ? sanitize_text_field($_POST['boc_start_date']) : '';
            $end_date = isset($_POST['boc_end_date']) ? sanitize_text_field($_POST['boc_end_date']) : '';
            
            // Validation
            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug are required.', 'zc-dmt') . '</p></div>';
            } elseif (!$method || ($method === 'json_url' && !$json_url) || ($method === 'csv_url' && !$csv_url) || ($method === 'series' && !$series_code)) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Please select a method and fill the required fields.', 'zc-dmt') . '</p></div>';
            } else {
                // Build source config based on selected method
                $source_config = null;
                if ($method === 'json_url' && $json_url) {
                    $source_config = array('json_url' => $json_url);
                } elseif ($method === 'csv_url' && $csv_url) {
                    $source_config = array('csv_url' => $csv_url);
                } elseif ($method === 'series' && $series_code) {
                    $series_data = array('series' => $series_code);
                    if (!empty($start_date)) {
                        $series_data['start_date'] = $start_date;
                    }
                    if (!empty($end_date)) {
                        $series_data['end_date'] = $end_date;
                    }
                    $source_config = array('series' => $series_data);
                }
                
                // Create indicator
                if (class_exists('ZC_DMT_Indicators') && $source_config) {
                    $result = ZC_DMT_Indicators::create_indicator($name, $slug, $description, 'bank-of-canada', $source_config, 1);
                    if (is_wp_error($result)) {
                        $notice = '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                    } else {
                        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Bank of Canada indicator created successfully!', 'zc-dmt') . '</p></div>';
                        // Clear form
                        $_POST = array();
                    }
                } else {
                    $notice = '<div class="notice notice-error"><p>' . esc_html__('Failed to build configuration.', 'zc-dmt') . '</p></div>';
                }
            }
        } elseif ($action === 'test_connection') {
            // Test connection functionality
            $test_method = isset($_POST['test_method']) ? sanitize_text_field($_POST['test_method']) : '';
            $test_json_url = isset($_POST['test_json_url']) ? esc_url_raw($_POST['test_json_url']) : '';
            $test_csv_url = isset($_POST['test_csv_url']) ? esc_url_raw($_POST['test_csv_url']) : '';
            $test_series = isset($_POST['test_series']) ? sanitize_text_field($_POST['test_series']) : '';
            
            $test_config = null;
            if ($test_method === 'json_url' && $test_json_url) {
                $test_config = array('json_url' => $test_json_url);
            } elseif ($test_method === 'csv_url' && $test_csv_url) {
                $test_config = array('csv_url' => $test_csv_url);
            } elseif ($test_method === 'series' && $test_series) {
                $test_config = array('series' => array('series' => $test_series));
            }
            
            if ($test_config) {
                // Create a temporary indicator object for testing
                $test_indicator = (object) array(
                    'id' => 0,
                    'name' => 'Test',
                    'slug' => 'test-boc',
                    'source_type' => 'bank-of-canada',
                    'source_config' => wp_json_encode($test_config)
                );
                
                if (class_exists('ZC_DMT_DataSource_BankOfCanada')) {
                    $test_result = ZC_DMT_DataSource_BankOfCanada::get_series_for_indicator($test_indicator);
                    if (is_wp_error($test_result)) {
                        $notice = '<div class="notice notice-error"><p><strong>Test Failed:</strong> ' . esc_html($test_result->get_error_message()) . '</p></div>';
                    } else {
                        $count = isset($test_result['series']) ? count($test_result['series']) : 0;
                        $notice = '<div class="notice notice-success"><p><strong>Test Successful!</strong> Retrieved ' . esc_html($count) . ' data points from Bank of Canada.</p></div>';
                    }
                } else {
                    $notice = '<div class="notice notice-error"><p>Bank of Canada data source class not found.</p></div>';
                }
            } else {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Please select a method and provide test data.', 'zc-dmt') . '</p></div>';
            }
        }
    }
}

// Get current form values
$form_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
$form_slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
$form_description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
$form_method = isset($_POST['boc_method']) ? sanitize_text_field($_POST['boc_method']) : 'series';
$form_json_url = isset($_POST['boc_json_url']) ? esc_url_raw($_POST['boc_json_url']) : '';
$form_csv_url = isset($_POST['boc_csv_url']) ? esc_url_raw($_POST['boc_csv_url']) : '';
$form_series_code = isset($_POST['boc_series_code']) ? sanitize_text_field($_POST['boc_series_code']) : '';
$form_start_date = isset($_POST['boc_start_date']) ? sanitize_text_field($_POST['boc_start_date']) : '';
$form_end_date = isset($_POST['boc_end_date']) ? sanitize_text_field($_POST['boc_end_date']) : '';
?>

<div class="wrap zc-dmt-source-form">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Bank of Canada (Valet) Data Source', 'zc-dmt'); ?>
    </h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action">
        <?php esc_html_e('← Back to Data Sources', 'zc-dmt'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <?php echo $notice; ?>
    
    <div class="zc-source-info" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 20px 0;">
        <h3><?php esc_html_e('About Bank of Canada (Valet) Data Source', 'zc-dmt'); ?></h3>
        <p><?php esc_html_e('Bank of Canada Valet API provides access to economic indicators and time series data. Supports multiple access methods with automatic fallbacks.', 'zc-dmt'); ?></p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 12px;">
            <div>
                <h4><?php esc_html_e('Supported Methods:', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><strong>Series Code:</strong> <?php esc_html_e('Official Valet series (recommended)', 'zc-dmt'); ?></li>
                    <li><strong>JSON URL:</strong> <?php esc_html_e('Direct Valet JSON endpoint', 'zc-dmt'); ?></li>
                    <li><strong>CSV URL:</strong> <?php esc_html_e('Direct Valet CSV endpoint', 'zc-dmt'); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php esc_html_e('Features:', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><?php esc_html_e('Automatic JSON to CSV fallback', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('Date range filtering', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('20 minutes caching', 'zc-dmt'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="zc-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <!-- Add New Indicator Form -->
        <div class="zc-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
            <h2><?php esc_html_e('Add New Bank of Canada Indicator', 'zc-dmt'); ?></h2>
            
            <form method="post" id="boc-indicator-form">
                <?php wp_nonce_field('zc_source_action', 'zc_source_nonce'); ?>
                <input type="hidden" name="zc_source_action" value="add_indicator">
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="zc_name"><?php esc_html_e('Indicator Name', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="zc_name" name="name" class="regular-text" 
                                       value="<?php echo esc_attr($form_name); ?>" 
                                       placeholder="<?php esc_attr_e('e.g., Canada Prime Rate', 'zc-dmt'); ?>" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="zc_slug"><?php esc_html_e('Slug', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="zc_slug" name="slug" class="regular-text" 
                                       value="<?php echo esc_attr($form_slug); ?>" 
                                       placeholder="<?php esc_attr_e('e.g., ca-prime-rate', 'zc-dmt'); ?>" required>
                                <p class="description"><?php esc_html_e('Unique identifier for shortcodes.', 'zc-dmt'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="zc_description"><?php esc_html_e('Description', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <textarea id="zc_description" name="description" class="large-text" rows="3" 
                                          placeholder="<?php esc_attr_e('Brief description of the indicator...', 'zc-dmt'); ?>"><?php echo esc_textarea($form_description); ?></textarea>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Data Source Method', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php esc_html_e('Bank of Canada Data Source Method', 'zc-dmt'); ?></legend>
                                    
                                    <!-- Method Selection -->
                                    <div style="margin-bottom: 16px;">
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="radio" name="boc_method" value="series" <?php checked($form_method, 'series'); ?>>
                                            <strong><?php esc_html_e('Series Code (Recommended)', 'zc-dmt'); ?></strong>
                                        </label>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="radio" name="boc_method" value="json_url" <?php checked($form_method, 'json_url'); ?>>
                                            <strong><?php esc_html_e('JSON URL', 'zc-dmt'); ?></strong>
                                        </label>
                                        <label style="display: block;">
                                            <input type="radio" name="boc_method" value="csv_url" <?php checked($form_method, 'csv_url'); ?>>
                                            <strong><?php esc_html_e('CSV URL', 'zc-dmt'); ?></strong>
                                        </label>
                                    </div>
                                    
                                    <!-- Series Code Method -->
                                    <div id="method-series" class="boc-method-section" style="<?php echo ($form_method !== 'series') ? 'display: none;' : ''; ?>">
                                        <div style="margin-bottom: 12px;">
                                            <label style="display: block; font-weight: 600;">
                                                <?php esc_html_e('Series Code', 'zc-dmt'); ?>
                                            </label>
                                            <input type="text" name="boc_series_code" class="regular-text" 
                                                   value="<?php echo esc_attr($form_series_code); ?>"
                                                   placeholder="V39079">
                                            <p class="description">
                                                <?php esc_html_e('Official Bank of Canada series identifier (e.g., V39079 for Prime Rate).', 'zc-dmt'); ?>
                                            </p>
                                        </div>
                                        
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                            <div>
                                                <label style="display: block; font-weight: 600;">
                                                    <?php esc_html_e('Start Date (Optional)', 'zc-dmt'); ?>
                                                </label>
                                                <input type="date" name="boc_start_date" class="regular-text" 
                                                       value="<?php echo esc_attr($form_start_date); ?>">
                                            </div>
                                            <div>
                                                <label style="display: block; font-weight: 600;">
                                                    <?php esc_html_e('End Date (Optional)', 'zc-dmt'); ?>
                                                </label>
                                                <input type="date" name="boc_end_date" class="regular-text" 
                                                       value="<?php echo esc_attr($form_end_date); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- JSON URL Method -->
                                    <div id="method-json" class="boc-method-section" style="<?php echo ($form_method !== 'json_url') ? 'display: none;' : ''; ?>">
                                        <label style="display: block; font-weight: 600;">
                                            <?php esc_html_e('JSON URL', 'zc-dmt'); ?>
                                        </label>
                                        <input type="url" name="boc_json_url" class="regular-text" style="min-width: 400px;" 
                                               value="<?php echo esc_attr($form_json_url); ?>"
                                               placeholder="https://www.bankofcanada.ca/valet/observations/V39079/json">
                                        <p class="description">
                                            <?php esc_html_e('Direct JSON URL to Bank of Canada Valet API endpoint.', 'zc-dmt'); ?>
                                        </p>
                                    </div>
                                    
                                    <!-- CSV URL Method -->
                                    <div id="method-csv" class="boc-method-section" style="<?php echo ($form_method !== 'csv_url') ? 'display: none;' : ''; ?>">
                                        <label style="display: block; font-weight: 600;">
                                            <?php esc_html_e('CSV URL', 'zc-dmt'); ?>
                                        </label>
                                        <input type="url" name="boc_csv_url" class="regular-text" style="min-width: 400px;" 
                                               value="<?php echo esc_attr($form_csv_url); ?>"
                                               placeholder="https://www.bankofcanada.ca/valet/observations/V39079/csv">
                                        <p class="description">
                                            <?php esc_html_e('Direct CSV URL to Bank of Canada Valet API endpoint.', 'zc-dmt'); ?>
                                        </p>
                                    </div>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Create Bank of Canada Indicator', 'zc-dmt'); ?>
                    </button>
                </p>
            </form>
        </div>
        
        <!-- Test Connection -->
        <div class="zc-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
            <h2><?php esc_html_e('Test Connection', 'zc-dmt'); ?></h2>
            <p class="description">
                <?php esc_html_e('Test your Bank of Canada data source before creating an indicator.', 'zc-dmt'); ?>
            </p>
            
            <form method="post">
                <?php wp_nonce_field('zc_source_action', 'zc_source_nonce'); ?>
                <input type="hidden" name="zc_source_action" value="test_connection">
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Test Method', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="radio" name="test_method" value="series" checked>
                                        <?php esc_html_e('Series Code', 'zc-dmt'); ?>
                                    </label>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="radio" name="test_method" value="json_url">
                                        <?php esc_html_e('JSON URL', 'zc-dmt'); ?>
                                    </label>
                                    <label style="display: block;">
                                        <input type="radio" name="test_method" value="csv_url">
                                        <?php esc_html_e('CSV URL', 'zc-dmt'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr id="test-series-row">
                            <th scope="row">
                                <label for="test_series"><?php esc_html_e('Series Code', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="test_series" name="test_series" class="regular-text"
                                       placeholder="V39079">
                            </td>
                        </tr>
                        
                        <tr id="test-json-row" style="display: none;">
                            <th scope="row">
                                <label for="test_json_url"><?php esc_html_e('JSON URL', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="test_json_url" name="test_json_url" class="regular-text" style="min-width: 400px;"
                                       placeholder="https://www.bankofcanada.ca/valet/observations/V39079/json">
                            </td>
                        </tr>
                        
                        <tr id="test-csv-row" style="display: none;">
                            <th scope="row">
                                <label for="test_csv_url"><?php esc_html_e('CSV URL', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="test_csv_url" name="test_csv_url" class="regular-text" style="min-width: 400px;"
                                       placeholder="https://www.bankofcanada.ca/valet/observations/V39079/csv">
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-secondary">
                        <?php esc_html_e('Test Connection', 'zc-dmt'); ?>
                    </button>
                </p>
            </form>
            
            <!-- Examples -->
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <h3><?php esc_html_e('Popular Series Examples:', 'zc-dmt'); ?></h3>
                <div style="margin: 12px 0;">
                    <strong><?php esc_html_e('V39079:', 'zc-dmt'); ?></strong> <?php esc_html_e('Bank Rate (Prime Rate)', 'zc-dmt'); ?><br>
                    <strong><?php esc_html_e('V41690973:', 'zc-dmt'); ?></strong> <?php esc_html_e('Consumer Price Index', 'zc-dmt'); ?><br>
                    <strong><?php esc_html_e('V37426:', 'zc-dmt'); ?></strong> <?php esc_html_e('GDP at market prices', 'zc-dmt'); ?>
                </div>
                
                <div style="margin-top: 16px;">
                    <p class="description">
                        <strong><?php esc_html_e('Note:', 'zc-dmt'); ?></strong>
                        <?php esc_html_e('Visit the Bank of Canada Valet API documentation to find more series codes and available data.', 'zc-dmt'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Auto-generate slug from name
    $('#zc_name').on('input', function() {
        var name = $(this).val();
        var slug = name.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
        $('#zc_slug').val(slug);
    });
    
    // Method selection for main form
    $('input[name="boc_method"]').on('change', function() {
        $('.boc-method-section').hide();
        $('#method-' + $(this).val()).show();
    });
    
    // Test method selection
    $('input[name="test_method"]').on('change', function() {
        $('#test-series-row, #test-json-row, #test-csv-row').hide();
        $('#test-' + $(this).val().replace('_url', '') + '-row').show();
    });
    
    // Copy from main form to test form
    $('input[name="boc_series_code"]').on('input', function() {
        $('#test_series').val($(this).val());
    });
    $('input[name="boc_json_url"]').on('input', function() {
        $('#test_json_url').val($(this).val());
    });
    $('input[name="boc_csv_url"]').on('input', function() {
        $('#test_csv_url').val($(this).val());
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

.boc-method-section {
    padding: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    margin-top: 8px;
}

@media (max-width: 768px) {
    .zc-grid {
        grid-template-columns: 1fr !important;
    }
    
    .zc-source-info > div {
        grid-template-columns: 1fr !important;
    }
    
    .boc-method-section div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>