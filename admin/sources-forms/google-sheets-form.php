<?php
/**
 * Google Sheets Data Source Form - FIXED VERSION
 * Path: admin/sources-forms/google-sheets.php
 * Individual form for Google Sheets data source
 * Supports: CSV URL (no API key required)
 */

if (!defined('ABSPATH')) {
    exit; // Security check
}

// Security check
if (!current_user_can('manage_options')) {
    return;
}

// Handle form submission - FIXED: Using same action as indicators.txt
$notice = '';
if (!empty($_POST['zc_dmt_indicators_action'])) {
    if (!isset($_POST['zc_dmt_indicators_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['zc_dmt_indicators_nonce']), 'zc_dmt_indicators_action')) {
        $notice = '<div class="notice notice-error"><p>' . esc_html__('Security check failed. Please try again.', 'zc-dmt') . '</p></div>';
    } else {
        $action = sanitize_text_field($_POST['zc_dmt_indicators_action']);
        
        if ($action === 'add_indicator') {
            // Get form data
            $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
            $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
            $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
            
            // Google Sheets specific fields
            $google_url = isset($_POST['google_sheets_url']) ? sanitize_text_field($_POST['google_sheets_url']) : '';
            
            // Validation
            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug are required.', 'zc-dmt') . '</p></div>';
            } elseif (empty($google_url)) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Please paste a Google Sheets URL for this source type.', 'zc-dmt') . '</p></div>';
            } else {
                // Build source config - FIXED: Using same format as indicators.txt
                $source_config = array('url' => $google_url);
                
                // Create indicator using same method as indicators.txt
                if (class_exists('ZC_DMT_Indicators')) {
                    $result = ZC_DMT_Indicators::create_indicator($name, $slug, $description, 'google_sheets', $source_config, 1);
                    if (is_wp_error($result)) {
                        $notice = '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                    } else {
                        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Google Sheets indicator created successfully!', 'zc-dmt') . '</p></div>';
                        // Clear form
                        $_POST = array();
                    }
                }
            }
        } elseif ($action === 'test_connection') {
            // Test connection functionality
            $test_url = isset($_POST['test_url']) ? sanitize_text_field($_POST['test_url']) : '';
            
            if (!$test_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Please provide a URL to test.', 'zc-dmt') . '</p></div>';
            } else {
                if (class_exists('ZC_DMT_DataSource_GoogleSheets')) {
                    $test_result = ZC_DMT_DataSource_GoogleSheets::test_connection($test_url);
                    if (is_wp_error($test_result)) {
                        $notice = '<div class="notice notice-error"><p><strong>Test Failed:</strong> ' . esc_html($test_result->get_error_message()) . '</p></div>';
                    } else {
                        $notice = '<div class="notice notice-success"><p><strong>Test Successful!</strong> ' . esc_html($test_result['message']) . '</p></div>';
                    }
                } else {
                    $notice = '<div class="notice notice-error"><p>Google Sheets data source class not found.</p></div>';
                }
            }
        }
    }
}

// Get current form values
$form_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
$form_slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
$form_description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
$form_url = isset($_POST['google_sheets_url']) ? sanitize_text_field($_POST['google_sheets_url']) : '';
?>

