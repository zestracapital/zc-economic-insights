<?php
/**
 * World Bank Data Source Form - COMPLETE FIXED VERSION
 * Path: admin/sources-forms/world-bank.php
 * Individual form for World Bank data source
 * Supports: Both CSV URL and Country/Indicator methods
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
            
            // World Bank specific fields
            $wb_csv_url = isset($_POST['wb_csv_url']) ? esc_url_raw($_POST['wb_csv_url']) : '';
            $wb_country = isset($_POST['wb_country_code']) ? strtoupper(sanitize_text_field($_POST['wb_country_code'])) : '';
            $wb_indicator = isset($_POST['wb_indicator_code']) ? sanitize_text_field($_POST['wb_indicator_code']) : '';
            
            // Validation
            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug are required.', 'zc-dmt') . '</p></div>';
            } elseif (empty($wb_csv_url) && (empty($wb_country) || empty($wb_indicator))) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Please provide either a CSV URL OR both country and indicator codes.', 'zc-dmt') . '</p></div>';
            } else {
                // Build source config - FIXED: Using same format as indicators.txt
                $source_config = null;
                if (!empty($wb_csv_url)) {
                    $source_config = array('csv_url' => $wb_csv_url);
                } else {
                    $source_config = array('country_code' => $wb_country, 'indicator_code' => $wb_indicator);
                }
                
                // Create indicator using same method as indicators.txt
                if (class_exists('ZC_DMT_Indicators')) {
                    $result = ZC_DMT_Indicators::create_indicator($name, $slug, $description, 'world_bank', $source_config, 1);
                    if (is_wp_error($result)) {
                        $notice = '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                    } else {
                        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('World Bank indicator created successfully!', 'zc-dmt') . '</p></div>';
                        // Clear form
                        $_POST = array();
                    }
                }
            }
        } elseif ($action === 'test_connection') {
            // Test connection functionality
            $test_csv_url = isset($_POST['test_csv_url']) ? esc_url_raw($_POST['test_csv_url']) : '';
            $test_country = isset($_POST['test_country_code']) ? strtoupper(sanitize_text_field($_POST['test_country_code'])) : '';
            $test_indicator = isset($_POST['test_indicator_code']) ? sanitize_text_field($_POST['test_indicator_code']) : '';
            
            if (empty($test_csv_url) && (empty($test_country) || empty($test_indicator))) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Please provide either a CSV URL OR both country and indicator codes to test.', 'zc-dmt') . '</p></div>';
            } else {
                if (class_exists('ZC_DMT_DataSource_WorldBank')) {
                    if (!empty($test_csv_url)) {
                        $test_result = ZC_DMT_DataSource_WorldBank::test_csv_connection($test_csv_url);
                    } else {
                        $test_result = ZC_DMT_DataSource_WorldBank::test_connection($test_country, $test_indicator);
                    }
                    
                    if (is_wp_error($test_result)) {
                        $notice = '<div class="notice notice-error"><p><strong>Test Failed:</strong> ' . esc_html($test_result->get_error_message()) . '</p></div>';
                    } else {
                        $notice = '<div class="notice notice-success"><p><strong>Test Successful!</strong> ' . esc_html($test_result['message']) . '</p></div>';
                    }
                } else {
                    $notice = '<div class="notice notice-error"><p>World Bank data source class not found.</p></div>';
                }
            }
        }
    }
}

// Get current form values
$form_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
$form_slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
$form_description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
$form_csv_url = isset($_POST['wb_csv_url']) ? esc_url_raw($_POST['wb_csv_url']) : '';
$form_country = isset($_POST['wb_country_code']) ? strtoupper(sanitize_text_field($_POST['wb_country_code'])) : 'US';
$form_indicator = isset($_POST['wb_indicator_code']) ? sanitize_text_field($_POST['wb_indicator_code']) : '';
?>

<div class="wrap zc-dmt-source-form">
    <h1 class="wp-heading-inline"><?php echo esc_html__('World Bank Open Data Source', 'zc-dmt'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action"><?php echo esc_html__('← Back to Data Sources', 'zc-dmt'); ?></a>
    <hr class="wp-header-end" />
    
    <?php echo $notice; ?>
    
    <!-- Source Info -->
    <div class="zc-source-info" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 20px 0;">
        <h3><?php echo esc_html__('About World Bank Data Source', 'zc-dmt'); ?></h3>
        <p><?php echo esc_html__('World Bank Open Data provides free access to global development data including GDP, population, education, health, and economic indicators from around the world.', 'zc-dmt'); ?></p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 12px;">
            <div>
                <h4><?php echo esc_html__('Requirements', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><?php echo esc_html__('No API key required', 'zc-dmt'); ?></li>
                    <li><?php echo esc_html__('Choose: CSV URL OR Country + Indicator', 'zc-dmt'); ?></li>
                    <li><?php echo esc_html__('Valid country code (ISO 3166)', 'zc-dmt'); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php echo esc_html__('Features', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><?php echo esc_html__('Global economic indicators', 'zc-dmt'); ?></li>
                    <li><?php echo esc_html__('Historical data (1960+)', 'zc-dmt'); ?></li>
                    <li><?php echo esc_html__('200+ countries/regions', 'zc-dmt'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="zc-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <!-- Add New Indicator Form -->
        <div class="zc-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
            <h2><?php echo esc_html__('Add New World Bank Indicator', 'zc-dmt'); ?></h2>
            
            <form method="post" id="wb-indicator-form">
                <?php wp_nonce_field('zc_dmt_indicators_action', 'zc_dmt_indicators_nonce'); ?>
                <input type="hidden" name="zc_dmt_indicators_action" value="add_indicator" />
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="zc_name"><?php echo esc_html__('Indicator Name', 'zc-dmt'); ?></label></th>
                            <td>
                                <input type="text" id="zc_name" name="name" class="regular-text" value="<?php echo esc_attr($form_name); ?>" placeholder="<?php echo esc_attr__('e.g., US GDP (Current US$)', 'zc-dmt'); ?>" required />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zc_slug"><?php echo esc_html__('Slug', 'zc-dmt'); ?></label></th>
                            <td>
                                <input type="text" id="zc_slug" name="slug" class="regular-text" value="<?php echo esc_attr($form_slug); ?>" placeholder="<?php echo esc_attr__('e.g., us-gdp-current', 'zc-dmt'); ?>" required />
                                <p class="description"><?php echo esc_html__('Unique identifier for shortcodes.', 'zc-dmt'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zc_description"><?php echo esc_html__('Description', 'zc-dmt'); ?></label></th>
                            <td>
                                <textarea id="zc_description" name="description" class="large-text" rows="3" placeholder="<?php echo esc_attr__('Brief description of the indicator...', 'zc-dmt'); ?>"><?php echo esc_textarea($form_description); ?></textarea>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h3 style="margin-top: 24px; margin-bottom: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0;"><?php echo esc_html__('Choose ONE method:', 'zc-dmt'); ?></h3>
                
                <div class="zc-method-tabs" style="margin-bottom: 20px;">
                    <button type="button" class="zc-tab-button active" data-tab="csv"><?php echo esc_html__('CSV URL Method', 'zc-dmt'); ?></button>
                    <button type="button" class="zc-tab-button" data-tab="api"><?php echo esc_html__('API Method', 'zc-dmt'); ?></button>
                </div>
                
                <!-- CSV Method -->
                <div id="csv-method" class="zc-tab-content active">
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="zc_wb_csv_url"><?php echo esc_html__('CSV URL', 'zc-dmt'); ?></label></th>
                                <td>
                                    <input id="zc_wb_csv_url" name="wb_csv_url" type="url" class="regular-text" value="<?php echo esc_attr($form_csv_url); ?>" placeholder="https://api.worldbank.org/v2/country/US/indicator/NY.GDP.MKTP.KD.ZG?format=csv&per_page=1000" />
                                    <p class="description">
                                        <?php echo esc_html__('Direct CSV download link from World Bank API (recommended method)', 'zc-dmt'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- API Method -->
                <div id="api-method" class="zc-tab-content">
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="zc_wb_country_code"><?php echo esc_html__('Country Code', 'zc-dmt'); ?></label></th>
                                <td>
                                    <input type="text" id="zc_wb_country_code" name="wb_country_code" class="regular-text" style="width:100px" value="<?php echo esc_attr($form_country); ?>" placeholder="US" list="wb_countries" />
                                    
                                    <datalist id="wb_countries">
                                        <option value="US">United States</option>
                                        <option value="CN">China</option>
                                        <option value="JP">Japan</option>
                                        <option value="DE">Germany</option>
                                        <option value="GB">United Kingdom</option>
                                        <option value="FR">France</option>
                                        <option value="IN">India</option>
                                        <option value="WLD">World (aggregate)</option>
                                    </datalist>
                                    
                                    <p class="description"><?php echo esc_html__('ISO country code (e.g., US, GB, DE, CN). Use WLD for world data.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="zc_wb_indicator_code"><?php echo esc_html__('Indicator Code', 'zc-dmt'); ?></label></th>
                                <td>
                                    <input id="zc_wb_indicator_code" name="wb_indicator_code" list="zc_wb_indicator_list" type="text" class="regular-text" value="<?php echo esc_attr($form_indicator); ?>" placeholder="NY.GDP.MKTP.CD" />
                                    
                                    <datalist id="zc_wb_indicator_list">
                                        <option value="NY.GDP.MKTP.CD">GDP (current US$)</option>
                                        <option value="NY.GDP.MKTP.KD.ZG">GDP growth (annual %)</option>
                                        <option value="NY.GDP.PCAP.CD">GDP per capita (current US$)</option>
                                        <option value="SL.UEM.TOTL.ZS">Unemployment, total (% of labor force)</option>
                                        <option value="FP.CPI.TOTL.ZG">Inflation, consumer prices (annual %)</option>
                                        <option value="SP.POP.TOTL">Population, total</option>
                                        <option value="NE.EXP.GNFS.ZS">Exports of goods and services (% of GDP)</option>
                                        <option value="NE.IMP.GNFS.ZS">Imports of goods and services (% of GDP)</option>
                                    </datalist>
                                    
                                    <p class="description">
                                        <?php echo esc_html__('World Bank indicator code (e.g., NY.GDP.MKTP.CD).', 'zc-dmt'); ?>
                                        <a href="https://datacatalog.worldbank.org/home" target="_blank"><?php echo esc_html__('Browse World Bank Catalog →', 'zc-dmt'); ?></a>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__('Create World Bank Indicator', 'zc-dmt'); ?>
                    </button>
                </p>
            </form>
        </div>
        
        <!-- Test Connection -->
        <div class="zc-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
            <h2><?php echo esc_html__('Test Connection', 'zc-dmt'); ?></h2>
            <p class="description"><?php echo esc_html__('Test your World Bank data source before creating an indicator.', 'zc-dmt'); ?></p>
            
            <form method="post">
                <?php wp_nonce_field('zc_dmt_indicators_action', 'zc_dmt_indicators_nonce'); ?>
                <input type="hidden" name="zc_dmt_indicators_action" value="test_connection" />
                
                <div class="zc-test-tabs" style="margin-bottom: 16px;">
                    <button type="button" class="zc-test-tab-button active" data-tab="test-csv"><?php echo esc_html__('Test CSV', 'zc-dmt'); ?></button>
                    <button type="button" class="zc-test-tab-button" data-tab="test-api"><?php echo esc_html__('Test API', 'zc-dmt'); ?></button>
                </div>
                
                <!-- Test CSV -->
                <div id="test-csv" class="zc-test-tab-content active">
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="test_csv_url"><?php echo esc_html__('CSV URL', 'zc-dmt'); ?></label></th>
                                <td>
                                    <input type="url" id="test_csv_url" name="test_csv_url" class="regular-text" placeholder="https://api.worldbank.org/..." />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Test API -->
                <div id="test-api" class="zc-test-tab-content">
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="test_country_code"><?php echo esc_html__('Country Code', 'zc-dmt'); ?></label></th>
                                <td>
                                    <input type="text" id="test_country_code" name="test_country_code" class="regular-text" style="width:100px" placeholder="US" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="test_indicator_code"><?php echo esc_html__('Indicator Code', 'zc-dmt'); ?></label></th>
                                <td>
                                    <input type="text" id="test_indicator_code" name="test_indicator_code" class="regular-text" placeholder="NY.GDP.MKTP.KD.ZG" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-secondary">
                        <?php echo esc_html__('Test Connection', 'zc-dmt'); ?>
                    </button>
                </p>
            </form>
            
            <!-- Examples and Instructions -->
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <h3><?php echo esc_html__('How to Get CSV URLs', 'zc-dmt'); ?></h3>
                
                <div style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 4px; padding: 12px; margin: 12px 0;">
                    <p><strong><?php echo esc_html__('CSV URL Format:', 'zc-dmt'); ?></strong></p>
                    <code style="display: block; margin: 8px 0; padding: 8px; background: white; border-radius: 3px;">
                        https://api.worldbank.org/v2/country/COUNTRY/indicator/INDICATOR?format=csv&per_page=1000
                    </code>
                    
                    <p><strong><?php echo esc_html__('Example:', 'zc-dmt'); ?></strong></p>
                    <code style="display: block; margin: 8px 0; padding: 8px; background: white; border-radius: 3px;">
                        https://api.worldbank.org/v2/country/US/indicator/NY.GDP.MKTP.KD.ZG?format=csv&per_page=1000
                    </code>
                </div>
                
                <h3><?php echo esc_html__('Popular World Bank Indicators', 'zc-dmt'); ?></h3>
                <div style="margin: 12px 0; font-size: 13px;">
                    <strong>NY.GDP.MKTP.CD</strong> - GDP (current US$)<br>
                    <strong>NY.GDP.MKTP.KD.ZG</strong> - GDP growth (annual %)<br>
                    <strong>NY.GDP.PCAP.CD</strong> - GDP per capita<br>
                    <strong>SL.UEM.TOTL.ZS</strong> - Unemployment rate<br>
                    <strong>FP.CPI.TOTL.ZG</strong> - Inflation rate<br>
                    <strong>SP.POP.TOTL</strong> - Total population
                </div>
                
                <h3><?php echo esc_html__('Common Country Codes', 'zc-dmt'); ?></h3>
                <div style="margin: 12px 0; font-size: 13px;">
                    <strong>US</strong> - United States<br>
                    <strong>CN</strong> - China<br>
                    <strong>DE</strong> - Germany<br>
                    <strong>GB</strong> - United Kingdom<br>
                    <strong>JP</strong> - Japan<br>
                    <strong>WLD</strong> - World (aggregate data)
                </div>
                
                <div style="margin-top: 16px;">
                    <p class="description">
                        <strong><?php echo esc_html__('How to find codes:', 'zc-dmt'); ?></strong><br>
                        1. <?php echo esc_html__('Visit', 'zc-dmt'); ?> <a href="https://datacatalog.worldbank.org/" target="_blank">World Bank Data Catalog</a><br>
                        2. <?php echo esc_html__('Search for your desired indicator', 'zc-dmt'); ?><br>
                        3. <?php echo esc_html__('Copy the indicator code from the dataset page', 'zc-dmt'); ?>
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
    
    // Tab functionality for main form
    $('.zc-tab-button').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');
        
        // Update active tab button
        $('.zc-tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Show/hide tab content
        $('.zc-tab-content').removeClass('active');
        $('#' + tabId + '-method').addClass('active');
        
        // Clear form fields from non-active tab
        if (tabId === 'csv') {
            $('#zc_wb_country_code, #zc_wb_indicator_code').val('');
        } else {
            $('#zc_wb_csv_url').val('');
        }
    });
    
    // Tab functionality for test form
    $('.zc-test-tab-button').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');
        
        // Update active tab button
        $('.zc-test-tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Show/hide tab content
        $('.zc-test-tab-content').removeClass('active');
        $('#' + tabId).addClass('active');
    });
    
    // Copy from main form to test form
    $('#zc_wb_csv_url').on('input', function() {
        $('#test_csv_url').val($(this).val());
    });
    
    $('#zc_wb_country_code').on('input', function() {
        $('#test_country_code').val($(this).val());
    });
    
    $('#zc_wb_indicator_code').on('input', function() {
        $('#test_indicator_code').val($(this).val());
    });
    
    // Generate CSV URL from API inputs
    function generateCsvUrl() {
        var country = $('#zc_wb_country_code').val().trim();
        var indicator = $('#zc_wb_indicator_code').val().trim();
        
        if (country && indicator) {
            var csvUrl = 'https://api.worldbank.org/v2/country/' + encodeURIComponent(country) + '/indicator/' + encodeURIComponent(indicator) + '?format=csv&per_page=1000';
            return csvUrl;
        }
        return '';
    }
    
    // Add "Generate CSV URL" helper button
    $('#api-method .form-table').append(
        '<tr><td colspan="2" style="padding-top: 16px; border-top: 1px solid #e2e8f0;">' +
        '<button type="button" id="generate-csv-btn" class="button button-secondary">' +
        '<?php echo esc_js(__("Generate CSV URL from above", "zc-dmt")); ?>' +
        '</button>' +
        '<p class="description"><?php echo esc_js(__("This will generate a CSV URL and switch to CSV method", "zc-dmt")); ?></p>' +
        '</td></tr>'
    );
    
    $('#generate-csv-btn').on('click', function() {
        var csvUrl = generateCsvUrl();
        if (csvUrl) {
            $('#zc_wb_csv_url').val(csvUrl);
            $('[data-tab="csv"]').click();
            $(this).text('<?php echo esc_js(__("CSV URL Generated!", "zc-dmt")); ?>');
            setTimeout(function() {
                $('#generate-csv-btn').text('<?php echo esc_js(__("Generate CSV URL from above", "zc-dmt")); ?>');
            }, 2000);
        } else {
            alert('<?php echo esc_js(__("Please enter both country and indicator codes first", "zc-dmt")); ?>');
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

/* Tab styling */
.zc-method-tabs, .zc-test-tabs {
    display: flex;
    gap: 2px;
    border-bottom: 1px solid #e2e8f0;
}

.zc-tab-button, .zc-test-tab-button {
    padding: 8px 16px;
    border: none;
    background: #f8fafc;
    color: #64748b;
    cursor: pointer;
    border-radius: 4px 4px 0 0;
    transition: all 0.2s;
}

.zc-tab-button:hover, .zc-test-tab-button:hover {
    background: #e2e8f0;
    color: #334155;
}

.zc-tab-button.active, .zc-test-tab-button.active {
    background: #fff;
    color: #0f172a;
    border-bottom: 2px solid #3b82f6;
}

.zc-tab-content, .zc-test-tab-content {
    display: none;
    padding: 16px 0;
}

.zc-tab-content.active, .zc-test-tab-content.active {
    display: block;
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
    
    .zc-method-tabs, .zc-test-tabs {
        flex-direction: column;
    }
    
    .zc-tab-button, .zc-test-tab-button {
        border-radius: 4px;
        margin-bottom: 2px;
    }
}
</style>