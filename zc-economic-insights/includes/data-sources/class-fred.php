<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FRED (Federal Reserve Economic Data) API Data Source Adapter
 *
 * Fetches economic data from the St. Louis Federal Reserve FRED API.
 * - Requires API key stored in plugin settings
 * - Normalizes dates to Y-m-d and values to float
 * - Caches parsed series with transients for performance
 */
if (!class_exists('ZC_DMT_DataSource_FRED')) {
    class ZC_DMT_DataSource_FRED {

        /**
         * Get series for an indicator that uses FRED API.
         * @param object $indicator Row from zc_dmt_indicators (must have source_config with series_id)
         * @param string|null $start Y-m-d
         * @param string|null $end Y-m-d
         * @return array|WP_Error { indicator: {...}, series: [[date, value], ...] }
         */
        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $config = self::parse_config($indicator);
            if (is_wp_error($config)) {
                return $config;
            }
            $series_id = $config['series_id'];

            // Get API key from settings
            $api_key = get_option('zc_fred_api_key', '');
            if (empty($api_key)) {
                return new WP_Error('missing_api_key', __('FRED API key not configured. Please add it in Settings.', 'zc-dmt'));
            }

            // Use a 15-minute cache per series to minimize API calls
            $cache_key = 'zc_dmt_fred_' . md5($series_id . $start . $end);
            $cached = get_transient($cache_key);
            if ($cached && is_array($cached) && isset($cached['series'])) {
                $series = $cached['series'];
            } else {
                $series = self::fetch_fred_series($api_key, $series_id, $start, $end);
                if (is_wp_error($series)) {
                    return $series;
                }
                // Cache for 15 minutes
                set_transient($cache_key, array('series' => $series), 15 * MINUTE_IN_SECONDS);
            }

            // Build indicator response
            return array(
                'indicator' => array(
                    'id'          => (int) $indicator->id,
                    'name'        => (string) $indicator->name,
                    'slug'        => (string) $indicator->slug,
                    'description' => (string) $indicator->description,
                    'source_type' => (string) $indicator->source_type,
                    'is_active'   => (int) $indicator->is_active,
                ),
                'series' => $series,
            );
        }

        /**
         * Parse indicator->source_config to extract series_id.
         */
        private static function parse_config($indicator) {
            $cfg = null;
            if (!empty($indicator->source_config)) {
                $cfg = json_decode($indicator->source_config, true);
            }
            if (!is_array($cfg)) {
                return new WP_Error('invalid_config', __('Invalid FRED configuration.', 'zc-dmt'));
            }
            $series_id = '';
            if (!empty($cfg['series_id'])) {
                $series_id = trim((string) $cfg['series_id']);
            } elseif (!empty($cfg['fred_series_id'])) {
                $series_id = trim((string) $cfg['fred_series_id']);
            }
            if (empty($series_id)) {
                return new WP_Error('missing_series_id', __('Missing FRED series ID.', 'zc-dmt'));
            }
            return array('series_id' => $series_id);
        }

        /**
         * Fetch data from FRED API
         */
        private static function fetch_fred_series($api_key, $series_id, $start = null, $end = null) {
            $base_url = 'https://api.stlouisfed.org/fred/series/observations';
            $params = array(
                'series_id'   => $series_id,
                'api_key'     => $api_key,
                'file_type'   => 'json',
                'sort_order'  => 'asc',
            );

            // Add date range if provided
            if ($start) {
                $params['observation_start'] = self::normalize_date_for_fred($start);
            }
            if ($end) {
                $params['observation_end'] = self::normalize_date_for_fred($end);
            }

            $url = $base_url . '?' . http_build_query($params);

            $args = array(
                'timeout'     => 15,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array(
                    'User-Agent' => 'ZC-DMT-Plugin/1.0',
                ),
            );

            $response = wp_remote_get($url, $args);
            if (is_wp_error($response)) {
                return new WP_Error('fetch_failed', sprintf(__('Failed to fetch FRED data: %s', 'zc-dmt'), $response->get_error_message()));
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                return new WP_Error('http_error', sprintf(__('FRED API HTTP error: %d', 'zc-dmt'), $code));
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                return new WP_Error('empty_response', __('Empty FRED API response.', 'zc-dmt'));
            }

            $data = json_decode($body, true);
            if (!is_array($data) || !isset($data['observations'])) {
                return new WP_Error('invalid_json', __('Invalid FRED API response format.', 'zc-dmt'));
            }

            if (isset($data['error_message'])) {
                return new WP_Error('fred_api_error', sprintf(__('FRED API error: %s', 'zc-dmt'), $data['error_message']));
            }

            $observations = $data['observations'];
            if (!is_array($observations)) {
                return new WP_Error('no_observations', __('No observations in FRED response.', 'zc-dmt'));
            }

            // Convert to our format: [[Y-m-d, float|null], ...]
            $series = array();
            foreach ($observations as $obs) {
                if (!is_array($obs) || !isset($obs['date']) || !isset($obs['value'])) {
                    continue;
                }

                $date = self::normalize_date($obs['date']);
                if ($date === null) {
                    continue;
                }

                $value = $obs['value'];
                // FRED uses "." for missing values
                if ($value === '.' || $value === '' || $value === null) {
                    $value = null;
                } else {
                    $value = is_numeric($value) ? (float) $value : null;
                }

                $series[] = array($date, $value);
            }

            // Sort by date (should already be sorted from FRED, but ensure it)
            usort($series, function($a, $b) {
                return strcmp($a[0], $b[0]);
            });

            return $series;
        }

        /**
         * Normalize date for FRED API (expects YYYY-MM-DD format)
         */
        private static function normalize_date_for_fred($date) {
            $ts = strtotime($date);
            if ($ts === false) {
                return $date; // Return as-is if can't parse
            }
            return gmdate('Y-m-d', $ts);
        }

        /**
         * Normalize date to Y-m-d format
         */
        private static function normalize_date($date) {
            $ts = strtotime($date);
            if ($ts === false) {
                return null;
            }
            return gmdate('Y-m-d', $ts);
        }

        /**
         * Test FRED API connection with given API key and series ID
         * @param string $api_key
         * @param string $series_id
         * @return array|WP_Error { success: bool, message: string, sample_count?: int }
         */
        public static function test_connection($api_key, $series_id) {
            if (empty($api_key) || empty($series_id)) {
                return new WP_Error('missing_params', __('API key and series ID are required.', 'zc-dmt'));
            }

            // Test with last 10 observations
            $url = sprintf(
                'https://api.stlouisfed.org/fred/series/observations?series_id=%s&api_key=%s&file_type=json&limit=10&sort_order=desc',
                rawurlencode($series_id),
                rawurlencode($api_key)
            );

            $args = array(
                'timeout'   => 10,
                'sslverify' => true,
                'headers'   => array(
                    'User-Agent' => 'ZC-DMT-Plugin/1.0',
                ),
            );

            $response = wp_remote_get($url, $args);
            if (is_wp_error($response)) {
                return new WP_Error('connection_failed', sprintf(__('Connection failed: %s', 'zc-dmt'), $response->get_error_message()));
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                return new WP_Error('http_error', sprintf(__('HTTP error %d. Check your API key and series ID.', 'zc-dmt'), $code));
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!is_array($data)) {
                return new WP_Error('invalid_response', __('Invalid API response format.', 'zc-dmt'));
            }

            if (isset($data['error_message'])) {
                return new WP_Error('api_error', sprintf(__('FRED API error: %s', 'zc-dmt'), $data['error_message']));
            }

            if (!isset($data['observations']) || !is_array($data['observations'])) {
                return new WP_Error('no_data', __('No observations found for this series.', 'zc-dmt'));
            }

            $count = count($data['observations']);
            return array(
                'success' => true,
                'message' => sprintf(__('Connection successful! Found %d recent observations.', 'zc-dmt'), $count),
                'sample_count' => $count,
            );
        }
    }
}
