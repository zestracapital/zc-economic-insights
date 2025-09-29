<?php
/**
 * DBnomics Data Source Form
 * Path: admin/sources-forms/dbnomics.php
 *
 * Individual form for DBnomics data source
 * Supports: dataset+series config, JSON URL, CSV URL
 */

if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

$notice = '';
if (!empty($_POST['zc_source_action'])) {
    if (!isset($_POST['zc_source_nonce']) || !wp_verify_nonce($_POST['zc_source_nonce'], 'zc_source_action')) {
        $notice = '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'zc-dmt') . '</p></div>';
    } else {
        $action = sanitize_text_field($_POST['zc_source_action']);
        if ($action === 'add_indicator') {
            $name = sanitize_text_field($_POST['name'] ?? '');
            $slug = sanitize_title($_POST['slug'] ?? '');
            $description = wp_kses_post($_POST['description'] ?? '');
            $method = sanitize_text_field($_POST['dbn_method'] ?? '');

            // DBnomics specific fields
            $database = sanitize_text_field($_POST['dbn_database'] ?? '');
            $dataset = sanitize_text_field($_POST['dbn_dataset'] ?? ''); // Added dataset variable
            $series = sanitize_text_field($_POST['dbn_series'] ?? '');
            $json_url = esc_url_raw($_POST['dbn_json_url'] ?? '');
            $csv_url = esc_url_raw($_POST['dbn_csv_url'] ?? '');

            // Validation
            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug are required.', 'zc-dmt') . '</p></div>';
            } elseif ($method === 'config' && (!$database || !$dataset || !$series)) { // Updated validation
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Database, Dataset, and Series are required for this method.', 'zc-dmt') . '</p></div>';
            } elseif ($method === 'json' && !$json_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('JSON URL is required.', 'zc-dmt') . '</p></div>';
            } elseif ($method === 'csv' && !$csv_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('CSV URL is required.', 'zc-dmt') . '</p></div>';
            } else {
                $source_config = [];
                if ($method === 'config') {
                    $source_config = ['database' => $database, 'dataset' => $dataset, 'series' => $series]; // Updated config
                } elseif ($method === 'json') {
                    $source_config = ['json_url' => $json_url];
                } elseif ($method === 'csv') {
                    $source_config = ['csv_url' => $csv_url];
                }

                if (class_exists('ZC_DMT_Indicators')) {
                    $res = ZC_DMT_Indicators::create_indicator($name, $slug, $description, 'dbnomics', $source_config, 1);
                    if (is_wp_error($res)) {
                        $notice = '<div class="notice notice-error"><p>' . esc_html($res->get_error_message()) . '</p></div>';
                    } else {
                        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('DBnomics indicator created successfully!', 'zc-dmt') . '</p></div>';
                        // Clear form
                        $_POST = array();
                    }
                }
            }
        } elseif ($action === 'test_connection') {
            // Test connection functionality
            $test_method = sanitize_text_field($_POST['test_method'] ?? 'config');
            $test_database = sanitize_text_field($_POST['test_database'] ?? '');
            $test_dataset = sanitize_text_field($_POST['test_dataset'] ?? ''); // Added test_dataset
            $test_series = sanitize_text_field($_POST['test_series'] ?? '');
            $test_json_url = esc_url_raw($_POST['test_json_url'] ?? '');
            $test_csv_url = esc_url_raw($_POST['test_csv_url'] ?? '');

            $test_config = null;
            if ($test_method === 'config' && ($test_database && $test_dataset && $test_series)) { // Updated test config validation
                $test_config = ['database' => $test_database, 'dataset' => $test_dataset, 'series' => $test_series];
            } elseif ($test_method === 'json' && $test_json_url) {
                $test_config = ['json_url' => $test_json_url];
            } elseif ($test_method === 'csv' && $test_csv_url) {
                $test_config = ['csv_url' => $test_csv_url];
            }

            if ($test_config) {
                // Create a temporary indicator object for testing
                $test_indicator = (object) array(
                    'id' => 0,
                    'name' => 'Test',
                    'slug' => 'test-dbnomics',
                    'source_type' => 'dbnomics',
                    'source_config' => wp_json_encode($test_config)
                );

                if (class_exists('ZC_DMT_DataSource_DBnomics')) {
                    $test_result = ZC_DMT_DataSource_DBnomics::get_series_for_indicator($test_indicator);
                    if (is_wp_error($test_result)) {
                        $notice = '<div class="notice notice-error"><p><strong>Test Failed:</strong> ' . esc_html($test_result->get_error_message()) . '</p></div>';
                    } else {
                        $count = isset($test_result['series']) ? count($test_result['series']) : 0;
                        $notice = '<div class="notice notice-success"><p><strong>Test Successful!</strong> Retrieved ' . esc_html($count) . ' data points from DBnomics.</p></div>';
                    }
                } else {
                    $notice = '<div class="notice notice-error"><p>DBnomics data source class not found.</p></div>';
                }
            } else {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Please provide all required fields for the selected test method.', 'zc-dmt') . '</p></div>';
            }
        }
    }
}

