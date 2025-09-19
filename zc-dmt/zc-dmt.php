<?php
/**
 * Plugin Name: Zestra Capital - Economic Insights (DMT + Charts)
 * Plugin URI: https://client.zestracapital.com
 * Description: Unified data management and chart rendering plugin with API keys, REST, nonce-protected AJAX (no public data via direct links), and fallback stubs for Google Drive backups.
 * Version: 0.1.0
 * Author: Zestra Capital
 * Text Domain: zc-dmt
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

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
            if (!defined('ZC_DMT_VERSION'))  define('ZC_DMT_VERSION', '0.1.0');
            if (!defined('ZC_DMT_FILE'))     define('ZC_DMT_FILE', __FILE__);
            if (!defined('ZC_DMT_BASENAME')) define('ZC_DMT_BASENAME', plugin_basename(__FILE__));
            if (!defined('ZC_DMT_DIR'))      define('ZC_DMT_DIR', plugin_dir_path(__FILE__));
            if (!defined('ZC_DMT_URL'))      define('ZC_DMT_URL', plugin_dir_url(__FILE__));
            // Avoid "already defined" warnings if another plugin defined it first (e.g., Charts).
            if (!defined('ZC_DMT_REST_NS'))  define('ZC_DMT_REST_NS', 'zc-dmt/v1');
        }

        private function includes() {
            // Includes (ensure files exist as we scaffold further)
            // Core
            require_once ZC_DMT_DIR . 'includes/class-database.php';
            require_once ZC_DMT_DIR . 'includes/class-security.php';
            require_once ZC_DMT_DIR . 'includes/class-indicators.php';
            require_once ZC_DMT_DIR . 'includes/class-rest-api.php';
            // Data sources
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-google-sheets.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-google-sheets.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-fred.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-fred.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-world-bank.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-world-bank.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-dbnomics.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-dbnomics.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-eurostat.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-eurostat.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-oecd.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-oecd.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-uk-ons.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-uk-ons.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-yahoo-finance.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-yahoo-finance.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-google-finance.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-google-finance.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-quandl.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-quandl.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-bank-of-canada.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-bank-of-canada.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-statcan.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-statcan.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-australia-rba.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-australia-rba.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-ecb.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-ecb.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-universal-csv.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-universal-csv.php';
            }
            if (file_exists(ZC_DMT_DIR . 'includes/data-sources/class-universal-json.php')) {
                require_once ZC_DMT_DIR . 'includes/data-sources/class-universal-json.php';
            }
            // Charts (merged into DMT)
            require_once ZC_DMT_DIR . 'includes/class-shortcodes.php';
            // AJAX handlers (secure nonce-based)
            if (file_exists(ZC_DMT_DIR . 'includes/class-ajax.php')) {
                require_once ZC_DMT_DIR . 'includes/class-ajax.php';
            }
 
            // Admin
            if (is_admin()) {
                require_once ZC_DMT_DIR . 'admin/settings.php';
                require_once ZC_DMT_DIR . 'admin/indicators.php';
            }
        }

        private function hooks() {
            register_activation_hook(__FILE__, array($this, 'on_activate'));
            register_deactivation_hook(__FILE__, array($this, 'on_deactivate'));

            add_action('init', array($this, 'load_textdomain'));
            // Register shortcodes for merged Charts
            add_action('init', function () {
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
            load_plugin_textdomain('zc-dmt', false, dirname(ZC_DMT_BASENAME) . '/languages/');
        }

        public function on_activate() {
            // Create/upgrade DB schema
            if (class_exists('ZC_DMT_Database')) {
                ZC_DMT_Database::install();
            }
            // Flush rewrite rules for REST routes if needed
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules();
            }
        }

        public function on_deactivate() {
            // Keep data by default; do not drop tables.
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules();
            }
        }

        public function boot_services() {
            // Initialize REST API routes
            if (class_exists('ZC_DMT_Rest_API')) {
                (new ZC_DMT_Rest_API())->register_routes();
            }
            // Register AJAX endpoints
            if (class_exists('ZC_DMT_Ajax')) {
                ZC_DMT_Ajax::register();
            }
            // Admin menus
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

            // Indicators
            add_submenu_page(
                'zc-dmt-dashboard',
                __('Indicators', 'zc-dmt'),
                __('Indicators', 'zc-dmt'),
                'manage_options',
                'zc-dmt-indicators',
                'zc_dmt_render_indicators_page'
            );

            // Settings (includes API Keys)
            add_submenu_page(
                'zc-dmt-dashboard',
                __('Settings', 'zc-dmt'),
                __('Settings', 'zc-dmt'),
                'manage_options',
                'zc-dmt-settings',
                'zc_dmt_render_settings_page'
            );
        }

        public function enqueue_admin_assets($hook) {
            // Load minimal admin styles for DMT pages
            if (strpos($hook, 'zc-dmt') !== false) {
                wp_enqueue_style('zc-dmt-admin', ZC_DMT_URL . 'assets/css/admin.css', array(), ZC_DMT_VERSION);
                wp_enqueue_script('zc-dmt-admin', ZC_DMT_URL . 'assets/js/admin.js', array('jquery'), ZC_DMT_VERSION, true);
                // Provide AJAX config (used by Shortcode Builder and other admin tools)
                $admin_config = array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce('zc_dmt_chart'),
                );
                wp_localize_script('zc-dmt-admin', 'zcDmtAdmin', $admin_config);
            }
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
                    'nonce'   => wp_create_nonce('zc_dmt_chart'),
                    'defaults' => array(
                        'library'   => get_option('zc_charts_default_library', 'chartjs'),
                        'timeframe' => get_option('zc_charts_default_timeframe', '1y'),
                        'height'    => get_option('zc_charts_default_height', '300px'),
                        'controls'  => (bool) get_option('zc_charts_enable_controls', true),
                        'fallback'  => (bool) get_option('zc_charts_enable_fallback', true),
                    ),
                );
                wp_localize_script('zc-dmt-charts', 'zcDmtChartsConfig', $config);
                wp_enqueue_script('zc-dmt-charts');
            }
        }

        public function render_dashboard_page() {
            // Simple dashboard placeholder
            if (!current_user_can('manage_options')) {
                return;
            }
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('ZC DMT Dashboard', 'zc-dmt') . '</h1>';
            echo '<p>' . esc_html__('Welcome. Use the submenu to manage Indicators and Settings (API Keys).', 'zc-dmt') . '</p>';
            echo '<ul style="list-style:disc;padding-left:20px;">';
            echo '<li>' . esc_html__('Go to Indicators to create a dataset (slug, name) and add data points.', 'zc-dmt') . '</li>';
            echo '<li>' . esc_html__('Go to Settings to generate an API Key for the Charts plugin.', 'zc-dmt') . '</li>';
            echo '</ul>';
            echo '</div>';
        }
    }

    // Bootstrap plugin
    function zc_dmt() {
        return ZC_DMT_Plugin::instance();
    }
    zc_dmt();
}
