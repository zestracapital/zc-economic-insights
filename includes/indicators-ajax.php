<?php
if (!defined('ABSPATH')) exit;

/**
 * Extended AJAX handlers for improved indicators functionality
 * FIXED for correct class names (ZC_DMT_Indicators with underscores)
 */

// Add these actions to the existing AJAX class register method or create new handlers
add_action('wp_ajax_zc_dmt_toggle_indicator', array('ZC_DMT_Indicators_Ajax', 'toggle_indicator'));
add_action('wp_ajax_zc_dmt_delete_indicator', array('ZC_DMT_Indicators_Ajax', 'delete_indicator'));
add_action('wp_ajax_zc_dmt_get_edit_form', array('ZC_DMT_Indicators_Ajax', 'get_edit_form'));
add_action('wp_ajax_zc_dmt_save_indicator', array('ZC_DMT_Indicators_Ajax', 'save_indicator'));
add_action('wp_ajax_zc_dmt_test_data_fetch', array('ZC_DMT_Indicators_Ajax', 'test_data_fetch'));

if (!class_exists('ZC_DMT_Indicators_Ajax')) {
    
    class ZC_DMT_Indicators_Ajax {
        
        /**
         * Toggle indicator active status - FIXED for correct class names
         */
        public static function toggle_indicator() {
            // Security check
            if (!wp_verify_nonce(sanitize_text_field($_REQUEST['nonce'] ?? ''), 'zc_dmt_indicators_action')) {
                wp_send_json_error(['message' => __('Security check failed.', 'zc-dmt')]);
            }
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Insufficient permissions.', 'zc-dmt')]);
            }
            
            $indicator_id = isset($_POST['indicator_id']) ? intval($_POST['indicator_id']) : 0;
            $enable = isset($_POST['enable']) ? intval($_POST['enable']) : 0;
            
            if ($indicator_id <= 0) {
                wp_send_json_error(['message' => __('Invalid indicator ID.', 'zc-dmt')]);
            }
            
            if (class_exists('ZC_DMT_Indicators')) {
                $result = ZC_DMT_Indicators::update_indicator($indicator_id, ['is_active' => $enable ? 1 : 0]);
                
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()]);
                }
                
                wp_send_json_success([
                    'message' => $enable ? __('Indicator activated.', 'zc-dmt') : __('Indicator deactivated.', 'zc-dmt'),
                    'status' => $enable ? 'active' : 'inactive'
                ]);
            }
            
            wp_send_json_error(['message' => __('ZC_DMT_Indicators class not found.', 'zc-dmt')]);
        }
        
        /**
         * Delete indicator and its data points - FIXED for correct class names
         */
        public static function delete_indicator() {
            // Security check
            if (!wp_verify_nonce(sanitize_text_field($_REQUEST['nonce'] ?? ''), 'zc_dmt_indicators_action')) {
                wp_send_json_error(['message' => __('Security check failed.', 'zc-dmt')]);
            }
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Insufficient permissions.', 'zc-dmt')]);
            }
            
            $indicator_id = isset($_POST['indicator_id']) ? intval($_POST['indicator_id']) : 0;
            
            if ($indicator_id <= 0) {
                wp_send_json_error(['message' => __('Invalid indicator ID.', 'zc-dmt')]);
            }
            
            global $wpdb;
            $indicators_table = $wpdb->prefix . 'zc_dmt_indicators';
            $datapoints_table = $wpdb->prefix . 'zc_dmt_data_points';
            
            // First, delete all data points
            $wpdb->delete($datapoints_table, ['indicator_id' => $indicator_id], ['%d']);
            
            // Then delete the indicator
            $deleted = $wpdb->delete($indicators_table, ['id' => $indicator_id], ['%d']);
            
            if ($deleted !== false) {
                wp_send_json_success(['message' => __('Indicator deleted successfully.', 'zc-dmt')]);
            }
            
            wp_send_json_error(['message' => __('Failed to delete indicator.', 'zc-dmt')]);
        }
        
        /**
         * Get edit form HTML for an indicator - FIXED for correct class names
         */
        public static function get_edit_form() {
            // Security check
            if (!wp_verify_nonce(sanitize_text_field($_REQUEST['nonce'] ?? ''), 'zc_dmt_indicators_action')) {
                wp_send_json_error(['message' => __('Security check failed.', 'zc-dmt')]);
            }
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Insufficient permissions.', 'zc-dmt')]);
            }
            
            $indicator_id = isset($_POST['indicator_id']) ? intval($_POST['indicator_id']) : 0;
            
            if ($indicator_id <= 0) {
                wp_send_json_error(['message' => __('Invalid indicator ID.', 'zc-dmt')]);
            }
            
            // Get indicator data using ZC_DMT_Indicators method
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_indicators';
            $indicator = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $indicator_id));
            
            if (!$indicator) {
                wp_send_json_error(['message' => __('Indicator not found.', 'zc-dmt')]);
            }
            
            $source_config = '';
            if ($indicator->source_config) {
                $config = json_decode($indicator->source_config, true);
                if (is_array($config)) {
                    $source_config = wp_json_encode($config, JSON_PRETTY_PRINT);
                }
            }
            
            ob_start();
            ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="edit_name"><?php echo esc_html__('Name', 'zc-dmt'); ?></label>
                        </th>
                        <td>
                            <input id="edit_name" name="name" type="text" class="regular-text" 
                                   value="<?php echo esc_attr($indicator->name); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="edit_slug"><?php echo esc_html__('Slug', 'zc-dmt'); ?></label>
                        </th>
                        <td>
                            <input id="edit_slug" name="slug" type="text" class="regular-text" 
                                   value="<?php echo esc_attr($indicator->slug); ?>" required>
                            <p class="description"><?php echo esc_html__('Changing the slug will break existing shortcodes.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="edit_description"><?php echo esc_html__('Description', 'zc-dmt'); ?></label>
                        </th>
                        <td>
                            <textarea id="edit_description" name="description" class="large-text" rows="3"><?php echo esc_textarea($indicator->description); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="edit_source_type"><?php echo esc_html__('Source Type', 'zc-dmt'); ?></label>
                        </th>
                        <td>
                            <select id="edit_source_type" name="source_type" class="regular-text">
                                <option value="manual" <?php selected($indicator->source_type, 'manual'); ?>><?php echo esc_html__('Manual', 'zc-dmt'); ?></option>
                                <option value="fred" <?php selected($indicator->source_type, 'fred'); ?>><?php echo esc_html__('FRED API', 'zc-dmt'); ?></option>
                                <option value="world_bank" <?php selected($indicator->source_type, 'world_bank'); ?>><?php echo esc_html__('World Bank', 'zc-dmt'); ?></option>
                                <option value="dbnomics" <?php selected($indicator->source_type, 'dbnomics'); ?>><?php echo esc_html__('DBnomics', 'zc-dmt'); ?></option>
                                <option value="google_sheets" <?php selected($indicator->source_type, 'google_sheets'); ?>><?php echo esc_html__('Google Sheets', 'zc-dmt'); ?></option>
                                <option value="oecd" <?php selected($indicator->source_type, 'oecd'); ?>><?php echo esc_html__('OECD', 'zc-dmt'); ?></option>
                                <option value="yahoo_finance" <?php selected($indicator->source_type, 'yahoo_finance'); ?>><?php echo esc_html__('Yahoo Finance', 'zc-dmt'); ?></option>
                                <option value="google_finance" <?php selected($indicator->source_type, 'google_finance'); ?>><?php echo esc_html__('Google Finance', 'zc-dmt'); ?></option>
                                <option value="uk_ons" <?php selected($indicator->source_type, 'uk_ons'); ?>><?php echo esc_html__('UK ONS', 'zc-dmt'); ?></option>
                                <option value="ecb" <?php selected($indicator->source_type, 'ecb'); ?>><?php echo esc_html__('ECB', 'zc-dmt'); ?></option>
                                <option value="quandl" <?php selected($indicator->source_type, 'quandl'); ?>><?php echo esc_html__('Nasdaq Data Link (Quandl)', 'zc-dmt'); ?></option>
                                <option value="bank_of_canada" <?php selected($indicator->source_type, 'bank_of_canada'); ?>><?php echo esc_html__('Bank of Canada', 'zc-dmt'); ?></option>
                                <option value="statcan" <?php selected($indicator->source_type, 'statcan'); ?>><?php echo esc_html__('Statistics Canada', 'zc-dmt'); ?></option>
                                <option value="australia_rba" <?php selected($indicator->source_type, 'australia_rba'); ?>><?php echo esc_html__('Reserve Bank of Australia', 'zc-dmt'); ?></option>
                                <option value="universal_csv" <?php selected($indicator->source_type, 'universal_csv'); ?>><?php echo esc_html__('Universal CSV', 'zc-dmt'); ?></option>
                                <option value="universal_json" <?php selected($indicator->source_type, 'universal_json'); ?>><?php echo esc_html__('Universal JSON', 'zc-dmt'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="edit_source_config"><?php echo esc_html__('Source Configuration', 'zc-dmt'); ?></label>
                        </th>
                        <td>
                            <textarea id="edit_source_config" name="source_config" class="large-text code" rows="8"><?php echo esc_textarea($source_config); ?></textarea>
                            <p class="description"><?php echo esc_html__('JSON configuration for the data source. Leave empty for manual indicators.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Status', 'zc-dmt'); ?>
                        </th>
                        <td>
                            <label>
                                <input name="is_active" type="checkbox" value="1" <?php checked($indicator->is_active, 1); ?>>
                                <?php echo esc_html__('Active', 'zc-dmt'); ?>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php if ($indicator->source_type === 'manual'): ?>
            <!-- Manual Data Entry Section -->
            <h3><?php echo esc_html__('Manual Data Entry', 'zc-dmt'); ?></h3>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="manual_date"><?php echo esc_html__('Date', 'zc-dmt'); ?></label>
                        </th>
                        <td>
                            <input id="manual_date" name="manual_date" type="date" class="regular-text" value="<?php echo esc_attr(date('Y-m-d')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="manual_value"><?php echo esc_html__('Value', 'zc-dmt'); ?></label>
                        </th>
                        <td>
                            <input id="manual_value" name="manual_value" type="number" step="any" class="regular-text" placeholder="0.00">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Bulk Data Import', 'zc-dmt'); ?></th>
                        <td>
                            <textarea name="bulk_csv_data" class="large-text" rows="6" placeholder="<?php echo esc_attr__("Paste CSV data here:\nYYYY-MM-DD,value\n2024-01-01,100.5\n2024-02-01,101.2", 'zc-dmt'); ?>"></textarea>
                            <p class="description"><?php echo esc_html__('Format: date,value (one per line). This will add to existing data.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>
            
            <div class="zc-modal-actions">
                <button type="button" class="button button-primary" onclick="zcSaveIndicator(<?php echo esc_attr($indicator_id); ?>)">
                    <?php echo esc_html__('Save Changes', 'zc-dmt'); ?>
                </button>
                <button type="button" class="button" onclick="zcCloseEditModal()">
                    <?php echo esc_html__('Cancel', 'zc-dmt'); ?>
                </button>
                <?php if ($indicator->source_type !== 'manual'): ?>
                    <button type="button" class="button button-secondary" onclick="zcTestIndicator('<?php echo esc_js($indicator->slug); ?>', this)" style="float: right;">
                        <span class="dashicons dashicons-update"></span>
                        <?php echo esc_html__('Test Data Fetch', 'zc-dmt'); ?>
                    </button>
                <?php endif; ?>
            </div>
            
            <style>
            .zc-modal-actions {
                padding-top: 15px;
                border-top: 1px solid #e2e8f0;
                margin-top: 20px;
                display: flex;
                gap: 8px;
                align-items: center;
            }
            </style>
            
            <script>
            // Save indicator function
            function zcSaveIndicator(indicatorId) {
                const formData = new FormData();
                formData.append('action', 'zc_dmt_save_indicator');
                formData.append('nonce', '<?php echo wp_create_nonce("zc_dmt_indicators_action"); ?>');
                formData.append('indicator_id', indicatorId);
                
                // Collect form data
                const form = document.getElementById('zc-edit-form');
                const inputs = form.querySelectorAll('input, textarea, select');
                inputs.forEach(input => {
                    if (input.type === 'checkbox') {
                        formData.append(input.name, input.checked ? '1' : '0');
                    } else {
                        formData.append(input.name, input.value);
                    }
                });
                
                // Submit via AJAX
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        zcShowToast('Indicator updated successfully!', 'success');
                        zcCloseEditModal();
                        location.reload(); // Refresh to show changes
                    } else {
                        zcShowToast(data.data.message || 'Failed to update indicator', 'error');
                    }
                })
                .catch(error => {
                    zcShowToast('Network error occurred', 'error');
                });
            }
            </script>
            <?php
            
            $form_html = ob_get_clean();
            wp_send_json_success($form_html);
        }
        
        /**
         * Save indicator changes - FIXED for correct class names
         */
        public static function save_indicator() {
            // Security check
            if (!wp_verify_nonce(sanitize_text_field($_REQUEST['nonce'] ?? ''), 'zc_dmt_indicators_action')) {
                wp_send_json_error(['message' => __('Security check failed.', 'zc-dmt')]);
            }
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Insufficient permissions.', 'zc-dmt')]);
            }
            
            $indicator_id = isset($_POST['indicator_id']) ? intval($_POST['indicator_id']) : 0;
            
            if ($indicator_id <= 0) {
                wp_send_json_error(['message' => __('Invalid indicator ID.', 'zc-dmt')]);
            }
            
            // Collect form data
            $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
            $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
            $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
            $source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : '';
            $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;
            $source_config_raw = isset($_POST['source_config']) ? wp_unslash($_POST['source_config']) : '';
            
            if (!$name || !$slug) {
                wp_send_json_error(['message' => __('Name and slug are required.', 'zc-dmt')]);
            }
            
            // Parse source config
            $source_config = null;
            if ($source_config_raw && trim($source_config_raw) !== '') {
                $decoded = json_decode($source_config_raw, true);
                if (is_array($decoded)) {
                    $source_config = $decoded;
                } else {
                    wp_send_json_error(['message' => __('Invalid JSON in source configuration.', 'zc-dmt')]);
                }
            }
            
            // Update indicator using correct class name
            if (class_exists('ZC_DMT_Indicators')) {
                $update_data = [
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'source_type' => $source_type,
                    'source_config' => $source_config,
                    'is_active' => $is_active
                ];
                
                $result = ZC_DMT_Indicators::update_indicator($indicator_id, $update_data);
                
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()]);
                }
                
                // Handle manual data entry if provided
                if ($source_type === 'manual') {
                    self::handle_manual_data_entry($indicator_id);
                }
                
                wp_send_json_success(['message' => __('Indicator updated successfully.', 'zc-dmt')]);
            }
            
            wp_send_json_error(['message' => __('ZC_DMT_Indicators class not found.', 'zc-dmt')]);
        }
        
        /**
         * Test data fetch for an indicator - FIXED for correct class names
         */
        public static function test_data_fetch() {
            // Security check
            if (!wp_verify_nonce(sanitize_text_field($_REQUEST['nonce'] ?? ''), 'zc_dmt_chart')) {
                wp_send_json_error(['message' => __('Security check failed.', 'zc-dmt')]);
            }
            
            $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
            
            if (!$slug) {
                wp_send_json_error(['message' => __('Invalid indicator slug.', 'zc-dmt')]);
            }
            
            if (class_exists('ZC_DMT_Indicators')) {
                $result = ZC_DMT_Indicators::get_data_by_slug($slug, null, null);
                
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()]);
                }
                
                $series_count = isset($result['series']) ? count($result['series']) : 0;
                
                wp_send_json_success([
                    'message' => sprintf(__('✓ Successfully loaded %d data points', 'zc-dmt'), $series_count),
                    'data_points' => $series_count,
                    'indicator' => $result['indicator'] ?? null
                ]);
            }
            
            wp_send_json_error(['message' => __('ZC_DMT_Indicators class not found.', 'zc-dmt')]);
        }
        
        /**
         * Handle manual data entry from edit form - FIXED for correct class names
         */
        private static function handle_manual_data_entry($indicator_id) {
            // Single data point
            $manual_date = isset($_POST['manual_date']) ? sanitize_text_field($_POST['manual_date']) : '';
            $manual_value = isset($_POST['manual_value']) ? sanitize_text_field($_POST['manual_value']) : '';
            
            if ($manual_date && $manual_value !== '' && is_numeric($manual_value)) {
                if (class_exists('ZC_DMT_Indicators')) {
                    ZC_DMT_Indicators::add_data_point($indicator_id, $manual_date, floatval($manual_value));
                }
            }
            
            // Bulk CSV data
            $bulk_csv = isset($_POST['bulk_csv_data']) ? wp_unslash($_POST['bulk_csv_data']) : '';
            
            if ($bulk_csv && trim($bulk_csv) !== '') {
                $lines = preg_split('/\r\n|\r|\n/', trim($bulk_csv));
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!$line || strpos($line, ',') === false) continue;
                    
                    $parts = str_getcsv($line);
                    if (count($parts) >= 2) {
                        $date = trim($parts[0]);
                        $value = trim($parts[1]);
                        
                        if ($date && is_numeric($value)) {
                            if (class_exists('ZC_DMT_Indicators')) {
                                ZC_DMT_Indicators::add_data_point($indicator_id, $date, floatval($value));
                            }
                        }
                    }
                }
            }
        }
    }
}