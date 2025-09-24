<?php
/**
 * Plugin Name: Zestra Capital - Economic Insights DMT & Charts
 * Plugin URI: https://client.zestracapital.com
 * Description: Unified data management and chart rendering plugin with improved UI, API keys, REST, nonce-protected AJAX, and comprehensive data source management. FIXED for correct class names.
 * Version: 0.2.2
 * Author: Zestra Capital
 * Text Domain: zc-dmt
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('ZC_DMT_Plugin')) {
    
    final class ZC_DMT_Plugin {
        
        private static $instance = null;
        
        public static function instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        private function __construct() {
            $this->define_constants();
            $this->includes();
            $this->hooks();
        }
        
        private function define_constants() {
            if (!defined('ZC_DMT_VERSION')) define('ZC_DMT_VERSION', '0.2.2');
            if (!defined('ZC_DMT_FILE')) define('ZC_DMT_FILE', __FILE__);
            if (!defined('ZC_DMT_BASENAME')) define('ZC_DMT_BASENAME', plugin_basename(__FILE__));
            if (!defined('ZC_DMT_DIR')) define('ZC_DMT_DIR', plugin_dir_path(__FILE__));
            if (!defined('ZC_DMT_URL')) define('ZC_DMT_URL', plugin_dir_url(__FILE__));
            
            // Avoid already defined warnings if another plugin defined it first
            if (!defined('ZC_DMT_REST_NS')) define('ZC_DMT_REST_NS', 'zc-dmt/v1');
        }
        
        private function includes() {
            // Core includes - FIXED for correct class names
            require_once ZC_DMT_DIR . 'includes/class-database.php';
            require_once ZC_DMT_DIR . 'includes/class-security.php';
            require_once ZC_DMT_DIR . 'includes/class-indicators.php';
            require_once ZC_DMT_DIR . 'includes/class-rest-api.php';
            
            // All 15 data sources - FIXED for correct class names with underscores
            $data_source_files = [
                'class-google-sheets.php',      // ZC_DMT_DataSource_Google_Sheets
                'class-fred.php',               // ZC_DMT_DataSource_FRED
                'class-world-bank.php',         // ZC_DMT_DataSource_WorldBank
                'class-dbnomics.php',           // ZC_DMT_DataSource_DBnomics
                'class-oecd.php',               // ZC_DMT_DataSource_OECD
                'class-yahoo-finance.php',      // ZC_DMT_DataSource_YahooFinance
                'class-google-finance.php',     // ZC_DMT_DataSource_GoogleFinance
                'class-uk-ons.php',             // ZC_DMT_DataSource_UK_ONS
                'class-ecb.php',                // ZC_DMT_DataSource_ECB
                'class-quandl.php',             // ZC_DMT_DataSource_Quandl
                'class-bank-of-canada.php',    // ZC_DMT_DataSource_BankOfCanada
                'class-statcan.php',            // ZC_DMT_DataSource_StatCan
                'class-australia-rba.php',     // ZC_DMT_DataSource_Australia_RBA
                'class-universal-csv.php',     // ZC_DMT_DataSource_Universal_CSV
                'class-universal-json.php'     // ZC_DMT_DataSource_Universal_JSON
            ];
            
            foreach ($data_source_files as $file) {
                $filepath = ZC_DMT_DIR . 'includes/data-sources/' . $file;
                if (file_exists($filepath)) {
                    require_once $filepath;
                }
            }
            
            // Charts functionality merged into DMT
            require_once ZC_DMT_DIR . 'includes/class-shortcodes.php';
            
            // AJAX handlers (secure, nonce-based) - FIXED class names
            if (file_exists(ZC_DMT_DIR . 'includes/class-ajax.php')) {
                require_once ZC_DMT_DIR . 'includes/class-ajax.php';
            }
            
            // Enhanced AJAX for indicators - FIXED class names
            if (file_exists(ZC_DMT_DIR . 'includes/indicators-ajax.php')) {
                require_once ZC_DMT_DIR . 'includes/indicators-ajax.php';
            }
            
            // Admin interface
            if (is_admin()) {
                require_once ZC_DMT_DIR . 'admin/settings.php';
                require_once ZC_DMT_DIR . 'admin/indicators.php'; // Updated version
                require_once ZC_DMT_DIR . 'admin/data-sources.php'; // New data sources page
            }
        }
        
        private function hooks() {
            register_activation_hook(__FILE__, array($this, 'on_activate'));
            register_deactivation_hook(__FILE__, array($this, 'on_deactivate'));
            
            add_action('init', array($this, 'load_textdomain'));
            
            // Register shortcodes for merged Charts - FIXED class names
            add_action('init', function() {
                if (class_exists('ZC_DMT_Shortcodes')) {
                    ZC_DMT_Shortcodes::register();
                }
            });
            
            // Frontend assets for charts
            add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
            
            // Boot core subsystems
            add_action('plugins_loaded', array($this, 'boot_services'));
        }
        
        public function load_textdomain() {
            load_plugin_textdomain('zc-dmt', false, dirname(ZC_DMT_BASENAME) . '/languages');
        }
        
        public function on_activate() {
            // Create/upgrade DB schema - FIXED class names
            if (class_exists('ZC_DMT_Database')) {
                ZC_DMT_Database::install();
            }
            
            // Flush rewrite rules for REST routes if needed
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules();
            }
        }
        
        public function on_deactivate() {
            // Keep data by default, do not drop tables
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules();
            }
        }
        
        public function boot_services() {
            // Initialize REST API routes - FIXED class names
            if (class_exists('ZC_DMT_RestAPI')) {
                (new ZC_DMT_RestAPI())->register_routes();
            }
            
            // Register AJAX endpoints - FIXED class names
            if (class_exists('ZC_DMT_Ajax')) {
                ZC_DMT_Ajax::register();
            }
            
            // Admin menus and assets
            if (is_admin()) {
                add_action('admin_menu', array($this, 'register_admin_menus'));
                add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            }
        }
        
        public function register_admin_menus() {
            // Top-level menu
            add_menu_page(
                __('ZC DMT', 'zc-dmt'),
                __('ZC DMT', 'zc-dmt'),
                'manage_options',
                'zc-dmt-dashboard',
                array($this, 'render_dashboard_page'),
                'dashicons-chart-line',
                56
            );
            
            // Data Sources (new primary page)
            add_submenu_page(
                'zc-dmt-dashboard',
                __('Data Sources', 'zc-dmt'),
                __('Data Sources', 'zc-dmt'),
                'manage_options',
                'zc-dmt-data-sources',
                'zc_dmt_render_data_sources_page'
            );
            
            // Individual Source Forms (hidden submenu for router)
            add_submenu_page(
                null, // Hidden from menu
                __('Configure Data Source', 'zc-dmt'),
                __('Configure Source', 'zc-dmt'),
                'manage_options',
                'zc-dmt-sources-forms',
                'zc_dmt_render_sources_forms_page'
            );
            
            // Indicators (improved)
            add_submenu_page(
                'zc-dmt-dashboard',
                __('Indicators', 'zc-dmt'),
                __('Indicators', 'zc-dmt'),
                'manage_options',
                'zc-dmt-indicators',
                'zc_dmt_render_indicators_page'
            );
            
            // Settings (includes API Keys and Charts settings)
            add_submenu_page(
                'zc-dmt-dashboard',
                __('Settings', 'zc-dmt'),
                __('Settings', 'zc-dmt'),
                'manage_options',
                'zc-dmt-settings',
                'zc_dmt_render_settings_page'
            );
            
            // NEW: Charts submenu page (placeholder for Shortcode Builder)
            add_submenu_page(
                'zc-dmt-dashboard',
                __('Charts', 'zc-dmt'),
                __('Charts', 'zc-dmt'),
                'manage_options',
                'zc-dmt-charts',
                array($this, 'render_charts_page_placeholder') // Placeholder callback
            );
            
            // Remove the duplicate dashboard submenu that WordPress auto-creates
            remove_submenu_page('zc-dmt-dashboard', 'zc-dmt-dashboard');
        }
        
        public function enqueue_admin_assets($hook) {
            // Load minimal admin styles for DMT pages
            if (strpos($hook, 'zc-dmt') !== false) {
                wp_enqueue_style(
                    'zc-dmt-admin',
                    ZC_DMT_URL . 'assets/css/admin.css',
                    array(),
                    ZC_DMT_VERSION
                );
                
                wp_enqueue_script(
                    'zc-dmt-admin',
                    ZC_DMT_URL . 'assets/js/admin.js',
                    array('jquery'),
                    ZC_DMT_VERSION,
                    true
                );
                
                // Provide AJAX config used by admin tools
                $admin_config = array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('zc_dmt_chart'),
                    'indicatorsNonce' => wp_create_nonce('zc_dmt_indicators_action'),
                    'restUrl' => rest_url(ZC_DMT_REST_NS . '/'),
                    'strings' => array(
                        'confirmDelete' => __('Are you sure you want to delete this indicator? This action cannot be undone.', 'zc-dmt'),
                        'selectIndicator' => __('Please select an indicator first.', 'zc-dmt'),
                        'testSuccess' => __('✓ Data loaded successfully', 'zc-dmt'),
                        'testError' => __('✗ Failed to load data', 'zc-dmt'),
                        'copied' => __('Copied to clipboard!', 'zc-dmt'),
                        'copyFailed' => __('Failed to copy', 'zc-dmt')
                    )
                );
                
                wp_localize_script('zc-dmt-admin', 'zcDmtAdmin', $admin_config);
            }
            
            // Charts UI assets (for shortcode builder) will be enqueued in admin/charts-builder.php
            // or a dedicated function for that page.
        }
        
        public function enqueue_public_assets() {
            // Public CSS for charts
            $public_css = ZC_DMT_URL . 'assets/css/public.css';
            if (file_exists(ZC_DMT_DIR . 'assets/css/public.css')) {
                wp_enqueue_style('zc-dmt-public', $public_css, array(), ZC_DMT_VERSION);
            }
            
            // Chart loader (nonce-protected AJAX, no API key exposed)
            $loader_js = ZC_DMT_URL . 'assets/js/chart-loader.js';
            if (file_exists(ZC_DMT_DIR . 'assets/js/chart-loader.js')) {
                wp_register_script('zc-dmt-charts', $loader_js, array(), ZC_DMT_VERSION, true);
                
                $config = array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('zc_dmt_chart'),
                    'defaults' => array(
                        'library' => get_option('zc_charts_default_library', 'chartjs'),
                        'timeframe' => get_option('zc_charts_default_timeframe', '1y'),
                        'height' => get_option('zc_charts_default_height', '300px'),
                        'controls' => (bool) get_option('zc_charts_enable_controls', true),
                        'fallback' => (bool) get_option('zc_charts_enable_fallback', true),
                    )
                );
                
                wp_localize_script('zc-dmt-charts', 'zcDmtChartsConfig', $config);
                wp_enqueue_script('zc-dmt-charts');
            }

            // Frontend Charts UI assets (for shortcodes) will be handled by class-shortcodes.php
            // or a dedicated function that checks for shortcode presence.
        }
        
        public function render_dashboard_page() {
            if (!current_user_can('manage_options')) return;
            
            // Get basic statistics - FIXED class names and table names
            global $wpdb;
            $indicators_table = $wpdb->prefix . 'zc_dmt_indicators';
            $datapoints_table = $wpdb->prefix . 'zc_dmt_data_points'; // Assuming this table exists
            
            $total_indicators = $wpdb->get_var("SELECT COUNT(*) FROM {$indicators_table}");
            $active_indicators = $wpdb->get_var("SELECT COUNT(*) FROM {$indicators_table} WHERE is_active = 1");
            $total_datapoints = $wpdb->get_var("SELECT COUNT(*) FROM {$datapoints_table}"); // Will be 0 if table doesn't exist
            $recent_indicators = $wpdb->get_results("SELECT name, slug, source_type FROM {$indicators_table} ORDER BY created_at DESC LIMIT 5");
            
            ?>
            <div class="wrap zc-dmt-dashboard">
                <h1><?php echo esc_html__('ZC DMT Dashboard', 'zc-dmt'); ?></h1>
                <p><?php echo esc_html__('Economic Data Management & Charts - Unified Plugin', 'zc-dmt'); ?></p>
                
                <!-- Statistics Cards -->
                <div class="zc-stats-grid">
                    <div class="zc-stat-card">
                        <div class="zc-stat-number"><?php echo esc_html($total_indicators); ?></div>
                        <div class="zc-stat-label"><?php echo esc_html__('Total Indicators', 'zc-dmt'); ?></div>
                    </div>
                    <div class="zc-stat-card">
                        <div class="zc-stat-number"><?php echo esc_html($active_indicators); ?></div>
                        <div class="zc-stat-label"><?php echo esc_html__('Active Indicators', 'zc-dmt'); ?></div>
                    </div>
                    <div class="zc-stat-card">
                        <div class="zc-stat-number"><?php echo esc_html(number_format($total_datapoints)); ?></div>
                        <div class="zc-stat-label"><?php echo esc_html__('Data Points', 'zc-dmt'); ?></div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="zc-dashboard-grid">
                    <div class="zc-card">
                        <h2><?php echo esc_html__('Quick Actions', 'zc-dmt'); ?></h2>
                        <div class="zc-action-buttons">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="button button-primary button-large">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php echo esc_html__('Add Data Source', 'zc-dmt'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-indicators')); ?>" class="button button-secondary button-large">
                                <span class="dashicons dashicons-chart-line"></span>
                                <?php echo esc_html__('Manage Indicators', 'zc-dmt'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-settings')); ?>" class="button button-secondary button-large">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php echo esc_html__('Settings', 'zc-dmt'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-charts')); ?>" class="button button-secondary button-large">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <?php echo esc_html__('Charts UI Builder', 'zc-dmt'); ?>
                            </a>
                        </div>
                    </div>
                    
                    <div class="zc-card">
                        <h2><?php echo esc_html__('Recent Indicators', 'zc-dmt'); ?></h2>
                        <?php if (!empty($recent_indicators)): ?>
                            <ul class="zc-recent-list">
                                <?php foreach ($recent_indicators as $indicator): ?>
                                    <li>
                                        <strong><?php echo esc_html($indicator->name); ?></strong>
                                        <span class="zc-indicator-meta">
                                            <code><?php echo esc_html($indicator->slug); ?></code>
                                            <span class="zc-source-tag"><?php echo esc_html(ucwords(str_replace(['_', '-'], ' ', $indicator->source_type))); ?></span>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-indicators')); ?>">
                                    <?php echo esc_html__('View all indicators →', 'zc-dmt'); ?>
                                </a>
                            </p>
                        <?php else: ?>
                            <p><?php echo esc_html__('No indicators created yet.', 'zc-dmt'); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="button button-primary">
                                <?php echo esc_html__('Add Your First Indicator', 'zc-dmt'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Data Sources Available -->
                <div class="zc-card">
                    <h2><?php echo esc_html__('Available Data Sources', 'zc-dmt'); ?></h2>
                    <p><?php echo esc_html__('The plugin supports 15 different economic data sources:', 'zc-dmt'); ?></p>
                    <div class="zc-sources-summary">
                        <div class="zc-source-group">
                            <strong><?php echo esc_html__('Government & Central Banks:', 'zc-dmt'); ?></strong>
                            <span>FRED, World Bank, ECB, UK ONS, Bank of Canada, Statistics Canada, RBA</span>
                        </div>
                        <div class="zc-source-group">
                            <strong><?php echo esc_html__('Financial Markets:', 'zc-dmt'); ?></strong>
                            <span>Yahoo Finance, Google Finance, Nasdaq Data Link (Quandl)</span>
                        </div>
                        <div class="zc-source-group">
                            <strong><?php echo esc_html__('International Organizations:', 'zc-dmt'); ?></strong>
                            <span>OECD, DBnomics</span>
                        </div>
                        <div class="zc-source-group">
                            <strong><?php echo esc_html__('Custom Data:', 'zc-dmt'); ?></strong>
                            <span>Google Sheets, Universal CSV, Universal JSON</span>
                        </div>
                    </div>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="button">
                            <?php echo esc_html__('Browse Data Sources', 'zc-dmt'); ?>
                        </a>
                    </p>
                </div>
                
                <!-- System Information -->
                <div class="zc-card">
                    <h2><?php echo esc_html__('System Information', 'zc-dmt'); ?></h2>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><?php echo esc_html__('Plugin Version', 'zc-dmt'); ?></th>
                                <td><code><?php echo esc_html(ZC_DMT_VERSION); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__('Database Schema', 'zc-dmt'); ?></th>
                                <td><code><?php echo esc_html(get_option('zc_dmt_schema_version', 'Not installed')); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__('REST API Base', 'zc-dmt'); ?></th>
                                <td><code><?php echo esc_html(rest_url(ZC_DMT_REST_NS . '/')); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__('Data Sources Available', 'zc-dmt'); ?></th>
                                <td>
                                    <span style="color: #00a32a; font-weight: 600;">15 sources active</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <style>
            /* Dashboard specific styles */
            .zc-sources-summary {
                margin: 15px 0;
                padding: 15px;
                background: #f8fafc;
                border-radius: 4px;
            }
            
            .zc-source-group {
                margin-bottom: 8px;
                display: block;
            }
            
            .zc-source-group strong {
                color: #1d2327;
                display: inline-block;
                min-width: 180px;
            }
            
            .zc-source-group span {
                color: #646970;
                font-size: 14px;
            }
            </style>
            <?php
        }

        // NEW: Placeholder for Charts Shortcode Builder page
        public function render_charts_page_placeholder() {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'zc-dmt'));
            }

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Charts Shortcode Builder', 'zc-dmt') . '</h1>';
            echo '<p>' . esc_html__('This page will host the Shortcode Builder for generating static and dynamic chart shortcodes.', 'zc-dmt') . '</p>';
            echo '<p>' . esc_html__('Next, we will create the UI and logic for this builder here.', 'zc-dmt') . '</p>';
            echo '</div>';
        }
    }
}

// Bootstrap the plugin
function zc_dmt() {
    return ZC_DMT_Plugin::instance();
}

// Initialize
zc_dmt();
