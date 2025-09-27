<?php
if (!defined('ABSPATH')) exit;

/**
 * Simple Charts Builder Page
 * User-friendly interface for creating chart shortcodes
 */

function zc_dmt_render_charts_builder_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'zc-dmt'));
    }

    // Get all active indicators for the builder
    global $wpdb;
    $indicators_table = $wpdb->prefix . 'zc_dmt_indicators';
    $indicators = $wpdb->get_results("SELECT * FROM {$indicators_table} WHERE is_active = 1 ORDER BY name ASC");

    // Get default API key if available
    $default_api_key = get_option('zc_dmt_default_api_key', '');

    // Enqueue necessary scripts
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-autocomplete');
    ?>

    <div class="wrap zc-charts-builder-simple">
        <div class="zc-header">
            <h1 class="zc-page-title">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php _e('Charts Builder', 'zc-dmt'); ?>
            </h1>
            <p class="zc-subtitle"><?php _e('Create professional economic charts with a few clicks', 'zc-dmt'); ?></p>
        </div>

        <div class="zc-builder-container">
            <!-- Main Builder Form -->
            <div class="zc-builder-form">
                <div class="zc-form-section">
                    <h2><?php _e('Chart Configuration', 'zc-dmt'); ?></h2>
                    
                    <!-- Chart Type -->
                    <div class="zc-field-group">
                        <label for="chart_type" class="zc-label">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php _e('Chart Type', 'zc-dmt'); ?>
                        </label>
                        <select id="chart_type" class="zc-select">
                            <option value="dynamic"><?php _e('Dynamic (Full Functionality)', 'zc-dmt'); ?></option>
                            <option value="static"><?php _e('Static (Partial Functionality)', 'zc-dmt'); ?></option>
                            <option value="card"><?php _e('Card (Simple Chart)', 'zc-dmt'); ?></option>
                        </select>
                        <p class="zc-description"><?php _e('Dynamic: Interactive with search & comparison. Static: Fixed display. Card: Simple chart only.', 'zc-dmt'); ?></p>
                    </div>

                    <!-- Search Bar for Indicators -->
                    <div class="zc-field-group">
                        <label for="indicator_search" class="zc-label">
                            <span class="dashicons dashicons-search"></span>
                            <?php _e('Search Indicators', 'zc-dmt'); ?>
                        </label>
                        <div class="zc-search-container">
                            <input type="text" id="indicator_search" class="zc-search-input" placeholder="<?php _e('Type to search indicators...', 'zc-dmt'); ?>" autocomplete="off">
                            <input type="hidden" id="selected_indicator" value="">
                            <div id="search_results" class="zc-search-results"></div>
                        </div>
                        <p class="zc-description"><?php _e('Search and select an indicator from your available data sources.', 'zc-dmt'); ?></p>
                    </div>

                    <!-- Comparison Indicators -->
                    <div class="zc-field-group" id="comparison_section" style="display: none;">
                        <label class="zc-label">
                            <span class="dashicons dashicons-chart-line"></span>
                            <?php _e('Comparison Indicators', 'zc-dmt'); ?>
                        </label>
                        <div class="zc-comparison-builder">
                            <div class="zc-comparison-search">
                                <input type="text" id="comparison_search" class="zc-search-input" placeholder="<?php _e('Add indicator for comparison...', 'zc-dmt'); ?>" autocomplete="off">
                                <div id="comparison_results" class="zc-search-results"></div>
                            </div>
                            <div id="selected_comparisons" class="zc-selected-comparisons"></div>
                            <input type="hidden" id="comparison_indicators" value="">
                        </div>
                        <p class="zc-description"><?php _e('Add multiple indicators to compare on the same chart (2-5 indicators recommended).', 'zc-dmt'); ?></p>
                    </div>

                    <!-- Chart Library -->
                    <div class="zc-field-group">
                        <label for="chart_library" class="zc-label">
                            <span class="dashicons dashicons-chart-line"></span>
                            <?php _e('Chart Library', 'zc-dmt'); ?>
                        </label>
                        <select id="chart_library" class="zc-select">
                            <option value="line"><?php _e('Line Chart', 'zc-dmt'); ?></option>
                            <option value="bar"><?php _e('Bar Chart', 'zc-dmt'); ?></option>
                            <option value="area"><?php _e('Area Chart', 'zc-dmt'); ?></option>
                            <option value="scatter"><?php _e('Scatter Chart', 'zc-dmt'); ?></option>
                            <option value="all"><?php _e('All Charts (Show All Types)', 'zc-dmt'); ?></option>
                        </select>
                        <p class="zc-description"><?php _e('Choose the chart visualization type for your data.', 'zc-dmt'); ?></p>
                    </div>

                    <!-- Default Timeframe -->
                    <div class="zc-field-group">
                        <label for="default_timeframe" class="zc-label">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php _e('Default Timeframe', 'zc-dmt'); ?>
                        </label>
                        <select id="default_timeframe" class="zc-select">
                            <option value="3M"><?php _e('3 Months', 'zc-dmt'); ?></option>
                            <option value="6M"><?php _e('6 Months', 'zc-dmt'); ?></option>
                            <option value="1Y" selected><?php _e('1 Year', 'zc-dmt'); ?></option>
                            <option value="2Y"><?php _e('2 Years', 'zc-dmt'); ?></option>
                            <option value="3Y"><?php _e('3 Years', 'zc-dmt'); ?></option>
                            <option value="5Y"><?php _e('5 Years', 'zc-dmt'); ?></option>
                            <option value="10Y"><?php _e('10 Years', 'zc-dmt'); ?></option>
                            <option value="15Y"><?php _e('15 Years', 'zc-dmt'); ?></option>
                            <option value="20Y"><?php _e('20 Years', 'zc-dmt'); ?></option>
                            <option value="ALL"><?php _e('All Available Data', 'zc-dmt'); ?></option>
                        </select>
                        <p class="zc-description"><?php _e('The time period that loads when the chart first displays.', 'zc-dmt'); ?></p>
                    </div>

                    <!-- Chart Height -->
                    <div class="zc-field-group">
                        <label for="chart_height" class="zc-label">
                            <span class="dashicons dashicons-editor-expand"></span>
                            <?php _e('Chart Height', 'zc-dmt'); ?>
                        </label>
                        <div class="zc-height-controls">
                            <input type="range" id="height_slider" min="300" max="800" value="600" class="zc-slider">
                            <input type="number" id="chart_height" min="300" max="800" value="600" class="zc-height-input">
                            <span class="zc-unit">px</span>
                        </div>
                        <p class="zc-description"><?php _e('Adjust the height of your chart (300px - 800px).', 'zc-dmt'); ?></p>
                    </div>

                    <!-- API Key -->
                    <div class="zc-field-group">
                        <label for="api_key" class="zc-label">
                            <span class="dashicons dashicons-lock"></span>
                            <?php _e('API Key', 'zc-dmt'); ?>
                        </label>
                        <input type="text" id="api_key" class="zc-text-input" value="<?php echo esc_attr($default_api_key); ?>" placeholder="<?php _e('Enter your API key...', 'zc-dmt'); ?>">
                        <p class="zc-description"><?php _e('Provide an API key to secure your chart data. If left blank, the default key from settings will be used if available.', 'zc-dmt'); ?></p>
                    </div>

                    <!-- Interactive Controls -->
                    <div class="zc-field-group" id="interactive_controls">
                        <label class="zc-label">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php _e('Interactive Controls', 'zc-dmt'); ?>
                        </label>
                        <div class="zc-controls-grid">
                            <label class="zc-checkbox-label">
                                <input type="checkbox" id="show_search" checked>
                                <span class="zc-checkmark"></span>
                                <?php _e('Show Search', 'zc-dmt'); ?>
                            </label>
                            <label class="zc-checkbox-label">
                                <input type="checkbox" id="show_comparison" checked>
                                <span class="zc-checkmark"></span>
                                <?php _e('Show Comparison', 'zc-dmt'); ?>
                            </label>
                            <label class="zc-checkbox-label">
                                <input type="checkbox" id="show_timeframes" checked>
                                <span class="zc-checkmark"></span>
                                <?php _e('Show Timeframes', 'zc-dmt'); ?>
                            </label>
                            <label class="zc-checkbox-label">
                                <input type="checkbox" id="show_stats" checked>
                                <span class="zc-checkmark"></span>
                                <?php _e('Show Statistics', 'zc-dmt'); ?>
                            </label>
                            <label class="zc-checkbox-label">
                                <input type="checkbox" id="show_fullscreen" checked>
                                <span class="zc-checkmark"></span>
                                <?php _e('Show Fullscreen', 'zc-dmt'); ?>
                            </label>
                            <label class="zc-checkbox-label">
                                <input type="checkbox" id="show_theme_toggle" checked>
                                <span class="zc-checkmark"></span>
                                <?php _e('Show Theme Toggle', 'zc-dmt'); ?>
                            </label>
                        </div>
                        <p class="zc-description"><?php _e('Select which interactive features to include in your chart.', 'zc-dmt'); ?></p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="zc-actions">
                    <button type="button" id="build_shortcode" class="zc-btn zc-btn-primary">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php _e('Build Shortcode', 'zc-dmt'); ?>
                    </button>
                    <button type="button" id="test_data" class="zc-btn zc-btn-secondary">
                        <span class="dashicons dashicons-admin-network"></span>
                        <?php _e('Test Data', 'zc-dmt'); ?>
                    </button>
                </div>
            </div>

            <!-- Shortcode Output -->
            <div class="zc-output-section">
                <div class="zc-output-header">
                    <h3><?php _e('Generated Shortcode', 'zc-dmt'); ?></h3>
                    <div class="zc-output-actions">
                        <button type="button" id="copy_shortcode" class="zc-btn zc-btn-copy" disabled>
                            <span class="dashicons dashicons-admin-page"></span>
                            <?php _e('Copy', 'zc-dmt'); ?>
                        </button>
                    </div>
                </div>
                <div class="zc-shortcode-display">
                    <textarea id="generated_shortcode" readonly placeholder="<?php _e('Your shortcode will appear here...', 'zc-dmt'); ?>"></textarea>
                </div>
                <div class="zc-status-message" id="status_message"></div>
            </div>
        </div>

        <!-- Usage Guide -->
        <div class="zc-usage-guide">
            <h3><?php _e('How to Use Your Shortcode', 'zc-dmt'); ?></h3>
            <div class="zc-steps">
                <div class="zc-step">
                    <span class="zc-step-number">1</span>
                    <div class="zc-step-content">
                        <h4><?php _e('Configure Your Chart', 'zc-dmt'); ?></h4>
                        <p><?php _e('Select chart type, search for an indicator, and customize the settings above.', 'zc-dmt'); ?></p>
                    </div>
                </div>
                <div class="zc-step">
                    <span class="zc-step-number">2</span>
                    <div class="zc-step-content">
                        <h4><?php _e('Generate Shortcode', 'zc-dmt'); ?></h4>
                        <p><?php _e('Click "Build Shortcode" to generate your custom chart shortcode.', 'zc-dmt'); ?></p>
                    </div>
                </div>
                <div class="zc-step">
                    <span class="zc-step-number">3</span>
                    <div class="zc-step-content">
                        <h4><?php _e('Copy & Paste', 'zc-dmt'); ?></h4>
                        <p><?php _e('Copy the shortcode and paste it into any WordPress post or page.', 'zc-dmt'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .zc-charts-builder-simple {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .zc-header {
        text-align: center;
        margin-bottom: 40px;
        padding: 30px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }

    .zc-page-title {
        font-size: 2.5em;
        margin: 0 0 10px 0;
        font-weight: 300;
    }

    .zc-page-title .dashicons {
        font-size: 1em;
        margin-right: 10px;
        vertical-align: middle;
    }

    .zc-subtitle {
        font-size: 1.2em;
        margin: 0;
        opacity: 0.9;
    }

    .zc-builder-container {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
        margin-bottom: 40px;
    }

    .zc-builder-form {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border: 1px solid #e1e5e9;
    }

    .zc-form-section h2 {
        color: #2c3e50;
        margin-bottom: 25px;
        font-size: 1.5em;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }

    .zc-field-group {
        margin-bottom: 25px;
    }

    .zc-label {
        display: block;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
        font-size: 14px;
    }

    .zc-label .dashicons {
        color: #3498db;
        margin-right: 8px;
        font-size: 16px;
    }

    .zc-select, .zc-search-input, .zc-text-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
    }

    .zc-select:focus, .zc-search-input:focus, .zc-text-input:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        outline: none;
    }

    .zc-search-container {
        position: relative;
    }

    .zc-search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 2px solid #e1e5e9;
        border-top: none;
        border-radius: 0 0 8px 8px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }

    .zc-search-result {
        padding: 12px 16px;
        cursor: pointer;
        border-bottom: 1px solid #f8f9fa;
        transition: background 0.2s ease;
    }

    .zc-search-result:hover {
        background: #f8f9fa;
    }

    .zc-search-result:last-child {
        border-bottom: none;
    }

    .zc-result-name {
        font-weight: 600;
        color: #2c3e50;
        display: block;
        margin-bottom: 4px;
    }

    .zc-result-meta {
        font-size: 12px;
        color: #7f8c8d;
    }

    .zc-result-source {
        background: #3498db;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        margin-left: 8px;
    }

    .zc-height-controls {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .zc-slider {
        flex: 1;
        height: 6px;
        border-radius: 3px;
        background: #e1e5e9;
        outline: none;
        -webkit-appearance: none;
    }

    .zc-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #3498db;
        cursor: pointer;
    }

    .zc-height-input {
        width: 80px;
        padding: 8px 12px;
        border: 2px solid #e1e5e9;
        border-radius: 6px;
        text-align: center;
    }

    .zc-unit {
        color: #7f8c8d;
        font-weight: 600;
    }

    .zc-controls-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .zc-checkbox-label {
        display: flex;
        align-items: center;
        cursor: pointer;
        padding: 10px;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .zc-checkbox-label:hover {
        border-color: #3498db;
        background: #f8f9fa;
    }

    .zc-checkbox-label input[type="checkbox"] {
        margin-right: 10px;
        transform: scale(1.2);
    }

    .zc-description {
        margin-top: 8px;
        font-size: 13px;
        color: #7f8c8d;
        line-height: 1.4;
    }

    .zc-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        padding-top: 25px;
        border-top: 2px solid #f8f9fa;
    }

    .zc-btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .zc-btn-primary {
        background: #3498db;
        color: white;
    }

    .zc-btn-primary:hover {
        background: #2980b9;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
    }

    .zc-btn-secondary {
        background: #95a5a6;
        color: white;
    }

    .zc-btn-secondary:hover {
        background: #7f8c8d;
        transform: translateY(-2px);
    }

    .zc-output-section {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border: 1px solid #e1e5e9;
        height: fit-content;
    }

    .zc-output-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f8f9fa;
    }

    .zc-output-header h3 {
        margin: 0;
        color: #2c3e50;
    }

    .zc-btn-copy {
        background: #27ae60;
        color: white;
        padding: 8px 16px;
        font-size: 12px;
    }

    .zc-btn-copy:hover:not(:disabled) {
        background: #229954;
        transform: translateY(-1px);
    }

    .zc-btn-copy:disabled {
        background: #bdc3c7;
        cursor: not-allowed;
        transform: none;
    }

    .zc-shortcode-display textarea {
        width: 100%;
        min-height: 120px;
        padding: 15px;
        border: 2px dashed #e1e5e9;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 13px;
        background: #f8f9fa;
        resize: vertical;
        line-height: 1.5;
    }

    .zc-shortcode-display textarea:focus {
        border-color: #3498db;
        background: white;
        outline: none;
    }

    .zc-status-message {
        margin-top: 15px;
        padding: 10px 15px;
        border-radius: 6px;
        font-size: 13px;
        display: none;
    }

    .zc-status-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .zc-status-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .zc-usage-guide {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border: 1px solid #e1e5e9;
    }

    .zc-usage-guide h3 {
        color: #2c3e50;
        margin-bottom: 25px;
        text-align: center;
        font-size: 1.4em;
    }

    .zc-steps {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
    }

    .zc-step {
        display: flex;
        align-items: flex-start;
        gap: 15px;
    }

    .zc-step-number {
        background: #3498db;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        flex-shrink: 0;
    }

    .zc-step-content h4 {
        margin: 0 0 8px 0;
        color: #2c3e50;
    }

    .zc-step-content p {
        margin: 0;
        color: #7f8c8d;
        line-height: 1.5;
    }

    /* Comparison Builder Styles */
    .zc-comparison-builder {
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        padding: 15px;
        background: #f8f9fa;
    }

    .zc-comparison-search {
        position: relative;
        margin-bottom: 15px;
    }

    .zc-selected-comparisons {
        min-height: 40px;
    }

    .zc-comparison-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .zc-comparison-tag {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #00bcd4;
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }

    .zc-remove-comparison {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        font-size: 16px;
        line-height: 1;
        padding: 0;
        margin-left: 4px;
    }

    .zc-remove-comparison:hover {
        opacity: 0.8;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .zc-builder-container {
            grid-template-columns: 1fr;
        }
        
        .zc-controls-grid {
            grid-template-columns: 1fr;
        }
        
        .zc-actions {
            flex-direction: column;
        }
        
        .zc-steps {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        let indicators = <?php echo json_encode($indicators); ?> || [];
        let selectedIndicator = null;

        // Always refresh indicators on page load so builder sees the latest added indicators
        (function refreshIndicators(){
            jQuery.post(ajaxurl, {
                action: 'zc_dmt_list_indicators',
                nonce: '<?php echo wp_create_nonce('zc_dmt_dashboard'); ?>',
                limit: 1000
            }).done(function(resp){
                if (resp && resp.success && resp.data && Array.isArray(resp.data.indicators)) {
                    indicators = resp.data.indicators;
                }
            }).fail(function(){
                // keep the PHP-provided fallback list
            });
        })();

        // Chart type change handler
        $('#chart_type').on('change', function() {
            const chartType = $(this).val();
            const $controls = $('#interactive_controls');
            
            if (chartType === 'card') {
                $controls.hide();
            } else if (chartType === 'static') {
                $controls.show();
                $('#show_search, #show_comparison').prop('checked', false).prop('disabled', true);
            } else {
                $controls.show();
                $('#show_search, #show_comparison').prop('checked', true).prop('disabled', false);
            }
        });

        // Height slider sync
        $('#height_slider').on('input', function() {
            $('#chart_height').val($(this).val());
        });

        $('#chart_height').on('input', function() {
            $('#height_slider').val($(this).val());
        });

        // Indicator search functionality
        $('#indicator_search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            const $results = $('#search_results');
            
            if (searchTerm.length < 2) {
                $results.hide().empty();
                return;
            }

            const filteredIndicators = indicators.filter(indicator => 
                indicator.name.toLowerCase().includes(searchTerm) ||
                indicator.slug.toLowerCase().includes(searchTerm) ||
                indicator.source_type.toLowerCase().includes(searchTerm)
            );

            if (filteredIndicators.length > 0) {
                let html = '';
                filteredIndicators.slice(0, 10).forEach(indicator => {
                    html += `
                        <div class="zc-search-result" data-slug="${indicator.slug}" data-name="${indicator.name}">
                            <span class="zc-result-name">${indicator.name}</span>
                            <span class="zc-result-meta">
                                ${indicator.slug}
                                <span class="zc-result-source">${indicator.source_type.replace(/_/g, ' ').toUpperCase()}</span>
                            </span>
                        </div>
                    `;
                });
                $results.html(html).show();
            } else {
                $results.html('<div class="zc-search-result">No indicators found</div>').show();
            }
        });

        // Handle indicator selection
        $(document).on('click', '.zc-search-result', function() {
            if ($(this).data('slug')) {
                const slug = $(this).data('slug');
                const name = $(this).data('name');
                
                $('#indicator_search').val(name);
                $('#selected_indicator').val(slug);
                $('#search_results').hide();
                
                selectedIndicator = indicators.find(i => i.slug === slug);
                showStatus('Indicator selected: ' + name, 'success');
            }
        });

        // Hide search results when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.zc-search-container').length) {
                $('#search_results').hide();
            }
        });

        // Comparison functionality
        let comparisonIndicators = [];

        // Show/hide comparison section based on chart type
        $('#chart_type').on('change', function() {
            const chartType = $(this).val();
            const $controls = $('#interactive_controls');
            const $comparison = $('#comparison_section');
            
            if (chartType === 'card') {
                $controls.hide();
                $comparison.hide();
            } else if (chartType === 'static') {
                $controls.show();
                $comparison.show(); // Show comparison for static charts too
                $('#show_search, #show_comparison').prop('checked', false).prop('disabled', true);
            } else {
                $controls.show();
                $comparison.show();
                $('#show_search, #show_comparison').prop('checked', true).prop('disabled', false);
            }
        });

        // Trigger initial state
        $('#chart_type').trigger('change');

        // Comparison search functionality
        $('#comparison_search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            const $results = $('#comparison_results');
            
            if (searchTerm.length < 2) {
                $results.hide().empty();
                return;
            }

            const filteredIndicators = indicators.filter(indicator => 
                indicator.name.toLowerCase().includes(searchTerm) &&
                indicator.slug !== selectedIndicator?.slug &&
                !comparisonIndicators.find(comp => comp.slug === indicator.slug)
            );

            if (filteredIndicators.length > 0) {
                let html = '';
                filteredIndicators.slice(0, 10).forEach(indicator => {
                    html += `
                        <div class="zc-search-result zc-comparison-result" data-slug="${indicator.slug}" data-name="${indicator.name}">
                            <span class="zc-result-name">${indicator.name}</span>
                            <span class="zc-result-meta">
                                ${indicator.slug}
                                <span class="zc-result-source">${indicator.source_type.replace(/_/g, ' ').toUpperCase()}</span>
                            </span>
                        </div>
                    `;
                });
                $results.html(html).show();
            } else {
                $results.html('<div class="zc-search-result">No indicators found</div>').show();
            }
        });

        // Handle comparison selection
        $(document).on('click', '.zc-comparison-result', function() {
            if ($(this).data('slug') && comparisonIndicators.length < 10) {
                const slug = $(this).data('slug');
                const name = $(this).data('name');
                
                comparisonIndicators.push({ slug, name });
                updateComparisonDisplay();
                
                $('#comparison_search').val('');
                $('#comparison_results').hide();
                
                showStatus('Added to comparison: ' + name, 'success');
            } else if (comparisonIndicators.length >= 10) {
                showStatus('Maximum 10 comparison indicators allowed', 'error');
            }
        });

        function updateComparisonDisplay() {
            const $container = $('#selected_comparisons');
            const $hiddenInput = $('#comparison_indicators');
            
            if (comparisonIndicators.length === 0) {
                $container.empty();
                $hiddenInput.val('');
                return;
            }

            let html = '<div class="zc-comparison-tags">';
            comparisonIndicators.forEach((indicator, index) => {
                html += `
                    <div class="zc-comparison-tag">
                        <span>${indicator.name}</span>
                        <button type="button" class="zc-remove-comparison" data-index="${index}">×</button>
                    </div>
                `;
            });
            html += '</div>';
            
            $container.html(html);
            $hiddenInput.val(comparisonIndicators.map(i => i.slug).join(','));
        }

        // Remove comparison indicator
        $(document).on('click', '.zc-remove-comparison', function() {
            const index = parseInt($(this).data('index'));
            comparisonIndicators.splice(index, 1);
            updateComparisonDisplay();
        });

        // Build shortcode
        $('#build_shortcode').on('click', function() {
            if (!selectedIndicator) {
                showStatus('Please select an indicator first', 'error');
                return;
            }

            const chartType = $('#chart_type').val();
            const chartLibrary = $('#chart_library').val();
            const timeframe = $('#default_timeframe').val();
            const height = $('#chart_height').val();
            const apiKey = $('#api_key').val().trim(); // Get API key from input
            
            let shortcode = '';
            
            if (chartType === 'dynamic') {
                shortcode = `[zc_economic_dashboard`;
                shortcode += ` default_indicator="${selectedIndicator.slug}"`;
                shortcode += ` default_time_range="${timeframe}"`;
                shortcode += ` default_chart_type="${chartLibrary}"`;
                shortcode += ` height="${height}"`;
                
                if (apiKey) shortcode += ` access_key="${apiKey}"`;
                if (!$('#show_search').is(':checked')) shortcode += ` show_search="false"`;
                if (!$('#show_comparison').is(':checked')) shortcode += ` show_comparison="false"`;
                if (!$('#show_timeframes').is(':checked')) shortcode += ` show_timeframes="false"`;
                if (!$('#show_stats').is(':checked')) shortcode += ` show_stats="false"`;
                if (!$('#show_fullscreen').is(':checked')) shortcode += ` show_fullscreen="false"`;
                if (!$('#show_theme_toggle').is(':checked')) shortcode += ` show_theme_toggle="false"`;
                
                shortcode += `]`;
            } else if (chartType === 'static') {
                if (comparisonIndicators.length > 0) {
                    // Generate comparison shortcode
                    const allIndicators = [selectedIndicator.slug, ...comparisonIndicators.map(i => i.slug)];
                    shortcode = `[zc_chart_comparison`;
                    shortcode += ` indicators="${allIndicators.join(',')}"`;
                    shortcode += ` chart_type="${chartLibrary}"`;
                    shortcode += ` time_range="${timeframe}"`;
                    shortcode += ` height="${height}"`;
                    if (apiKey) shortcode += ` access_key="${apiKey}"`;
                    shortcode += `]`;
                } else {
                    shortcode = `[zc_chart_enhanced`;
                    shortcode += ` id="${selectedIndicator.slug}"`;
                    shortcode += ` type="${chartLibrary}"`;
                    shortcode += ` time_range="${timeframe}"`;
                    shortcode += ` height="${height}"`;
                    if (apiKey) shortcode += ` access_key="${apiKey}"`;
                    shortcode += ` show_controls="false"`;
                    shortcode += ` comparison_enabled="false"`;
                    shortcode += `]`;
                }
            } else { // card
                shortcode = `[zc_chart_enhanced`;
                shortcode += ` id="${selectedIndicator.slug}"`;
                shortcode += ` type="${chartLibrary}"`;
                shortcode += ` height="${height}"`;
                if (apiKey) shortcode += ` access_key="${apiKey}"`;
                shortcode += ` show_stats="false"`;
                shortcode += ` show_controls="false"`;
                shortcode += ` show_timeframes="false"`;
                shortcode += `]`;
            }

            $('#generated_shortcode').val(shortcode);
            $('#copy_shortcode').prop('disabled', false);
            showStatus('Shortcode generated successfully!', 'success');
        });

        // Copy shortcode
        $('#copy_shortcode').on('click', function() {
            const $textarea = $('#generated_shortcode');
            $textarea.select();
            document.execCommand('copy');
            showStatus('Shortcode copied to clipboard!', 'success');
        });

        // Test data functionality
        $('#test_data').on('click', function() {
            if (!selectedIndicator) {
                showStatus('Please select an indicator first', 'error');
                return;
            }

            const $button = $(this);
            const originalText = $button.html();
            $button.html('<span class="dashicons dashicons-update spin"></span> Testing...').prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'zc_dmt_get_dashboard_data',
                    nonce: '<?php echo wp_create_nonce('zc_dmt_dashboard'); ?>',
                    slug: selectedIndicator.slug
                },
                success: function(response) {
                    if (response.success && response.data.series && response.data.series.length > 0) {
                        showStatus(`✅ Success! Found ${response.data.series.length} data points for "${selectedIndicator.name}"`, 'success');
                    } else {
                        showStatus('⚠️ No data found for this indicator. Please check the data source configuration.', 'error');
                    }
                },
                error: function() {
                    showStatus('❌ Failed to test data. Please check your indicator configuration.', 'error');
                },
                complete: function() {
                    $button.html(originalText).prop('disabled', false);
                }
            });
        });

        // Status message helper
        function showStatus(message, type) {
            const $status = $('#status_message');
            $status.removeClass('zc-status-success zc-status-error')
                   .addClass('zc-status-' + type)
                   .text(message)
                   .show();
            
            setTimeout(function() {
                $status.fadeOut();
            }, 5000);
        }

        // Auto-hide search results on scroll
        $(window).on('scroll', function() {
            $('#search_results').hide();
        });
    });
    </script>
    <?php
}
?>
