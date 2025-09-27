<?php
if (!defined('ABSPATH')) exit;

/**
 * Simple Calculations Page with Templates
 * Easy-to-use formula templates for economic analysis
 */

function zc_dmt_render_calculations_simple_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'zc-dmt'));
    }

    // Handle template-based calculation creation
    if ($_POST && wp_verify_nonce(isset($_POST['zc_calculations_nonce']) ? $_POST['zc_calculations_nonce'] : '', 'zc_calculations_action')) {
        $action = sanitize_text_field(isset($_POST['action']) ? $_POST['action'] : '');
        
        if ($action === 'create_from_template') {
            $template_id = sanitize_text_field(isset($_POST['template_id']) ? $_POST['template_id'] : '');
            $indicator_slug = sanitize_text_field(isset($_POST['indicator_slug']) ? $_POST['indicator_slug'] : '');
            $periods = intval(isset($_POST['periods']) ? $_POST['periods'] : 12);
            $custom_name = sanitize_text_field(isset($_POST['custom_name']) ? $_POST['custom_name'] : '');
            
            if (!empty($template_id) && !empty($indicator_slug)) {
                $result = create_calculation_from_template($template_id, $indicator_slug, $periods, $custom_name);
                
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Calculation created and added to indicators!', 'zc-dmt') . '</p></div>';
                }
            }
        }
    }

    // Get available indicators
    global $wpdb;
    $indicators_table = $wpdb->prefix . 'zc_dmt_indicators';
    $indicators = $wpdb->get_results("SELECT id, name, slug FROM {$indicators_table} WHERE is_active = 1 AND source_type != 'calculation' ORDER BY name ASC");

    // Get existing calculations
    $calculations = ZC_DMT_Calculations::list_calculations(50);

    // Formula templates
    $templates = get_formula_templates();

    wp_enqueue_script('jquery');
    ?>

    <div class="wrap zc-calculations-simple">
        <div class="zc-header">
            <h1 class="zc-page-title">
                <span class="dashicons dashicons-calculator"></span>
                <?php _e('Economic Analysis Templates', 'zc-dmt'); ?>
            </h1>
            <p class="zc-subtitle"><?php _e('Create technical indicators with one click using pre-built templates', 'zc-dmt'); ?></p>
        </div>

        <div class="zc-templates-container">
            <!-- Template Selection -->
            <div class="zc-templates-grid">
                <?php foreach ($templates as $category => $category_templates): ?>
                    <div class="zc-template-category">
                        <h3><?php echo esc_html(ucwords(str_replace('_', ' ', $category))); ?> Analysis</h3>
                        
                        <?php foreach ($category_templates as $template_id => $template): ?>
                            <div class="zc-template-card" data-template="<?php echo esc_attr($template_id); ?>">
                                <div class="zc-template-header">
                                    <h4><?php echo esc_html($template['name']); ?></h4>
                                    <span class="zc-template-type"><?php echo esc_html($template['type']); ?></span>
                                </div>
                                <p class="zc-template-description"><?php echo esc_html($template['description']); ?></p>
                                <div class="zc-template-example">
                                    <strong>Example:</strong> <code><?php echo esc_html($template['example']); ?></code>
                                </div>
                                <button type="button" class="zc-btn zc-btn-primary zc-use-template" 
                                        data-template="<?php echo esc_attr($template_id); ?>"
                                        data-name="<?php echo esc_attr($template['name']); ?>"
                                        data-formula="<?php echo esc_attr($template['formula']); ?>"
                                        data-periods="<?php echo esc_attr($template['default_periods']); ?>">
                                    <?php _e('Use This Template', 'zc-dmt'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Configuration Form -->
            <div class="zc-config-form" id="config_form" style="display: none;">
                <h3 id="selected_template_name"><?php _e('Configure Template', 'zc-dmt'); ?></h3>
                
                <form method="post" action="">
                    <?php wp_nonce_field('zc_calculations_action', 'zc_calculations_nonce'); ?>
                    <input type="hidden" name="action" value="create_from_template">
                    <input type="hidden" id="template_id" name="template_id" value="">
                    
                    <!-- Indicator Selection with Search -->
                    <div class="zc-field-group">
                        <label for="indicator_search" class="zc-label">
                            <span class="dashicons dashicons-chart-line"></span>
                            <?php _e('Select Indicator', 'zc-dmt'); ?>
                        </label>
                        <div class="zc-indicator-search-container">
                            <input type="text" id="indicator_search" class="zc-input zc-indicator-search" 
                                   placeholder="<?php _e('Search indicators...', 'zc-dmt'); ?>" autocomplete="off">
                            <input type="hidden" id="indicator_slug" name="indicator_slug" required>
                            <div id="indicator_results" class="zc-indicator-results"></div>
                        </div>
                        <p class="zc-description"><?php _e('Search and select the indicator to apply this analysis to.', 'zc-dmt'); ?></p>
                    </div>

                    <!-- Periods (for time-based calculations) -->
                    <div class="zc-field-group" id="periods_field">
                        <label for="periods" class="zc-label">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php _e('Time Periods', 'zc-dmt'); ?>
                        </label>
                        <input type="number" id="periods" name="periods" class="zc-input" value="12" min="1" max="100">
                        <p class="zc-description" id="periods_description"><?php _e('Number of periods for the calculation.', 'zc-dmt'); ?></p>
                    </div>

                    <!-- Custom Name -->
                    <div class="zc-field-group">
                        <label for="custom_name" class="zc-label">
                            <span class="dashicons dashicons-tag"></span>
                            <?php _e('Custom Name (Optional)', 'zc-dmt'); ?>
                        </label>
                        <input type="text" id="custom_name" name="custom_name" class="zc-input" 
                               placeholder="<?php _e('Leave empty to use auto-generated name', 'zc-dmt'); ?>">
                        <p class="zc-description"><?php _e('Custom name for this calculation. If empty, will auto-generate based on template and indicator.', 'zc-dmt'); ?></p>
                    </div>

                    <!-- Preview -->
                    <div class="zc-field-group">
                        <label class="zc-label">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php _e('Formula Preview', 'zc-dmt'); ?>
                        </label>
                        <div class="zc-formula-preview">
                            <code id="formula_preview"><?php _e('Select a template and indicator to see preview', 'zc-dmt'); ?></code>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="zc-actions">
                        <button type="button" id="cancel_template" class="zc-btn zc-btn-secondary">
                            <?php _e('Cancel', 'zc-dmt'); ?>
                        </button>
                        <button type="submit" class="zc-btn zc-btn-primary">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php _e('Create Analysis', 'zc-dmt'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Existing Calculations -->
        <?php if (!empty($calculations)): ?>
            <div class="zc-existing-calculations">
                <h2><?php _e('Your Economic Analysis', 'zc-dmt'); ?></h2>
                <div class="zc-calculations-grid">
                    <?php foreach ($calculations as $calc): ?>
                        <div class="zc-calculation-card">
                            <h4><?php echo esc_html($calc->name); ?></h4>
                            <p class="zc-calc-formula"><code><?php echo esc_html($calc->formula); ?></code></p>
                            <p class="zc-calc-meta">
                                <span class="zc-calc-type"><?php echo esc_html(ucwords($calc->output_type)); ?></span>
                                <span class="zc-calc-date"><?php echo esc_html(date_i18n('M j, Y', strtotime($calc->created_at))); ?></span>
                            </p>
                            <div class="zc-calc-actions">
                                <button type="button" class="zc-btn zc-btn-small zc-test-calc" 
                                        data-slug="<?php echo esc_attr($calc->slug); ?>">
                                    <?php _e('Test', 'zc-dmt'); ?>
                                </button>
                                <button type="button" class="zc-btn zc-btn-small zc-copy-shortcode" 
                                        data-slug="<?php echo esc_attr($calc->slug); ?>">
                                    <?php _e('Copy Shortcode', 'zc-dmt'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .zc-calculations-simple {
        max-width: 1400px;
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

    .zc-subtitle {
        font-size: 1.2em;
        margin: 0;
        opacity: 0.9;
    }

    .zc-templates-container {
        margin-bottom: 40px;
    }

    .zc-templates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 30px;
        margin-bottom: 30px;
    }

    .zc-template-category h3 {
        color: #2c3e50;
        margin-bottom: 20px;
        font-size: 1.3em;
        border-bottom: 2px solid #3498db;
        padding-bottom: 8px;
    }

    .zc-template-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border: 1px solid #e1e5e9;
        margin-bottom: 15px;
        transition: transform 0.2s ease;
    }

    .zc-template-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }

    .zc-template-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .zc-template-header h4 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.1em;
    }

    .zc-template-type {
        background: #3498db;
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }

    .zc-template-description {
        color: #7f8c8d;
        margin-bottom: 10px;
        line-height: 1.4;
    }

    .zc-template-example {
        background: #f8f9fa;
        padding: 8px 12px;
        border-radius: 6px;
        margin-bottom: 15px;
        font-size: 13px;
    }

    .zc-template-example code {
        background: #e9ecef;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 12px;
    }

    .zc-config-form {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border: 1px solid #e1e5e9;
        margin-bottom: 30px;
    }

    .zc-config-form h3 {
        color: #2c3e50;
        margin-bottom: 25px;
        font-size: 1.4em;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }

    .zc-field-group {
        margin-bottom: 20px;
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

    .zc-input, .zc-select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
    }

    .zc-input:focus, .zc-select:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        outline: none;
    }

    .zc-description {
        margin-top: 6px;
        font-size: 13px;
        color: #7f8c8d;
        line-height: 1.4;
    }

    .zc-formula-preview {
        background: #f8f9fa;
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid #e1e5e9;
    }

    .zc-formula-preview code {
        font-family: 'Courier New', monospace;
        font-size: 14px;
        color: #2c3e50;
    }

    .zc-btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .zc-btn-primary {
        background: #3498db;
        color: white;
    }

    .zc-btn-primary:hover {
        background: #2980b9;
        transform: translateY(-1px);
    }

    .zc-btn-secondary {
        background: #95a5a6;
        color: white;
    }

    .zc-btn-secondary:hover {
        background: #7f8c8d;
    }

    .zc-btn-small {
        padding: 6px 12px;
        font-size: 12px;
    }

    .zc-actions {
        display: flex;
        gap: 15px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #e1e5e9;
    }

    .zc-existing-calculations h2 {
        color: #2c3e50;
        margin-bottom: 20px;
        font-size: 1.5em;
    }

    .zc-calculations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .zc-calculation-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border: 1px solid #e1e5e9;
    }

    .zc-calculation-card h4 {
        margin: 0 0 10px 0;
        color: #2c3e50;
    }

    .zc-calc-formula {
        background: #f8f9fa;
        padding: 8px 12px;
        border-radius: 6px;
        margin-bottom: 10px;
    }

    .zc-calc-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        font-size: 12px;
        color: #7f8c8d;
    }

    .zc-calc-type {
        background: #e3f2fd;
        color: #1976d2;
        padding: 3px 8px;
        border-radius: 10px;
        font-weight: 600;
    }

    .zc-calc-actions {
        display: flex;
        gap: 8px;
    }

    /* Indicator Search Styles */
    .zc-indicator-search-container {
        position: relative;
    }

    .zc-indicator-search {
        position: relative;
        z-index: 2;
    }

    .zc-indicator-results {
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
        z-index: 10;
        display: none;
    }

    .zc-indicator-results.active {
        display: block;
    }

    .zc-indicator-result-item {
        padding: 12px 16px;
        cursor: pointer;
        border-bottom: 1px solid #f1f3f4;
        transition: background-color 0.2s ease;
    }

    .zc-indicator-result-item:hover {
        background-color: #f8f9fa;
    }

    .zc-indicator-result-item:last-child {
        border-bottom: none;
    }

    .zc-indicator-result-item.selected {
        background-color: #e3f2fd;
        color: #1976d2;
    }

    .zc-indicator-result-name {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 4px;
    }

    .zc-indicator-result-meta {
        font-size: 12px;
        color: #7f8c8d;
    }

    .zc-indicator-result-slug {
        background: #f1f3f4;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: monospace;
        margin-right: 8px;
    }

    .zc-indicator-selected {
        background: #e8f5e8 !important;
        border-color: #4caf50 !important;
    }

    .zc-indicator-selected::after {
        content: '✓';
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #4caf50;
        font-weight: bold;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .zc-templates-grid {
            grid-template-columns: 1fr;
        }
        
        .zc-calculations-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        let selectedTemplate = null;

        // Template selection
        $('.zc-use-template').on('click', function() {
            const templateId = $(this).data('template');
            const templateName = $(this).data('name');
            const formula = $(this).data('formula');
            const periods = $(this).data('periods');

            selectedTemplate = {
                id: templateId,
                name: templateName,
                formula: formula,
                periods: periods
            };

            // Update form
            $('#template_id').val(templateId);
            $('#selected_template_name').text('Configure: ' + templateName);
            $('#periods').val(periods);
            
            // Show/hide periods field based on formula
            if (formula.includes('{periods}')) {
                $('#periods_field').show();
            } else {
                $('#periods_field').hide();
            }

            // Show config form
            $('#config_form').show();
            
            // Scroll to form
            $('html, body').animate({
                scrollTop: $('#config_form').offset().top - 100
            }, 500);

            updatePreview();
        });

        // Cancel template
        $('#cancel_template').on('click', function() {
            $('#config_form').hide();
            selectedTemplate = null;
        });

        // Indicator search functionality
        const indicators = <?php echo wp_json_encode($indicators); ?>;
        let searchTimeout;

        $('#indicator_search').on('input', function() {
            const query = $(this).val().toLowerCase();
            const resultsContainer = $('#indicator_results');
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                if (query.length < 2) {
                    resultsContainer.removeClass('active').empty();
                    return;
                }

                const filtered = indicators.filter(indicator => 
                    indicator.name.toLowerCase().includes(query) ||
                    indicator.slug.toLowerCase().includes(query)
                );

                resultsContainer.empty();

                if (filtered.length > 0) {
                    filtered.slice(0, 10).forEach(indicator => {
                        const item = $(`
                            <div class="zc-indicator-result-item" data-slug="${indicator.slug}" data-name="${indicator.name}">
                                <div class="zc-indicator-result-name">${indicator.name}</div>
                                <div class="zc-indicator-result-meta">
                                    <span class="zc-indicator-result-slug">${indicator.slug}</span>
                                </div>
                            </div>
                        `);
                        
                        item.on('click', function() {
                            const slug = $(this).data('slug');
                            const name = $(this).data('name');
                            
                            $('#indicator_search').val(name).addClass('zc-indicator-selected');
                            $('#indicator_slug').val(slug);
                            resultsContainer.removeClass('active').empty();
                            
                            updatePreview();
                        });
                        
                        resultsContainer.append(item);
                    });
                    
                    resultsContainer.addClass('active');
                } else {
                    resultsContainer.html('<div class="zc-indicator-result-item" style="opacity: 0.6;">No indicators found</div>').addClass('active');
                }
            }, 300);
        });

        // Clear search when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.zc-indicator-search-container').length) {
                $('#indicator_results').removeClass('active');
            }
        });

        // Clear selection when search input changes
        $('#indicator_search').on('input', function() {
            if ($(this).hasClass('zc-indicator-selected')) {
                $(this).removeClass('zc-indicator-selected');
                $('#indicator_slug').val('');
                updatePreview();
            }
        });

        // Update preview when inputs change
        $('#indicator_slug, #periods').on('change', updatePreview);

        function updatePreview() {
            if (!selectedTemplate) return;

            const indicatorSlug = $('#indicator_slug').val();
            const periods = $('#periods').val();

            if (indicatorSlug) {
                let formula = selectedTemplate.formula;
                formula = formula.replace('{indicator}', indicatorSlug.toUpperCase());
                formula = formula.replace('{periods}', periods);
                
                $('#formula_preview').text(formula);
            } else {
                $('#formula_preview').text('Select an indicator to see formula preview');
            }
        }

        // Copy shortcode functionality
        $('.zc-copy-shortcode').on('click', function() {
            const slug = $(this).data('slug');
            const shortcode = `[zc_chart_calculation id="${slug}" height="600" chart_type="line"]`;
            
            // Copy to clipboard
            navigator.clipboard.writeText(shortcode).then(function() {
                alert('Shortcode copied to clipboard!');
            }).catch(function() {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = shortcode;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Shortcode copied to clipboard!');
            });
        });
    });
    </script>
    <?php
}

