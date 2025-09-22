<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * World Bank Data Source Adapter
 *
 * Fetches economic data from the World Bank API.
 * - No API key required (public API)
 * - Normalizes dates to Y-m-d and values to float
 * - Caches parsed series with transients for performance
 */
if (!class_exists('ZC_DMT_DataSource_WorldBank')) {
    class ZC_DMT_DataSource_WorldBank {

        /**
         * Get series for an indicator that uses World Bank API.
         * @param object $indicator Row from zc_dmt_indicators (must have source_config with indicator_code and country_code)
         * @param string|null $start Y-m-d
         * @param string|null $end Y-m-d
         * @return array|WP_Error { indicator: {...}, series: [[date, value], ...] }
         */
        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $config = self::parse_config($indicator);
            if (is_wp_error($config)) {
                return $config;
            }
            $indicator_code = $config['indicator_code'];
            $country_code = $config['country_code'];

            // Use a 20-minute cache per series to minimize API calls
            $cache_key = 'zc_dmt_wb_' . md5($country_code . '_' . $indicator_code . $start . $end);
            $cached = get_transient($cache_key);
            if ($cached && is_array($cached) && isset($cached['series'])) {
                $series = $cached['series'];
            } else {
                $series = self::fetch_world_bank_series($country_code, $indicator_code, $start, $end);
                if (is_wp_error($series)) {
                    return $series;
                }
                // Cache for 20 minutes
                set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
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
         * Parse indicator->source_config to extract indicator_code and country_code.
         */
        private static function parse_config($indicator) {
            $cfg = null;
            if (!empty($indicator->source_config)) {
                $cfg = json_decode($indicator->source_config, true);
            }
            if (!is_array($cfg)) {
                return new WP_Error('invalid_config', __('Invalid World Bank configuration.', 'zc-dmt'));
            }
            
            $indicator_code = '';
            $country_code = '';
            
            if (!empty($cfg['indicator_code'])) {
                $indicator_code = trim((string) $cfg['indicator_code']);
            } elseif (!empty($cfg['wb_indicator_code'])) {
                $indicator_code = trim((string) $cfg['wb_indicator_code']);
            }
            
            if (!empty($cfg['country_code'])) {
                $country_code = trim((string) $cfg['country_code']);
            } elseif (!empty($cfg['wb_country_code'])) {
                $country_code = trim((string) $cfg['wb_country_code']);
            }
            
            if (empty($indicator_code)) {
                return new WP_Error('missing_indicator_code', __('Missing World Bank indicator code.', 'zc-dmt'));
            }
            if (empty($country_code)) {
                return new WP_Error('missing_country_code', __('Missing World Bank country code.', 'zc-dmt'));
            }
            
            return array(
                'indicator_code' => $indicator_code,
                'country_code' => $country_code
            );
        }

        /**
         * Fetch data from World Bank API
         */
        private static function fetch_world_bank_series($country_code, $indicator_code, $start = null, $end = null) {
            // World Bank API format: https://api.worldbank.org/v2/country/{country}/indicator/{indicator}?format=json&per_page=1000
            $base_url = 'https://api.worldbank.org/v2/country/' . rawurlencode($country_code) . '/indicator/' . rawurlencode($indicator_code);
            $params = array(
                'format' => 'json',
                'per_page' => '1000', // Max results per page
                'date' => self::build_date_range($start, $end),
            );

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
                return new WP_Error('fetch_failed', sprintf(__('Failed to fetch World Bank data: %s', 'zc-dmt'), $response->get_error_message()));
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                return new WP_Error('http_error', sprintf(__('World Bank API HTTP error: %d', 'zc-dmt'), $code));
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                return new WP_Error('empty_response', __('Empty World Bank API response.', 'zc-dmt'));
            }

            $data = json_decode($body, true);
            if (!is_array($data) || count($data) < 2) {
                return new WP_Error('invalid_json', __('Invalid World Bank API response format.', 'zc-dmt'));
            }

            // World Bank API returns [metadata, data] array
            $metadata = $data[0];
            $observations = $data[1];

            if (!is_array($observations)) {
                return new WP_Error('no_observations', __('No observations in World Bank response.', 'zc-dmt'));
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
                // World Bank uses null for missing values
                if ($value === null || $value === '' || $value === 'null') {
                    $value = null;
                } else {
                    $value = is_numeric($value) ? (float) $value : null;
                }

                $series[] = array($date, $value);
            }

            // Sort by date ascending (World Bank usually returns descending)
            usort($series, function($a, $b) {
                return strcmp($a[0], $b[0]);
            });

            return $series;
        }

        /**
         * Build date range parameter for World Bank API
         */
        private static function build_date_range($start, $end) {
            $start_year = $start ? gmdate('Y', strtotime($start)) : '1960';
            $end_year = $end ? gmdate('Y', strtotime($end)) : gmdate('Y');
            
            if ($start_year === $end_year) {
                return $start_year;
            }
            
            return $start_year . ':' . $end_year;
        }

        /**
         * Normalize date to Y-m-d format (World Bank typically returns just year)
         */
        private static function normalize_date($date) {
            // World Bank typically returns year only (e.g., "2023")
            if (preg_match('/^(\d{4})$/', $date, $matches)) {
                return $matches[1] . '-01-01'; // Use January 1st for annual data
            }
            
            $ts = strtotime($date);
            if ($ts === false) {
                return null;
            }
            return gmdate('Y-m-d', $ts);
        }

        /**
         * Test World Bank API connection with given country and indicator codes
         * @param string $country_code
         * @param string $indicator_code
         * @return array|WP_Error { success: bool, message: string, sample_count?: int }
         */
        public static function test_connection($country_code, $indicator_code) {
            if (empty($country_code) || empty($indicator_code)) {
                return new WP_Error('missing_params', __('Country code and indicator code are required.', 'zc-dmt'));
            }

            // Test with last 5 years of data
            $current_year = gmdate('Y');
            $start_year = $current_year - 4;
            
            $url = sprintf(
                'https://api.worldbank.org/v2/country/%s/indicator/%s?format=json&per_page=10&date=%s:%s',
                rawurlencode($country_code),
                rawurlencode($indicator_code),
                $start_year,
                $current_year
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
                return new WP_Error('http_error', sprintf(__('HTTP error %d. Check your country and indicator codes.', 'zc-dmt'), $code));
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!is_array($data) || count($data) < 2) {
                return new WP_Error('invalid_response', __('Invalid API response format.', 'zc-dmt'));
            }

            $observations = $data[1];
            if (!is_array($observations)) {
                return new WP_Error('no_data', __('No observations found for this country/indicator combination.', 'zc-dmt'));
            }

            $count = count($observations);
            return array(
                'success' => true,
                'message' => sprintf(__('Connection successful! Found %d recent observations.', 'zc-dmt'), $count),
                'sample_count' => $count,
            );
        }

        /**
         * Get popular World Bank indicators for reference
         */
        public static function get_popular_indicators() {
            return array(
                'NY.GDP.MKTP.CD' => 'GDP (current US$)',
                'NY.GDP.PCAP.CD' => 'GDP per capita (current US$)',
                'SL.UEM.TOTL.ZS' => 'Unemployment, total (% of total labor force)',
                'FP.CPI.TOTL.ZG' => 'Inflation, consumer prices (annual %)',
                'SP.POP.TOTL' => 'Population, total',
                'SE.ADT.LITR.ZS' => 'Literacy rate, adult total (% of people ages 15 and above)',
                'SH.DYN.MORT' => 'Mortality rate, under-5 (per 1,000 live births)',
                'EN.ATM.CO2E.PC' => 'CO2 emissions (metric tons per capita)',
                'IT.NET.USER.ZS' => 'Individuals using the Internet (% of population)',
                'BX.KLT.DINV.WD.GD.ZS' => 'Foreign direct investment, net inflows (% of GDP)',
            );
        }

        /**
         * Get popular country codes for reference
         */
        public static function get_popular_countries() {
            return array(
                'US' => 'United States',
                'CN' => 'China',
                'JP' => 'Japan',
                'DE' => 'Germany',
                'IN' => 'India',
                'UK' => 'United Kingdom',
                'FR' => 'France',
                'IT' => 'Italy',
                'BR' => 'Brazil',
                'CA' => 'Canada',
                'RU' => 'Russian Federation',
                'KR' => 'Korea, Rep.',
                'AU' => 'Australia',
                'ES' => 'Spain',
                'MX' => 'Mexico',
                'WLD' => 'World',
            );
        }
    }
}
