<?php
if (!defined('ABSPATH')) {
    exit;
}

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
         * Render full economic dashboard with modern UI
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
                'access_key' => '' // Secure access key parameter
            ), $atts, 'zc_economic_dashboard');

            // Validate and sanitize attributes for security
            $atts['mode'] = in_array($atts['mode'], ['dynamic', 'static']) ? $atts['mode'] : 'dynamic';
            $atts['height'] = max(400, min(1200, intval($atts['height'])));
            $atts['theme'] = in_array($atts['theme'], ['auto', 'light', 'dark']) ? $atts['theme'] : 'auto';
            $atts['default_chart_type'] = in_array($atts['default_chart_type'], ['line', 'bar']) ? $atts['default_chart_type'] : 'line';
            
            // Sanitize text inputs
            $atts['title'] = sanitize_text_field($atts['title']);
            $atts['description'] = sanitize_text_field($atts['description']);
            $atts['class'] = sanitize_html_class($atts['class']);
            $atts['access_key'] = sanitize_text_field($atts['access_key']);
            
            // Enqueue modern dashboard assets
            self::enqueue_modern_dashboard_assets();

            $container_id = 'zc-modern-dashboard-' . wp_generate_uuid4();

            // Secure configuration for JavaScript
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
                'defaultIndicator' => sanitize_title($atts['default_indicator']),
                'theme' => $atts['theme'],
                'title' => $atts['title'],
                'description' => $atts['description'],
                'indicators' => $atts['indicators'],
                'class' => $atts['class'],
                // Only pass access key if provided and not empty
                'accessKey' => !empty($atts['access_key']) ? $atts['access_key'] : ''
            );
            
            $config_json = wp_json_encode($js_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            ob_start();
            ?>
            <div id="<?php echo esc_attr($container_id); ?>" 
                 class="zc-zestra-dashboard-container <?php echo esc_attr($atts['class']); ?>"
                 data-config="<?php echo esc_attr($config_json); ?>"
                 style="min-height: <?php echo esc_attr($atts['height']); ?>px;">
                <div class="zc-dashboard-loading">
                    <div class="zc-loading-spinner"></div>
                    <span><?php esc_html_e('Loading Modern Dashboard...', 'zc-dmt'); ?></span>
                </div>
            </div>

            <script type="text/javascript">
            (function() {
                'use strict';
                
                function initSecureDashboard() {
                    if (window.ZCZestraDashboard) {
                        try {
                            const config = <?php echo $config_json; ?>;
                            const instance = window.ZCZestraDashboard.init('<?php echo esc_js($container_id); ?>', config);
                            if (!instance) {
                                console.error('Failed to initialize dashboard');
                            }
                        } catch (error) {
                            console.error('Dashboard initialization error:', error);
                        }
                    } else {
                        // Retry initialization after 100ms if ZCZestraDashboard not ready
                        setTimeout(initSecureDashboard, 100);
                    }
                }
                
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initSecureDashboard);
                } else {
                    initSecureDashboard();
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
                background: linear-gradient(135deg, #f8fafc, #e2e8f0);
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                color: #4a5568;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            }
            .zc-dashboard-loading .zc-loading-spinner {
                width: 48px;
                height: 48px;
                border: 4px solid #e2e8f0;
                border-top: 4px solid #00bcd4;
                border-radius: 50%;
                animation: zcSpin 1s linear infinite;
                margin-bottom: 20px;
            }
            .zc-dashboard-loading span {
                font-size: 0.9rem;
                font-weight: 500;
                opacity: 0.8;
            }
            @keyframes zcSpin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            </style>
            <?php
            return ob_get_clean();
        }

        /**
         * Render enhanced chart with modern UI features
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
                'access_key' => ''
            ), $atts, 'zc_chart_enhanced');

            $slug = sanitize_title($atts['id']);
            if (empty($slug)) {
                return self::error_box(__('Missing required attribute: id (indicator slug).', 'zc-dmt'));
            }

            // Verify indicator exists and user has access
            if (!self::validate_indicator_access($slug)) {
                return self::error_box(__('Indicator not found or access denied: ' . esc_html($slug), 'zc-dmt'));
            }

            // Get indicator info safely
            $indicator = ZC_DMT_Indicators::get_indicator_by_slug($slug);
            if (!$indicator) {
                return self::error_box(__('Indicator not found: ' . esc_html($slug), 'zc-dmt'));
            }

            // Create secure static dashboard config
            $static_config = array(
                'mode' => 'static',
                'height' => max(400, min(1200, intval($atts['height']))),
                'show_header' => 'true',
                'show_search' => 'false',
                'show_comparison' => 'false',
                'show_timeframes' => ($atts['show_timeframes'] === 'true') ? 'true' : 'false',
                'show_chart_types' => ($atts['show_controls'] === 'true') ? 'true' : 'false',
                'show_stats' => ($atts['show_stats'] === 'true') ? 'true' : 'false',
                'show_fullscreen' => 'false',
                'show_theme_toggle' => 'false',
                'default_time_range' => sanitize_text_field($atts['time_range']),
                'default_chart_type' => in_array($atts['type'], ['line', 'bar']) ? $atts['type'] : 'line',
                'default_indicator' => $slug,
                'theme' => in_array($atts['theme'], ['auto', 'light', 'dark']) ? $atts['theme'] : 'auto',
                'title' => !empty($atts['title']) ? sanitize_text_field($atts['title']) : $indicator->name,
                'description' => sanitize_text_field($indicator->description ?? ''),
                'class' => sanitize_html_class($atts['class']) . ' zc-static-chart',
                'access_key' => sanitize_text_field($atts['access_key'])
            );

            return self::render_dashboard($static_config);
        }

        /**
         * Render comparison chart with security
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
                'access_key' => ''
            ), $atts, 'zc_chart_comparison');

            if (empty($atts['indicators'])) {
                return self::error_box(__('Missing required attribute: indicators (comma-separated slugs).', 'zc-dmt'));
            }

            $indicator_slugs = array_map('trim', explode(',', $atts['indicators']));
            $indicator_slugs = array_map('sanitize_title', $indicator_slugs);
            
            if (count($indicator_slugs) < 2) {
                return self::error_box(__('Comparison requires at least 2 indicators.', 'zc-dmt'));
            }

            // Validate all indicators exist and user has access
            $valid_indicators = array();
            foreach ($indicator_slugs as $slug) {
                if (!empty($slug) && self::validate_indicator_access($slug)) {
                    $indicator = ZC_DMT_Indicators::get_indicator_by_slug($slug);
                    if ($indicator) {
                        $valid_indicators[] = $slug;
                    }
                }
            }

            if (count($valid_indicators) < 2) {
                return self::error_box(__('At least 2 valid indicators required for comparison.', 'zc-dmt'));
            }

            // Prepare secure dashboard config for comparison
            $dashboard_config = array(
                'mode' => 'static',
                'height' => max(400, min(1200, intval($atts['height']))),
                'show_header' => 'true',
                'show_search' => 'false',
                'show_comparison' => 'true',
                'show_timeframes' => 'true',
                'show_chart_types' => 'true',
                'show_stats' => 'true',
                'show_fullscreen' => 'false',
                'show_theme_toggle' => 'false',
                'default_time_range' => sanitize_text_field($atts['time_range']),
                'default_chart_type' => in_array($atts['chart_type'], ['line', 'bar']) ? $atts['chart_type'] : 'line',
                'default_indicator' => $valid_indicators[0],
                'theme' => in_array($atts['theme'], ['auto', 'light', 'dark']) ? $atts['theme'] : 'auto',
                'title' => sanitize_text_field($atts['title']),
                'description' => sprintf(__('Comparing %d economic indicators', 'zc-dmt'), count($valid_indicators)),
                'indicators' => implode(',', $valid_indicators),
                'class' => sanitize_html_class($atts['class']) . ' zc-comparison-mode',
                'access_key' => sanitize_text_field($atts['access_key'])
            );

            return self::render_dashboard($dashboard_config);
        }

        /**
         * Enqueue modern dashboard assets with security
         */
        private static function enqueue_modern_dashboard_assets() {
            // Only enqueue if not already enqueued
            if (!wp_script_is('zc-zestra-dashboard', 'enqueued')) {

                // Cache-busting versions based on file modification time
                $css_path = ZC_DMT_DIR . 'assets/css/zestra-dashboard.css';
                $js_path  = ZC_DMT_DIR . 'assets/js/zestra-dashboard.js';
                $css_ver  = file_exists($css_path) ? filemtime($css_path) : ZC_DMT_VERSION;
                $js_ver   = file_exists($js_path) ? filemtime($js_path) : ZC_DMT_VERSION;

                // Enqueue Chart.js from CDN with integrity check
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

                // Enqueue modern dashboard styles (with cache-busting)
                wp_enqueue_style(
                    'zc-zestra-dashboard',
                    ZC_DMT_URL . 'assets/css/zestra-dashboard.css',
                    array(),
                    $css_ver
                );

                // Enqueue modern dashboard script (with cache-busting)
                wp_enqueue_script(
                    'zc-zestra-dashboard',
                    ZC_DMT_URL . 'assets/js/zestra-dashboard.js',
                    array('chartjs', 'chartjs-adapter'),
                    $js_ver,
                    true
                );

                // Secure localized data (NO SENSITIVE INFORMATION)
                $localized_data = array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('zc_dmt_dashboard_' . get_current_user_id()),
                    'restUrl' => rest_url(ZC_DMT_REST_NS . '/'),
                    'currentUserId' => get_current_user_id(),
                    // Only pass minimal, safe indicator list
                    'indicators' => self::get_safe_indicators_list(),
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
                        'accessDenied' => __('Access denied', 'zc-dmt'),
                        'invalidRequest' => __('Invalid request', 'zc-dmt')
                    ),
                    'security' => array(
                        'maxHeight' => 1200,
                        'minHeight' => 400,
                        'allowedChartTypes' => ['line', 'bar'],
                        'allowedTimeRanges' => ['6M', '1Y', '2Y', '3Y', '5Y', '10Y', '15Y', '20Y', 'All'],
                        'maxIndicators' => 10
                    )
                );

                // DO NOT include API keys or sensitive data in localized script
                wp_localize_script('zc-zestra-dashboard', 'zcDmtConfig', $localized_data);
            }
        }

        /**
         * Get safe indicators list (only essential public info)
         */
        private static function get_safe_indicators_list() {
            $indicators = ZC_DMT_Indicators::list_indicators(50); // Limit to 50
            $safe_list = array();

            foreach ($indicators as $indicator) {
                // Only include public, non-sensitive information
                if (self::validate_indicator_access($indicator->slug)) {
                    $safe_list[] = array(
                        'slug' => sanitize_title($indicator->slug),
                        'name' => sanitize_text_field($indicator->name),
                        // Remove all other potentially sensitive data
                    );
                }
            }

            return $safe_list;
        }

        /**
         * Validate indicator access
         */
        private static function validate_indicator_access($slug) {
            if (empty($slug)) {
                return false;
            }

            // Check if indicator exists
            $indicator = ZC_DMT_Indicators::get_indicator_by_slug(sanitize_title($slug));
            if (!$indicator) {
                return false;
            }

            // Check if indicator is active
            if (!$indicator->is_active) {
                return false;
            }

            // Add additional access checks here if needed
            // For example: user capabilities, subscription status, etc.

            return true;
        }

        /**
         * Secure error message box
         */
        private static function error_box($message) {
            return '<div class="zc-chart-error" role="alert">
                <div class="error-icon" aria-hidden="true">⚠️</div>
                <div class="error-message">' . esc_html($message) . '</div>
            </div>';
        }

        /**
         * Render calculation result as chart with security
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
                'access_key'   => ''
            ), $atts, 'zc_chart_calculation');

            $calculation_slug = sanitize_title($atts['id']);
            if (empty($calculation_slug)) {
                return self::error_box(__('Missing calculation ID.', 'zc-dmt'));
            }

            // Check if calculations class exists
            if (!class_exists('ZC_DMT_Calculations')) {
                return self::error_box(__('Calculations module not available.', 'zc-dmt'));
            }

            // Check if calculation exists
            $calculation = ZC_DMT_Calculations::get_calculation_by_slug($calculation_slug);
            if (!$calculation) {
                return self::error_box(__('Calculation not found: ' . esc_html($calculation_slug), 'zc-dmt'));
            }

            // Create secure dashboard config for calculation display
            $calc_config = array(
                'mode' => 'static',
                'height' => max(400, min(1200, intval($atts['height']))),
                'show_header' => 'true',
                'show_search' => 'false',
                'show_comparison' => 'false',
                'show_timeframes' => ($atts['show_controls'] === 'true') ? 'true' : 'false',
                'show_chart_types' => ($atts['show_controls'] === 'true') ? 'true' : 'false',
                'show_stats' => ($atts['show_stats'] === 'true') ? 'true' : 'false',
                'show_fullscreen' => 'false',
                'show_theme_toggle' => 'false',
                'default_time_range' => sanitize_text_field($atts['time_range']),
                'default_chart_type' => in_array($atts['chart_type'], ['line', 'bar']) ? $atts['chart_type'] : 'line',
                'default_indicator' => '',
                'theme' => 'auto',
                'title' => !empty($atts['title']) ? sanitize_text_field($atts['title']) : $calculation->name,
                'description' => sprintf(__('Formula: %s', 'zc-dmt'), esc_html($calculation->formula ?? '')),
                'calculation_slug' => $calculation_slug,
                'class' => sanitize_html_class($atts['class']) . ' zc-calculation-chart',
                'access_key' => sanitize_text_field($atts['access_key'])
            );

            return self::render_dashboard($calc_config);
        }

        /**
         * Check if shortcode is present in content
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
         * Conditional asset loading for performance
         */
        public static function conditional_assets() {
            global $post;

            // Only load dashboard assets if shortcode is present
            if (is_a($post, 'WP_Post') && self::has_dashboard_shortcode($post->post_content)) {
                self::enqueue_modern_dashboard_assets();
            }
        }

        /**
         * Security: Prevent unauthorized access
         */
        public static function security_check() {
            // Add security headers for dashboard pages
            if (self::has_dashboard_shortcode()) {
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: SAMEORIGIN');
                header('X-XSS-Protection: 1; mode=block');
            }
        }
    }

    // Register hooks
    add_action('wp_enqueue_scripts', array('ZC_DMT_Enhanced_Shortcodes', 'conditional_assets'));
    add_action('wp_head', array('ZC_DMT_Enhanced_Shortcodes', 'security_check'));
}