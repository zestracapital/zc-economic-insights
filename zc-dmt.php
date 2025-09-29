<?php
/**
 * Plugin Name: Zestra Capital - Economic Insights DMT & Charts (Secure)
 * Plugin URI: https://client.zestracapital.com
 * Description: Secure unified data management and chart rendering plugin with enhanced security, secure dashboard UI, and comprehensive data protection.
 * Version: 0.3.0
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
            if (!defined('ZC_DMT_VERSION')) define('ZC_DMT_VERSION', '0.3.0');
            if (!defined('ZC_DMT_FILE')) define('ZC_DMT_FILE', __FILE__);
            if (!defined('ZC_DMT_BASENAME')) define('ZC_DMT_BASENAME', plugin_basename(__FILE__));
            if (!defined('ZC_DMT_DIR')) define('ZC_DMT_DIR', plugin_dir_path(__FILE__));
            if (!defined('ZC_DMT_URL')) define('ZC_DMT_URL', plugin_dir_url(__FILE__));
            
            // REST API namespace
            if (!defined('ZC_DMT_REST_NS')) define('ZC_DMT_REST_NS', 'zc-dmt/v1');
        }
        
        private function includes() {
            // Core security components (load first)
            require_once ZC_DMT_DIR . 'includes/class-database.php';
            require_once ZC_DMT_DIR . 'includes/class-security.php';
            
            // Secure AJAX handler (NEW - replaces old handlers)
            require_once ZC_DMT_DIR . 'includes/class-secure-ajax-handler.php';
            
            // Core functionality
            require_once ZC_DMT_DIR . 'includes/class-indicators.php';
            require_once ZC_DMT_DIR . 'includes/class-rest-api.php';
            
            // Calculations system
            require_once ZC_DMT_DIR . 'includes/class-calculations.php';
            if (file_exists(ZC_DMT_DIR . 'includes/calculations/class-formula-parser.php')) {
                require_once ZC_DMT_DIR . 'includes/calculations/class-formula-parser.php';
            }
            
            // Data sources (all 15 sources)
            $data_source_files = [
                'class-google-sheets.php',
                'class-fred.php',
                'class-world-bank.php',
                'class-dbnomics.php',
                'class-oecd.php',
                'class-yahoo-finance.php',
                'class-google-finance.php',
                'class-uk-ons.php',
                'class-ecb.php',
                'class-quandl.php',
                'class-bank-of-canada.php',
                'class-statcan.php',
                'class-australia-rba.php',
                'class-universal-csv.php',
                'class-universal-json.php'
            ];
            
            foreach ($data_source_files as $file) {
                $filepath = ZC_DMT_DIR . 'includes/data-sources/' . $file;
                if (file_exists($filepath)) {
                    require_once $filepath;
                }
            }
            
            // Legacy charts functionality (fallback)
            if (file_exists(ZC_DMT_DIR . 'includes/class-shortcodes.php')) {
                require_once ZC_DMT_DIR . 'includes/class-shortcodes.php';
            }
            
            // NEW: Enhanced secure shortcodes (primary)
            require_once ZC_DMT_DIR . 'includes/class-enhanced-shortcodes.php';
            
            // Legacy dashboard AJAX (keeping for backward compatibility)
            if (file_exists(ZC_DMT_DIR . 'includes/class-dashboard-ajax.php')) {
                require_once ZC_DMT_DIR . 'includes/class-dashboard-ajax.php';
            }
            
            // Legacy AJAX handlers (keeping for compatibility)
            if (file_exists(ZC_DMT_DIR . 'includes/class-ajax.php')) {
                require_once ZC_DMT_DIR . 'includes/class-ajax.php';
            }
            
            if (file_exists(ZC_DMT_DIR . 'includes/indicators-ajax.php')) {
                require_once ZC_DMT_DIR . 'includes/indicators-ajax.php';
            }
            
            // Admin interface
            if (is_admin()) {
                require_once ZC_DMT_DIR . 'admin/settings.php';
                require_once ZC_DMT_DIR . 'admin/indicators.php';
                
                if (file_exists(ZC_DMT_DIR . 'admin/data-sources.php')) {
                    require_once ZC_DMT_DIR . 'admin/data-sources.php';
                }
                
                if (file_exists(ZC_DMT_DIR . 'admin/charts-builder-simple.php')) {
                    require_once ZC_DMT_DIR . 'admin/charts-builder-simple.php';
                }
                
                if (file_exists(ZC_DMT_DIR . 'admin/calculations-simple.php')) {
                    require_once ZC_DMT_DIR . 'admin/calculations-simple.php';
                }
            }
        }
        
        private function hooks() {
            register_activation_hook(__FILE__, array($this, 'on_activate'));
            register_deactivation_hook(__FILE__, array($this, 'on_deactivate'));
            
            add_action('init', array($this, 'load_textdomain'));
            
            // Register secure shortcodes (NEW - highest priority)
            add_action('init', function() {
                if (class_exists('ZC_DMT_Enhanced_Shortcodes')) {
                    ZC_DMT_Enhanced_Shortcodes::register();
                }
            }, 5);
            
            // Register legacy shortcodes (fallback)
            add_action('init', function() {
                if (class_exists('ZC_DMT_Shortcodes')) {
                    ZC_DMT_Shortcodes::register();
                }
            }, 10);
            
            // Frontend assets for secure charts
            add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
            
            // Boot core subsystems
            add_action('plugins_loaded', array($this, 'boot_services'));
            
            // Security cleanup
            add_action('wp_logout', array($this, 'cleanup_user_data'));
            add_action('wp_login', array($this, 'cleanup_expired_data'));
        }
        
        public function load_textdomain() {
            load_plugin_textdomain('zc-dmt', false, dirname(ZC_DMT_BASENAME) . '/languages');
        }
        
        public function on_activate() {
            // Create/upgrade DB schema
            if (class_exists('ZC_DMT_Database')) {
                ZC_DMT_Database::install();
            }
            
            // Set secure defaults
            $this->set_secure_defaults();
            
            // Flush rewrite rules
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules();
            }
        }
        
        public function on_deactivate() {
            // Clean up transients and temporary data
            $this->cleanup_plugin_data();
            
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules();
            }
        }
        
        /**
         * Set secure defaults on activation
         */
        private function set_secure_defaults() {
            // Security settings
            if (get_option('zc_dmt_security_mode') === false) {
                update_option('zc_dmt_security_mode', 'strict');
            }
            
            if (get_option('zc_dmt_require_key_shortcodes') === false) {
                update_option('zc_dmt_require_key_shortcodes', false); // Start with false for easy setup
            }
            
            if (get_option('zc_dmt_enable_comparison') === false) {
                update_option('zc_dmt_enable_comparison', false); // Disabled by default for security
            }
            
            if (get_option('zc_dmt_enable_fullscreen') === false) {
                update_option('zc_dmt_enable_fullscreen', false); // Disabled by default for security
            }
            
            // Dashboard preferences
            if (get_option('zc_dmt_default_theme') === false) {
                update_option('zc_dmt_default_theme', 'light');
            }
            
            if (get_option('zc_dmt_default_chart_height') === false) {
                update_option('zc_dmt_default_chart_height', 600);
            }
        }
        
        /**
         * Cleanup plugin data on deactivation
         */
        private function cleanup_plugin_data() {
            global $wpdb;
            
            // Clean up transients
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_zc_dmt_%' 
                 OR option_name LIKE '_transient_timeout_zc_dmt_%'"
            );
        }
        
        /**
         * Cleanup user-specific data on logout
         */
        public function cleanup_user_data() {
            $user_id = get_current_user_id();
            if ($user_id) {
                // Clean user-specific transients
                delete_transient('zc_dmt_user_cache_' . $user_id);
            }
        }
        
        /**
         * Cleanup expired data on login
         */
        public function cleanup_expired_data() {
            // Schedule cleanup if not already scheduled
            if (!wp_next_scheduled('zc_dmt_cleanup_expired')) {
                wp_schedule_event(time(), 'hourly', 'zc_dmt_cleanup_expired');
            }
        }
        
        public function boot_services() {
            // Initialize secure AJAX handler first (NEW)
            if (class_exists('ZC_DMT_Secure_Ajax_Handler')) {
                ZC_DMT_Secure_Ajax_Handler::init();
            }
            
            // Initialize REST API routes
            if (class_exists('ZC_DMT_Rest_API')) {
                (new ZC_DMT_Rest_API())->register_routes();
            }
            
            // Register legacy AJAX endpoints (for backward compatibility)
            if (class_exists('ZC_DMT_Ajax')) {
                ZC_DMT_Ajax::register();
            }
            
            // Register enhanced shortcodes
            if (class_exists('ZC_DMT_Enhanced_Shortcodes')) {
                ZC_DMT_Enhanced_Shortcodes::register();
            }
            
            // Register legacy dashboard AJAX endpoints (for compatibility)
            if (class_exists('ZC_DMT_Dashboard_Ajax')) {
                ZC_DMT_Dashboard_Ajax::register();
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
            
            // Data Sources
            if (function_exists('zc_dmt_render_data_sources_page')) {
                add_submenu_page(
                    'zc-dmt-dashboard',
                    __('Data Sources', 'zc-dmt'),
                    __('Data Sources', 'zc-dmt'),
                    'manage_options',
                    'zc-dmt-data-sources',
                    'zc_dmt_render_data_sources_page'
                );
            }
            
            // Individual Source Forms (hidden)
            if (function_exists('zc_dmt_render_sources_forms_page')) {
                add_submenu_page(
                    null,
                    __('Configure Data Source', 'zc-dmt'),
                    __('Configure Source', 'zc-dmt'),
                    'manage_options',
                    'zc-dmt-sources-forms',
                    'zc_dmt_render_sources_forms_page'
                );
            }
            
            // Indicators
            if (function_exists('zc_dmt_render_indicators_page')) {
                add_submenu_page(
                    'zc-dmt-dashboard',
                    __('Indicators', 'zc-dmt'),
                    __('Indicators', 'zc-dmt'),
                    'manage_options',
                    'zc-dmt-indicators',
                    'zc_dmt_render_indicators_page'
                );
            }
            
            // Settings
            if (function_exists('zc_dmt_render_settings_page')) {
                add_submenu_page(
                    'zc-dmt-dashboard',
                    __('Settings', 'zc-dmt'),
                    __('Settings', 'zc-dmt'),
                    'manage_options',
                    'zc-dmt-settings',
                    'zc_dmt_render_settings_page'
                );
            }
            
            // Charts Builder
            if (function_exists('zc_dmt_render_charts_builder_page')) {
                add_submenu_page(
                    'zc-dmt-dashboard',
                    __('Charts Builder', 'zc-dmt'),
                    __('Charts Builder', 'zc-dmt'),
                    'manage_options',
                    'zc-dmt-charts',
                    'zc_dmt_render_charts_builder_page'
                );
            }
            
            // Calculations
            if (function_exists('zc_dmt_render_calculations_simple_page')) {
                add_submenu_page(
                    'zc-dmt-dashboard',
                    __('Calculations', 'zc-dmt'),
                    __('Calculations', 'zc-dmt'),
                    'manage_options',
                    'zc-dmt-calculations',
                    'zc_dmt_render_calculations_simple_page'
                );
            }
            
            // Remove duplicate dashboard submenu
            remove_submenu_page('zc-dmt-dashboard', 'zc-dmt-dashboard');
        }
        
        public function enqueue_admin_assets($hook) {
            // Load admin assets for DMT pages
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
                
                // Secure admin configuration
                $admin_config = array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('zc_dmt_admin_' . get_current_user_id()),
                    'indicatorsNonce' => wp_create_nonce('zc_dmt_indicators_action'),
                    'restUrl' => rest_url(ZC_DMT_REST_NS . '/'),
                    'version' => ZC_DMT_VERSION,
                    'strings' => array(
                        'confirmDelete' => __('Are you sure you want to delete this item? This action cannot be undone.', 'zc-dmt'),
                        'selectIndicator' => __('Please select an indicator first.', 'zc-dmt'),
                        'testSuccess' => __('✓ Data loaded successfully', 'zc-dmt'),
                        'testError' => __('✗ Failed to load data', 'zc-dmt'),
                        'copied' => __('Copied to clipboard!', 'zc-dmt'),
                        'copyFailed' => __('Failed to copy', 'zc-dmt'),
                        'securityError' => __('Security validation failed', 'zc-dmt'),
                        'rateLimitError' => __('Too many requests. Please wait.', 'zc-dmt')
                    ),
                    'security' => array(
                        'mode' => get_option('zc_dmt_security_mode', 'strict'),
                        'requireKeys' => get_option('zc_dmt_require_key_shortcodes', false)
                    )
                );
                
                wp_localize_script('zc-dmt-admin', 'zcDmtAdmin', $admin_config);
            }
        }
        
        public function enqueue_public_assets() {
            // Public CSS for secure charts
            $public_css_path = ZC_DMT_DIR . 'assets/css/public.css';
            if (file_exists($public_css_path)) {
                wp_enqueue_style(
                    'zc-dmt-public', 
                    ZC_DMT_URL . 'assets/css/public.css', 
                    array(), 
                    filemtime($public_css_path)
                );
            }
            
            // Legacy chart loader (for backward compatibility)
            $loader_js_path = ZC_DMT_DIR . 'assets/js/chart-loader.js';
            if (file_exists($loader_js_path)) {
                wp_register_script(
                    'zc-dmt-charts', 
                    ZC_DMT_URL . 'assets/js/chart-loader.js', 
                    array(), 
                    filemtime($loader_js_path), 
                    true
                );
                
                $config = array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('zc_dmt_chart'),
                    'version' => ZC_DMT_VERSION,
                    'defaults' => array(
                        'library' => get_option('zc_charts_default_library', 'chartjs'),
                        'timeframe' => get_option('zc_charts_default_timeframe', '1y'),
                        'height' => get_option('zc_charts_default_height', '300px'),
                        'controls' => (bool) get_option('zc_charts_enable_controls', true),
                        'fallback' => (bool) get_option('zc_charts_enable_fallback', true),
                    )
                );
                
                wp_localize_script('zc-dmt-charts', 'zcDmtChartsConfig', $config);
            }
        }
        
        public function render_dashboard_page() {
            if (!current_user_can('manage_options')) return;
            
            // Get statistics
            global $wpdb;
            $indicators_table = $wpdb->prefix . 'zc_dmt_indicators';
            $datapoints_table = $wpdb->prefix . 'zc_dmt_data_points';
            
            $total_indicators = $wpdb->get_var("SELECT COUNT(*) FROM {$indicators_table}") ?: 0;
            $active_indicators = $wpdb->get_var("SELECT COUNT(*) FROM {$indicators_table} WHERE is_active = 1") ?: 0;
            $total_datapoints = $wpdb->get_var("SELECT COUNT(*) FROM {$datapoints_table}") ?: 0;
            $recent_indicators = $wpdb->get_results("SELECT name, slug, source_type FROM {$indicators_table} ORDER BY created_at DESC LIMIT 5") ?: array();
            
            // Get security statistics
            $security_stats = array();
            if (class_exists('ZC_DMT_Secure_Ajax_Handler')) {
                $security_stats = ZC_DMT_Secure_Ajax_Handler::get_security_stats();
            }
            
            ?>
            <div class="wrap zc-dmt-dashboard">
                <h1><?php echo esc_html__('ZC DMT Dashboard', 'zc-dmt'); ?></h1>
                <p><?php echo esc_html__('Secure Economic Data Management & Charts Platform', 'zc-dmt'); ?></p>
                
                <!-- Security Status -->
                <div class="notice notice-info">
                    <p>
                        <strong><?php echo esc_html__('Security Status:', 'zc-dmt'); ?></strong>
                        <span style="color: #00a32a; font-weight: 600;">
                            <?php echo esc_html__('Secure Dashboard Active', 'zc-dmt'); ?>
                        </span>
                        - <?php echo esc_html__('Enhanced protection against data exposure and unauthorized access', 'zc-dmt'); ?>
                    </p>
                </div>
                
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
                    <?php if (!empty($security_stats)): ?>
                    <div class="zc-stat-card">
                        <div class="zc-stat-number"><?php echo esc_html($security_stats['total_events'] ?? 0); ?></div>
                        <div class="zc-stat-label"><?php echo esc_html__('Security Events', 'zc-dmt'); ?></div>
                    </div>
                    <?php endif; ?>
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
                            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-charts')); ?>" class="button button-secondary button-large">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <?php echo esc_html__('Secure Charts Builder', 'zc-dmt'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-settings')); ?>" class="button button-secondary button-large">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php echo esc_html__('Security Settings', 'zc-dmt'); ?>
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
                
                <!-- Security Features -->
                <div class="zc-card">
                    <h2><?php echo esc_html__('Security Features', 'zc-dmt'); ?></h2>
                    <div class="zc-security-features">
                        <div class="zc-feature">
                            <span class="dashicons dashicons-shield-alt" style="color: #00a32a;"></span>
                            <strong><?php echo esc_html__('Secure Dashboard:', 'zc-dmt'); ?></strong>
                            <span><?php echo esc_html__('Data is not exposed in browser developer tools', 'zc-dmt'); ?></span>
                        </div>
                        <div class="zc-feature">
                            <span class="dashicons dashicons-lock" style="color: #00a32a;"></span>
                            <strong><?php echo esc_html__('AJAX Protection:', 'zc-dmt'); ?></strong>
                            <span><?php echo esc_html__('Rate limiting, nonce validation, and signature verification', 'zc-dmt'); ?></span>
                        </div>
                        <div class="zc-feature">
                            <span class="dashicons dashicons-privacy" style="color: #00a32a;"></span>
                            <strong><?php echo esc_html__('Data Sanitization:', 'zc-dmt'); ?></strong>
                            <span><?php echo esc_html__('All data is sanitized and validated before display', 'zc-dmt'); ?></span>
                        </div>
                        <div class="zc-feature">
                            <span class="dashicons dashicons-admin-users" style="color: #00a32a;"></span>
                            <strong><?php echo esc_html__('Access Control:', 'zc-dmt'); ?></strong>
                            <span><?php echo esc_html__('Optional access key validation for shortcodes', 'zc-dmt'); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="zc-card">
                    <h2><?php echo esc_html__('System Information', 'zc-dmt'); ?></h2>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th><?php echo esc_html__('Plugin Version', 'zc-dmt'); ?></th>
                                <td><code><?php echo esc_html(ZC_DMT_VERSION); ?></code> <span style="color: #00a32a;">(Secure)</span></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__('Security Mode', 'zc-dmt'); ?></th>
                                <td><strong><?php echo esc_html(ucfirst(get_option('zc_dmt_security_mode', 'strict'))); ?></strong></td>
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
                                <th><?php echo esc_html__('Data Sources', 'zc-dmt'); ?></th>
                                <td><span style="color: #00a32a; font-weight: 600;">15 sources available</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <style>
            .zc-security-features {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 16px;
                margin: 16px 0;
            }
            
            .zc-feature {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 12px;
                background: #f8fafc;
                border-radius: 4px;
                border-left: 3px solid #00a32a;
            }
            
            .zc-feature .dashicons {
                flex-shrink: 0;
            }
            
            .zc-feature strong {
                color: #1d2327;
                margin-right: 4px;
            }
            
            .zc-feature span:last-child {
                color: #646970;
                font-size: 14px;
            }
            </style>
            <?php
        }
    }
}

// Bootstrap the plugin
function zc_dmt() {
    return ZC_DMT_Plugin::instance();
}

// Initialize
zc_dmt();