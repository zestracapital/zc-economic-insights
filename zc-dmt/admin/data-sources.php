<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced Data Sources Management Page
 * Source-specific configuration panels with validation and testing
 */

function zc_dmt_render_data_sources_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle form submissions
    if (!empty($_POST['zc_dmt_sources_action'])) {
        if (!isset($_POST['zc_dmt_sources_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['zc_dmt_sources_nonce']), 'zc_dmt_sources_action')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed. Please try again.', 'zc-dmt') . '</p></div>';
        } else {
            $action = sanitize_text_field($_POST['zc_dmt_sources_action']);

            if ($action === 'save_api_keys') {
                // Save all API keys
                $keys = array(
                    'fred_api_key' => sanitize_text_field($_POST['fred_api_key'] ?? ''),
                    'quandl_api_key' => sanitize_text_field($_POST['quandl_api_key'] ?? ''),
                    'alpha_vantage_key' => sanitize_text_field($_POST['alpha_vantage_key'] ?? ''),
                    'polygon_api_key' => sanitize_text_field($_POST['polygon_api_key'] ?? ''),
                    'twelve_data_key' => sanitize_text_field($_POST['twelve_data_key'] ?? ''),
                );

                foreach ($keys as $option_name => $value) {
                    update_option('zc_' . $option_name, $value);
                }

                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('API keys saved successfully.', 'zc-dmt') . '</p></div>';
            }

            if ($action === 'test_connection') {
                $source_type = sanitize_text_field($_POST['source_type'] ?? '');
                $test_result = zc_dmt_test_data_source_connection($source_type);
                
                if ($test_result['success']) {
                    echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html($source_type) . ':</strong> ' . esc_html($test_result['message']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html($source_type) . ':</strong> ' . esc_html($test_result['message']) . '</p></div>';
                }
            }
        }
    }

    // Get current API keys
    $api_keys = array(
        'fred_api_key' => get_option('zc_fred_api_key', ''),
        'quandl_api_key' => get_option('zc_quandl_api_key', ''),
        'alpha_vantage_key' => get_option('zc_alpha_vantage_key', ''),
        'polygon_api_key' => get_option('zc_polygon_api_key', ''),
        'twelve_data_key' => get_option('zc_twelve_data_key', ''),
    );

    ?>
    <div class="wrap zc-dmt-data-sources">
        <div class="zc-dmt-header">
            <h1 class="zc-dmt-title">
                <span class="zc-dmt-icon">🔌</span>
                <?php echo esc_html__('Data Sources Management', 'zc-dmt'); ?>
            </h1>
            <div class="zc-dmt-header-actions">
                <a href="<?php echo admin_url('admin.php?page=zc-dmt-indicators'); ?>" class="button">
                    <?php echo esc_html__('Back to Indicators', 'zc-dmt'); ?>
                </a>
            </div>
        </div>

        <!-- API Keys Configuration -->
        <div class="zc-dmt-section">
            <div class="zc-dmt-card">
                <div class="card-header">
                    <h2><?php echo esc_html__('API Keys Configuration', 'zc-dmt'); ?></h2>
                    <p class="description"><?php echo esc_html__('Configure API keys for external data sources that require authentication.', 'zc-dmt'); ?></p>
                </div>
                <div class="card-body">
                    <form method="post" class="zc-dmt-api-keys-form">
                        <?php wp_nonce_field('zc_dmt_sources_action', 'zc_dmt_sources_nonce'); ?>
                        <input type="hidden" name="zc_dmt_sources_action" value="save_api_keys" />
                        
                        <div class="api-keys-grid">
                            <!-- FRED -->
                            <div class="api-key-item">
                                <div class="key-info">
                                    <h4><?php echo esc_html__('FRED (Federal Reserve)', 'zc-dmt'); ?></h4>
                                    <p><?php echo esc_html__('US Federal Reserve Economic Data', 'zc-dmt'); ?></p>
                                    <a href="https://fred.stlouisfed.org/docs/api/api_key.html" target="_blank" class="external-link">
                                        <?php echo esc_html__('Get API Key', 'zc-dmt'); ?> ↗
                                    </a>
                                </div>
                                <div class="key-input">
                                    <input type="text" name="fred_api_key" class="regular-text" 
                                           value="<?php echo esc_attr($api_keys['fred_api_key']); ?>" 
                                           placeholder="<?php echo esc_attr__('Enter FRED API Key', 'zc-dmt'); ?>" />
                                    <button type="button" class="button test-connection" data-source="fred">
                                        <?php echo esc_html__('Test', 'zc-dmt'); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Quandl -->
                            <div class="api-key-item">
                                <div class="key-info">
                                    <h4><?php echo esc_html__('Quandl / Nasdaq Data Link', 'zc-dmt'); ?></h4>
                                    <p><?php echo esc_html__('Financial and economic data', 'zc-dmt'); ?></p>
                                    <a href="https://data.nasdaq.com/tools/api" target="_blank" class="external-link">
                                        <?php echo esc_html__('Get API Key', 'zc-dmt'); ?> ↗
                                    </a>
                                </div>
                                <div class="key-input">
                                    <input type="text" name="quandl_api_key" class="regular-text" 
                                           value="<?php echo esc_attr($api_keys['quandl_api_key']); ?>" 
                                           placeholder="<?php echo esc_attr__('Enter Quandl API Key', 'zc-dmt'); ?>" />
                                    <button type="button" class="button test-connection" data-source="quandl">
                                        <?php echo esc_html__('Test', 'zc-dmt'); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Alpha Vantage -->
                            <div class="api-key-item">
                                <div class="key-info">
                                    <h4><?php echo esc_html__('Alpha Vantage', 'zc-dmt'); ?></h4>
                                    <p><?php echo esc_html__('Real-time financial data', 'zc-dmt'); ?></p>
                                    <a href="https://www.alphavantage.co/support/#api-key" target="_blank" class="external-link">
                                        <?php echo esc_html__('Get API Key', 'zc-dmt'); ?> ↗
                                    </a>
                                </div>
                                <div class="key-input">
                                    <input type="text" name="alpha_vantage_key" class="regular-text" 
                                           value="<?php echo esc_attr($api_keys['alpha_vantage_key']); ?>" 
                                           placeholder="<?php echo esc_attr__('Enter Alpha Vantage API Key', 'zc-dmt'); ?>" />
                                    <button type="button" class="button test-connection" data-source="alpha_vantage">
                                        <?php echo esc_html__('Test', 'zc-dmt'); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Polygon -->
                            <div class="api-key-item">
                                <div class="key-info">
                                    <h4><?php echo esc_html__('Polygon.io', 'zc-dmt'); ?></h4>
                                    <p><?php echo esc_html__('Stock market data', 'zc-dmt'); ?></p>
                                    <a href="https://polygon.io/dashboard/api-keys" target="_blank" class="external-link">
                                        <?php echo esc_html__('Get API Key', 'zc-dmt'); ?> ↗
                                    </a>
                                </div>
                                <div class="key-input">
                                    <input type="text" name="polygon_api_key" class="regular-text" 
                                           value="<?php echo esc_attr($api_keys['polygon_api_key']); ?>" 
                                           placeholder="<?php echo esc_attr__('Enter Polygon API Key', 'zc-dmt'); ?>" />
                                    <button type="button" class="button test-connection" data-source="polygon">
                                        <?php echo esc_html__('Test', 'zc-dmt'); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Twelve Data -->
                            <div class="api-key-item">
                                <div class="key-info">
                                    <h4><?php echo esc_html__('Twelve Data', 'zc-dmt'); ?></h4>
                                    <p><?php echo esc_html__('Financial market data', 'zc-dmt'); ?></p>
                                    <a href="https://twelvedata.com/apikey" target="_blank" class="external-link">
                                        <?php echo esc_html__('Get API Key', 'zc-dmt'); ?> ↗
                                    </a>
                                </div>
                                <div class="key-input">
                                    <input type="text" name="twelve_data_key" class="regular-text" 
                                           value="<?php echo esc_attr($api_keys['twelve_data_key']); ?>" 
                                           placeholder="<?php echo esc_attr__('Enter Twelve Data API Key', 'zc-dmt'); ?>" />
                                    <button type="button" class="button test-connection" data-source="twelve_data">
                                        <?php echo esc_html__('Test', 'zc-dmt'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="button button-primary">
                                <?php echo esc_html__('Save API Keys', 'zc-dmt'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Data Source Categories -->
        <div class="zc-dmt-section">
            <div class="source-categories-grid">
                <!-- Open Data Sources -->
                <div class="zc-dmt-card">
                    <div class="card-header">
                        <h3><?php echo esc_html__('Open Data Sources', 'zc-dmt'); ?></h3>
                        <span class="status-badge free"><?php echo esc_html__('Free', 'zc-dmt'); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="source-list">
                            <?php
                            $open_sources = array(
                                'world_bank' => array('name' => 'World Bank', 'description' => 'Global economic indicators', 'status' => 'available'),
                                'eurostat' => array('name' => 'Eurostat', 'description' => 'European statistical data', 'status' => 'available'),
                                'oecd' => array('name' => 'OECD', 'description' => 'Economic indicators', 'status' => 'available'),
                                'dbnomics' => array('name' => 'DBnomics', 'description' => 'Aggregated economic data', 'status' => 'available'),
                                'google_sheets' => array('name' => 'Google Sheets', 'description' => 'Custom data via CSV export', 'status' => 'available'),
                            );
                            foreach ($open_sources as $key => $source) :
                            ?>
                                <div class="source-item">
                                    <div class="source-info">
                                        <h4><?php echo esc_html($source['name']); ?></h4>
                                        <p><?php echo esc_html($source['description']); ?></p>
                                    </div>
                                    <div class="source-status">
                                        <span class="status-indicator <?php echo esc_attr($source['status']); ?>"></span>
                                        <span class="status-text"><?php echo esc_html(ucfirst($source['status'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Government Sources -->
                <div class="zc-dmt-card">
                    <div class="card-header">
                        <h3><?php echo esc_html__('Government Sources', 'zc-dmt'); ?></h3>
                        <span class="status-badge free"><?php echo esc_html__('Official', 'zc-dmt'); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="source-list">
                            <?php
                            $gov_sources = array(
                                'uk_ons' => array('name' => 'UK ONS', 'description' => 'UK Office for National Statistics', 'status' => 'available'),
                                'statcan' => array('name' => 'Statistics Canada', 'description' => 'Canadian economic data', 'status' => 'available'),
                                'bank_of_canada' => array('name' => 'Bank of Canada', 'description' => 'Canadian financial data', 'status' => 'available'),
                                'australia_rba' => array('name' => 'Australia RBA', 'description' => 'Reserve Bank of Australia', 'status' => 'available'),
                                'ecb' => array('name' => 'European Central Bank', 'description' => 'ECB Statistical Data Warehouse', 'status' => 'available'),
                            );
                            foreach ($gov_sources as $key => $source) :
                            ?>
                                <div class="source-item">
                                    <div class="source-info">
                                        <h4><?php echo esc_html($source['name']); ?></h4>
                                        <p><?php echo esc_html($source['description']); ?></p>
                                    </div>
                                    <div class="source-status">
                                        <span class="status-indicator <?php echo esc_attr($source['status']); ?>"></span>
                                        <span class="status-text"><?php echo esc_html(ucfirst($source['status'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Financial Data Sources -->
                <div class="zc-dmt-card">
                    <div class="card-header">
                        <h3><?php echo esc_html__('Financial Data', 'zc-dmt'); ?></h3>
                        <span class="status-badge premium"><?php echo esc_html__('API Key Required', 'zc-dmt'); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="source-list">
                            <?php
                            $financial_sources = array(
                                'yahoo_finance' => array('name' => 'Yahoo Finance', 'description' => 'Market data (free tier)', 'status' => 'available'),
                                'google_finance' => array('name' => 'Google Finance', 'description' => 'Via Google Sheets integration', 'status' => 'available'),
                                'fred' => array('name' => 'FRED', 'description' => 'Federal Reserve Economic Data', 'status' => !empty($api_keys['fred_api_key']) ? 'configured' : 'needs_key'),
                                'quandl' => array('name' => 'Quandl', 'description' => 'Financial & economic data', 'status' => !empty($api_keys['quandl_api_key']) ? 'configured' : 'needs_key'),
                                'alpha_vantage' => array('name' => 'Alpha Vantage', 'description' => 'Real-time financial data', 'status' => !empty($api_keys['alpha_vantage_key']) ? 'configured' : 'needs_key'),
                            );
                            foreach ($financial_sources as $key => $source) :
                            ?>
                                <div class="source-item">
                                    <div class="source-info">
                                        <h4><?php echo esc_html($source['name']); ?></h4>
                                        <p><?php echo esc_html($source['description']); ?></p>
                                    </div>
                                    <div class="source-status">
                                        <span class="status-indicator <?php echo esc_attr($source['status']); ?>"></span>
                                        <span class="status-text">
                                            <?php 
                                            echo $source['status'] === 'needs_key' 
                                                ? esc_html__('Needs API Key', 'zc-dmt')
                                                : esc_html(ucfirst(str_replace('_', ' ', $source['status'])));
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Universal Sources -->
                <div class="zc-dmt-card">
                    <div class="card-header">
                        <h3><?php echo esc_html__('Universal Importers', 'zc-dmt'); ?></h3>
                        <span class="status-badge flexible"><?php echo esc_html__('Any Source', 'zc-dmt'); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="source-list">
                            <div class="source-item">
                                <div class="source-info">
                                    <h4><?php echo esc_html__('Universal CSV', 'zc-dmt'); ?></h4>
                                    <p><?php echo esc_html__('Import from any CSV URL with column mapping', 'zc-dmt'); ?></p>
                                </div>
                                <div class="source-status">
                                    <span class="status-indicator available"></span>
                                    <span class="status-text"><?php echo esc_html__('Available', 'zc-dmt'); ?></span>
                                </div>
                            </div>
                            <div class="source-item">
                                <div class="source-info">
                                    <h4><?php echo esc_html__('Universal JSON', 'zc-dmt'); ?></h4>
                                    <p><?php echo esc_html__('Import from any JSON API with path mapping', 'zc-dmt'); ?></p>
                                </div>
                                <div class="source-status">
                                    <span class="status-indicator available"></span>
                                    <span class="status-text"><?php echo esc_html__('Available', 'zc-dmt'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Connection Testing Results -->
        <div id="connection-test-results" style="margin-top: 24px;"></div>
    </div>

    <style>
    .zc-dmt-data-sources .api-keys-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }

    .api-key-item {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: center;
        padding: 20px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
    }

    .key-info h4 {
        margin: 0 0 4px 0;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
    }

    .key-info p {
        margin: 0 0 8px 0;
        color: #6b7280;
        font-size: 14px;
    }

    .external-link {
        color: #2563eb;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
    }

    .external-link:hover {
        text-decoration: underline;
    }

    .key-input {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .key-input input {
        flex-grow: 1;
    }

    .source-categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 24px;
    }

    .source-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .source-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        background: #f3f4f6;
        border-radius: 6px;
    }

    .source-info h4 {
        margin: 0 0 2px 0;
        font-size: 14px;
        font-weight: 600;
        color: #1f2937;
    }

    .source-info p {
        margin: 0;
        font-size: 13px;
        color: #6b7280;
    }

    .source-status {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .status-indicator.available {
        background: #10b981;
    }

    .status-indicator.configured {
        background: #2563eb;
    }

    .status-indicator.needs_key {
        background: #f59e0b;
    }

    .status-text {
        font-size: 12px;
        font-weight: 500;
        color: #6b7280;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-badge.free {
        background: #dcfce7;
        color: #166534;
    }

    .status-badge.premium {
        background: #fef3c7;
        color: #92400e;
    }

    .status-badge.flexible {
        background: #dbeafe;
        color: #1e40af;
    }

    @media (max-width: 768px) {
        .api-key-item {
            grid-template-columns: 1fr;
            gap: 16px;
            text-align: left;
        }

        .source-categories-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Test connection functionality
        $('.test-connection').on('click', function() {
            const button = $(this);
            const source = button.data('source');
            const originalText = button.text();

            button.text('<?php echo esc_js(__('Testing...', 'zc-dmt')); ?>').prop('disabled', true);

            $.post(ajaxurl, {
                action: 'zc_dmt_test_connection',
                source_type: source,
                nonce: '<?php echo wp_create_nonce('zc_dmt_test_connection'); ?>'
            }, function(response) {
                if (response.success) {
                    button.text('<?php echo esc_js(__('✓ Success', 'zc-dmt')); ?>').addClass('button-primary');
                } else {
                    button.text('<?php echo esc_js(__('✗ Failed', 'zc-dmt')); ?>').addClass('button-secondary');
                }

                setTimeout(function() {
                    button.text(originalText).prop('disabled', false)
                          .removeClass('button-primary button-secondary');
                }, 3000);
            });
        });
    });
    </script>
    <?php
}

/**
 * Test data source connection
 */
function zc_dmt_test_data_source_connection($source_type) {
    switch ($source_type) {
        case 'fred':
            $api_key = get_option('zc_fred_api_key', '');
            if (empty($api_key)) {
                return array('success' => false, 'message' => __('API key is required', 'zc-dmt'));
            }
            // Test FRED connection
            $test_url = 'https://api.stlouisfed.org/fred/series?series_id=GDP&api_key=' . $api_key . '&file_type=json';
            $response = wp_remote_get($test_url, array('timeout' => 10));
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            $code = wp_remote_retrieve_response_code($response);
            return array('success' => $code === 200, 'message' => $code === 200 ? __('Connection successful', 'zc-dmt') : __('Connection failed', 'zc-dmt'));

        case 'world_bank':
            // Test World Bank connection
            $test_url = 'https://api.worldbank.org/v2/country/us/indicator/NY.GDP.MKTP.CD?format=json&per_page=1';
            $response = wp_remote_get($test_url, array('timeout' => 10));
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            $code = wp_remote_retrieve_response_code($response);
            return array('success' => $code === 200, 'message' => $code === 200 ? __('Connection successful', 'zc-dmt') : __('Connection failed', 'zc-dmt'));

        default:
            return array('success' => false, 'message' => __('Source type not supported for testing', 'zc-dmt'));
    }
}