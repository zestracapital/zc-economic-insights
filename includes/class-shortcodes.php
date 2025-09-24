<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Shortcodes')) {

    class ZC_DMT_Shortcodes {

        public static function register() {
            add_shortcode('zc_chart_dynamic', array(__CLASS__, 'render_dynamic'));
            add_shortcode('zc_chart_static', array(__CLASS__, 'render_static'));
        }

        /**
         * Dynamic chart shortcode
         * Usage: [zc_chart_dynamic id="gdp_us" library="chartjs" timeframe="1y" height="400px" controls="true"]
         */
        public static function render_dynamic($atts) {
            $atts = shortcode_atts(array(
                'id'        => '',
                'library'   => get_option('zc_charts_default_library', 'chartjs'),
                'timeframe' => get_option('zc_charts_default_timeframe', '1y'),
                'height'    => get_option('zc_charts_default_height', '300px'),
                'controls'  => get_option('zc_charts_enable_controls', true) ? 'true' : 'false',
            ), $atts, 'zc_chart_dynamic');

            $slug = sanitize_title($atts['id']);
            if (empty($slug)) {
                return self::error_box(__('Missing required attribute: id (indicator slug).', 'zc-dmt'));
            }

            $container_id = 'zc-chart-' . uniqid();
            $library   = esc_attr($atts['library']);
            $timeframe = esc_attr($atts['timeframe']);
            $height    = esc_attr($atts['height']);
            $controls  = esc_attr($atts['controls']) === 'true' ? 'true' : 'false';

            ob_start();
            ?>
            <div class="zc-chart-wrapper">
                <?php if ($controls === 'true') : ?>
                    <div class="zc-chart-controls" data-for="<?php echo esc_attr($container_id); ?>">
                        <div class="timeframe-controls">
                            <?php
                            $ranges = array('3m','6m','1y','2y','3y','5y','10y','15y','20y','25y','all');
                            foreach ($ranges as $r) {
                                $active = $r === $timeframe ? 'active' : '';
                                echo '<button type="button" class="zc-tf-btn ' . esc_attr($active) . '" data-range="' . esc_attr($r) . '">' . esc_html(strtoupper($r)) . '</button>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="<?php echo esc_attr($container_id); ?>"
                     class="zc-chart-container"
                     style="height: <?php echo esc_attr($height); ?>;"
                     data-slug="<?php echo esc_attr($slug); ?>"
                     data-library="<?php echo esc_attr($library); ?>"
                     data-timeframe="<?php echo esc_attr($timeframe); ?>"
                     data-height="<?php echo esc_attr($height); ?>"
                     data-controls="<?php echo esc_attr($controls); ?>"
                     data-type="dynamic">
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Static chart shortcode
         * Usage: [zc_chart_static id="unemployment_rate" library="chartjs" height="250px"]
         */
        public static function render_static($atts) {
            $atts = shortcode_atts(array(
                'id'      => '',
                'library' => get_option('zc_charts_default_library', 'chartjs'),
                'height'  => get_option('zc_charts_default_height', '300px'),
            ), $atts, 'zc_chart_static');

            $slug = sanitize_title($atts['id']);
            if (empty($slug)) {
                return self::error_box(__('Missing required attribute: id (indicator slug).', 'zc-dmt'));
            }

            $container_id = 'zc-chart-' . uniqid();
            $library = esc_attr($atts['library']);
            $height  = esc_attr($atts['height']);

            ob_start();
            ?>
            <div class="zc-chart-wrapper">
                <div id="<?php echo esc_attr($container_id); ?>"
                     class="zc-chart-container"
                     style="height: <?php echo esc_attr($height); ?>;"
                     data-slug="<?php echo esc_attr($slug); ?>"
                     data-library="<?php echo esc_attr($library); ?>"
                     data-height="<?php echo esc_attr($height); ?>"
                     data-type="static">
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private static function error_box($message) {
            return '<div class="zc-chart-error"><div class="error-icon">⚠️</div><div class="error-message">' . esc_html($message) . '</div></div>';
        }
    }
}
