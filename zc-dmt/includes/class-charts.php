<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Charts')) {

    class ZC_DMT_Charts {

        /**
         * Supported chart types
         */
        const CHART_TYPES = array(
            'line' => 'Line Chart',
            'bar' => 'Bar Chart', 
            'area' => 'Area Chart',
            'scatter' => 'Scatter Plot',
            'candlestick' => 'Candlestick Chart',
            'histogram' => 'Histogram'
        );

        /**
         * Supported timeframes
         */
        const TIMEFRAMES = array(
            '3m' => '3 Months',
            '6m' => '6 Months', 
            '1y' => '1 Year',
            '2y' => '2 Years',
            '3y' => '3 Years',
            '5y' => '5 Years',
            '10y' => '10 Years',
            '15y' => '15 Years',
            '20y' => '20 Years',
            '25y' => '25 Years',
            'all' => 'All Data'
        );

        /**
         * Chart color schemes
         */
        const COLOR_SCHEMES = array(
            'default' => array(
                'primary' => '#2563eb',
                'secondary' => '#10b981',
                'accent' => '#f59e0b',
                'background' => 'rgba(37, 99, 235, 0.1)'
            ),
            'professional' => array(
                'primary' => '#1f2937',
                'secondary' => '#374151',
                'accent' => '#6b7280',
                'background' => 'rgba(31, 41, 55, 0.1)'
            ),
            'vibrant' => array(
                'primary' => '#7c3aed',
                'secondary' => '#ec4899', 
                'accent' => '#06b6d4',
                'background' => 'rgba(124, 58, 237, 0.1)'
            ),
            'nature' => array(
                'primary' => '#059669',
                'secondary' => '#0891b2',
                'accent' => '#ca8a04',
                'background' => 'rgba(5, 150, 105, 0.1)'
            )
        );

        /**
         * Prepare chart configuration for frontend rendering
         */
        public static function prepare_chart_config($slug, $options = array()) {
            $defaults = array(
                'type' => 'line',
                'library' => get_option('zc_charts_default_library', 'chartjs'),
                'timeframe' => get_option('zc_charts_default_timeframe', '1y'),
                'height' => get_option('zc_charts_default_height', '300px'),
                'theme' => 'default',
                'animate' => true,
                'responsive' => true,
                'legend' => true,
                'grid' => true,
                'crosshair' => false,
                'zoom' => false,
                'export' => false
            );

            $config = wp_parse_args($options, $defaults);

            // Get indicator data
            $indicator = ZC_DMT_Indicators::get_indicator_by_slug($slug);
            if (!$indicator) {
                return new WP_Error('not_found', __('Indicator not found', 'zc-dmt'));
            }

            // Get data series
            $data_result = ZC_DMT_Indicators::get_data_by_slug($slug);
            if (is_wp_error($data_result)) {
                return $data_result;
            }

            // Filter by timeframe if specified
            $series = self::filter_series_by_timeframe($data_result['series'], $config['timeframe']);

            $chart_config = array(
                'indicator' => $data_result['indicator'],
                'series' => $series,
                'config' => array(
                    'type' => $config['type'],
                    'library' => $config['library'],
                    'height' => $config['height'],
                    'theme' => $config['theme'],
                    'responsive' => $config['responsive'],
                    'animation' => $config['animate'],
                    'legend' => array(
                        'display' => $config['legend'],
                        'position' => 'top'
                    ),
                    'grid' => array(
                        'display' => $config['grid'],
                        'color' => 'rgba(0,0,0,0.08)'
                    ),
                    'plugins' => array(
                        'crosshair' => $config['crosshair'],
                        'zoom' => $config['zoom'],
                        'export' => $config['export']
                    ),
                    'colors' => self::COLOR_SCHEMES[$config['theme']] ?? self::COLOR_SCHEMES['default']
                )
            );

            return $chart_config;
        }

        /**
         * Generate Chart.js configuration
         */
        public static function generate_chartjs_config($chart_data) {
            $indicator = $chart_data['indicator'];
            $series = $chart_data['series'];
            $config = $chart_data['config'];
            $colors = $config['colors'];

            $labels = array();
            $data = array();

            foreach ($series as $point) {
                $labels[] = $point[0]; // date
                $data[] = $point[1];   // value
            }

            $chartjs_config = array(
                'type' => self::map_chart_type_to_chartjs($config['type']),
                'data' => array(
                    'labels' => $labels,
                    'datasets' => array(array(
                        'label' => $indicator['name'],
                        'data' => $data,
                        'borderColor' => $colors['primary'],
                        'backgroundColor' => $colors['background'],
                        'borderWidth' => 2,
                        'pointRadius' => 0,
                        'pointHoverRadius' => 4,
                        'tension' => $config['type'] === 'line' ? 0.2 : 0,
                        'fill' => $config['type'] === 'area'
                    ))
                ),
                'options' => array(
                    'responsive' => $config['responsive'],
                    'maintainAspectRatio' => false,
                    'animation' => $config['animation'],
                    'interaction' => array(
                        'mode' => 'index',
                        'intersect' => false
                    ),
                    'plugins' => array(
                        'legend' => array(
                            'display' => $config['legend']['display'],
                            'position' => $config['legend']['position'],
                            'labels' => array(
                                'color' => '#374151',
                                'font' => array(
                                    'family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                                    'size' => 14
                                )
                            )
                        ),
                        'tooltip' => array(
                            'backgroundColor' => 'rgba(0, 0, 0, 0.8)',
                            'titleColor' => 'white',
                            'bodyColor' => 'white',
                            'cornerRadius' => 6,
                            'displayColors' => false
                        )
                    ),
                    'scales' => array(
                        'x' => array(
                            'display' => true,
                            'grid' => array(
                                'display' => $config['grid']['display'],
                                'color' => $config['grid']['color']
                            ),
                            'ticks' => array(
                                'color' => '#6b7280',
                                'maxTicksLimit' => 8,
                                'font' => array(
                                    'size' => 12
                                )
                            )
                        ),
                        'y' => array(
                            'display' => true,
                            'grid' => array(
                                'display' => $config['grid']['display'],
                                'color' => $config['grid']['color']
                            ),
                            'ticks' => array(
                                'color' => '#6b7280',
                                'font' => array(
                                    'size' => 12
                                )
                            )
                        )
                    )
                )
            );

            // Add zoom plugin if enabled
            if ($config['plugins']['zoom']) {
                $chartjs_config['options']['plugins']['zoom'] = array(
                    'zoom' => array(
                        'wheel' => array(
                            'enabled' => true,
                        ),
                        'pinch' => array(
                            'enabled' => true
                        ),
                        'mode' => 'x',
                    ),
                    'pan' => array(
                        'enabled' => true,
                        'mode' => 'x',
                    )
                );
            }

            return $chartjs_config;
        }

        /**
         * Map chart types to Chart.js types
         */
        private static function map_chart_type_to_chartjs($type) {
            $mapping = array(
                'line' => 'line',
                'bar' => 'bar',
                'area' => 'line',
                'scatter' => 'scatter',
                'candlestick' => 'candlestick', // requires Chart.js financial plugin
                'histogram' => 'bar'
            );

            return $mapping[$type] ?? 'line';
        }

        /**
         * Filter series data by timeframe
         */
        public static function filter_series_by_timeframe($series, $timeframe) {
            if (!$series || !is_array($series) || empty($series) || $timeframe === 'all') {
                return $series;
            }

            $last_date = end($series)[0];
            if (!$last_date) {
                return $series;
            }

            $end_timestamp = strtotime($last_date);
            $start_timestamp = self::calculate_timeframe_start($end_timestamp, $timeframe);

            if (!$start_timestamp) {
                return $series;
            }

            $filtered = array();
            foreach ($series as $point) {
                $point_timestamp = strtotime($point[0]);
                if ($point_timestamp >= $start_timestamp) {
                    $filtered[] = $point;
                }
            }

            return $filtered;
        }

        /**
         * Calculate start timestamp for timeframe
         */
        private static function calculate_timeframe_start($end_timestamp, $timeframe) {
            switch ($timeframe) {
                case '3m':
                    return strtotime('-3 months', $end_timestamp);
                case '6m':
                    return strtotime('-6 months', $end_timestamp);
                case '1y':
                    return strtotime('-1 year', $end_timestamp);
                case '2y':
                    return strtotime('-2 years', $end_timestamp);
                case '3y':
                    return strtotime('-3 years', $end_timestamp);
                case '5y':
                    return strtotime('-5 years', $end_timestamp);
                case '10y':
                    return strtotime('-10 years', $end_timestamp);
                case '15y':
                    return strtotime('-15 years', $end_timestamp);
                case '20y':
                    return strtotime('-20 years', $end_timestamp);
                case '25y':
                    return strtotime('-25 years', $end_timestamp);
                default:
                    return null;
            }
        }

        /**
         * Generate chart export data
         */
        public static function generate_export_data($slug, $format = 'csv', $timeframe = 'all') {
            $data_result = ZC_DMT_Indicators::get_data_by_slug($slug);
            if (is_wp_error($data_result)) {
                return $data_result;
            }

            $series = self::filter_series_by_timeframe($data_result['series'], $timeframe);
            $indicator = $data_result['indicator'];

            switch ($format) {
                case 'csv':
                    return self::export_to_csv($indicator, $series);
                case 'json':
                    return self::export_to_json($indicator, $series);
                case 'xlsx':
                    return self::export_to_xlsx($indicator, $series);
                default:
                    return new WP_Error('invalid_format', __('Unsupported export format', 'zc-dmt'));
            }
        }

        /**
         * Export data to CSV format
         */
        private static function export_to_csv($indicator, $series) {
            $csv_data = "Date," . $indicator['name'] . "\n";
            
            foreach ($series as $point) {
                $csv_data .= $point[0] . "," . $point[1] . "\n";
            }

            return array(
                'content' => $csv_data,
                'filename' => sanitize_file_name($indicator['slug'] . '_' . date('Y-m-d') . '.csv'),
                'mime_type' => 'text/csv'
            );
        }

        /**
         * Export data to JSON format
         */
        private static function export_to_json($indicator, $series) {
            $json_data = array(
                'indicator' => $indicator,
                'data' => $series,
                'exported_at' => current_time('mysql'),
                'total_points' => count($series)
            );

            return array(
                'content' => wp_json_encode($json_data, JSON_PRETTY_PRINT),
                'filename' => sanitize_file_name($indicator['slug'] . '_' . date('Y-m-d') . '.json'),
                'mime_type' => 'application/json'
            );
        }

        /**
         * Generate chart thumbnail for dashboard previews
         */
        public static function generate_chart_thumbnail($slug, $width = 300, $height = 200) {
            // This would integrate with image generation service
            // For now, return placeholder URL with meaningful description
            $indicator = ZC_DMT_Indicators::get_indicator_by_slug($slug);
            if (!$indicator) {
                return '';
            }

            $description = urlencode($indicator->name . ' Economic Data Chart Visualization');
            return "https://placehold.co/{$width}x{$height}?text=" . $description;
        }

        /**
         * Get chart statistics
         */
        public static function get_chart_statistics($slug) {
            $data_result = ZC_DMT_Indicators::get_data_by_slug($slug);
            if (is_wp_error($data_result)) {
                return $data_result;
            }

            $series = $data_result['series'];
            $values = array_column($series, 1);
            $values = array_filter($values, function($v) { return $v !== null; });

            if (empty($values)) {
                return array(
                    'total_points' => 0,
                    'min' => null,
                    'max' => null,
                    'mean' => null,
                    'std_dev' => null,
                    'latest' => null,
                    'change_pct' => null
                );
            }

            $total = count($values);
            $min = min($values);
            $max = max($values);
            $mean = array_sum($values) / $total;
            
            // Calculate standard deviation
            $variance = array_sum(array_map(function($x) use ($mean) {
                return pow($x - $mean, 2);
            }, $values)) / $total;
            $std_dev = sqrt($variance);

            $latest = end($values);
            $previous = count($values) > 1 ? $values[count($values) - 2] : null;
            $change_pct = ($previous && $previous != 0) ? (($latest - $previous) / $previous) * 100 : null;

            return array(
                'total_points' => $total,
                'min' => $min,
                'max' => $max,
                'mean' => $mean,
                'std_dev' => $std_dev,
                'latest' => $latest,
                'change_pct' => $change_pct,
                'date_range' => array(
                    'start' => $series[0][0] ?? null,
                    'end' => end($series)[0] ?? null
                )
            );
        }
    }
}