/**
 * Get formula templates
 */
function get_formula_templates() {
    return array(
        'growth' => array(
            'annual_growth' => array(
                'name' => 'Annual Growth Rate',
                'description' => 'Calculate year-over-year percentage change',
                'formula' => 'ROC({indicator}, 12)',
                'example' => 'ROC(GDP_US, 12) - 12 month growth rate',
                'type' => 'Growth',
                'default_periods' => 12
            ),
            'quarterly_growth' => array(
                'name' => 'Quarterly Growth Rate',
                'description' => 'Calculate quarter-over-quarter percentage change',
                'formula' => 'ROC({indicator}, 3)',
                'example' => 'ROC(GDP_US, 3) - 3 month growth rate',
                'type' => 'Growth',
                'default_periods' => 3
            ),
            'monthly_growth' => array(
                'name' => 'Monthly Growth Rate',
                'description' => 'Calculate month-over-month percentage change',
                'formula' => 'ROC({indicator}, 1)',
                'example' => 'ROC(INFLATION_US, 1) - monthly change',
                'type' => 'Growth',
                'default_periods' => 1
            )
        ),
        'trend' => array(
            'moving_average_12' => array(
                'name' => '12-Month Moving Average',
                'description' => 'Smooth out short-term fluctuations with 12-month average',
                'formula' => 'MA({indicator}, 12)',
                'example' => 'MA(UNEMPLOYMENT_US, 12) - annual trend',
                'type' => 'Trend',
                'default_periods' => 12
            ),
            'moving_average_6' => array(
                'name' => '6-Month Moving Average',
                'description' => 'Medium-term trend analysis with 6-month average',
                'formula' => 'MA({indicator}, 6)',
                'example' => 'MA(INFLATION_US, 6) - semi-annual trend',
                'type' => 'Trend',
                'default_periods' => 6
            ),
            'moving_average_3' => array(
                'name' => '3-Month Moving Average',
                'description' => 'Short-term trend analysis with 3-month average',
                'formula' => 'MA({indicator}, 3)',
                'example' => 'MA(EMPLOYMENT_US, 3) - quarterly trend',
                'type' => 'Trend',
                'default_periods' => 3
            )
        ),
        'momentum' => array(
            'rsi_14' => array(
                'name' => 'RSI (14 periods)',
                'description' => 'Relative Strength Index for momentum analysis',
                'formula' => 'RSI({indicator}, 14)',
                'example' => 'RSI(STOCK_INDEX, 14) - momentum indicator',
                'type' => 'Momentum',
                'default_periods' => 14
            ),
            'momentum_10' => array(
                'name' => 'Momentum (10 periods)',
                'description' => 'Price momentum over 10 periods',
                'formula' => 'MOMENTUM({indicator}, 10)',
                'example' => 'MOMENTUM(GDP_US, 10) - economic momentum',
                'type' => 'Momentum',
                'default_periods' => 10
            )
        ),
        'statistics' => array(
            'average' => array(
                'name' => 'Historical Average',
                'description' => 'Calculate the average of all historical values',
                'formula' => 'AVG({indicator})',
                'example' => 'AVG(INFLATION_US) - historical average',
                'type' => 'Statistics',
                'default_periods' => 0
            ),
            'maximum' => array(
                'name' => 'Historical Maximum',
                'description' => 'Find the highest value in the series',
                'formula' => 'MAX({indicator})',
                'example' => 'MAX(UNEMPLOYMENT_US) - peak unemployment',
                'type' => 'Statistics',
                'default_periods' => 0
            ),
            'minimum' => array(
                'name' => 'Historical Minimum',
                'description' => 'Find the lowest value in the series',
                'formula' => 'MIN({indicator})',
                'example' => 'MIN(INTEREST_RATE, 0) - lowest rate',
                'type' => 'Statistics',
                'default_periods' => 0
            )
        )
    );
}