// Get current form values for pre-filling
$form_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
$form_slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
$form_description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
$form_method = isset($_POST['dbn_method']) ? sanitize_text_field($_POST['dbn_method']) : 'config'; // Default to 'config'
$form_database = isset($_POST['dbn_database']) ? sanitize_text_field($_POST['dbn_database']) : '';
$form_dataset = isset($_POST['dbn_dataset']) ? sanitize_text_field($_POST['dbn_dataset']) : ''; // Added
$form_series = isset($_POST['dbn_series']) ? sanitize_text_field($_POST['dbn_series']) : '';
$form_json_url = isset($_POST['dbn_json_url']) ? esc_url_raw($_POST['dbn_json_url']) : '';
$form_csv_url = isset($_POST['dbn_csv_url']) ? esc_url_raw($_POST['dbn_csv_url']) : '';
?>

<div class="wrap zc-dmt-source-form">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('DBnomics Data Source', 'zc-dmt'); ?>
    </h1>

    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action">
        <?php esc_html_e('← Back to Data Sources', 'zc-dmt'); ?>
    </a>

    <hr class="wp-header-end">

    <?php echo $notice; ?>

    <div class="zc-source-info" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 20px 0;">
        <h3><?php esc_html_e('About DBnomics Data Source', 'zc-dmt'); ?></h3>
        <p><?php esc_html_e('DBnomics aggregates macroeconomic data from over 80 providers, including IMF, World Bank, OECD, Eurostat, and more. It offers a unified API for various datasets.', 'zc-dmt'); ?></p>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 12px;">
            <div>
                <h4><?php esc_html_e('Supported Methods:', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><strong><?php esc_html_e('Dataset+Series:', 'zc-dmt'); ?></strong> <?php esc_html_e('Specify Database, Dataset, and Series codes (recommended).', 'zc-dmt'); ?></li>
                    <li><strong><?php esc_html_e('JSON URL:', 'zc-dmt'); ?></strong> <?php esc_html_e('Direct JSON API endpoint from DBnomics.', 'zc-dmt'); ?></li>
                    <li><strong><?php esc_html_e('CSV URL:', 'zc-dmt'); ?></strong> <?php esc_html_e('Direct CSV download link from DBnomics.', 'zc-dmt'); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php esc_html_e('Features:', 'zc-dmt'); ?></h4>
                <ul style="margin: 8px 0; padding-left: 20px;">
                    <li><?php esc_html_e('Unified access to diverse economic data', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('Automatic date/value column detection', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('20 minutes caching', 'zc-dmt'); ?></li>
                    <li><?php esc_html_e('Date normalization to Y-m-d', 'zc-dmt'); ?></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="zc-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <!-- Add New Indicator Form -->
        <div class="zc-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
            <h2><?php esc_html_e('Add New DBnomics Indicator', 'zc-dmt'); ?></h2>

            <form method="post" id="dbnomics-indicator-form">
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
                                       placeholder="<?php esc_attr_e('e.g., US GDP Growth', 'zc-dmt'); ?>" required>
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
                                <label><?php esc_html_e('Data Source Method', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php esc_html_e('DBnomics Data Source Method', 'zc-dmt'); ?></legend>

                                    <!-- Method Selection -->
                                    <div style="margin-bottom: 16px;">
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="radio" name="dbn_method" value="config" <?php checked($form_method, 'config'); ?>>
                                            <strong><?php esc_html_e('Database, Dataset, Series (Recommended)', 'zc-dmt'); ?></strong>
                                        </label>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="radio" name="dbn_method" value="json" <?php checked($form_method, 'json'); ?>>
                                            <strong><?php esc_html_e('JSON URL', 'zc-dmt'); ?></strong>
                                        </label>
                                        <label style="display: block;">
                                            <input type="radio" name="dbn_method" value="csv" <?php checked($form_method, 'csv'); ?>>
                                            <strong><?php esc_html_e('CSV URL', 'zc-dmt'); ?></strong>
                                        </label>
                                    </div>

                                    <!-- Database, Dataset, Series Method -->
                                    <div id="method-config" class="dbn-method-section" style="<?php echo ($form_method !== 'config') ? 'display: none;' : ''; ?>">
                                        <div style="margin-bottom: 12px;">
                                            <label style="display: block; font-weight: 600;">
                                                <?php esc_html_e('Database Code', 'zc-dmt'); ?>
                                            </label>
                                            <input type="text" name="dbn_database" class="regular-text"
                                                   value="<?php echo esc_attr($form_database); ?>"
                                                   placeholder="IMF">
                                            <p class="description">
                                                <?php esc_html_e('e.g., IMF for International Monetary Fund.', 'zc-dmt'); ?>
                                            </p>
                                        </div>
                                        <div style="margin-bottom: 12px;">
                                            <label style="display: block; font-weight: 600;">
                                                <?php esc_html_e('Dataset Code', 'zc-dmt'); ?>
                                            </label>
                                            <input type="text" name="dbn_dataset" class="regular-text"
                                                   value="<?php echo esc_attr($form_dataset); ?>"
                                                   placeholder="IFS">
                                            <p class="description">
                                                <?php esc_html_e('e.g., IFS for International Financial Statistics.', 'zc-dmt'); ?>
                                            </p>
                                        </div>
                                        <div style="margin-bottom: 12px;">
                                            <label style="display: block; font-weight: 600;">
                                                <?php esc_html_e('Series Code', 'zc-dmt'); ?>
                                            </label>
                                            <input type="text" name="dbn_series" class="regular-text"
                                                   value="<?php echo esc_attr($form_series); ?>"
                                                   placeholder="A.US.PMP_IX">
                                            <p class="description">
                                                <?php esc_html_e('e.g., A.US.PMP_IX for US Producer Price Index.', 'zc-dmt'); ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- JSON URL Method -->
                                    <div id="method-json" class="dbn-method-section" style="<?php echo ($form_method !== 'json') ? 'display: none;' : ''; ?>">
                                        <label style="display: block; font-weight: 600;">
                                            <?php esc_html_e('JSON URL', 'zc-dmt'); ?>
                                        </label>
                                        <input type="url" name="dbn_json_url" class="regular-text" style="min-width: 400px;"
                                               value="<?php echo esc_attr($form_json_url); ?>"
                                               placeholder="https://api.db.nomics.world/v22/series/IMF/IFS?q=A.US.PMP_IX&format=json">
                                        <p class="description">
                                            <?php esc_html_e('Direct JSON URL to DBnomics API endpoint.', 'zc-dmt'); ?>
                                        </p>
                                    </div>

                                    <!-- CSV URL Method -->
                                    <div id="method-csv" class="dbn-method-section" style="<?php echo ($form_method !== 'csv') ? 'display: none;' : ''; ?>">
                                        <label style="display: block; font-weight: 600;">
                                            <?php esc_html_e('CSV URL', 'zc-dmt'); ?>
                                        </label>
                                        <input type="url" name="dbn_csv_url" class="regular-text" style="min-width: 400px;"
                                               value="<?php echo esc_attr($form_csv_url); ?>"
                                               placeholder="https://api.db.nomics.world/v22/series/IMF/IFS?q=A.US.PMP_IX&format=csv">
                                        <p class="description">
                                            <?php esc_html_e('Direct CSV URL to DBnomics API endpoint.', 'zc-dmt'); ?>
                                        </p>
                                    </div>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Create DBnomics Indicator', 'zc-dmt'); ?>
                    </button>
                </p>
            </form>
        </div>

        <!-- Test Connection -->
        <div class="zc-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
            <h2><?php esc_html_e('Test Connection', 'zc-dmt'); ?></h2>
            <p class="description">
                <?php esc_html_e('Test your DBnomics data source before creating an indicator.', 'zc-dmt'); ?>
            </p>

            <form method="post">
                <?php wp_nonce_field('zc_source_action', 'zc_source_nonce'); ?>
                <input type="hidden" name="zc_source_action" value="test_connection">

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Test Method', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="radio" name="test_method" value="config" checked>
                                        <?php esc_html_e('Database, Dataset, Series', 'zc-dmt'); ?>
                                    </label>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="radio" name="test_method" value="json">
                                        <?php esc_html_e('JSON URL', 'zc-dmt'); ?>
                                    </label>
                                    <label style="display: block;">
                                        <input type="radio" name="test_method" value="csv">
                                        <?php esc_html_e('CSV URL', 'zc-dmt'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr id="test-config-database-row">
                            <th scope="row">
                                <label for="test_database"><?php esc_html_e('Database Code', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="test_database" name="test_database" class="regular-text"
                                       placeholder="IMF">
                            </td>
                        </tr>
                        <tr id="test-config-dataset-row">
                            <th scope="row">
                                <label for="test_dataset"><?php esc_html_e('Dataset Code', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="test_dataset" name="test_dataset" class="regular-text"
                                       placeholder="IFS">
                            </td>
                        </tr>
                        <tr id="test-config-series-row">
                            <th scope="row">
                                <label for="test_series"><?php esc_html_e('Series Code', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="test_series" name="test_series" class="regular-text"
                                       placeholder="A.US.PMP_IX">
                            </td>
                        </tr>

                        <tr id="test-json-row" style="display: none;">
                            <th scope="row">
                                <label for="test_json_url"><?php esc_html_e('JSON URL', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="test_json_url" name="test_json_url" class="regular-text" style="min-width: 400px;"
                                       placeholder="https://api.db.nomics.world/v22/series/IMF/IFS?q=A.US.PMP_IX&format=json">
                            </td>
                        </tr>

                        <tr id="test-csv-row" style="display: none;">
                            <th scope="row">
                                <label for="test_csv_url"><?php esc_html_e('CSV URL', 'zc-dmt'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="test_csv_url" name="test_csv_url" class="regular-text" style="min-width: 400px;"
                                       placeholder="https://api.db.nomics.world/v22/series/IMF/IFS?q=A.US.PMP_IX&format=csv">
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
                <h3><?php esc_html_e('Example DBnomics Series:', 'zc-dmt'); ?></h3>
                <div style="margin: 12px 0;">
                    <strong>IMF/IFS/A.US.PMP_IX:</strong> <?php esc_html_e('US Producer Price Index (IMF, International Financial Statistics)', 'zc-dmt'); ?><br>
                    <strong>OECD/MEI/CPI.TOT.IX.OECD:</strong> <?php esc_html_e('OECD Consumer Price Index (OECD, Main Economic Indicators)', 'zc-dmt'); ?><br>
                    <strong>WB/WDI/SP.POP.TOTL:</strong> <?php esc_html_e('Total Population (World Bank, World Development Indicators)', 'zc-dmt'); ?>
                </div>

                <div style="margin-top: 16px;">
                    <p class="description">
                        <strong><?php esc_html_e('How to find Database, Dataset, and Series codes:', 'zc-dmt'); ?></strong><br>
                        1. <?php esc_html_e('Visit', 'zc-dmt'); ?> <a href="https://db.nomics.world/" target="_blank">db.nomics.world</a><br>
                        2. <?php esc_html_e('Search for your desired economic indicator.', 'zc-dmt'); ?><br>
                        3. <?php esc_html_e('The codes are typically displayed in the URL or on the series page (e.g., `https://db.nomics.world/IMF/IFS/A.US.PMP_IX`).', 'zc-dmt'); ?>
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

    // Method selection for main form
    $('input[name="dbn_method"]').on('change', function() {
        $('.dbn-method-section').hide(); // Hide all sections first
        $('#method-' + $(this).val()).show(); // Show the selected section
    }).change(); // Trigger on page load to set initial visibility

    // Test method selection
    $('input[name="test_method"]').on('change', function() {
        // Hide all test method-specific rows first
        $('#test-config-database-row, #test-config-dataset-row, #test-config-series-row, #test-json-row, #test-csv-row').hide();

        if ($(this).val() === 'config') {
            $('#test-config-database-row, #test-config-dataset-row, #test-config-series-row').show();
        } else if ($(this).val() === 'json') {
            $('#test-json-row').show();
        } else if ($(this).val() === 'csv') {
            $('#test-csv-row').show();
        }
    }).change(); // Trigger on page load to set initial visibility

    // Copy from main form to test form (for config method)
    $('input[name="dbn_database"]').on('input', function() {
        $('#test_database').val($(this).val());
    });
    $('input[name="dbn_dataset"]').on('input', function() { // Added for dataset
        $('#test_dataset').val($(this).val());
    });
    $('input[name="dbn_series"]').on('input', function() {
        $('#test_series').val($(this).val());
    });

    // Copy from main form to test form (for URL methods)
    $('input[name="dbn_json_url"]').on('input', function() {
        $('#test_json_url').val($(this).val());
    });
    $('input[name="dbn_csv_url"]').on('input', function() {
        $('#test_csv_url').val($(this).val());
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

.dbn-method-section {
    padding: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    margin-top: 8px;
}

@media (max-width: 768px) {
    .zc-grid {
        grid-template-columns: 1fr !important;
    }

    .zc-source-info > div {
        grid-template-columns: 1fr !important;
    }

    .dbn-method-section div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>
