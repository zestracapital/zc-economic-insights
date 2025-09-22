<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data Sources Page - Updated with Individual Form Links
 * Integrates with separate form files created earlier
 */

function zc_dmt_render_data_sources_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Get current stage and selected source
    $stage = isset($_GET['stage']) ? sanitize_text_field($_GET['stage']) : '1';
    $source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
    
    ?>
    
    <div class="wrap zc-dmt-data-sources">
        <h1 class="wp-heading-inline"><?php echo esc_html__('Data Sources', 'zc-dmt'); ?></h1>
        
        <?php if ($stage === '1'): ?>
            <!-- STAGE 1: Data Source Selection -->
            <p class="description"><?php echo esc_html__('Select a data source to add economic indicators. Each source has multiple methods to fetch data.', 'zc-dmt'); ?></p>
            <hr class="wp-header-end">
            
            <div class="zc-sources-grid">
                
                <!-- Google Sheets -->
                <div class="zc-source-card">
                    <div class="zc-source-header">
                        <span class="zc-source-icon zc-icon-google">G</span>
                        <h3>Google Sheets</h3>
                    </div>
                    <p>Import data from any public Google Sheets CSV. Auto-detects date and value columns.</p>
                    <div class="zc-methods">
                        <span class="zc-method-badge zc-method-csv">CSV URL</span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=google-sheets')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                </div>
                
                <!-- FRED API -->
                <div class="zc-source-card">
                    <div class="zc-source-header">
                        <span class="zc-source-icon zc-icon-fred">F</span>
                        <h3>FRED API</h3>
                        <?php $fred_configured = get_option('zc_fred_api_key'); ?>
                        <span class="zc-api-status <?php echo $fred_configured ? 'configured' : 'not-configured'; ?>">
                            <?php echo $fred_configured ? 'API Configured' : 'API Required'; ?>
                        </span>
                    </div>
                    <p>US Federal Reserve Economic Data. Over 800,000 time series from 100+ sources.</p>
                    <div class="zc-methods">
                        <span class="zc-method-badge zc-method-series">Series ID</span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=fred')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                </div>
                
                <!-- World Bank -->
                <div class="zc-source-card">
                    <div class="zc-source-header">
                        <span class="zc-source-icon zc-icon-wb">WB</span>
                        <h3>World Bank</h3>
                        <span class="zc-api-status configured">Open API</span>
                    </div>
                    <p>Global development data for all countries. GDP, population, economic indicators.</p>
                    <div class="zc-methods">
                        <span class="zc-method-badge zc-method-country">Country + Indicator</span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=world-bank')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                </div>
                
                <!-- DBnomics -->
                <div class="zc-source-card">
                    <div class="zc-source-header">
                        <span class="zc-source-icon zc-icon-dbn">DB</span>
                        <h3>DBnomics</h3>
                        <span class="zc-api-status configured">Open API</span>
                    </div>
                    <p>Aggregated macroeconomic data from 80+ providers including IMF, World Bank, OECD.</p>
                    <div class="zc-methods">
                        <span class="zc-method-badge zc-method-series">Series ID</span>
                        <span class="zc-method-badge zc-method-json">JSON URL</span>
                        <span class="zc-method-badge zc-method-csv">CSV URL</span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=dbnomics')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                </div>
                
                <!-- Eurostat -->
                <div class="zc-source-card">
                    <div class="zc-source-header">
                        <span class="zc-source-icon zc-icon-eu">EU</span>
                        <h3>Eurostat</h3>
                        <span class="zc-api-status configured">Open API</span>
                    </div>
                    <p>Official EU statistics. Economic data for European countries and regions.</p>
                    <div class="zc-methods">
                        <span class="zc-method-badge zc-method-dataset">Dataset Code</span>
                        <span class="zc-method-badge zc-method-json">JSON URL</span>
                        <span class="zc-method-badge zc-method-csv">CSV URL</span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=eurostat')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                </div>
                
                <!-- OECD -->
                <div class="zc-source-card">
                    <div class="zc-source-header">
                        <span class="zc-source-icon zc-icon-oecd">OE</span>
                        <h3>OECD</h3>
                        <span class="zc-api-status configured">Open API</span>
                    </div>
                    <p>OECD countries economic data. SDMX-JSON and CSV formats supported.</p>
                    <div class="zc-methods">
                        <span class="zc-method-badge zc-method-dataset">Dataset Key</span>
                        <span class="zc-method-badge zc-method-json">JSON URL</span>
                        <span class="zc-method-badge zc-method-csv">CSV URL</span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=oecd')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                </div>
                
                <!-- UK ONS -->
                <div class="zc-source-card">
                    <div class="zc-source-header">
                        <span class="zc-source-icon zc-icon-uk">UK</span>
                        <h3>UK ONS</h3>
                        <span class="zc-api-status configured">Open API</span>
                    </div>
                    <p>UK Office for National Statistics. Economic data for United Kingdom.</p>
                    <div class="zc-methods">
                        <span class="zc-method-badge zc-method-json">JSON URL</span>
                        <span class="zc-method-badge zc-method-csv">CSV URL</span>
                        <span class="zc-method-badge zc-method-series">Timeseries</span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=uk-ons')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                </div>
                
                <!-- Yahoo Finance -->
                <div class="zc-source-card">
                    <div class="zc-source-header">
                        <span class="zc-source-icon zc-icon-yahoo">Y!</span>
                        <h3>Yahoo Finance</h3>
                        <span class="zc-api-status configured">No API Key</span>
                    </div>
                    <p>Stock prices, forex, crypto, indices. Real-time and historical data.</p>
                    <div class="zc-methods">
                        <span class="zc-method-badge zc-method-series">Symbol</span>
                        <span class="zc-method-badge zc-method-json">JSON URL</span>
                        <span class="zc-method-badge zc-method-csv">CSV URL</span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=yahoo-finance')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                </div>
                
                <!-- Additional sources -->
                <div class="zc-source-card zc-source-more">
                    <details>
                        <summary>More Data Sources (8 additional sources)</summary>
                        <div class="zc-sources-grid-inner">
                            
                            <!-- Google Finance -->
                            <div class="zc-source-card">
                                <div class="zc-source-header">
                                    <span class="zc-source-icon zc-icon-gfinance">GF</span>
                                    <h3>Google Finance</h3>
                                </div>
                                <p>Market data via Google Sheets GOOGLEFINANCE.</p>
                                <div class="zc-methods">
                                    <span class="zc-method-badge zc-method-json">JSON URL</span>
                                    <span class="zc-method-badge zc-method-csv">CSV URL</span>
                                </div>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=google-finance')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                            </div>
                            
                            <!-- Nasdaq Data Link -->
                            <div class="zc-source-card">
                                <div class="zc-source-header">
                                    <span class="zc-source-icon zc-icon-quandl">Q</span>
                                    <h3>Nasdaq Data Link</h3>
                                </div>
                                <p>Financial data (formerly Quandl). Free tier available.</p>
                                <div class="zc-methods">
                                    <span class="zc-method-badge zc-method-series">Dataset Code</span>
                                    <span class="zc-method-badge zc-method-csv">CSV URL</span>
                                    <span class="zc-method-badge zc-method-json">JSON URL</span>
                                </div>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=nasdaq-data-link')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                            </div>
                            
                            <!-- Bank of Canada -->
                            <div class="zc-source-card">
                                <div class="zc-source-header">
                                    <span class="zc-source-icon zc-icon-canada">CA</span>
                                    <h3>Bank of Canada</h3>
                                </div>
                                <p>Canadian economic data via Valet API.</p>
                                <div class="zc-methods">
                                    <span class="zc-method-badge zc-method-series">Series ID</span>
                                    <span class="zc-method-badge zc-method-json">JSON URL</span>
                                    <span class="zc-method-badge zc-method-csv">CSV URL</span>
                                </div>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=bank-of-canada')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                            </div>
                            
                            <!-- Statistics Canada -->
                            <div class="zc-source-card">
                                <div class="zc-source-header">
                                    <span class="zc-source-icon zc-icon-statcan">SC</span>
                                    <h3>Statistics Canada</h3>
                                </div>
                                <p>Canadian national statistics.</p>
                                <div class="zc-methods">
                                    <span class="zc-method-badge zc-method-json">JSON URL</span>
                                    <span class="zc-method-badge zc-method-csv">CSV URL</span>
                                </div>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=statcan')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                            </div>
                            
                            <!-- Australia RBA -->
                            <div class="zc-source-card">
                                <div class="zc-source-header">
                                    <span class="zc-source-icon zc-icon-australia">AU</span>
                                    <h3>Reserve Bank Australia</h3>
                                </div>
                                <p>Australian economic data from RBA.</p>
                                <div class="zc-methods">
                                    <span class="zc-method-badge zc-method-series">Series ID</span>
                                    <span class="zc-method-badge zc-method-csv">CSV URL</span>
                                    <span class="zc-method-badge zc-method-json">JSON URL</span>
                                </div>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=australia-rba')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                            </div>
                            
                            <!-- ECB -->
                            <div class="zc-source-card">
                                <div class="zc-source-header">
                                    <span class="zc-source-icon zc-icon-ecb">ECB</span>
                                    <h3>European Central Bank</h3>
                                </div>
                                <p>ECB Statistical Data Warehouse.</p>
                                <div class="zc-methods">
                                    <span class="zc-method-badge zc-method-series">Path</span>
                                    <span class="zc-method-badge zc-method-json">JSON URL</span>
                                    <span class="zc-method-badge zc-method-csv">CSV URL</span>
                                </div>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=ecb')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                            </div>
                            
                            <!-- Universal CSV -->
                            <div class="zc-source-card">
                                <div class="zc-source-header">
                                    <span class="zc-source-icon zc-icon-csv">CSV</span>
                                    <h3>Universal CSV</h3>
                                </div>
                                <p>Import from any CSV URL with flexible parsing.</p>
                                <div class="zc-methods">
                                    <span class="zc-method-badge zc-method-csv">Any CSV URL</span>
                                </div>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=universal-csv')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                            </div>
                            
                            <!-- Universal JSON -->
                            <div class="zc-source-card">
                                <div class="zc-source-header">
                                    <span class="zc-source-icon zc-icon-json">JSON</span>
                                    <h3>Universal JSON</h3>
                                </div>
                                <p>Import from any JSON URL with flexible parsing.</p>
                                <div class="zc-methods">
                                    <span class="zc-method-badge zc-method-json">Any JSON URL</span>
                                </div>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-sources-forms&source=universal-json')); ?>" class="button button-primary zc-full-width">Add Indicator</a>
                            </div>
                            
                        </div>
                    </details>
                </div>
                
            </div>
            
        <?php else: ?>
            <!-- STAGE 2: Configuration Form - This should not be reached since we use separate pages -->
            <p>Configuration forms are now handled on separate pages.</p>
        <?php endif; ?>
    </div>
    
    <style>
    .zc-sources-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
    .zc-sources-grid-inner { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-top: 15px; }
    .zc-source-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; background: #fff; transition: box-shadow 0.2s ease; }
    .zc-source-card:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
    .zc-source-more { grid-column: 1 / -1; }
    .zc-source-header { display: flex; align-items: center; margin-bottom: 12px; }
    .zc-source-icon { width: 32px; height: 32px; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; margin-right: 12px; }
    .zc-source-header h3 { margin: 0; }
    .zc-api-status { margin-left: auto; padding: 4px 8px; border-radius: 4px; font-size: 11px; }
    .zc-api-status.configured { background: #dcfce7; color: #166534; }
    .zc-api-status.not-configured { background: #fef2f2; color: #dc2626; }
    .zc-methods { margin-bottom: 16px; }
    .zc-method-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; margin-right: 4px; margin-bottom: 4px; white-space: nowrap; }
    .zc-method-csv { background: #e0f2fe; color: #0277bd; }
    .zc-method-series { background: #fff3cd; color: #856404; }
    .zc-method-json { background: #e0f2fe; color: #0277bd; }
    .zc-method-country { background: #f0f9ff; color: #0369a1; }
    .zc-method-dataset { background: #f0f9ff; color: #0369a1; }
    .zc-method-filter { background: #fef3c7; color: #92400e; }
    .zc-full-width { width: 100%; }
    .zc-disabled { opacity: 0.6; }
    .zc-config-note { margin-top: 8px; font-size: 12px; color: #dc2626; }
    
    .zc-icon-google { background: #34a853; }
    .zc-icon-fred { background: #1976d2; }
    .zc-icon-wb { background: #0073e6; }
    .zc-icon-dbn { background: #7c3aed; }
    .zc-icon-eu { background: #003399; }
    .zc-icon-oecd { background: #0066cc; }
    .zc-icon-uk { background: #0f172a; }
    .zc-icon-yahoo { background: #5f01d1; }
    .zc-icon-gfinance { background: #ea4335; }
    .zc-icon-quandl { background: #ff6600; }
    .zc-icon-canada { background: #ff0000; }
    .zc-icon-statcan { background: #005ce6; }
    .zc-icon-australia { background: #00843d; }
    .zc-icon-ecb { background: #0066cc; }
    .zc-icon-csv { background: #6b7280; }
    .zc-icon-json { background: #059669; }
    
    details summary { cursor: pointer; padding: 12px; background: #f8fafc; border-radius: 6px; font-weight: 600; }
    details[open] summary { margin-bottom: 16px; }
    
    @media (max-width: 768px) {
        .zc-sources-grid, .zc-sources-grid-inner { grid-template-columns: 1fr; }
    }
    </style>
    
    <?php
}

/**
 * Main router function for individual source forms
 * This handles routing to the separate form files we created
 */
function zc_dmt_render_sources_forms_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
    
    // Map source parameter to actual form file (with "-form.php" suffix)
    $form_files = array(
        'australia-rba'     => 'australia-rba-form.php',
        'bank-of-canada'    => 'bank-of-canada-form.php',
        'dbnomics'          => 'dbnomics-form.php',
        'ecb'               => 'ecb-form.php',
        'eurostat'          => 'eurostat-form.php',
        'fred'              => 'fred-form.php',
        'google-finance'    => 'google-finance-form.php',
        'google-sheets'     => 'google-sheets-form.php',
        'nasdaq-data-link'  => 'nasdaq-data-link-form.php',
        'oecd'              => 'oecd-form.php',
        'statcan'           => 'statcan-form.php',
        'uk-ons'            => 'uk-ons-form.php',
        'universal-csv'     => 'universal-csv-form.php',
        'universal-json'    => 'universal-json-form.php',
        'world-bank'        => 'world-bank-form.php',
        'yahoo-finance'     => 'yahoo-finance-form.php',
    );
    
    if (empty($source) || !isset($form_files[$source])) {
        echo '<div class="wrap"><h1>Invalid Data Source</h1>';
        echo '<p>The requested data source form was not found.</p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=zc-dmt-data-sources')) . '" class="button">← Back to Data Sources</a>';
        echo '</div>';
        return;
    }
    
    // Include the specific form file
    $form_path = plugin_dir_path(__FILE__) . 'sources-forms/' . $form_files[$source];
    
    if (file_exists($form_path)) {
        include $form_path;
    } else {
        echo '<div class="wrap"><h1>Form Not Found</h1>';
        echo '<p>The form file ' . esc_html($form_files[$source]) . ' was not found.</p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=zc-dmt-data-sources')) . '" class="button">← Back to Data Sources</a>';
        echo '</div>';
    }
}
