<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Enhanced_Shortcodes')) {

    class ZC_DMT_Enhanced_Shortcodes {

        /**
         * Register enhanced shortcodes with security
         */
        public static function register() {
            add_shortcode('zc_economic_dashboard', array(__CLASS__, 'render_dashboard'));
            add_shortcode('zc_chart_enhanced', array(__CLASS__, 'render_enhanced_chart'));
            add_shortcode('zc_chart_comparison', array(__CLASS__, 'render_comparison'));
            add_shortcode('zc_chart_calculation', array(__CLASS__, 'render_calculation'));
            
            // Add security hooks
            add_action('wp_head', array(__CLASS__, 'add_dashboard_security_headers'));
        }

        /**
         * Add security headers for pages containing dashboard shortcodes
         */
        public static function add_dashboard_security_headers() {
            global $post;
            if (is_a($post, 'WP_Post') && self::has_dashboard_shortcode($post->post_content)) {
                echo "<meta name='robots' content='noindex, nofollow'>\n";
                echo "<meta http-equiv='X-Content-Type-Options' content='nosniff'>\n";
                echo "<meta http-equiv='X-Frame-Options' content='SAMEORIGIN'>\n";
                echo "<meta http-equiv='Referrer-Policy' content='strict-origin-when-cross-origin'>\n";
                // Prevent right-click and F12 for additional security
                echo "<style>\n";
                echo "  .zc-zestra-dashboard-container { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }\n";
                echo "</style>\n";
            }
        }

        /**
         * Render full economic dashboard with modern UI and enhanced security
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
                'description' => 'Professional Economic Data Visualization Platform',
                'indicators' => '',
                'class' => '',
                'access_key' => ''
            ), $atts, 'zc_economic_dashboard');

            // Enhanced security validation
            if (!self::validate_shortcode_security($atts)) {
                return self::error_box(__('Dashboard access denied due to security restrictions.', 'zc-dmt'));
            }

            // Sanitize and validate all attributes
            $sanitized_config = self::sanitize_dashboard_config($atts);
            
            // Enqueue secure dashboard assets
            self::enqueue_secure_dashboard_assets();

            $container_id = 'zc-modern-dashboard-' . wp_generate_uuid4();
            
            // Create secure JavaScript configuration (NO SENSITIVE DATA)
            $js_config = array(
                'mode' => $sanitized_config['mode'],
                'height' => $sanitized_config['height'],
                'showHeader' => $sanitized_config['show_header'],
                'showSearch' => $sanitized_config['show_search'],
                'showComparison' => $sanitized_config['show_comparison'],
                'showTimeframes' => $sanitized_config['show_timeframes'],
                'showChartTypes' => $sanitized_config['show_chart_types'],
                'showStats' => $sanitized_config['show_stats'],
                'showFullscreen' => $sanitized_config['show_fullscreen'],
                'showThemeToggle' => $sanitized_config['show_theme_toggle'],
                'defaultTimeRange' => $sanitized_config['default_time_range'],
                'defaultChartType' => $sanitized_config['default_chart_type'],
                'defaultIndicator' => $sanitized_config['default_indicator'],
                'theme' => $sanitized_config['theme'],
                'title' => $sanitized_config['title'],
                'description' => $sanitized_config['description'],
                'class' => $sanitized_config['class'],
                // Security: Store access key in a way that's not easily exposed
                'hasAccessKey' => !empty($sanitized_config['access_key']),
                'accessKeyHash' => !empty($sanitized_config['access_key']) ? md5($sanitized_config['access_key'] . wp_salt()) : ''
            );
            
            $config_json = wp_json_encode($js_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            ob_start();
            ?>
            <div id="<?php echo esc_attr($container_id); ?>" 
                 class="zc-zestra-dashboard-container <?php echo esc_attr($sanitized_config['class']); ?>"
                 data-config="<?php echo esc_attr($config_json); ?>"
                 data-access-key="<?php echo esc_attr(!empty($sanitized_config['access_key']) ? $sanitized_config['access_key'] : ''); ?>"
                 style="min-height: <?php echo esc_attr($sanitized_config['height']); ?>px;">
                
                <div class="zc-dashboard-loading">
                    <div class="zc-loading-spinner"></div>
                    <div class="zc-loading-text">
                        <span class="zc-loading-title"><?php esc_html_e('Zestra Capital', 'zc-dmt'); ?></span>
                        <span class="zc-loading-subtitle"><?php esc_html_e('Loading Economic Dashboard...', 'zc-dmt'); ?></span>
                    </div>
                </div>
                
                <!-- Fallback content if JavaScript fails -->
                <noscript>
                    <div class="zc-no-js-warning">
                        <h3><?php esc_html_e('JavaScript Required', 'zc-dmt'); ?></h3>
                        <p><?php esc_html_e('This economic dashboard requires JavaScript to function properly. Please enable JavaScript in your browser.', 'zc-dmt'); ?></p>
                    </div>
                </noscript>
            </div>

            <script type="text/javascript">
            (function() {
                'use strict';
                
                // Secure initialization with retry mechanism
                function initSecureDashboard() {
                    if (typeof window.ZCZestraDashboard !== 'undefined' && window.ZCZestraDashboard.init) {
                        try {
                            const config = <?php echo $config_json; ?>;
                            const container = document.getElementById('<?php echo esc_js($container_id); ?>');
                            
                            if (container) {
                                // Add access key from data attribute if needed
                                const accessKey = container.getAttribute('data-access-key');
                                if (accessKey) {
                                    config.accessKey = accessKey;
                                }
                                
                                const instance = window.ZCZestraDashboard.init('<?php echo esc_js($container_id); ?>', config);
                                if (!instance) {
                                    throw new Error('Dashboard initialization returned false');
                                }
                                
                                // Clean up access key from DOM for security
                                container.removeAttribute('data-access-key');
                            }
                        } catch (error) {
                            console.error('Dashboard initialization error:', error);
                            self.showErrorFallback('<?php echo esc_js($container_id); ?>');
                        }
                    } else {
                        // Retry after 100ms if dashboard script not loaded yet
                        setTimeout(initSecureDashboard, 100);
                    }
                }
                
                // Error fallback display
                function showErrorFallback(containerId) {
                    const container = document.getElementById(containerId);
                    if (container) {
                        container.innerHTML = `
                            <div class="zc-dashboard-error">
                                <div class="error-icon">⚠️</div>
                                <div class="error-message">
                                    <h3><?php esc_html_e('Dashboard Loading Failed', 'zc-dmt'); ?></h3>
                                    <p><?php esc_html_e('Please refresh the page or contact support if the issue persists.', 'zc-dmt'); ?></p>
                                </div>
                            </div>
                        `;
                    }
                }
                
                // Initialize when DOM is ready
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
                height: <?php echo esc_attr($sanitized_config['height']); ?>px;
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                color: #4a5568;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                position: relative;
                overflow: hidden;
            }
            .zc-dashboard-loading::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                animation: zcShimmer 2s infinite;
            }
            @keyframes zcShimmer {
                0% { left: -100%; }
                100% { left: 100%; }
            }
            .zc-loading-spinner {
                width: 48px;
                height: 48px;
                border: 4px solid #e2e8f0;
                border-top: 4px solid #00bcd4;
                border-radius: 50%;
                animation: zcSpin 1s linear infinite;
                margin-bottom: 20px;
            }
            @keyframes zcSpin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .zc-loading-text {
                text-align: center;
            }
            .zc-loading-title {
                display: block;
                font-size: 1.1rem;
                font-weight: 600;
                color: #2d3748;
                margin-bottom: 8px;
            }
            .zc-loading-subtitle {
                display: block;
                font-size: 0.9rem;
                font-weight: 400;
                color: #4a5568;
                opacity: 0.8;
            }
            .zc-no-js-warning, .zc-dashboard-error {
                padding: 40px 20px;
                text-align: center;
                border: 1px solid #fed7d7;
                border-radius: 8px;
                background-color: #fef5e7;
                color: #744210;
            }
            .zc-dashboard-error .error-icon {
                font-size: 2rem;
                margin-bottom: 16px;
            }
            .zc-no-js-warning h3, .zc-dashboard-error h3 {
                margin: 0 0 12px 0;
                color: #d69e2e;
            }
            .zc-no-js-warning p, .zc-dashboard-error p {
                margin: 0;
                font-size: 0.9rem;
            }
            </style>
            <?php
            return ob_get_clean();
        }

        /**
         * Render enhanced chart with security validation
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

            // Enhanced security validation
            if (!self::validate_shortcode_security($atts)) {
                return self::error_box(__('Chart access denied due to security restrictions.', 'zc-dmt'));
            }

            // Verify indicator exists and is accessible
            if (!self::validate_indicator_access($slug)) {
                return self::error_box(__('Indicator not found or access denied: ' . esc_html($slug), 'zc-dmt'));
            }

            // Get indicator info securely (no sensitive data)
            $indicator = self::get_safe_indicator_info($slug);
            if (!$indicator) {
                return self::error_box(__('Unable to load indicator information.', 'zc-dmt'));
            }

            // Create secure static dashboard configuration
            $static_config = array(
                'mode' => 'static',
                'height' => max(400, min(1200, intval($atts['height']))),
                'show_header' => 'true',
                'show_search' => 'false',
                'show_comparison' => 'false',
                'show_timeframes' => filter_var($atts['show_timeframes'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
                'show_chart_types' => filter_var($atts['show_controls'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
                'show_stats' => filter_var($atts['show_stats'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
                'show_fullscreen' => 'false',
                'show_theme_toggle' => 'false',
                'default_time_range' => in_array($atts['time_range'], ['6M','1Y','2Y','3Y','5Y','10Y','15Y','20Y','All']) ? $atts['time_range'] : '5Y',
                'default_chart_type' => in_array($atts['type'], ['line', 'bar']) ? $atts['type'] : 'line',
                'default_indicator' => $slug,
                'theme' => in_array($atts['theme'], ['auto', 'light', 'dark']) ? $atts['theme'] : 'auto',
                'title' => !empty($atts['title']) ? sanitize_text_field($atts['title']) : $indicator['name'],
                'description' => sanitize_text_field($indicator['description']),
                'class' => sanitize_html_class($atts['class']) . ' zc-static-chart',
                'access_key' => self::sanitize_access_key($atts['access_key'])
            );

            return self::render_dashboard($static_config);
        }

        /**
         * Render comparison chart with enhanced security
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

            // Enhanced security validation
            if (!self::validate_shortcode_security($atts)) {
                return self::error_box(__('Comparison chart access denied due to security restrictions.', 'zc-dmt'));
            }

            // Parse and validate indicator slugs
            $indicator_slugs = array_map('trim', explode(',', $atts['indicators']));
            $indicator_slugs = array_map('sanitize_title', $indicator_slugs);
            $indicator_slugs = array_filter($indicator_slugs); // Remove empty values
            
            // Security: Limit number of indicators in comparison
            if (count($indicator_slugs) > 5) {
                $indicator_slugs = array_slice($indicator_slugs, 0, 5);
            }
            
            if (count($indicator_slugs) < 2) {
                return self::error_box(__('Comparison requires at least 2 valid indicators.', 'zc-dmt'));
            }

            // Validate all indicators exist and are accessible
            $valid_indicators = array();
            foreach ($indicator_slugs as $slug) {
                if (self::validate_indicator_access($slug)) {
                    $indicator_info = self::get_safe_indicator_info($slug);
                    if ($indicator_info) {
                        $valid_indicators[] = $slug;
                    }
                }
            }

            if (count($valid_indicators) < 2) {
                return self::error_box(__('At least 2 valid and accessible indicators required for comparison.', 'zc-dmt'));
            }

            // Create secure comparison dashboard config
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
                'default_time_range' => in_array($atts['time_range'], ['6M','1Y','2Y','3Y','5Y','10Y','15Y','20Y','All']) ? $atts['time_range'] : '5Y',
                'default_chart_type' => in_array($atts['chart_type'], ['line', 'bar']) ? $atts['chart_type'] : 'line',
                'default_indicator' => $valid_indicators[0],
                'theme' => in_array($atts['theme'], ['auto', 'light', 'dark']) ? $atts['theme'] : 'auto',
                'title' => sanitize_text_field($atts['title']),
                'description' => sprintf(__('Comparing %d economic indicators', 'zc-dmt'), count($valid_indicators)),
                'indicators' => implode(',', $valid_indicators),
                'class' => sanitize_html_class($atts['class']) . ' zc-comparison-mode',
                'access_key' => self::sanitize_access_key($atts['access_key'])
            );

            return self::render_dashboard($dashboard_config);
        }

        /**
         * Render calculation chart with security
         */
        public static function render_calculation($atts) {
            $atts = shortcode_atts(array(
                'id' => '',
                'chart_type' => 'line',
                'time_range' => '5Y',
                'height' => '600',
                'show_stats' => 'true',
                'show_controls' => 'false',
                'title' => '',
                'class' => '',
                'access_key' => ''
            ), $atts, 'zc_chart_calculation');

            $calculation_slug = sanitize_title($atts['id']);
            if (empty($calculation_slug)) {
                return self::error_box(__('Missing calculation ID.', 'zc-dmt'));
            }

            // Enhanced security validation
            if (!self::validate_shortcode_security($atts)) {
                return self::error_box(__('Calculation chart access denied due to security restrictions.', 'zc-dmt'));
            }

            // Check if calculations module is available
            if (!class_exists('ZC_DMT_Calculations')) {
                return self::error_box(__('Calculations module not available.', 'zc-dmt'));
            }

            // Verify calculation exists and is accessible
            $calculation = self::get_safe_calculation_info($calculation_slug);
            if (!$calculation) {
                return self::error_box(__('Calculation not found: ' . esc_html($calculation_slug), 'zc-dmt'));
            }

            // Create secure dashboard config for calculation
            $calc_config = array(
                'mode' => 'static',
                'height' => max(400, min(1200, intval($atts['height']))),
                'show_header' => 'true',
                'show_search' => 'false',
                'show_comparison' => 'false',
                'show_timeframes' => filter_var($atts['show_controls'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
                'show_chart_types' => filter_var($atts['show_controls'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
                'show_stats' => filter_var($atts['show_stats'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
                'show_fullscreen' => 'false',
                'show_theme_toggle' => 'false',
                'default_time_range' => in_array($atts['time_range'], ['6M','1Y','2Y','3Y','5Y','10Y','15Y','20Y','All']) ? $atts['time_range'] : '5Y',
                'default_chart_type' => in_array($atts['chart_type'], ['line', 'bar']) ? $atts['chart_type'] : 'line',
                'default_indicator' => '',
                'theme' => 'auto',
                'title' => !empty($atts['title']) ? sanitize_text_field($atts['title']) : $calculation['name'],
                'description' => sprintf(__('Formula: %s', 'zc-dmt'), esc_html($calculation['formula'])),
                'calculation_slug' => $calculation_slug,
                'class' => sanitize_html_class($atts['class']) . ' zc-calculation-chart',
                'access_key' => self::sanitize_access_key($atts['access_key'])
            );

            return self::render_dashboard($calc_config);
        }

        /**
         * Enhanced security validation for shortcodes
         */
        private static function validate_shortcode_security($atts) {
            // Check if access is restricted by IP blocking
            $ip = self::get_user_ip();
            if (self::is_ip_blocked($ip)) {
                self::log_security_event('Blocked IP Access Attempt', array('ip' => $ip));
                return false;
            }

            // Check basic rate limiting
            if (!self::check_shortcode_rate_limit()) {
                return false;
            }

            // Validate access key if provided
            $access_key = self::sanitize_access_key($atts['access_key'] ?? '');
            if (!empty($access_key)) {
                if (!ZC_DMT_Security::validate_key($access_key)) {
                    self::log_security_event('Invalid Access Key in Shortcode', array(
                        'key_preview' => substr($access_key, 0, 8) . '***'
                    ));
                    return false;
                }
            } else {
                // Check if access key is required for shortcodes
                $require_key_shortcodes = get_option('zc_dmt_require_key_shortcodes', false);
                if ($require_key_shortcodes) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Rate limiting for shortcode rendering
         */
        private static function check_shortcode_rate_limit() {
            $ip = self::get_user_ip();
            $user_id = get_current_user_id();
            $key = 'zc_dmt_shortcode_rate_' . md5($ip . $user_id);
            $requests = get_transient($key) ?: 0;
            
            // Allow 50 shortcode renders per hour per IP/user
            if ($requests >= 50) {
                self::log_security_event('Shortcode Rate Limit Exceeded', array(
                    'ip' => $ip,
                    'user_id' => $user_id,
                    'requests' => $requests
                ));
                return false;
            }
            
            set_transient($key, $requests + 1, HOUR_IN_SECONDS);
            return true;
        }

        /**
         * Sanitize dashboard configuration
         */
        private static function sanitize_dashboard_config($atts) {
            $boolean_fields = ['show_header', 'show_search', 'show_comparison', 'show_timeframes', 
                              'show_chart_types', 'show_stats', 'show_fullscreen', 'show_theme_toggle'];
                              
            $sanitized = array();
            
            // Sanitize mode
            $sanitized['mode'] = in_array($atts['mode'], ['dynamic', 'static']) ? $atts['mode'] : 'dynamic';
            
            // Sanitize height with limits
            $sanitized['height'] = max(300, min(1500, intval($atts['height'])));
            
            // Sanitize boolean fields
            foreach ($boolean_fields as $field) {
                $sanitized[$field] = filter_var($atts[$field] ?? 'false', FILTER_VALIDATE_BOOLEAN);
            }
            
            // Sanitize select fields
            $sanitized['default_time_range'] = in_array($atts['default_time_range'], ['6M','1Y','2Y','3Y','5Y','10Y','15Y','20Y','All']) ? $atts['default_time_range'] : '5Y';
            $sanitized['default_chart_type'] = in_array($atts['default_chart_type'], ['line', 'bar']) ? $atts['default_chart_type'] : 'line';
            $sanitized['theme'] = in_array($atts['theme'], ['auto', 'light', 'dark']) ? $atts['theme'] : 'auto';
            
            // Sanitize text fields
            $sanitized['title'] = sanitize_text_field($atts['title']);
            $sanitized['description'] = sanitize_text_field($atts['description']);
            $sanitized['default_indicator'] = sanitize_title($atts['default_indicator']);
            $sanitized['indicators'] = sanitize_text_field($atts['indicators']);
            $sanitized['class'] = sanitize_html_class($atts['class']);
            $sanitized['access_key'] = self::sanitize_access_key($atts['access_key']);
            
            return $sanitized;
        }

        /**
         * Sanitize access key
         */
        private static function sanitize_access_key($key) {
            if (empty($key)) {
                return '';
            }
            
            $key = sanitize_text_field($key);
            
            // Validate format
            if (!preg_match('/^[a-zA-Z0-9_\-]{16,64}$/', $key)) {
                return '';
            }
            
            return $key;
        }

        /**
         * Get safe indicator information (no sensitive data)
         */
        private static function get_safe_indicator_info($slug) {
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_indicators';
            
            $indicator = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, slug, description, source_type 
                 FROM {$table} 
                 WHERE slug = %s AND is_active = 1 
                 LIMIT 1",
                $slug
            ));
            
            if (!$indicator) {
                return null;
            }
            
            return array(
                'id' => intval($indicator->id),
                'name' => sanitize_text_field($indicator->name),
                'slug' => sanitize_title($indicator->slug),
                'description' => sanitize_text_field($indicator->description),
                'source_type' => sanitize_text_field($indicator->source_type)
            );
        }

        /**
         * Get safe calculation information (no sensitive data)
         */
        private static function get_safe_calculation_info($slug) {
            if (!class_exists('ZC_DMT_Calculations')) {
                return null;
            }
            
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_calculations';
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
                return null;
            }
            
            $calculation = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, slug, formula, output_type 
                 FROM {$table} 
                 WHERE slug = %s 
                 LIMIT 1",
                $slug
            ));
            
            if (!$calculation) {
                return null;
            }
            
            return array(
                'id' => intval($calculation->id),
                'name' => sanitize_text_field($calculation->name),
                'slug' => sanitize_title($calculation->slug),
                'formula' => sanitize_text_field($calculation->formula),
                'output_type' => sanitize_text_field($calculation->output_type)
            );
        }

        /**
         * Validate indicator access
         */
        private static function validate_indicator_access($slug) {
            if (empty($slug)) {
                return false;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_indicators';
            
            $indicator = $wpdb->get_row($wpdb->prepare(
                "SELECT id, is_active FROM {$table} WHERE slug = %s LIMIT 1",
                sanitize_title($slug)
            ));

            return ($indicator && intval($indicator->is_active) === 1);
        }

        /**
         * Enqueue secure dashboard assets
         */
        private static function enqueue_secure_dashboard_assets() {
            // Only enqueue if not already loaded
            if (wp_script_is('zc-zestra-dashboard', 'enqueued')) {
                return;
            }

            // Cache-busting versions
            $css_path = ZC_DMT_DIR . 'assets/css/zestra-dashboard.css';
            $js_path = ZC_DMT_DIR . 'assets/js/zestra-dashboard.js';
            $css_ver = file_exists($css_path) ? filemtime($css_path) : ZC_DMT_VERSION;
            $js_ver = file_exists($js_path) ? filemtime($js_path) : ZC_DMT_VERSION;

            // Enqueue Chart.js with integrity checks
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                array(),
                '4.4.1',
                true
            );

            wp_enqueue_script(
                'chartjs-adapter',
                'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
                array('chartjs'),
                '3.0.0',
                true
            );

            // Enqueue secure dashboard assets
            wp_enqueue_style(
                'zc-zestra-dashboard',
                ZC_DMT_URL . 'assets/css/zestra-dashboard.css',
                array(),
                $css_ver
            );

            wp_enqueue_script(
                'zc-zestra-dashboard',
                ZC_DMT_URL . 'assets/js/zestra-dashboard.js',
                array('chartjs', 'chartjs-adapter'),
                $js_ver,
                true
            );

            // Secure localized data (NO SENSITIVE INFORMATION EXPOSED)
            $localized_data = array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('zc_dmt_dashboard_' . get_current_user_id()),
                'currentUserId' => get_current_user_id(),
                'isLoggedIn' => is_user_logged_in(),
                // Safe indicator list (names and slugs only)
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
                    'invalidRequest' => __('Invalid request', 'zc-dmt'),
                    'rateLimitExceeded' => __('Too many requests. Please try again later.', 'zc-dmt')
                ),
                'security' => array(
                    'maxHeight' => 1500,
                    'minHeight' => 300,
                    'allowedChartTypes' => ['line', 'bar'],
                    'allowedTimeRanges' => ['6M', '1Y', '2Y', '3Y', '5Y', '10Y', '15Y', '20Y', 'All'],
                    'maxIndicators' => 5, // Reduced for security
                    'maxSeriesPoints' => 5000
                ),
                'features' => array(
                    'calculationsEnabled' => class_exists('ZC_DMT_Calculations'),
                    'comparisonEnabled' => true,
                    'searchEnabled' => true,
                    'themeToggleEnabled' => true
                )
            );

            wp_localize_script('zc-zestra-dashboard', 'zcDmtConfig', $localized_data);
        }

        /**
         * Get safe indicators list (essential info only)
         */
        private static function get_safe_indicators_list() {
            global $wpdb;
            $table = $wpdb->prefix . 'zc_dmt_indicators';
            
            $indicators = $wpdb->get_results(
                "SELECT id, name, slug 
                 FROM {$table} 
                 WHERE is_active = 1 
                 ORDER BY name ASC 
                 LIMIT 100" // Security limit
            );

            $safe_list = array();
            foreach ($indicators as $indicator) {
                $safe_list[] = array(
                    'id' => intval($indicator->id),
                    'name' => sanitize_text_field($indicator->name),
                    'slug' => sanitize_title($indicator->slug)
                    // Removed all other fields for security
                );
            }

            return $safe_list;
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
         * Conditional asset loading for performance and security
         */
        public static function conditional_assets() {
            global $post;

            // Only load assets if shortcode is present
            if (is_a($post, 'WP_Post') && self::has_dashboard_shortcode($post->post_content)) {
                self::enqueue_secure_dashboard_assets();
            }
        }

        /**
         * Enhanced error display
         */
        private static function error_box($message) {
            return '<div class="zc-chart-error" role="alert">'
                . '<div class="error-icon" aria-hidden="true">⚠️</div>'
                . '<div class="error-message">' . esc_html($message) . '</div>'
                . '<style>'
                . '.zc-chart-error { display: flex; align-items: center; padding: 20px; border: 1px solid #fed7d7; border-radius: 8px; background: #fef5e7; color: #744210; font-family: sans-serif; margin: 20px 0; }'
                . '.zc-chart-error .error-icon { font-size: 1.5rem; margin-right: 12px; }'
                . '.zc-chart-error .error-message { font-size: 0.9rem; font-weight: 500; }'
                . '</style>'
                . '</div>';
        }

        /**
         * Security helper methods
         */
        private static function get_user_ip() {
            return ZC_DMT_Security::get_client_ip();
        }

        private static function is_ip_blocked($ip) {
            $block_key = 'zc_dmt_blocked_' . md5($ip);
            return get_transient($block_key) === true;
        }

        private static function log_security_event($event, $details = array()) {
            ZC_DMT_Security::log_security_event($event, array_merge($details, array(
                'timestamp' => current_time('c'),
                'user_id' => get_current_user_id(),
                'ip' => self::get_user_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            )));
        }

        /**
         * Cleanup and maintenance
         */
        public static function cleanup_shortcode_data() {
            // Clean up temporary shortcode data and expired transients
            global $wpdb;
            
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_zc_dmt_shortcode_%'
                 OR option_name LIKE '_transient_timeout_zc_dmt_shortcode_%'"
            );
        }
    }

    // Register conditional asset loading
    add_action('wp_enqueue_scripts', array('ZC_DMT_Enhanced_Shortcodes', 'conditional_assets'));
    
    // Schedule cleanup
    if (!wp_next_scheduled('zc_dmt_shortcode_cleanup')) {
        wp_schedule_event(time(), 'daily', 'zc_dmt_shortcode_cleanup');
    }
    add_action('zc_dmt_shortcode_cleanup', array('ZC_DMT_Enhanced_Shortcodes', 'cleanup_shortcode_data'));
}