/**
 * Create calculation from template and auto-add as indicator
 */
function create_calculation_from_template($template_id, $indicator_slug, $periods, $custom_name = '') {
    $templates = get_formula_templates();
    
    // Find template
    $template = null;
    foreach ($templates as $category => $category_templates) {
        if (isset($category_templates[$template_id])) {
            $template = $category_templates[$template_id];
            break;
        }
    }
    
    if (!$template) {
        return new WP_Error('template_not_found', 'Template not found');
    }

    // Get indicator info
    $indicator = ZC_DMT_Indicators::get_indicator_by_slug($indicator_slug);
    if (!$indicator) {
        return new WP_Error('indicator_not_found', 'Indicator not found');
    }

    // Build formula
    $formula = $template['formula'];
    $formula = str_replace('{indicator}', strtoupper($indicator_slug), $formula);
    $formula = str_replace('{periods}', $periods, $formula);

    // Generate name
    if (empty($custom_name)) {
        $name = $template['name'] . ' - ' . $indicator->name;
        if ($periods > 0 && strpos($template['formula'], '{periods}') !== false) {
            $name = str_replace('periods', $periods . ' periods', $name);
        }
    } else {
        $name = $custom_name;
    }

    // Create calculation
    $calc_id = ZC_DMT_Calculations::create_calculation(
        $name,
        $formula,
        array($indicator_slug),
        'series'
    );

    if (is_wp_error($calc_id)) {
        return $calc_id;
    }

    // Get the created calculation
    $calculation = ZC_DMT_Calculations::get_calculation_by_slug(sanitize_title($name));
    if (!$calculation) {
        return new WP_Error('calc_not_found', 'Created calculation not found');
    }

    // Auto-create as indicator so it appears in charts
    $indicator_id = ZC_DMT_Indicators::create_indicator(
        $name,
        $calculation->slug,
        'Calculated indicator: ' . $formula,
        'calculation',
        array('calculation_id' => $calc_id, 'formula' => $formula),
        1
    );

    if (is_wp_error($indicator_id)) {
        return $indicator_id;
    }

    return array(
        'calculation_id' => $calc_id,
        'indicator_id' => $indicator_id,
        'name' => $name,
        'slug' => $calculation->slug,
        'formula' => $formula
    );
}
?>
