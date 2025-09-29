<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced Shortcodes for Zestra Dashboard Integration
 * 
 * Updated to use secure AJAX endpoints and fixed integration issues
 */
if (!class_exists('ZC_DMT_Enhanced_Shortcodes')) {

    class ZC_DMT_Enhanced_Shortcodes {

        /**
         * Register enhanced shortcodes
         */
        public static function register() {
            add_shortcode('zc_economic_dashboard', array(__CLASS__, 'render_dashboard'));
            add_shortcode('zc_chart_enhanced', array(__CLASS__, 'render_enhanced_chart'));
            add_shortcode('zc_chart_comparison', array(__CLASS__, 'render_comparison'));
            add_shortcode('zc_chart_calculation', array(__CLASS__, 'render_calculation'));
        }

        /**
         * Render full economic dashboard
         * Usage: [zc_economic_dashboard mode="dynamic" height="800" show_search="true" access_key="your_key"]
         */
        public static function render_dashboard($atts) {
            $atts = shortcode_atts(array(
                'mode' => 'dynamic',
                'height' => '600',
                'show_header' => 'true',
                'show_search' => 'true',
                'show_comparison' => 'true',
                'show_timeframes' => 'true',
                'show_chart_types' => 'true',
                'show_stats' => 'true',
                'show_fullscreen' => 'true',
                'show_theme_toggle' => 'true',
                'default_time_range' => '5Y',
                'default_chart_type' => 'line',
                'default_indicator' => '',
                'theme' => 'auto',
                'title' => 'Zestra Capital - Economic Analytics',
                'description' => 'Professional Economic Data Visualization & Analysis Platform',
                'indicators' => '',
                'class' => '',
                'access_key' => '' // Access key for secure API calls
            ), $atts, 'zc_economic_dashboard');

            // Validate and sanitize attributes
            $atts['mode'] = in_array($atts['mode'], ['dynamic', 'static']) ? $atts['mode'] : 'dynamic';
            $atts['height'] = max(400, min(1200, intval($atts['height'])));
            $atts['theme'] = in_array($atts['theme'], ['auto', 'light', 'dark']) ? $atts['theme'] : 'auto';
            
            // Enqueue modern dashboard assets
            self::enqueue_dashboard_assets();

            $container_id = 'zc-zestra-dashboard-' . uniqid();

            // Prepare configuration for JavaScript
            $js_config = array(
                'mode' => $atts['mode'],
                'height' => intval($atts['height']),
                'showHeader' => ($atts['show_header'] === 'true'),
                'showSearch' => ($atts['show_search'] === 'true'),
                'showComparison' => ($atts['show_comparison'] === 'true'),
                'showTimeframes' => ($atts['show_timeframes'] === 'true'),
                'showChartTypes' => ($atts['show_chart_types'] === 'true'),
                'showStats' => ($atts['show_stats'] === 'true'),
                'showFullscreen' => ($atts['show_fullscreen'] === 'true'),
                'showThemeToggle' => ($atts['show_theme_toggle'] === 'true'),
                'defaultTimeRange' => $atts['default_time_range'],
                'defaultChartType' => $atts['default_chart_type'],
                'defaultIndicator' => $atts['default_indicator'],
                'theme' => $atts['theme'],
                'title' => $atts['title'],
                'description' => $atts['description'],
                'indicators' => $atts['indicators'],
                'class' => $atts['class'],
                'accessKey' => $atts['access_key'] // Pass access key securely
            );
            $config_json = wp_json_encode($js_config);

            ob_start();
            ?>
            <div id="<?php echo esc_attr($container_id); ?>" 
                 class="zc-zestra-dashboard-container <?php echo esc_attr($atts['class']); ?>"
                 data-config="<?php echo esc_attr($config_json); ?>"
                 style="min-height: <?php echo esc_attr($atts['height']); ?>px;">
                <div class="zc-dashboard-loading">
                    <div class="zc-loading-spinner"></div>
                    <span><?php _e('Loading Modern Dashboard...', 'zc-dmt'); ?></span>
                </div>
            </div>

            <script>
            (function() {
                function initZestraDashboard() {
                    if (window.ZCZestraDashboard) {
                        const config = <?php echo $config_json; ?>;
                        window.ZCZestraDashboard.init('<?php echo $container_id; ?>', config);
                    } else {
                        setTimeout(initZestraDashboard, 100);
                    }
                }
                
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initZestraDashboard);
                } else {
                    initZestraDashboard();
                }
            })();
            </script>

            <style>
            .zc-dashboard-loading {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: <?php echo esc_attr($atts['height']); ?>px;
                background: var(--zd-bg-secondary, #f8fafc);
                border: 1px solid var(--zd-border-light, #e2e8f0);
                border-radius: 12px;
                color: var(--zd-text-secondary, #4a5568);
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            .zc-dashboard-loading .zc-loading-spinner {
                width: 40px;
                height: 40px;
                border: 4px solid var(--zd-border-light, #e2e8f0);
                border-top: 4px solid var(--zd-primary, #00bcd4);
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-bottom: 16px;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            </style>
            <?php
            return ob_get_clean();
        }

        /**
         * Render enhanced chart with modern UI
         * Usage: [zc_chart_enhanced id="gdp_us" type="line" show_stats="true" access_key="your_key"]
         */
        public static function render_enhanced_chart($atts) {
            $atts = shortcode_atts(array(
                'id' => '',
                'type' => 'line',
                'height' => '600',
                'show_stats' => 'true',
                'show_controls' => 'true',
                'show_timeframes' => 'true',
                'comparison_enabled' => 'false',
                'theme' => 'auto',
                'time_range' => '5Y',
                'title' => '',
                'class' => '',
                'access_key' => '' // Access key for API calls
            ), $atts, 'zc_chart_enhanced');

            $slug = sanitize_title($atts['id']);
            if (empty($slug)) {
                return self::error_box(__('Missing required attribute: id (indicator slug).', 'zc-dmt'));
            }

            // Get indicator info for validation
            $indicator = ZC_DMT_Indicators::get_indicator_by_slug($slug);
            if (!$indicator) {
                return self::error_box(__('Indicator not found: ' . $slug, 'zc-dmt'));
            }

            if (!$indicator->is_active) {
                return self::error_box(__('Indicator is not active: ' . $slug, 'zc-dmt'));
            }

            // Create static dashboard config for single chart
            $static_config = array(
                'mode' => 'static',
                'height' => intval($atts['height']),
                'show_header' => 'true',
                'show_search' => 'false',
                'show_comparison' => 'false',
                'show_timeframes' => $atts['show_timeframes'],
                'show_chart_types' => 'true',
                'show_stats' => $atts['show_stats'],
                'show_fullscreen' => 'false',
                'show_theme_toggle' => 'false',
                'default_time_range' => $atts['time_range'],
                'default_chart_type' => $atts['type'],
                'default_indicator' => $slug,
                'theme' => $atts['theme'],
                'title' => !empty($atts['title']) ? $atts['title'] : $indicator->name,
                'description' => $indicator->description ?? '',
                'class' => $atts['class'] . ' zc-static-chart',
                'access_key' => $atts['access_key'] // Pass access key
            );

            return self::render_dashboard($static_config);
        }

        /**
         * Render comparison chart
         * Usage: [zc_chart_comparison indicators="gdp_us,unemployment_rate" height="600" access_key="your_key"]
         */
        public static function render_comparison($atts) {
            $atts = shortcode_atts(array(
                'indicators' => '',
                'height' => '600',
                'chart_type' => 'line',
                'time_range' => '5Y',
                'theme' => 'auto',
                'title' => 'Economic Indicators Comparison',
                'class' => '',
                'access_key' => '' // Access key for API calls
            ), $atts, 'zc_chart_comparison');

            if (empty($atts['indicators'])) {
                return self::error_box(__('Missing required attribute: indicators (comma-separated slugs).', 'zc-dmt'));
            }

            $indicator_slugs = array_map('trim', explode(',', $atts['indicators']));
            if (count($indicator_slugs) < 2) {
                return self::error_box(__('Comparison requires at least 2 indicators.', 'zc-dmt'));
            }

            // Validate indicators exist and are active
            $valid_indicators = array();
            foreach ($indicator_slugs as $slug) {
                $indicator = ZC_DMT_Indicators::get_indicator_by_slug(sanitize_title($slug));
                if ($indicator && $indicator->is_active) {
                    $valid_indicators[] = $slug;
                }
            }

            if (count($valid_indicators) < 2) {
                return self::error_box(__('At least 2 valid and active indicators required for comparison.', 'zc-dmt'));
            }

            // Prepare dashboard config for comparison mode
            $dashboard_config = array(
                'mode' => 'static',
                'height' => intval($atts['height']),
                'show_header' => 'true',
                'show_search' => 'false',
                'show_comparison' => 'true',
                'show_timeframes' => 'true',
                'show_chart_types' => 'true',
                'show_stats' => 'true',
                'show_fullscreen' => 'false',
                'show_theme_toggle' => 'false',
                'default_time_range' => $atts['time_range'],
                'default_chart_type' => $atts['chart_type'],
                'default_indicator' => $valid_indicators[0],
                'theme' => $atts['theme'],
                'title' => $atts['title'],
                'description' => 'Comparing ' . count($valid_indicators) . ' economic indicators',
                'indicators' => implode(',', $valid_indicators),
                'class' => $atts['class'] . ' zc-comparison-mode',
                'access_key' => $atts['access_key'] // Pass access key
            );

            return self::render_dashboard($dashboard_config);
        }

        /**
         * Render calculation result as chart
         * Usage: [zc_chart_calculation id="gdp_growth_rate" chart_type="line" height="600" access_key="your_key"]
         */
        public static function render_calculation($atts) {
            $atts = shortcode_atts(array(
                'id'           => '',
                'chart_type'   => 'line',
                'time_range'   => '5Y',
                'height'       => '600',
                'show_stats'   => 'true',
                'show_controls'=> 'false',
                'title'        => '',
                'class'        => '',
                'access_key'   => '' // Access key for API calls
            ), $atts, 'zc_chart_calculation');

            $calculation_slug = sanitize_title($atts['id']);
            if (empty($calculation_slug)) {
                return self::error_box(__('Missing calculation ID.', 'zc-dmt'));
            }

            // Check if calculations class exists and calculation is valid
            if (!class_exists('ZC_DMT_Calculations')) {
                return self::error_box(__('Calculations module not available.', 'zc-dmt'));
            }

            $calculation = ZC_DMT_Calculations::get_calculation_by_slug($calculation_slug);
            if (!$calculation) {
                return self::error_box(__('Calculation not found: ' . $calculation_slug, 'zc-dmt'));
            }

            // Create dashboard config for calculation display
            $calc_config = array(
                'mode' => 'static',
                'height' => intval($atts['height']),
                'show_header' => 'true',
                'show_search' => 'false',
                'show_comparison' => 'false',
                'show_timeframes' => $atts['show_controls'],
                'show_chart_types' => $atts['show_controls'],
                'show_stats' => $atts['show_stats'],
                'show_fullscreen' => 'false',
                'show_theme_toggle' => 'false',
                'default_time_range' => $atts['time_range'],
                'default_chart_type' => $atts['chart_type'],
                'default_indicator' => '',
                'theme' => 'auto',
                'title' => !empty($atts['title']) ? $atts['title'] : $calculation->name,
                'description' => 'Formula: ' . $calculation->formula,
                'calculation_slug' => $calculation_slug,
                'class' => $atts['class'] . ' zc-calculation-chart',
                'access_key' => $atts['access_key'] // Pass access key
            );

            return self::render_dashboard($calc_config);
        }

        /**
         * Enqueue dashboard assets with cache-busting and dependencies
         */
        private static function enqueue_dashboard_assets() {
            // Only enqueue if not already enqueued
            if (wp_script_is('zc-zestra-dashboard', 'enqueued')) {
                return;
            }

            // Cache-busting versions based on file modification time
            $css_path = ZC_DMT_DIR . 'assets/css/zestra-dashboard.css';
            $js_path  = ZC_DMT_DIR . 'assets/js/zestra-dashboard.js';
            $css_ver  = file_exists($css_path) ? filemtime($css_path) : ZC_DMT_VERSION;
            $js_ver   = file_exists($js_path) ? filemtime($js_path) : ZC_DMT_VERSION;

            // Enqueue Chart.js from CDN
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                array(),
                '4.4.1',
                true
            );

            // Enqueue Chart.js date adapter
            wp_enqueue_script(
                'chartjs-adapter',
                'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
                array('chartjs'),
                '3.0.0',
                true
            );

            // Enqueue Zestra dashboard styles
            wp_enqueue_style(
                'zc-zestra-dashboard',
                ZC_DMT_URL . 'assets/css/zestra-dashboard.css',
                array(),
                $css_ver
            );

            // Enqueue Zestra dashboard script
            wp_enqueue_script(
                'zc-zestra-dashboard',
                ZC_DMT_URL . 'assets/js/zestra-dashboard.js',
                array('chartjs', 'chartjs-adapter'),
                $js_ver,
                true
            );

            // Localize script with secure configuration
            $localized_data = array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('zc_dmt_dashboard'),
                'restUrl' => rest_url(ZC_DMT_REST_NS . '/'),
                'currentUser' => array(
                    'id' => get_current_user_id(),
                    'loggedIn' => is_user_logged_in()
                ),
                // Minimal indicator list for search autocomplete
                'indicators' => self::get_minimal_indicators_list(),
                'strings' => array(
                    'loading' => __('Loading...', 'zc-dmt'),
                    'error' => __('Error loading data', 'zc-dmt'),
                    'noData' => __('No data available', 'zc-dmt'),
                    'selectIndicator' => __('Select an indicator', 'zc-dmt'),
                    'searchPlaceholder' => __('Search economic indicators...', 'zc-dmt'),
                    'addComparison' => __('Add Comparison', 'zc-dmt'),
                    'removeComparison' => __('Remove', 'zc-dmt'),
                    'toggleTheme' => __('Toggle Theme', 'zc-dmt'),
                    'fullscreen' => __('Fullscreen', 'zc-dmt'),
                    'exitFullscreen' => __('Exit Fullscreen', 'zc-dmt'),
                    'accessDenied' => __('Access denied. Please check your permissions.', 'zc-dmt'),
                    'rateLimited' => __('Rate limit exceeded. Please try again later.', 'zc-dmt')
                ),
                'security' => array(
                    'requireAuth' => get_option('zc_dmt_require_auth', false),
                    'enableRateLimit' => get_option('zc_dmt_enable_rate_limit', true),
                    'maxRetries' => 3
                )
                // NOTE: Do NOT include API keys or sensitive data here
            );

            wp_localize_script('zc-zestra-dashboard', 'zcDmtConfig', $localized_data);
        }

        /**
         * Get minimal list of indicators for search autocomplete
         * Returns only slug and name to reduce data exposure
         */
        private static function get_minimal_indicators_list() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'zc_dmt_indicators';
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
                return array();
            }
            
            $indicators = $wpdb->get_results(
                "SELECT slug, name FROM {$table_name} 
                 WHERE is_active = 1 
                 ORDER BY name ASC 
                 LIMIT 50" // Limit to 50 for performance
            );

            $formatted = array();
            foreach ($indicators as $indicator) {
                $formatted[] = array(
                    'slug' => sanitize_text_field($indicator->slug),
                    'name' => sanitize_text_field($indicator->name)
                    // Only essential data, no IDs, source configs, etc.
                );
            }

            return $formatted;
        }

        /**
         * Error message box with consistent styling
         */
        private static function error_box($message) {
            return '<div class="zc-chart-error">
                <div class="error-icon">⚠️</div>
                <div class="error-message">' . esc_html($message) . '</div>
            </div>';
        }

        /**
         * Check if any of our shortcodes is present in content
         */
        public static function has_dashboard_shortcode($content = null) {
            if ($content === null) {
                global $post;
                $content = $post ? $post->post_content : '';
            }

            return (
                has_shortcode($content, 'zc_economic_dashboard') ||
                has_shortcode($content, 'zc_chart_enhanced') ||
                has_shortcode($content, 'zc_chart_comparison') ||
                has_shortcode($content, 'zc_chart_calculation')
            );
        }

        /**
         * Conditional asset loading - only load when shortcodes are present
         */
        public static function conditional_assets() {
            global $post;

            // Only load dashboard assets if shortcode is present
            if (is_a($post, 'WP_Post') && self::has_dashboard_shortcode($post->post_content)) {
                self::enqueue_dashboard_assets();
            }
        }

        /**
         * Get default access key from settings if available
         */
        private static function get_default_access_key() {
            return get_option('zc_dmt_default_access_key', '');
        }

        /**
         * Validate shortcode access permissions
         */
        private static function validate_shortcode_access($access_key = '') {
            // If no access key provided, try to get default
            if (empty($access_key)) {
                $access_key = self::get_default_access_key();
            }

            // For logged-in users, allow access
            if (is_user_logged_in()) {
                return true;
            }

            // For guests, require valid access key if auth is enabled
            if (get_option('zc_dmt_require_auth', false)) {
                if (empty($access_key)) {
                    return false;
                }
                
                // Validate key
                $valid_keys = get_option('zc_dmt_access_keys', array());
                foreach ($valid_keys as $key_data) {
                    if (isset($key_data['key']) && 
                        isset($key_data['active']) && 
                        $key_data['active'] && 
                        hash_equals($key_data['key'], $access_key)) {
                        return true;
                    }
                }
                return false;
            }

            return true; // Allow access if auth not required
        }
    }

    // Register conditional asset loading
    add_action('wp_enqueue_scripts', array('ZC_DMT_Enhanced_Shortcodes', 'conditional_assets'));
}