<?php
if (!defined('ABSPATH')) exit;

/**
 * Improved Indicators Admin Page - FIXED for correct class names
 * 
 * Features:
 * - List all indicators with proper columns
 * - Search functionality
 * - Filter by data source
 * - Delete, Edit, Enable/Disable actions
 * - Data fetch test button
 * - Merged shortcode builder
 */

function zc_dmt_render_indicators_page() {
    if (!current_user_can('manage_options')) return;
    
    // Handle form submissions
    if (!empty($_POST['zc_dmt_indicators_action'])) {
        if (!isset($_POST['zc_dmt_indicators_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['zc_dmt_indicators_nonce']), 'zc_dmt_indicators_action')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed. Please try again.', 'zc-dmt') . '</p></div>';
        } else {
            $result = zc_dmt_process_indicators_action();
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } elseif ($result === true) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Action completed successfully!', 'zc-dmt') . '</p></div>';
            }
        }
    }
    
    // Get current filters
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $source_filter = isset($_GET['source_filter']) ? sanitize_text_field($_GET['source_filter']) : '';
    
    // Get indicators with filters
    $indicators = zc_dmt_get_filtered_indicators($search, $source_filter);
    $total_indicators = count($indicators);
    
    // Get available data sources for filter
    $available_sources = zc_dmt_get_indicator_sources();
    
    ?>
    <div class="wrap zc-dmt-indicators">
        <h1 class="wp-heading-inline"><?php echo esc_html__('Indicators', 'zc-dmt'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action">
            <?php echo esc_html__('Add New', 'zc-dmt'); ?>
        </a>
        <hr class="wp-header-end">
        
        <!-- Search and Filters -->
        <div class="zc-indicators-toolbar">
            <div class="zc-toolbar-left">
                <form method="get" class="zc-search-form">
                    <input type="hidden" name="page" value="zc-dmt-indicators">
                    <?php if ($source_filter): ?>
                        <input type="hidden" name="source_filter" value="<?php echo esc_attr($source_filter); ?>">
                    <?php endif; ?>
                    
                    <input type="text" name="search" placeholder="<?php echo esc_attr__('Search indicators...', 'zc-dmt'); ?>" 
                           value="<?php echo esc_attr($search); ?>" class="zc-search-input">
                    <button type="submit" class="button"><?php echo esc_html__('Search', 'zc-dmt'); ?></button>
                    
                    <?php if ($search || $source_filter): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-indicators')); ?>" class="button">
                            <?php echo esc_html__('Clear', 'zc-dmt'); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="zc-toolbar-right">
                <form method="get" class="zc-filter-form">
                    <input type="hidden" name="page" value="zc-dmt-indicators">
                    <?php if ($search): ?>
                        <input type="hidden" name="search" value="<?php echo esc_attr($search); ?>">
                    <?php endif; ?>
                    
                    <select name="source_filter" onchange="this.form.submit()">
                        <option value=""><?php echo esc_html__('All Sources', 'zc-dmt'); ?></option>
                        <?php foreach ($available_sources as $source): ?>
                            <option value="<?php echo esc_attr($source); ?>" <?php selected($source_filter, $source); ?>>
                                <?php echo esc_html(ucwords(str_replace(['_', '-'], ' ', $source))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
        
        <!-- Results Summary -->
        <div class="zc-results-summary">
            <span><?php echo sprintf(_n('%d indicator found', '%d indicators found', $total_indicators, 'zc-dmt'), $total_indicators); ?></span>
            <?php if ($search): ?>
                <span class="zc-search-term">for "<?php echo esc_html($search); ?>"</span>
            <?php endif; ?>
            <?php if ($source_filter): ?>
                <span class="zc-filter-term">in <?php echo esc_html(ucwords(str_replace(['_', '-'], ' ', $source_filter))); ?></span>
            <?php endif; ?>
        </div>
        
        <!-- Indicators Table -->
        <?php if (!empty($indicators)): ?>
            <form method="post" id="indicators-form">
                <?php wp_nonce_field('zc_dmt_indicators_action', 'zc_dmt_indicators_nonce'); ?>
                
                <table class="wp-list-table widefat fixed striped indicators">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th scope="col" class="manage-column column-name"><?php echo esc_html__('Name', 'zc-dmt'); ?></th>
                            <th scope="col" class="manage-column column-source"><?php echo esc_html__('Data Source', 'zc-dmt'); ?></th>
                            <th scope="col" class="manage-column column-status"><?php echo esc_html__('Status', 'zc-dmt'); ?></th>
                            <th scope="col" class="manage-column column-shortcode"><?php echo esc_html__('Shortcode', 'zc-dmt'); ?></th>
                            <th scope="col" class="manage-column column-actions"><?php echo esc_html__('Actions', 'zc-dmt'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($indicators as $indicator): ?>
                            <tr data-indicator-id="<?php echo esc_attr($indicator->id); ?>">
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="indicator_ids[]" value="<?php echo esc_attr($indicator->id); ?>">
                                </th>
                                
                                <td class="column-name">
                                    <strong><?php echo esc_html($indicator->name); ?></strong>
                                    <div class="row-actions visible">
                                        <span class="edit">
                                            <a href="#" onclick="zcEditIndicator(<?php echo esc_attr($indicator->id); ?>); return false;">
                                                <?php echo esc_html__('Edit', 'zc-dmt'); ?>
                                            </a> |
                                        </span>
                                        <span class="delete">
                                            <a href="#" onclick="zcDeleteIndicator(<?php echo esc_attr($indicator->id); ?>, '<?php echo esc_js($indicator->name); ?>'); return false;" class="submitdelete">
                                                <?php echo esc_html__('Delete', 'zc-dmt'); ?>
                                            </a>
                                        </span>
                                    </div>
                                    <?php if ($indicator->description): ?>
                                        <div class="description"><?php echo esc_html($indicator->description); ?></div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="column-source">
                                    <span class="zc-source-badge zc-source-<?php echo esc_attr($indicator->source_type); ?>">
                                        <?php echo esc_html(ucwords(str_replace(['_', '-'], ' ', $indicator->source_type))); ?>
                                    </span>
                                </td>
                                
                                <td class="column-status">
                                    <?php if ($indicator->is_active): ?>
                                        <span class="zc-status-badge zc-status-active"><?php echo esc_html__('Active', 'zc-dmt'); ?></span>
                                    <?php else: ?>
                                        <span class="zc-status-badge zc-status-inactive"><?php echo esc_html__('Inactive', 'zc-dmt'); ?></span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="column-shortcode">
                                    <code onclick="zcCopyToClipboard(this)">[zc_chart_dynamic id="<?php echo esc_attr($indicator->slug); ?>"]</code>
                                    <span class="zc-copy-hint"><?php echo esc_html__('Click to copy', 'zc-dmt'); ?></span>
                                </td>
                                
                                <td class="column-actions">
                                    <div class="zc-action-buttons">
                                        <button type="button" class="button button-small zc-test-btn" 
                                                onclick="zcTestIndicator('<?php echo esc_js($indicator->slug); ?>', this)"
                                                title="<?php echo esc_attr__('Test data fetch', 'zc-dmt'); ?>">
                                            <span class="dashicons dashicons-update"></span>
                                        </button>
                                        
                                        <button type="button" class="button button-small zc-toggle-btn" 
                                                onclick="zcToggleIndicator(<?php echo esc_attr($indicator->id); ?>, <?php echo $indicator->is_active ? 'false' : 'true'; ?>, this)"
                                                title="<?php echo $indicator->is_active ? esc_attr__('Disable indicator', 'zc-dmt') : esc_attr__('Enable indicator', 'zc-dmt'); ?>">
                                            <span class="dashicons dashicons-<?php echo $indicator->is_active ? 'pause' : 'controls-play'; ?>"></span>
                                        </button>
                                        
                                        <button type="button" class="button button-small zc-edit-btn" 
                                                onclick="zcEditIndicator(<?php echo esc_attr($indicator->id); ?>)"
                                                title="<?php echo esc_attr__('Edit indicator', 'zc-dmt'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        
                                        <button type="button" class="button button-small zc-delete-btn" 
                                                onclick="zcDeleteIndicator(<?php echo esc_attr($indicator->id); ?>, '<?php echo esc_js($indicator->name); ?>')"
                                                title="<?php echo esc_attr__('Delete indicator', 'zc-dmt'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Bulk Actions -->
                <div class="zc-bulk-actions">
                    <select name="bulk_action">
                        <option value=""><?php echo esc_html__('Bulk Actions', 'zc-dmt'); ?></option>
                        <option value="activate"><?php echo esc_html__('Activate', 'zc-dmt'); ?></option>
                        <option value="deactivate"><?php echo esc_html__('Deactivate', 'zc-dmt'); ?></option>
                        <option value="delete"><?php echo esc_html__('Delete', 'zc-dmt'); ?></option>
                    </select>
                    <button type="button" class="button" onclick="zcExecuteBulkAction()"><?php echo esc_html__('Apply', 'zc-dmt'); ?></button>
                </div>
            </form>
        <?php else: ?>
            <!-- Empty State -->
            <div class="zc-empty-state">
                <div class="zc-empty-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <h3><?php echo esc_html__('No indicators found', 'zc-dmt'); ?></h3>
                <p><?php echo esc_html__('Start by adding your first economic indicator from a data source.', 'zc-dmt'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="button button-primary button-hero">
                    <?php echo esc_html__('Add Your First Indicator', 'zc-dmt'); ?>
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Shortcode Builder Section -->
        <div class="zc-card zc-shortcode-builder" style="margin-top: 30px;">
            <h2><?php echo esc_html__('Shortcode Builder', 'zc-dmt'); ?></h2>
            <p><?php echo esc_html__('Generate shortcodes for your charts with custom options.', 'zc-dmt'); ?></p>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="zc_sb_indicator"><?php echo esc_html__('Select Indicator', 'zc-dmt'); ?></label>
                        </th>
                        <td>
                            <select id="zc_sb_indicator" class="regular-text">
                                <option value=""><?php echo esc_html__('-- Select Indicator --', 'zc-dmt'); ?></option>
                                <?php foreach ($indicators as $indicator): ?>
                                    <option value="<?php echo esc_attr($indicator->slug); ?>">
                                        <?php echo esc_html($indicator->name . ' (' . $indicator->slug . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="zc_sb_library"><?php echo esc_html__('Chart Library', 'zc-dmt'); ?></label>
                        </th>
                        <td>
                            <select id="zc_sb_library">
                                <option value="chartjs"><?php echo esc_html__('Chart.js', 'zc-dmt'); ?></option>
                                <option value="plotly"><?php echo esc_html__('Plotly', 'zc-dmt'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="zc_sb_timeframe"><?php echo esc_html__('Default Timeframe', 'zc-dmt'); ?></label>
                        </th>
                        <td>
                            <select id="zc_sb_timeframe">
                                <option value="1y"><?php echo esc_html__('1 Year', 'zc-dmt'); ?></option>
                                <option value="2y"><?php echo esc_html__('2 Years', 'zc-dmt'); ?></option>
                                <option value="5y"><?php echo esc_html__('5 Years', 'zc-dmt'); ?></option>
                                <option value="10y"><?php echo esc_html__('10 Years', 'zc-dmt'); ?></option>
                                <option value="all"><?php echo esc_html__('All Data', 'zc-dmt'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="zc_sb_height"><?php echo esc_html__('Chart Height', 'zc-dmt'); ?></label>
                        </th>
                        <td>
                            <input id="zc_sb_height" type="text" value="300px" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Show Controls', 'zc-dmt'); ?>
                        </th>
                        <td>
                            <label>
                                <input id="zc_sb_controls" type="checkbox" checked> 
                                <?php echo esc_html__('Show timeframe controls', 'zc-dmt'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="zc_sb_output"><?php echo esc_html__('Generated Shortcode', 'zc-dmt'); ?></label>
                        </th>
                        <td>
                            <textarea id="zc_sb_output" class="large-text code" rows="3" readonly 
                                      onclick="this.select(); zcCopyToClipboard(this);"
                                      placeholder="<?php echo esc_attr__('Select an indicator to generate shortcode...', 'zc-dmt'); ?>"></textarea>
                            <p class="description">
                                <button type="button" id="zc_sb_build" class="button button-primary" onclick="zcBuildShortcode()">
                                    <?php echo esc_html__('Build Shortcode', 'zc-dmt'); ?>
                                </button>
                                <button type="button" id="zc_sb_copy" class="button" onclick="zcCopyShortcode()">
                                    <?php echo esc_html__('Copy', 'zc-dmt'); ?>
                                </button>
                                <button type="button" id="zc_sb_test" class="button" onclick="zcTestShortcode()">
                                    <?php echo esc_html__('Test Data', 'zc-dmt'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Edit Indicator Modal -->
    <div id="zc-edit-modal" class="zc-modal" style="display: none;">
        <div class="zc-modal-content">
            <div class="zc-modal-header">
                <h2><?php echo esc_html__('Edit Indicator', 'zc-dmt'); ?></h2>
                <button type="button" class="zc-modal-close" onclick="zcCloseEditModal()">&times;</button>
            </div>
            <div class="zc-modal-body">
                <form id="zc-edit-form">
                    <!-- Content will be loaded dynamically -->
                </form>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Select all checkbox functionality
        $('#cb-select-all').on('change', function() {
            $('input[name="indicator_ids[]"]').prop('checked', this.checked);
        });
        
        // Auto-build shortcode when indicator changes
        $('#zc_sb_indicator').on('change', function() {
            if (this.value) {
                zcBuildShortcode();
            } else {
                $('#zc_sb_output').val('');
            }
        });
    });
    
    // Copy to clipboard function
    function zcCopyToClipboard(element) {
        const text = element.textContent || element.value;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                zcShowToast('Copied to clipboard!', 'success');
            });
        } else {
            // Fallback for older browsers
            element.select();
            document.execCommand('copy');
            zcShowToast('Copied to clipboard!', 'success');
        }
    }
    
    // Test indicator data fetch - FIXED for correct class names
    function zcTestIndicator(slug, button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="dashicons dashicons-update spin"></span>';
        button.disabled = true;
        
        jQuery.post(ajaxurl, {
            action: 'zc_dmt_get_data',
            nonce: '<?php echo wp_create_nonce("zc_dmt_chart"); ?>',
            slug: slug
        }).done(function(response) {
            if (response.status === 'success' && response.data.series.length > 0) {
                zcShowToast(`✓ OK: ${response.data.series.length} data points loaded`, 'success');
            } else {
                zcShowToast('⚠ No data or error occurred', 'warning');
            }
        }).fail(function() {
            zcShowToast('✗ Test failed - check configuration', 'error');
        }).always(function() {
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }
    
    // Toggle indicator status
    function zcToggleIndicator(id, enable, button) {
        jQuery.post(ajaxurl, {
            action: 'zc_dmt_toggle_indicator',
            nonce: '<?php echo wp_create_nonce("zc_dmt_indicators_action"); ?>',
            indicator_id: id,
            enable: enable ? 1 : 0
        }).done(function(response) {
            if (response.success) {
                location.reload(); // Simple reload to update status
            } else {
                zcShowToast('Failed to toggle indicator status', 'error');
            }
        }).fail(function() {
            zcShowToast('Failed to toggle indicator status', 'error');
        });
    }
    
    // Delete indicator
    function zcDeleteIndicator(id, name) {
        if (!confirm(`Are you sure you want to delete "${name}"? This cannot be undone.`)) {
            return;
        }
        
        jQuery.post(ajaxurl, {
            action: 'zc_dmt_delete_indicator',
            nonce: '<?php echo wp_create_nonce("zc_dmt_indicators_action"); ?>',
            indicator_id: id
        }).done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                zcShowToast('Failed to delete indicator', 'error');
            }
        }).fail(function() {
            zcShowToast('Failed to delete indicator', 'error');
        });
    }
    
    // Edit indicator modal
    function zcEditIndicator(id) {
        // Show modal and load edit form content
        jQuery('#zc-edit-modal').show();
        jQuery('#zc-edit-form').html('<div style="text-align:center;padding:20px;">Loading...</div>');
        
        // Load edit form via AJAX (to be implemented)
        jQuery.post(ajaxurl, {
            action: 'zc_dmt_get_edit_form',
            nonce: '<?php echo wp_create_nonce("zc_dmt_indicators_action"); ?>',
            indicator_id: id
        }).done(function(response) {
            if (response.success) {
                jQuery('#zc-edit-form').html(response.data);
            } else {
                jQuery('#zc-edit-form').html('<p>Failed to load edit form.</p>');
            }
        }).fail(function() {
            jQuery('#zc-edit-form').html('<p>Failed to load edit form.</p>');
        });
    }
    
    function zcCloseEditModal() {
        jQuery('#zc-edit-modal').hide();
    }
    
    // Build shortcode
    function zcBuildShortcode() {
        const indicator = jQuery('#zc_sb_indicator').val();
        if (!indicator) {
            zcShowToast('Please select an indicator', 'warning');
            return;
        }
        
        const library = jQuery('#zc_sb_library').val();
        const timeframe = jQuery('#zc_sb_timeframe').val();
        const height = jQuery('#zc_sb_height').val();
        const controls = jQuery('#zc_sb_controls').is(':checked');
        
        let shortcode = `[zc_chart_dynamic id="${indicator}"`;
        if (library !== 'chartjs') shortcode += ` library="${library}"`;
        if (timeframe !== '1y') shortcode += ` timeframe="${timeframe}"`;
        if (height !== '300px') shortcode += ` height="${height}"`;
        if (!controls) shortcode += ` controls="false"`;
        shortcode += ']';
        
        jQuery('#zc_sb_output').val(shortcode);
        zcShowToast('Shortcode generated!', 'success');
    }
    
    function zcCopyShortcode() {
        const shortcode = jQuery('#zc_sb_output').val();
        if (!shortcode) {
            zcShowToast('No shortcode to copy', 'warning');
            return;
        }
        zcCopyToClipboard(jQuery('#zc_sb_output')[0]);
    }
    
    function zcTestShortcode() {
        const indicator = jQuery('#zc_sb_indicator').val();
        if (!indicator) {
            zcShowToast('Please select an indicator first', 'warning');
            return;
        }
        zcTestIndicator(indicator, jQuery('#zc_sb_test')[0]);
    }
    
    // Execute bulk actions
    function zcExecuteBulkAction() {
        const action = jQuery('select[name="bulk_action"]').val();
        const selected = jQuery('input[name="indicator_ids[]"]:checked');
        
        if (!action) {
            zcShowToast('Please select a bulk action', 'warning');
            return;
        }
        
        if (selected.length === 0) {
            zcShowToast('Please select indicators first', 'warning');
            return;
        }
        
        if (action === 'delete' && !confirm(`Delete ${selected.length} selected indicators? This cannot be undone.`)) {
            return;
        }
        
        // Submit form with bulk action
        jQuery('<input>').attr({
            type: 'hidden',
            name: 'zc_dmt_indicators_action',
            value: 'bulk_' + action
        }).appendTo('#indicators-form');
        
        jQuery('#indicators-form').submit();
    }
    
    // Toast notification system
    function zcShowToast(message, type = 'info') {
        // Remove existing toasts
        jQuery('.zc-toast').remove();
        
        const toast = jQuery(`<div class="zc-toast zc-toast-${type}">${message}</div>`);
        jQuery('body').append(toast);
        
        // Add CSS if not already added
        if (!jQuery('#zc-toast-styles').length) {
            jQuery('head').append(`
                <style id="zc-toast-styles">
                .zc-toast {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 999999;
                    padding: 12px 16px;
                    border-radius: 4px;
                    color: white;
                    font-weight: 500;
                    max-width: 300px;
                    animation: zcToastSlideIn 0.3s ease;
                }
                .zc-toast-success { background: #00a32a; }
                .zc-toast-error { background: #d63638; }
                .zc-toast-warning { background: #f59e0b; }
                .zc-toast-info { background: #0073aa; }
                .spin { animation: spin 1s linear infinite; }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                @keyframes zcToastSlideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
                </style>
            `);
        }
        
        // Auto remove after 3 seconds
        setTimeout(() => toast.fadeOut(() => toast.remove()), 3000);
    }
    </script>
    <?php
}

/**
 * Get filtered indicators based on search and source filter - FIXED for correct class names
 */
function zc_dmt_get_filtered_indicators($search = '', $source_filter = '') {
    global $wpdb;
    
    $table = $wpdb->prefix . 'zc_dmt_indicators';
    $where_clauses = ['1=1'];
    $values = [];
    
    if (!empty($search)) {
        $where_clauses[] = "(name LIKE %s OR slug LIKE %s OR description LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $values[] = $search_term;
        $values[] = $search_term;
        $values[] = $search_term;
    }
    
    if (!empty($source_filter)) {
        $where_clauses[] = "source_type = %s";
        $values[] = $source_filter;
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    if (!empty($values)) {
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC", $values);
    } else {
        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC";
    }
    
    return $wpdb->get_results($sql);
}

/**
 * Get available indicator sources
 */
function zc_dmt_get_indicator_sources() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'zc_dmt_indicators';
    return $wpdb->get_col("SELECT DISTINCT source_type FROM {$table} WHERE source_type IS NOT NULL ORDER BY source_type");
}

/**
 * Process indicators actions - FIXED for correct class names
 */
function zc_dmt_process_indicators_action() {
    $action = isset($_POST['zc_dmt_indicators_action']) ? sanitize_text_field($_POST['zc_dmt_indicators_action']) : '';
    
    switch ($action) {
        case 'bulk_activate':
        case 'bulk_deactivate':
        case 'bulk_delete':
            return zc_dmt_handle_bulk_action($action);
        default:
            return new WP_Error('invalid_action', __('Invalid action specified.', 'zc-dmt'));
    }
}

/**
 * Handle bulk actions on indicators - FIXED for correct class names
 */
function zc_dmt_handle_bulk_action($action) {
    if (!isset($_POST['indicator_ids']) || !is_array($_POST['indicator_ids'])) {
        return new WP_Error('no_selection', __('No indicators selected.', 'zc-dmt'));
    }
    
    $ids = array_map('intval', $_POST['indicator_ids']);
    $count = 0;
    
    foreach ($ids as $id) {
        switch ($action) {
            case 'bulk_activate':
                if (class_exists('ZC_DMT_Indicators')) {
                    $result = ZC_DMT_Indicators::update_indicator($id, ['is_active' => 1]);
                    if (!is_wp_error($result)) $count++;
                }
                break;
                
            case 'bulk_deactivate':
                if (class_exists('ZC_DMT_Indicators')) {
                    $result = ZC_DMT_Indicators::update_indicator($id, ['is_active' => 0]);
                    if (!is_wp_error($result)) $count++;
                }
                break;
                
            case 'bulk_delete':
                if (class_exists('ZC_DMT_Indicators')) {
                    // Delete data points first
                    global $wpdb;
                    $wpdb->delete($wpdb->prefix . 'zc_dmt_data_points', ['indicator_id' => $id]);
                    // Delete indicator
                    $wpdb->delete($wpdb->prefix . 'zc_dmt_indicators', ['id' => $id]);
                    $count++;
                }
                break;
        }
    }
    
    return $count > 0 ? true : new WP_Error('bulk_failed', __('Bulk action failed.', 'zc-dmt'));
}