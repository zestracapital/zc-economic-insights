<?php
/**
 * Australia RBA Data Source Form
 * Path: admin/sources-forms/australia-rba.php
 * 
 * Individual form for Australia RBA (Reserve Bank of Australia) data source
 * Supports: CSV URL (recommended), JSON URL (optional)
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
            
            // RBA specific fields
            $csv_url = isset($_POST['rba_csv_url']) ? esc_url_raw($_POST['rba_csv_url']) : '';
            $json_url = isset($_POST['rba_json_url']) ? esc_url_raw($_POST['rba_json_url']) : '';
            
            // Validation
            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug are required.', 'zc-dmt') . '</p></div>';
            } elseif (!$csv_url && !$json_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Please provide either a CSV URL or JSON URL.', 'zc-dmt') . '</p></div>';
            } else {
                // Build source config
                $source_config = null;
                if (!empty($csv_url)) {
                    $source_config = array('csv_url' => $csv_url);
                } elseif (!empty($json_url)) {
                    $source_config = array('json_url' => $json_url);
                }
                
                // Create indicator
                if (class_exists('ZC_DMT_Indicators')) {
                    $result = ZC_DMT_Indicators::create_indicator($name, $slug, $description, 'australia-rba', $source_config, 1);
                    if (is_wp_error($result)) {
                        $notice = '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                    } else {
                        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Australia RBA indicator created successfully!', 'zc-dmt') . '</p></div>';
                        // Clear form
                        $_POST = array();
                    }
                }
            }
        } elseif ($action === 'test_connection') {
            // Test connection functionality
            $test_csv_url = isset($_POST['test_csv_url']) ? esc_url_raw($_POST['test_csv_url']) : '';
            $test_json_url = isset($_POST['test_json_url']) ? esc_url_raw($_POST['test_json_url']) : '';
            
            if ($test_csv_url || $test_json_url) {
                // Create a temporary indicator object for testing
                $test_indicator = (object) array(
                    'id' => 0,
                    'name' => 'Test',
                    'slug' => 'test-rba',
                    'source_type' => 'australia-rba',
                    'source_config' => wp_json_encode($test_csv_url ? array('csv_url' => $test_csv_url) : array('json_url' => $test_json_url))
                );
                
                if (class_exists('ZC_DMT_DataSource_Australia_RBA')) {
                    $test_result = ZC_DMT_DataSource_Australia_RBA::get_series_for_indicator($test_indicator);
                    if (is_wp_error($test_result)) {
                        $notice = '<div class="notice notice-error"><p><strong>Test Failed:</strong> ' . esc_html($test_result->get_error_message()) . '</p></div>';
                    } else {
                        $count = isset($test_result['series']) ? count($test_result['series']) : 0;
                        $notice = '<div class="notice notice-success"><p><strong>Test Successful!</strong> Retrieved ' . esc_html($count) . ' data points from Australia RBA source.</p></div>';
                    }
                } else {
                    $notice = '<div class="notice notice-error"><p>Australia RBA data source class not found.</p></div>';
                }
            } else {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Please provide a URL to test.', 'zc-dmt') . '</p></div>';
            }
        }
    }
}

// Get current form values
$form_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
$form_slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
$form_description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
$form_csv_url = isset($_POST['rba_csv_url']) ? esc_url_raw($_POST['rba_csv_url']) : '';
$form_json_url = isset($_POST['rba_json_url']) ? esc_url_raw($_POST['rba_json_url']) : '';
?>

<div class="wrap zc-dmt-source-form">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Australia RBA Data Source', 'zc-dmt'); ?>
    </h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action">
        <?php esc_html_e('← Back to Data Sources', 'zc-dmt'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <?php echo $notice; ?>
    
    <div class="zc-source-info" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 20px 0;">
        <h3><?php esc_html_e('About Australia RBA Data Source', 'zc-dmt'); ?></h3>
        <p><?php esc_html_e('Reserve Bank of Australia (RBA) publishes statistical tables. Many are downloadable as CSV. This adapter is format-tolerant and auto-detects date and value columns.', 'zc-dmt'); ?></p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 12px;">
            <div>
                <h4><?php esc_html_e('Supported Methods:', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><strong>CSV URL:</strong> <?php esc_html_e('Direct CSV link (recommended)', 'zc-dmt'); ?></li>
                    <li><strong>JSON URL:</strong> <?php esc_html_e('Any JSON endpoint with date/value', 'zc-dmt'); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php esc_html_e('Features:', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><?php esc_html_e('Auto-detects date/value columns', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('20 minutes caching', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('Date normalization to Y-m-d', 'zc-dmt'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="zc-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <!-- Add New Indicator Form -->
        <div class="zc-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
            <h2><?php esc_html_e('Add New Australia RBA Indicator', 'zc-dmt'); ?></h2>
            
            <form method="post" id="rba-indicator-form">
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
                                       placeholder="<?php esc_attr_e('e.g., Australia Cash Rate', 'zc-dmt'); ?>" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="zc_slug"><?php esc_html_e('Slug', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="zc_slug" name="slug" class="regular-text" 
                                       value="<?php echo esc_attr($form_slug); ?>" 
                                       placeholder="<?php esc_attr_e('e.g., au-cash-rate', 'zc-dmt'); ?>" required>
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
                                <label><?php esc_html_e('Data Source Method (Choose One)', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php esc_html_e('RBA Data Source Method', 'zc-dmt'); ?></legend>
                                    
                                    <div style="margin-bottom: 12px;">
                                        <label style="display: block; font-weight: 600;">
                                            <?php esc_html_e('CSV URL (Recommended)', 'zc-dmt'); ?>
                                        </label>
                                        <input type="url" name="rba_csv_url" class="regular-text" style="min-width: 400px;" 
                                               value="<?php echo esc_attr($form_csv_url); ?>"
                                               placeholder="https://www.rba.gov.au/statistics/tables/csv/example.csv">
                                        <p class="description">
                                            <?php esc_html_e('Direct CSV link to RBA statistical table. The adapter will auto-detect date and value columns.', 'zc-dmt'); ?>
                                        </p>
                                    </div>
                                    
                                    <div>
                                        <label style="display: block; font-weight: 600;">
                                            <?php esc_html_e('JSON URL (Optional)', 'zc-dmt'); ?>
                                        </label>
                                        <input type="url" name="rba_json_url" class="regular-text" style="min-width: 400px;" 
                                               value="<?php echo esc_attr($form_json_url); ?>"
                                               placeholder="https://api.example.com/rba-data.json">
                                        <p class="description">
                                            <?php esc_html_e('Any JSON endpoint that returns period/date and value fields.', 'zc-dmt'); ?>
                                        </p>
                                    </div>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Create Australia RBA Indicator', 'zc-dmt'); ?>
                    </button>
                </p>
            </form>
        </div>
        
        <!-- Test Connection -->
        <div class="zc-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
            <h2><?php esc_html_e('Test Connection', 'zc-dmt'); ?></h2>
            <p class="description">
                <?php esc_html_e('Test your RBA data source URL before creating an indicator.', 'zc-dmt'); ?>
            </p>
            
            <form method="post">
                <?php wp_nonce_field('zc_source_action', 'zc_source_nonce'); ?>
                <input type="hidden" name="zc_source_action" value="test_connection">
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="test_csv_url"><?php esc_html_e('CSV URL to Test', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="test_csv_url" name="test_csv_url" class="regular-text" style="min-width: 400px;"
                                       placeholder="https://www.rba.gov.au/statistics/tables/csv/example.csv">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="test_json_url"><?php esc_html_e('OR JSON URL to Test', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="test_json_url" name="test_json_url" class="regular-text" style="min-width: 400px;"
                                       placeholder="https://api.example.com/rba-data.json">
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
                <h3><?php esc_html_e('Example URLs:', 'zc-dmt'); ?></h3>
                <div style="margin: 12px 0;">
                    <strong><?php esc_html_e('CSV:', 'zc-dmt'); ?></strong><br>
                    <code style="background: #f8fafc; padding: 4px 8px; border-radius: 3px; word-break: break-all;">
                        https://www.rba.gov.au/statistics/tables/csv/f1-1.csv
                    </code>
                </div>
                <div>
                    <strong><?php esc_html_e('JSON:', 'zc-dmt'); ?></strong><br>
                    <code style="background: #f8fafc; padding: 4px 8px; border-radius: 3px; word-break: break-all;">
                        https://api.example.com/rba-data.json
                    </code>
                </div>
                
                <div style="margin-top: 16px;">
                    <p class="description">
                        <strong><?php esc_html_e('Note:', 'zc-dmt'); ?></strong>
                        <?php esc_html_e('RBA typically publishes statistical tables in CSV format. Visit the RBA Statistics page to find the direct CSV download links for your desired data series.', 'zc-dmt'); ?>
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
    
    // Ensure only one URL method is used
    $('input[name="rba_csv_url"]').on('input', function() {
        if ($(this).val()) {
            $('input[name="rba_json_url"]').val('');
        }
    });
    
    $('input[name="rba_json_url"]').on('input', function() {
        if ($(this).val()) {
            $('input[name="rba_csv_url"]').val('');
        }
    });
    
    // Copy from main form to test form
    $('#rba-indicator-form input[type="url"]').on('input', function() {
        var name = $(this).attr('name');
        if (name === 'rba_csv_url') {
            $('#test_csv_url').val($(this).val());
            $('#test_json_url').val('');
        } else if (name === 'rba_json_url') {
            $('#test_json_url').val($(this).val());
            $('#test_csv_url').val('');
        }
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