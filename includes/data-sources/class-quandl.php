<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nasdaq Data Link (formerly Quandl) Data Source Adapter
 *
 * Supported methods (choose ONE in source_config):
 *  - json_url: Direct JSON URL to a dataset (Data Link endpoint)
 *  - csv_url:  Direct CSV URL to a dataset
 *  - dataset:  {
 *        "database": "FRED",
 *        "dataset": "GDP",
 *        "api_key": "optional-api-key",
 *        "collapse": "daily|weekly|monthly|quarterly|annual",
 *        "start_date": "YYYY-MM-DD",
 *        "end_date": "YYYY-MM-DD"
 *     }
 *
 * Normalizes to: [ [Y-m-d, float|null], ... ] sorted asc
 * Caching: 20 minutes
 *
 * Notes:
 * - Data Link base: https://data.nasdaq.com/api/v3/datasets/{database}/{dataset}.json
 * - CSV base:       https://data.nasdaq.com/api/v3/datasets/{database}/{dataset}.csv
 * - JSON structure commonly has dataset.data as [[date, value], ...]
 */
if (!class_exists('ZC_DMT_DataSource_Quandl')) {
    class ZC_DMT_DataSource_Quandl {

        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;

            $cache_key = 'zc_dmt_quandl_';
            $series = null;

            if (isset($cfg['json_url'])) {
                $cache_key .= 'json_' . md5($cfg['json_url']);
                $series = self::maybe_from_cache($cache_key);
                if (!$series) {
                    $series = self::fetch_from_json_url($cfg['json_url']);
                    if (is_wp_error($series)) return $series;
                    self::set_cache($cache_key, $series);
                }
            } elseif (isset($cfg['csv_url'])) {
                $cache_key .= 'csv_' . md5($cfg['csv_url']);
                $series = self::maybe_from_cache($cache_key);
                if (!$series) {
                    $series = self::fetch_from_csv_url($cfg['csv_url']);
                    if (is_wp_error($series)) return $series;
                    self::set_cache($cache_key, $series);
                }
            } else {
                $database = $cfg['dataset']['database'];
                $dataset  = $cfg['dataset']['dataset'];
                $api_key  = isset($cfg['dataset']['api_key']) ? $cfg['dataset']['api_key'] : '';
                $collapse = isset($cfg['dataset']['collapse']) ? $cfg['dataset']['collapse'] : '';
                $ds_start = isset($cfg['dataset']['start_date']) ? $cfg['dataset']['start_date'] : '';
                $ds_end   = isset($cfg['dataset']['end_date']) ? $cfg['dataset']['end_date'] : '';

                $jsonUrl = self::build_json_url($database, $dataset, $api_key, $collapse, $ds_start, $ds_end);

                $cache_key .= 'ds_' . md5($jsonUrl);
                $series = self::maybe_from_cache($cache_key);
                if (!$series) {
                    $series = self::fetch_from_json_url($jsonUrl);
                    if (is_wp_error($series)) {
                        // Fallback to CSV if available
                        $csvUrl = self::build_csv_url($database, $dataset, $api_key, $collapse, $ds_start, $ds_end);
                        $series = self::fetch_from_csv_url($csvUrl);
                        if (is_wp_error($series)) return $series;
                    }
                    self::set_cache($cache_key, $series);
                }
            }

            // Date filter
            $series = self::filter_series_by_range($series, $start, $end);

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

        private static function parse_config($indicator) {
            $cfg = null;
            if (!empty($indicator->source_config)) {
                $cfg = json_decode($indicator->source_config, true);
            }
            if (!is_array($cfg)) {
                return new WP_Error('invalid_config', __('Invalid Quandl/Nasdaq Data Link configuration.', 'zc-dmt'));
            }
            $json_url = !empty($cfg['json_url']) ? esc_url_raw((string)$cfg['json_url']) : '';
            $csv_url  = !empty($cfg['csv_url'])  ? esc_url_raw((string)$cfg['csv_url'])  : '';
            $dataset  = null;
            if (!empty($cfg['dataset']) && is_array($cfg['dataset'])) {
                $db  = isset($cfg['dataset']['database']) ? sanitize_text_field($cfg['dataset']['database']) : '';
                $ds  = isset($cfg['dataset']['dataset'])  ? sanitize_text_field($cfg['dataset']['dataset'])  : '';
                if ($db !== '' && $ds !== '') {
                    $dataset = array(
                        'database'   => $db,
                        'dataset'    => $ds,
                        'api_key'    => isset($cfg['dataset']['api_key']) ? sanitize_text_field((string)$cfg['dataset']['api_key']) : '',
                        'collapse'   => isset($cfg['dataset']['collapse']) ? sanitize_text_field((string)$cfg['dataset']['collapse']) : '',
                        'start_date' => isset($cfg['dataset']['start_date']) ? sanitize_text_field((string)$cfg['dataset']['start_date']) : '',
                        'end_date'   => isset($cfg['dataset']['end_date']) ? sanitize_text_field((string)$cfg['dataset']['end_date']) : '',
                    );
                }
            }

            if ($json_url) return array('json_url' => $json_url);
            if ($csv_url)  return array('csv_url' => $csv_url);
            if ($dataset)  return array('dataset' => $dataset);

            return new WP_Error('missing_config', __('Provide a JSON URL, CSV URL, or a dataset {database, dataset, api_key?}.', 'zc-dmt'));
        }

        private static function build_json_url($database, $dataset, $api_key = '', $collapse = '', $start_date = '', $end_date = '') {
            $base = sprintf('https://data.nasdaq.com/api/v3/datasets/%s/%s.json', rawurlencode($database), rawurlencode($dataset));
            $q = array();
            if ($api_key !== '') $q['api_key'] = $api_key;
            if ($collapse !== '') $q['collapse'] = $collapse;
            if ($start_date !== '') $q['start_date'] = $start_date;
            if ($end_date !== '') $q['end_date'] = $end_date;
            if (!empty($q)) $base .= '?' . http_build_query($q);
            return $base;
        }

        private static function build_csv_url($database, $dataset, $api_key = '', $collapse = '', $start_date = '', $end_date = '') {
            $base = sprintf('https://data.nasdaq.com/api/v3/datasets/%s/%s.csv', rawurlencode($database), rawurlencode($dataset));
            $q = array();
            if ($api_key !== '') $q['api_key'] = $api_key;
            if ($collapse !== '') $q['collapse'] = $collapse;
            if ($start_date !== '') $q['start_date'] = $start_date;
            if ($end_date !== '') $q['end_date'] = $end_date;
            if (!empty($q)) $base .= '?' . http_build_query($q);
            return $base;
        }

        private static function fetch_from_json_url($url) {
            $args = array(
                'timeout' => 20,
                'headers' => array('Accept' => 'application/json', 'User-Agent' => 'ZC-DMT-Plugin/1.0'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) return new WP_Error('fetch_failed', sprintf(__('Quandl JSON fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) return new WP_Error('http_error', sprintf(__('Quandl JSON HTTP error: %d', 'zc-dmt'), $code));
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) return new WP_Error('invalid_json', __('Quandl invalid JSON response.', 'zc-dmt'));

            // dataset.data is usually [[date, value], ...]
            if (isset($json['dataset']) && isset($json['dataset']['data']) && is_array($json['dataset']['data'])) {
                $pairs = array();
                foreach ($json['dataset']['data'] as $row) {
                    if (!is_array($row) || count($row) < 2) continue;
                    $date = self::normalize_date($row[0]);
                    $val  = self::to_number($row[1]);
                    if ($date) $pairs[] = array($date, $val);
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            // Some endpoints may use "dataset_data" with "data"
            if (isset($json['dataset_data']) && isset($json['dataset_data']['data']) && is_array($json['dataset_data']['data'])) {
                $pairs = array();
                foreach ($json['dataset_data']['data'] as $row) {
                    if (!is_array($row) || count($row) < 2) continue;
                    $date = self::normalize_date($row[0]);
                    $val  = self::to_number($row[1]);
                    if ($date) $pairs[] = array($date, $val);
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            return new WP_Error('unexpected_format', __('Quandl JSON: unexpected structure.', 'zc-dmt'));
        }

        private static function fetch_from_csv_url($url) {
            $args = array(
                'timeout' => 20,
                'headers' => array('Accept' => 'text/csv', 'User-Agent' => 'ZC-DMT-Plugin/1.0'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) return new WP_Error('fetch_failed', sprintf(__('Quandl CSV fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) return new WP_Error('http_error', sprintf(__('Quandl CSV HTTP error: %d', 'zc-dmt'), $code));
            $body = wp_remote_retrieve_body($res);
            if (!is_string($body) || $body === '') return new WP_Error('empty_csv', __('Quandl CSV: empty body.', 'zc-dmt'));

            $lines = preg_split('/\r\n|\r|\n/', $body);
            if (!is_array($lines) || empty($lines)) return new WP_Error('invalid_csv', __('Quandl CSV: invalid content.', 'zc-dmt'));

            $header = null;
            $pairs = array();
            foreach ($lines as $i => $line) {
                $line = trim($line);
                if ($line === '') continue;
                $cols = str_getcsv($line);
                if ($i === 0) {
                    $header = array_map('strtolower', $cols);
                    continue;
                }
                if (!$header) continue;

                // Guess date/value columns
                $idxDate = self::find_header_index($header, array('date', 'time', 'period'));
                $idxVal  = self::find_header_index($header, array('value', 'close', 'price', 'adj close', 'adj_close', 'adjclose'));
                if ($idxDate === null) $idxDate = 0;
                if ($idxVal === null)  $idxVal = (count($cols) > 1 ? 1 : 0);

                $date = isset($cols[$idxDate]) ? $cols[$idxDate] : '';
                $val  = isset($cols[$idxVal])  ? $cols[$idxVal]  : null;

                $date = self::normalize_date($date);
                $val  = self::to_number($val);
                if ($date) $pairs[] = array($date, $val);
            }

            if (empty($pairs)) return new WP_Error('no_observations', __('Quandl CSV: no observations parsed.', 'zc-dmt'));
            usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        private static function maybe_from_cache($key) {
            $c = get_transient($key);
            return ($c && is_array($c) && isset($c['series'])) ? $c['series'] : null;
        }
        private static function set_cache($key, $series) {
            set_transient($key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
        }

        private static function find_header_index($header, $candidates) {
            foreach ($candidates as $h) {
                $pos = array_search(strtolower($h), $header);
                if ($pos !== false) return (int)$pos;
            }
            return null;
        }
        private static function normalize_date($s) {
            $s = trim((string)$s);
            if ($s === '') return null;
            $ts = strtotime($s);
            if ($ts === false) return null;
            return gmdate('Y-m-d', $ts);
        }
        private static function to_number($v) {
            if ($v === '.' || $v === '' || $v === null) return null;
            if (is_numeric($v)) return (float)$v;
            if (is_string($v)) {
                $s = trim($v);
                $s = str_replace('%', '', $s);
                if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
                    $s = str_replace(',', '', $s);
                } elseif (strpos($s, ',') !== false) {
                    $s = str_replace(',', '.', $s);
                }
                $s = preg_replace('/[^0-9\.\-\+eE]/', '', $s);
                if ($s === '' || $s === '-' || $s === '+') return null;
                return is_numeric($s) ? (float)$s : null;
            }
            return null;
        }

        private static function filter_series_by_range($series, $start, $end) {
            if (!$series || (!$start && !$end)) return $series;
            $s = $start ? gmdate('Y-m-d', strtotime($start)) : null;
            $e = $end ? gmdate('Y-m-d', strtotime($end)) : null;
            $out = array();
            foreach ($series as $row) {
                $d = $row[0];
                if ($s && strcmp($d, $s) < 0) continue;
                if ($e && strcmp($d, $e) > 0) continue;
                $out[] = $row;
            }
            return $out;
        }
    }
}
