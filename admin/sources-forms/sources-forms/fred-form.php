<?php
/**
 * FRED (Federal Reserve Economic Data) Data Source Form
 * Path: admin/sources-forms/fred.php
 * 
 * Individual form for FRED data source
 * Supports: Series ID (requires API key in settings)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_options')) {
    return;
}

// Check if API key is configured
$fred_api_key = get_option('zc_fred_api_key', '');
$api_key_configured = !empty($fred_api_key);

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
            
            // FRED specific fields
            $series_id = isset($_POST['fred_series_id']) ? sanitize_text_field($_POST['fred_series_id']) : '';
            $start_date = isset($_POST['fred_start_date']) ? sanitize_text_field($_POST['fred_start_date']) : '';
            $end_date = isset($_POST['fred_end_date']) ? sanitize_text_field($_POST['fred_end_date']) : '';
            
            // Validation
            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug are required.', 'zc-dmt') . '</p></div>';
            } elseif (!$series_id) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('FRED Series ID is required.', 'zc-dmt') . '</p></div>';
            } elseif (!$api_key_configured) {
                $notice = '<div class="notice notice-error"><p>' . sprintf(
                    esc_html__('FRED API key not configured. Please add it in %s.', 'zc-dmt'),
                    '<a href="' . esc_url(admin_url('admin.php?page=zc-dmt-settings')) . '">' . esc_html__('Settings', 'zc-dmt') . '</a>'
                ) . '</p></div>';
            } else {
                // Build source config
                $source_config = array(
                    'series_id' => $series_id
                );
                
                // Add optional date range
                if (!empty($start_date)) {
                    $source_config['start_date'] = $start_date;
                }
                if (!empty($end_date)) {
                    $source_config['end_date'] = $end_date;
                }
                
                // Create indicator
                if (class_exists('ZC_DMT_Indicators')) {
                    $result = ZC_DMT_Indicators::create_indicator($name, $slug, $description, 'fred', $source_config, 1);
                    if (is_wp_error($result)) {
                        $notice = '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                    } else {
                        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('FRED indicator created successfully!', 'zc-dmt') . '</p></div>';
                        // Clear form
                        $_POST = array();
                    }
                }
            }
        } elseif ($action === 'test_connection') {
            // Test connection functionality
            $test_series_id = isset($_POST['test_series_id']) ? sanitize_text_field($_POST['test_series_id']) : '';
            
            if (!$test_series_id) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Please provide a Series ID to test.', 'zc-dmt') . '</p></div>';
            } elseif (!$api_key_configured) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('FRED API key not configured.', 'zc-dmt') . '</p></div>';
            } else {
                if (class_exists('ZC_DMT_DataSource_FRED')) {
                    $test_result = ZC_DMT_DataSource_FRED::test_connection($fred_api_key, $test_series_id);
                    if (is_wp_error($test_result)) {
                        $notice = '<div class="notice notice-error"><p><strong>Test Failed:</strong> ' . esc_html($test_result->get_error_message()) . '</p></div>';
                    } else {
                        $notice = '<div class="notice notice-success"><p><strong>Test Successful!</strong> ' . esc_html($test_result['message']) . '</p></div>';
                    }
                } else {
                    $notice = '<div class="notice notice-error"><p>FRED data source class not found.</p></div>';
                }
            }
        }
    }
}

// Get current form values
$form_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
$form_slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
$form_description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
$form_series_id = isset($_POST['fred_series_id']) ? sanitize_text_field($_POST['fred_series_id']) : '';
$form_start_date = isset($_POST['fred_start_date']) ? sanitize_text_field($_POST['fred_start_date']) : '';
$form_end_date = isset($_POST['fred_end_date']) ? sanitize_text_field($_POST['fred_end_date']) : '';
?>

<div class="wrap zc-dmt-source-form">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('FRED (Federal Reserve Economic Data) Source', 'zc-dmt'); ?>
    </h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action">
        <?php esc_html_e('← Back to Data Sources', 'zc-dmt'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <?php echo $notice; ?>
    
    <!-- API Key Status Alert -->
    <?php if (!$api_key_configured) : ?>
        <div class="notice notice-warning" style="margin: 20px 0;">
            <p>
                <strong><?php esc_html_e('API Key Required:', 'zc-dmt'); ?></strong>
                <?php printf(
                    esc_html__('FRED requires an API key to access data. Please configure your API key in %s before creating indicators.', 'zc-dmt'),
                    '<a href="' . esc_url(admin_url('admin.php?page=zc-dmt-settings')) . '">' . esc_html__('Plugin Settings', 'zc-dmt') . '</a>'
                ); ?>
            </p>
            <p>
                <a href="https://research.stlouisfed.org/useraccount/apikeys" target="_blank" class="button button-secondary">
                    <?php esc_html_e('Get FRED API Key', 'zc-dmt'); ?> ↗
                </a>
            </p>
        </div>
    <?php else : ?>
        <div class="notice notice-success" style="margin: 20px 0;">
            <p>
                <strong><?php esc_html_e('API Key Configured:', 'zc-dmt'); ?></strong>
                <?php esc_html_e('FRED API key is configured and ready to use.', 'zc-dmt'); ?>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="zc-source-info" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 20px 0;">
        <h3><?php esc_html_e('About FRED Data Source', 'zc-dmt'); ?></h3>
        <p><?php esc_html_e('Federal Reserve Economic Data (FRED) is a database maintained by the Federal Reserve Bank of St. Louis. It provides access to US economic indicators, employment data, inflation rates, GDP, and more.', 'zc-dmt'); ?></p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 12px;">
            <div>
                <h4><?php esc_html_e('Requirements:', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><?php esc_html_e('Free FRED API key', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('Valid FRED Series ID', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('Optional date range filtering', 'zc-dmt'); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php esc_html_e('Features:', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><?php esc_html_e('Real-time US economic data', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('15 minutes caching', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('Date normalization', 'zc-dmt'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="zc-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <!-- Add New Indicator Form -->
        <div class="zc-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
            <h2><?php esc_html_e('Add New FRED Indicator', 'zc-dmt'); ?></h2>
            
            <form method="post" id="fred-indicator-form">
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
                                       placeholder="<?php esc_attr_e('e.g., US GDP Growth Rate', 'zc-dmt'); ?>" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="zc_slug"><?php esc_html_e('Slug', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="zc_slug" name="slug" class="regular-text" 
                                       value="<?php echo esc_attr($form_slug); ?>" 
                                       placeholder="<?php esc_attr_e('e.g., us-gdp-growth', 'zc-dmt'); ?>" required>
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
                                <label for="fred_series_id"><?php esc_html_e('FRED Series ID', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="fred_series_id" name="fred_series_id" class="regular-text" 
                                       value="<?php echo esc_attr($form_series_id); ?>" 
                                       placeholder="GDP" required
                                       <?php echo !$api_key_configured ? 'disabled' : ''; ?>>
                                <p class="description">
                                    <?php esc_html_e('Official FRED series identifier (e.g., GDP, UNRATE, CPIAUCSL).', 'zc-dmt'); ?>
                                    <a href="https://fred.stlouisfed.org/" target="_blank"><?php esc_html_e('Search FRED Database ↗', 'zc-dmt'); ?></a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Date Range (Optional)', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div>
                                        <label style="display: block; font-weight: 600; margin-bottom: 4px;">
                                            <?php esc_html_e('Start Date', 'zc-dmt'); ?>
                                        </label>
                                        <input type="date" name="fred_start_date" class="regular-text" 
                                               value="<?php echo esc_attr($form_start_date); ?>"
                                               <?php echo !$api_key_configured ? 'disabled' : ''; ?>>
                                    </div>
                                    <div>
                                        <label style="display: block; font-weight: 600; margin-bottom: 4px;">
                                            <?php esc_html_e('End Date', 'zc-dmt'); ?>
                                        </label>
                                        <input type="date" name="fred_end_date" class="regular-text" 
                                               value="<?php echo esc_attr($form_end_date); ?>"
                                               <?php echo !$api_key_configured ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <p class="description"><?php esc_html_e('Leave empty to get all available data.', 'zc-dmt'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" <?php echo !$api_key_configured ? 'disabled' : ''; ?>>
                        <?php esc_html_e('Create FRED Indicator', 'zc-dmt'); ?>
                    </button>
                </p>
            </form>
        </div>
        
        <!-- Test Connection -->
        <div class="zc-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
            <h2><?php esc_html_e('Test Connection', 'zc-dmt'); ?></h2>
            <p class="description">
                <?php esc_html_e('Test your FRED Series ID before creating an indicator.', 'zc-dmt'); ?>
            </p>
            
            <form method="post">
                <?php wp_nonce_field('zc_source_action', 'zc_source_nonce'); ?>
                <input type="hidden" name="zc_source_action" value="test_connection">
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="test_series_id"><?php esc_html_e('Series ID to Test', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="test_series_id" name="test_series_id" class="regular-text" 
                                       placeholder="GDP"
                                       <?php echo !$api_key_configured ? 'disabled' : ''; ?>>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-secondary" <?php echo !$api_key_configured ? 'disabled' : ''; ?>>
                        <?php esc_html_e('Test Connection', 'zc-dmt'); ?>
                    </button>
                </p>
            </form>
            
            <!-- Popular Series Examples -->
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <h3><?php esc_html_e('Popular FRED Series:', 'zc-dmt'); ?></h3>
                <div style="margin: 12px 0;">
                    <strong>GDP:</strong> <?php esc_html_e('Gross Domestic Product', 'zc-dmt'); ?><br>
                    <strong>UNRATE:</strong> <?php esc_html_e('Unemployment Rate', 'zc-dmt'); ?><br>
                    <strong>CPIAUCSL:</strong> <?php esc_html_e('Consumer Price Index', 'zc-dmt'); ?><br>
                    <strong>FEDFUNDS:</strong> <?php esc_html_e('Federal Funds Rate', 'zc-dmt'); ?><br>
                    <strong>PAYEMS:</strong> <?php esc_html_e('Total Nonfarm Payrolls', 'zc-dmt'); ?>
                </div>
                
                <div style="margin-top: 16px;">
                    <p class="description">
                        <strong><?php esc_html_e('How to find Series IDs:', 'zc-dmt'); ?></strong><br>
                        1. <?php esc_html_e('Visit', 'zc-dmt'); ?> <a href="https://fred.stlouisfed.org/" target="_blank">fred.stlouisfed.org</a><br>
                        2. <?php esc_html_e('Search for your desired economic indicator', 'zc-dmt'); ?><br>
                        3. <?php esc_html_e('The Series ID appears at the top of the data page', 'zc-dmt'); ?>
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
    
    // Copy from main form to test form
    $('#fred_series_id').on('input', function() {
        $('#test_series_id').val($(this).val());
    });
    
    // Disable/enable forms based on API key status
    var apiConfigured = <?php echo $api_key_configured ? 'true' : 'false'; ?>;
    if (!apiConfigured) {
        $('#fred-indicator-form input, #fred-indicator-form textarea, #fred-indicator-form button').prop('disabled', true);
        $('#test_series_id').prop('disabled', true);
        $('button[type="submit"]').prop('disabled', true);
    }
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

input:disabled, textarea:disabled, button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .zc-grid {
        grid-template-columns: 1fr !important;
    }
    
    .zc-source-info > div {
        grid-template-columns: 1fr !important;
    }
    
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>