<?php
if (!defined('ABSPATH')) exit;

/**
 * Calculations Admin Page
 * Formula builder and technical indicators management
 */

function zc_dmt_render_calculations_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'zc-dmt'));
    }

    // Handle form submissions
    if ($_POST && wp_verify_nonce($_POST['zc_calculations_nonce'] ?? '', 'zc_calculations_action')) {
        $action = sanitize_text_field($_POST['action'] ?? '');
        
        if ($action === 'create_calculation') {
            $name = sanitize_text_field($_POST['calculation_name'] ?? '');
            $formula = wp_kses_post($_POST['formula'] ?? '');
            $indicators = array_map('sanitize_text_field', $_POST['indicators'] ?? array());
            $output_type = sanitize_text_field($_POST['output_type'] ?? 'series');
            
            if (!empty($name) && !empty($formula)) {
                $result = ZC_DMT_Calculations::create_calculation($name, $formula, $indicators, $output_type);
                
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Calculation created successfully!', 'zc-dmt') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . __('Please provide both name and formula.', 'zc-dmt') . '</p></div>';
            }
        }
    }

    // Get existing calculations
    $calculations = ZC_DMT_Calculations::list_calculations(50);
    
    // Get available indicators for formula builder
    global $wpdb;
    $indicators_table = $wpdb->prefix . 'zc_dmt_indicators';
    $indicators = $wpdb->get_results("SELECT id, name, slug FROM {$indicators_table} WHERE is_active = 1 ORDER BY name ASC");

    // Get available functions
    $functions = ZC_DMT_Calculations::get_available_functions();

    // Enqueue necessary scripts
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-autocomplete');
    ?>

    <div class="wrap zc-calculations-page">
        <div class="zc-header">
            <h1 class="zc-page-title">
                <span class="dashicons dashicons-calculator"></span>
                <?php _e('Manual Calculations & Technical Indicators', 'zc-dmt'); ?>
            </h1>
            <p class="zc-subtitle"><?php _e('Create custom formulas and technical analysis indicators', 'zc-dmt'); ?></p>
        </div>

        <div class="zc-calculations-container">
            <!-- Formula Builder -->
            <div class="zc-builder-section">
                <div class="zc-builder-form">
                    <h2><?php _e('Formula Builder', 'zc-dmt'); ?></h2>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('zc_calculations_action', 'zc_calculations_nonce'); ?>
                        <input type="hidden" name="action" value="create_calculation">
                        
                        <!-- Calculation Name -->
                        <div class="zc-field-group">
                            <label for="calculation_name" class="zc-label">
                                <span class="dashicons dashicons-tag"></span>
                                <?php _e('Calculation Name', 'zc-dmt'); ?>
                            </label>
                            <input type="text" id="calculation_name" name="calculation_name" class="zc-input" 
                                   placeholder="<?php _e('e.g., GDP Growth Rate, RSI Analysis', 'zc-dmt'); ?>" required>
                            <p class="zc-description"><?php _e('A descriptive name for your calculation.', 'zc-dmt'); ?></p>
                        </div>

                        <!-- Formula Input -->
                        <div class="zc-field-group">
                            <label for="formula" class="zc-label">
                                <span class="dashicons dashicons-editor-code"></span>
                                <?php _e('Formula', 'zc-dmt'); ?>
                            </label>
                            <div class="zc-formula-builder">
                                <textarea id="formula" name="formula" class="zc-formula-input" rows="4" 
                                          placeholder="<?php _e('e.g., ROC(GDP_US, 4) or AVG(UNEMPLOYMENT_US)', 'zc-dmt'); ?>" required></textarea>
                                <div class="zc-formula-toolbar">
                                    <button type="button" class="zc-btn zc-btn-small" onclick="insertText('SUM()')">SUM</button>
                                    <button type="button" class="zc-btn zc-btn-small" onclick="insertText('AVG()')">AVG</button>
                                    <button type="button" class="zc-btn zc-btn-small" onclick="insertText('ROC(, 4)')">ROC</button>
                                    <button type="button" class="zc-btn zc-btn-small" onclick="insertText('MA(, 12)')">MA</button>
                                    <button type="button" class="zc-btn zc-btn-small" onclick="insertText('RSI(, 14)')">RSI</button>
                                </div>
                            </div>
                            <p class="zc-description"><?php _e('Use indicator slugs and functions to build your formula.', 'zc-dmt'); ?></p>
                        </div>

                        <!-- Indicators Used -->
                        <div class="zc-field-group">
                            <label class="zc-label">
                                <span class="dashicons dashicons-chart-line"></span>
                                <?php _e('Indicators Used', 'zc-dmt'); ?>
                            </label>
                            <div class="zc-indicators-selector">
                                <input type="text" id="indicator_search" class="zc-search-input" 
                                       placeholder="<?php _e('Search indicators...', 'zc-dmt'); ?>">
                                <div id="indicator_results" class="zc-search-results"></div>
                                <div id="selected_indicators" class="zc-selected-indicators"></div>
                            </div>
                            <p class="zc-description"><?php _e('Select the indicators your formula will use.', 'zc-dmt'); ?></p>
                        </div>

                        <!-- Output Type -->
                        <div class="zc-field-group">
                            <label for="output_type" class="zc-label">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php _e('Output Type', 'zc-dmt'); ?>
                            </label>
                            <select id="output_type" name="output_type" class="zc-select">
                                <option value="series"><?php _e('Time Series (Chart Data)', 'zc-dmt'); ?></option>
                                <option value="value"><?php _e('Single Value', 'zc-dmt'); ?></option>
                                <option value="indicator"><?php _e('New Indicator', 'zc-dmt'); ?></option>
                            </select>
                            <p class="zc-description"><?php _e('Choose how the calculation result should be formatted.', 'zc-dmt'); ?></p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="zc-actions">
                            <button type="button" id="test_formula" class="zc-btn zc-btn-secondary">
                                <span class="dashicons dashicons-admin-network"></span>
                                <?php _e('Test Formula', 'zc-dmt'); ?>
                            </button>
                            <button type="submit" class="zc-btn zc-btn-primary">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php _e('Create Calculation', 'zc-dmt'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Function Reference -->
                <div class="zc-functions-reference">
                    <h3><?php _e('Available Functions', 'zc-dmt'); ?></h3>
                    
                    <?php foreach ($functions as $category => $category_functions): ?>
                        <div class="zc-function-category">
                            <h4><?php echo esc_html(ucwords(str_replace('_', ' ', $category))); ?> Functions</h4>
                            <?php foreach ($category_functions as $func): ?>
                                <div class="zc-function-item">
                                    <div class="zc-function-header">
                                        <strong><?php echo esc_html($func['name']); ?></strong>
                                        <code><?php echo esc_html($func['syntax']); ?></code>
                                    </div>
                                    <p><?php echo esc_html($func['description']); ?></p>
                                    <div class="zc-function-example">
                                        <strong>Example:</strong> <code><?php echo esc_html($func['example']); ?></code>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Existing Calculations -->
            <div class="zc-calculations-list">
                <h2><?php _e('Existing Calculations', 'zc-dmt'); ?></h2>
                
                <?php if (!empty($calculations)): ?>
                    <div class="zc-calculations-table">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Name', 'zc-dmt'); ?></th>
                                    <th><?php _e('Formula', 'zc-dmt'); ?></th>
                                    <th><?php _e('Output Type', 'zc-dmt'); ?></th>
                                    <th><?php _e('Created', 'zc-dmt'); ?></th>
                                    <th><?php _e('Actions', 'zc-dmt'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($calculations as $calc): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($calc->name); ?></strong>
                                            <div class="row-actions">
                                                <span class="slug">Slug: <code><?php echo esc_html($calc->slug); ?></code></span>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="zc-formula-display"><?php echo esc_html($calc->formula); ?></code>
                                        </td>
                                        <td>
                                            <span class="zc-output-type"><?php echo esc_html(ucwords($calc->output_type)); ?></span>
                                        </td>
                                        <td>
                                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($calc->created_at))); ?>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small zc-test-calc" 
                                                    data-slug="<?php echo esc_attr($calc->slug); ?>">
                                                <?php _e('Test', 'zc-dmt'); ?>
                                            </button>
                                            <button type="button" class="button button-small button-link-delete zc-delete-calc" 
                                                    data-id="<?php echo esc_attr($calc->id); ?>">
                                                <?php _e('Delete', 'zc-dmt'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="zc-empty-state">
                        <div class="dashicons dashicons-calculator"></div>
                        <h3><?php _e('No Calculations Yet', 'zc-dmt'); ?></h3>
                        <p><?php _e('Create your first calculation using the formula builder above.', 'zc-dmt'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Test Results Modal -->
        <div id="zc-test-modal" class="zc-modal" style="display: none;">
            <div class="zc-modal-overlay"></div>
            <div class="zc-modal-content">
                <div class="zc-modal-header">
                    <h3><?php _e('Formula Test Results', 'zc-dmt'); ?></h3>
                    <button type="button" class="zc-modal-close">&times;</button>
                </div>
                <div class="zc-modal-body">
                    <div id="zc-test-results"></div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .zc-calculations-page {
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

    .zc-calculations-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 40px;
    }

    .zc-builder-section {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .zc-builder-form, .zc-functions-reference {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border: 1px solid #e1e5e9;
    }

    .zc-builder-form h2, .zc-functions-reference h3 {
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

    .zc-input, .zc-select, .zc-search-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
    }

    .zc-input:focus, .zc-select:focus, .zc-search-input:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        outline: none;
    }

    .zc-formula-builder {
        position: relative;
    }

    .zc-formula-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        resize: vertical;
        min-height: 100px;
    }

    .zc-formula-input:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        outline: none;
    }

    .zc-formula-toolbar {
        margin-top: 10px;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .zc-btn {
        padding: 8px 16px;
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

    .zc-btn-small {
        padding: 6px 12px;
        font-size: 12px;
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
        transform: translateY(-1px);
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

    .zc-function-category {
        margin-bottom: 25px;
    }

    .zc-function-category h4 {
        color: #2c3e50;
        margin-bottom: 15px;
        font-size: 1.1em;
    }

    .zc-function-item {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
        border-left: 4px solid #3498db;
    }

    .zc-function-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .zc-function-header strong {
        color: #2c3e50;
    }

    .zc-function-header code {
        background: #e9ecef;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
    }

    .zc-function-example {
        margin-top: 8px;
        font-size: 13px;
    }

    .zc-function-example code {
        background: #d4edda;
        color: #155724;
        padding: 2px 6px;
        border-radius: 3px;
    }

    .zc-calculations-list {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border: 1px solid #e1e5e9;
    }

    .zc-calculations-list h2 {
        color: #2c3e50;
        margin-bottom: 25px;
        font-size: 1.5em;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }

    .zc-formula-display {
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        max-width: 200px;
        display: inline-block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .zc-output-type {
        background: #e3f2fd;
        color: #1976d2;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }

    .zc-empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #7f8c8d;
    }

    .zc-empty-state .dashicons {
        font-size: 4em;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .zc-empty-state h3 {
        margin-bottom: 10px;
        color: #2c3e50;
    }

    .zc-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .zc-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
    }

    .zc-modal-content {
        position: relative;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        width: 90%;
        max-width: 600px;
        max-height: 80vh;
        overflow: hidden;
    }

    .zc-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #e1e5e9;
    }

    .zc-modal-header h3 {
        margin: 0;
        color: #2c3e50;
    }

    .zc-modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #7f8c8d;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    .zc-modal-close:hover {
        background: #f8f9fa;
        color: #2c3e50;
    }

    .zc-modal-body {
        padding: 20px;
        max-height: 60vh;
        overflow-y: auto;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .zc-calculations-container {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .zc-calculations-page {
            padding: 10px;
        }
        
        .zc-actions {
            flex-direction: column;
        }
        
        .zc-formula-toolbar {
            justify-content: center;
        }
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        const indicators = <?php echo json_encode($indicators); ?>;
        let selectedIndicators = [];

        // Insert text into formula textarea
        window.insertText = function(text) {
            const textarea = document.getElementById('formula');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const value = textarea.value;
            
            textarea.value = value.substring(0, start) + text + value.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + text.length, start + text.length);
        };

        // Indicator search functionality
        $('#indicator_search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            const $results = $('#indicator_results');
            
            if (searchTerm.length < 2) {
                $results.hide().empty();
                return;
            }

            const filteredIndicators = indicators.filter(indicator => 
                indicator.name.toLowerCase().includes(searchTerm) ||
                indicator.slug.toLowerCase().includes(searchTerm)
            );

            if (filteredIndicators.length > 0) {
                let html = '';
                filteredIndicators.slice(0, 10).forEach(indicator => {
                    if (!selectedIndicators.find(sel => sel.slug === indicator.slug)) {
                        html += `
                            <div class="zc-search-result" data-slug="${indicator.slug}" data-name="${indicator.name}">
                                <strong>${indicator.name}</strong>
                                <div><code>${indicator.slug}</code></div>
                            </div>
                        `;
                    }
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
                
                selectedIndicators.push({ slug, name });
                updateSelectedIndicators();
                
                $('#indicator_search').val('');
                $('#indicator_results').hide();
            }
        });

        function updateSelectedIndicators() {
            const $container = $('#selected_indicators');
            
            if (selectedIndicators.length === 0) {
                $container.empty();
                return;
            }

            let html = '<div class="zc-selected-tags">';
            selectedIndicators.forEach((indicator, index) => {
                html += `
                    <div class="zc-indicator-tag">
                        <span>${indicator.name}</span>
                        <input type="hidden" name="indicators[]" value="${indicator.slug}">
                        <button type="button" class="zc-remove-indicator" data-index="${index}">×</button>
                    </div>
                `;
            });
            html += '</div>';
            
            $container.html(html);
        }

        // Remove indicator
        $(document).on('click', '.zc-remove-indicator', function() {
            const index = parseInt($(this).data('index'));
            selectedIndicators.splice(index, 1);
            updateSelectedIndicators();
        });

        // Test formula
        $('#test_formula').on('click', function() {
            const formula = $('#formula').val();
            if (!formula) {
                alert('Please enter a formula first.');
                return;
            }

            const $button = $(this);
            const originalText = $button.html();
            $button.html('<span class="dashicons dashicons-update spin"></span> Testing...').prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'zc_dmt_test_formula',
                    nonce: '<?php echo wp_create_nonce('zc_dmt_calculations'); ?>',
                    formula: formula,
                    indicators: selectedIndicators.map(i => i.slug)
                },
                success: function(response) {
                    if (response.success) {
                        showTestResults(response.data);
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to test formula. Please try again.');
                },
                complete: function() {
                    $button.html(originalText).prop('disabled', false);
                }
            });
        });

        function showTestResults(data) {
            let html = '<div class="zc-test-success">';
            html += '<h4>✅ Formula Test Successful</h4>';
            
            if (Array.isArray(data)) {
                html += `<p><strong>Result Type:</strong> Time Series (${data.length} data points)</p>`;
                if (data.length > 0) {
                    html += '<div class="zc-sample-data">';
                    html += '<strong>Sample Data:</strong><br>';
                    data.slice(0, 5).forEach(point => {
                        html += `<code>${point[0]}: ${point[1]}</code><br>`;
                    });
                    if (data.length > 5) {
                        html += `<em>... and ${data.length - 5} more data points</em>`;
                    }
                    html += '</div>';
                }
            } else {
                html += `<p><strong>Result:</strong> ${data}</p>`;
            }
            
            html += '</div>';
            
            $('#zc-test-results').html(html);
            $('#zc-test-modal').show();
        }

        // Close modal
        $('.zc-modal-close, .zc-modal-overlay').on('click', function() {
            $('#zc-test-modal').hide();
        });

        // Hide search results when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#indicator_search, #indicator_results').length) {
                $('#indicator_results').hide();
            }
        });
    });
    </script>
    <?php
}
?>