<div class="wrap zc-dmt-source-form">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Google Sheets Data Source', 'zc-dmt'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action"><?php echo esc_html__('← Back to Data Sources', 'zc-dmt'); ?></a>
    <hr class="wp-header-end" />
    
    <?php echo $notice; ?>
    
    <!-- Source Info -->
    <div class="zc-source-info" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 20px 0;">
        <h3><?php echo esc_html__('About Google Sheets Data Source', 'zc-dmt'); ?></h3>
        <p><?php echo esc_html__('Import data directly from Google Sheets using published CSV links or public sharing URLs. The plugin automatically detects date and value columns and normalizes dates to Y-m-d format.', 'zc-dmt'); ?></p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 12px;">
            <div>
                <h4><?php echo esc_html__('Requirements', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><?php echo esc_html__('No API key required', 'zc-dmt'); ?></li>
                    <li><?php echo esc_html__('Public Google Sheets document', 'zc-dmt'); ?></li>
                    <li><?php echo esc_html__('Published CSV link (recommended)', 'zc-dmt'); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php echo esc_html__('Features', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><?php echo esc_html__('Auto-detects date/value columns', 'zc-dmt'); ?></li>
                    <li><?php echo esc_html__('Date normalization', 'zc-dmt'); ?></li>
                    <li><?php echo esc_html__('Live data updates', 'zc-dmt'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="zc-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <!-- Add New Indicator Form -->
        <div class="zc-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
            <h2><?php echo esc_html__('Add New Google Sheets Indicator', 'zc-dmt'); ?></h2>
            
            <form method="post" id="gs-indicator-form">
                <?php wp_nonce_field('zc_dmt_indicators_action', 'zc_dmt_indicators_nonce'); ?>
                <input type="hidden" name="zc_dmt_indicators_action" value="add_indicator" />
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="zc_name"><?php echo esc_html__('Indicator Name', 'zc-dmt'); ?></label></th>
                            <td>
                                <input type="text" id="zc_name" name="name" class="regular-text" value="<?php echo esc_attr($form_name); ?>" placeholder="<?php echo esc_attr__('e.g., Monthly Sales Data', 'zc-dmt'); ?>" required />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zc_slug"><?php echo esc_html__('Slug', 'zc-dmt'); ?></label></th>
                            <td>
                                <input type="text" id="zc_slug" name="slug" class="regular-text" value="<?php echo esc_attr($form_slug); ?>" placeholder="<?php echo esc_attr__('e.g., monthly-sales', 'zc-dmt'); ?>" required />
                                <p class="description"><?php echo esc_html__('Unique identifier for shortcodes.', 'zc-dmt'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zc_description"><?php echo esc_html__('Description', 'zc-dmt'); ?></label></th>
                            <td>
                                <textarea id="zc_description" name="description" class="large-text" rows="3" placeholder="<?php echo esc_attr__('Brief description of the indicator...', 'zc-dmt'); ?>"><?php echo esc_textarea($form_description); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zc_google_sheets_url"><?php echo esc_html__('Google Sheets URL', 'zc-dmt'); ?></label></th>
                            <td>
                                <input id="zc_google_sheets_url" name="google_sheets_url" type="url" class="regular-text" value="<?php echo esc_attr($form_url); ?>" placeholder="https://docs.google.com/...&output=csv" required />
                                <p class="description"><?php echo esc_html__('Paste a Published CSV link (recommended) or a normal share link. The plugin auto-detects date/value columns and normalizes dates to Y-m-d.', 'zc-dmt'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__('Create Google Sheets Indicator', 'zc-dmt'); ?>
                    </button>
                </p>
            </form>
        </div>
        
        <!-- Test Connection -->
        <div class="zc-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
            <h2><?php echo esc_html__('Test Connection', 'zc-dmt'); ?></h2>
            <p class="description"><?php echo esc_html__('Test your Google Sheets URL before creating an indicator.', 'zc-dmt'); ?></p>
            
            <form method="post">
                <?php wp_nonce_field('zc_dmt_indicators_action', 'zc_dmt_indicators_nonce'); ?>
                <input type="hidden" name="zc_dmt_indicators_action" value="test_connection" />
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="test_url"><?php echo esc_html__('URL to Test', 'zc-dmt'); ?></label></th>
                            <td>
                                <input type="url" id="test_url" name="test_url" class="regular-text" placeholder="https://docs.google.com/..." />
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-secondary">
                        <?php echo esc_html__('Test Connection', 'zc-dmt'); ?>
                    </button>
                </p>
            </form>
            
            <!-- Setup Instructions -->
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <h3><?php echo esc_html__('How to Setup Google Sheets', 'zc-dmt'); ?></h3>
                
                <div style="margin: 12px 0;">
                    <p><strong><?php echo esc_html__('Method 1: Published CSV (Recommended)', 'zc-dmt'); ?></strong></p>
                    <ol style="margin: 8px 0; padding-left: 20px;">
                        <li><?php echo esc_html__('In Google Sheets, go to File → Share → Publish to web', 'zc-dmt'); ?></li>
                        <li><?php echo esc_html__('Select "Comma-separated values (.csv)"', 'zc-dmt'); ?></li>
                        <li><?php echo esc_html__('Click "Publish" and copy the generated URL', 'zc-dmt'); ?></li>
                    </ol>
                    
                    <p><strong><?php echo esc_html__('Method 2: Share Link', 'zc-dmt'); ?></strong></p>
                    <ol style="margin: 8px 0; padding-left: 20px;">
                        <li><?php echo esc_html__('Click "Share" in Google Sheets', 'zc-dmt'); ?></li>
                        <li><?php echo esc_html__('Set to "Anyone with the link can view"', 'zc-dmt'); ?></li>
                        <li><?php echo esc_html__('Copy the sharing URL', 'zc-dmt'); ?></li>
                    </ol>
                </div>
                
                <div style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 4px; padding: 12px; margin-top: 16px;">
                    <p><strong><?php echo esc_html__('Data Format Requirements:', 'zc-dmt'); ?></strong></p>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li><?php echo esc_html__('First column: Date (any format)', 'zc-dmt'); ?></li>
                        <li><?php echo esc_html__('Second column: Value (numeric)', 'zc-dmt'); ?></li>
                        <li><?php echo esc_html__('Header row recommended', 'zc-dmt'); ?></li>
                    </ul>
                </div>
                
                <div style="margin-top: 16px;">
                    <p class="description">
                        <strong><?php echo esc_html__('Example URLs:', 'zc-dmt'); ?></strong><br>
                        • Published CSV: <code>https://docs.google.com/.../export?format=csv&gid=0</code><br>
                        • Share link: <code>https://docs.google.com/spreadsheets/d/.../edit#gid=0</code>
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
                      .replace(/[^a-z0-9-]/g, '-')
                      .replace(/-+/g, '-')
                      .replace(/^-|-$/g, '')
                      .replace(/--+/g, '-');
        $('#zc_slug').val(slug);
    });
    
    // Copy from main form to test form
    $('#zc_google_sheets_url').on('input', function() {
        $('#test_url').val($(this).val());
    });
    
    // URL validation helper
    $('#zc_google_sheets_url, #test_url').on('blur', function() {
        var url = $(this).val();
        if (url && !url.includes('docs.google.com')) {
            $(this).next('.description').after('<p style="color: #d63638; margin-top: 4px;"><em>Note: URL should be from docs.google.com</em></p>');
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
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>