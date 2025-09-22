<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Eurostat Data Source Adapter (JSON-stat + Direct URLs)
 *
 * Supports 3 methods:
 * 1. Dataset Code: Fetches from Eurostat API using dataset code + query
 * 2. JSON URL: Fetches from any Eurostat JSON URL
 * 3. CSV URL: Fetches from any Eurostat CSV/TSV URL
 */
if (!class_exists('ZC_DMT_DataSource_Eurostat')) {
    class ZC_DMT_DataSource_Eurostat {
        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;
            
            $method = $cfg['method'];
            $cache_key = 'zc_dmt_euro_' . md5(serialize($cfg));
            $cached = get_transient($cache_key);
            
            if ($cached && is_array($cached) && isset($cached['series'])) {
                $series = $cached['series'];
            } else {
                if ($method === 'dataset_code') {
                    $series = self::fetch_eurostat_series($cfg['dataset_code'], $cfg['query']);
                } elseif ($method === 'json_url') {
                    $series = self::fetch_from_url($cfg['url'], 'json');
                } elseif ($method === 'csv_url') {
                    $series = self::fetch_from_url($cfg['url'], 'csv');
                } else {
                    return new WP_Error('unknown_method', __('Unknown Eurostat method.', 'zc-dmt'));
                }
                
                if (is_wp_error($series)) return $series;
                set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
            }
            
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
                return new WP_Error('invalid_config', __('Invalid Eurostat configuration.', 'zc-dmt'));
            }

            // Method 1: Dataset Code + Query (existing method)
            if (isset($cfg['dataset_code'])) {
                $dataset = trim((string)$cfg['dataset_code']);
                $query   = isset($cfg['query']) ? trim((string)$cfg['query']) : '';
                if ($dataset === '') {
                    return new WP_Error('missing_dataset', __('Eurostat: dataset_code is required.', 'zc-dmt'));
                }
                return array('method' => 'dataset_code', 'dataset_code' => $dataset, 'query' => $query);
            }
            
            // Method 2: JSON URL (new method)
            if (isset($cfg['json_url'])) {
                $json_url = trim((string)$cfg['json_url']);
                if ($json_url === '') {
                    return new WP_Error('missing_json_url', __('Eurostat: json_url is required.', 'zc-dmt'));
                }
                return array('method' => 'json_url', 'url' => $json_url);
            }
            
            // Method 3: CSV URL (new method)
            if (isset($cfg['csv_url'])) {
                $csv_url = trim((string)$cfg['csv_url']);
                if ($csv_url === '') {
                    return new WP_Error('missing_csv_url', __('Eurostat: csv_url is required.', 'zc-dmt'));
                }
                return array('method' => 'csv_url', 'url' => $csv_url);
            }

            return new WP_Error('invalid_method', __('Eurostat: no valid method specified.', 'zc-dmt'));
        }
        
        private static function fetch_from_url($url, $format) {
            $args = array(
                'timeout'     => 20,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('Eurostat URL fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('Eurostat URL HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            
            if ($format === 'json') {
                return self::parse_json_data($body);
            } else {
                return self::parse_csv_data($body);
            }
        }
        
        private static function parse_json_data($body) {
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return new WP_Error('invalid_json', __('Eurostat invalid JSON response.', 'zc-dmt'));
            }
            
            // Check if it's JSON-stat format
            if (isset($json['value']) && isset($json['dimension']) && isset($json['id']) && isset($json['size'])) {
                return self::parse_jsonstat_data($json);
            }
            
            // Try to parse as simple JSON array
            return self::parse_simple_json($json);
        }
        
        private static function parse_csv_data($body) {
            $lines = explode("\n", $body);
            $pairs = array();
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                
                $parts = str_getcsv($line, "\t"); // TSV format
                if (count($parts) < 2) {
                    $parts = str_getcsv($line, ","); // CSV format
                }
                
                if (count($parts) >= 2) {
                    $date = self::normalize_period_label(trim($parts[0]));
                    $value = self::to_number(trim($parts[1]));
                    
                    if ($date && $value !== null) {
                        $pairs[] = array($date, $value);
                    }
                }
            }
            
            if (empty($pairs)) {
                return new WP_Error('no_data', __('No valid data found in CSV.', 'zc-dmt'));
            }
            
            usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }
        
        private static function parse_simple_json($json) {
            $pairs = array();
            
            if (isset($json['data']) && is_array($json['data'])) {
                foreach ($json['data'] as $item) {
                    if (isset($item['date']) && isset($item['value'])) {
                        $date = self::normalize_period_label($item['date']);
                        $value = self::to_number($item['value']);
                        if ($date && $value !== null) {
                            $pairs[] = array($date, $value);
                        }
                    }
                }
            }
            
            if (empty($pairs)) {
                return new WP_Error('no_data', __('No valid data found in JSON.', 'zc-dmt'));
            }
            
            usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }
        
        private static function parse_jsonstat_data($json) {
            $value = $json['value'];
            $dims  = $json['id'];
            $sizes = $json['size'];
            $dimMeta = $json['dimension'];
            
            // Identify time dimension index
            $timeIndex = -1;
            foreach ($dims as $i => $dimId) {
                if (strtolower($dimId) === 'time') {
                    $timeIndex = $i;
                    break;
                }
            }
            
            // If not found, try to locate a dimension that has a 'time' label/category
            if ($timeIndex === -1) {
                foreach ($dims as $i => $dimId) {
                    $lab = isset($dimMeta[$dimId]['label']) ? strtolower((string)$dimMeta[$dimId]['label']) : '';
                    if (strpos($lab, 'time') !== false) {
                        $timeIndex = $i;
                        break;
                    }
                }
            }
            
            // Fallback to last dimension if still not found
            if ($timeIndex === -1) {
                $timeIndex = count($dims) - 1;
            }
            
            // Build category labels for time dimension
            $timeDimId = $dims[$timeIndex];
            if (!isset($dimMeta[$timeDimId]['category']['index']) || !isset($dimMeta[$timeDimId]['category']['label'])) {
                return new WP_Error('no_time_category', __('Eurostat: time dimension category missing.', 'zc-dmt'));
            }
            
            $timeIndexMap = $dimMeta[$timeDimId]['category']['index']; // map: timeLabel => position
            
            // Build array mapping position -> label
            $timeLabelsByPos = array();
            foreach ($timeIndexMap as $label => $pos) {
                $timeLabelsByPos[(int)$pos] = $label;
            }
            ksort($timeLabelsByPos);
            
            // Compute linear index strides for each dimension to read the proper series
            $strides = array();
            $stride = 1;
            for ($i = count($sizes) - 1; $i >= 0; $i--) {
                $strides[$i] = $stride;
                $stride *= (int)$sizes[$i];
            }
            
            // Fix all non-time dims to 0
            $fixed = array();
            foreach ($sizes as $i => $sz) {
                $fixed[$i] = 0;
            }
            
            // Extract values along time dimension
            $pairs = array();
            foreach ($timeLabelsByPos as $tPos => $tLabel) {
                // Compute linear index
                $linear = 0;
                foreach ($sizes as $i => $sz) {
                    $idx = ($i === $timeIndex) ? $tPos : $fixed[$i];
                    $linear += $idx * $strides[$i];
                }
                
                // Value array in JSON-stat can be a dense array or dictionary
                $val = null;
                if (isset($value[$linear])) {
                    $val = $value[$linear];
                } elseif (isset($value[(string)$linear])) {
                    $val = $value[(string)$linear];
                }
                
                $date = self::normalize_period_label($tLabel);
                $num  = self::to_number($val);
                if ($date !== null) {
                    $pairs[] = array($date, $num);
                }
            }
            
            if (empty($pairs)) {
                return new WP_Error('no_observations', __('Eurostat: no observations parsed.', 'zc-dmt'));
            }
            
            usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }
        
        private static function fetch_eurostat_series($dataset_code, $query = '') {
            $base = 'https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/' . rawurlencode($dataset_code);
            $url = $base;
            if ($query !== '') {
                $url .= '?' . $query;
            }
            
            $args = array(
                'timeout'     => 20,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('Eurostat fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('Eurostat HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return new WP_Error('invalid_json', __('Eurostat invalid JSON response.', 'zc-dmt'));
            }
            
            return self::parse_jsonstat_data($json);
        }
        
        private static function normalize_period_label($label) {
            $s = trim((string)$label);
            if ($s === '') return null;
            // Year only
            if (preg_match('~^\d{4}$~', $s)) {
                return $s . '-01-01';
            }
            // Quarter (e.g., "2020-Q1")
            if (preg_match('~^(\d{4})-Q([1-4])$~', $s, $m)) {
                $year = (int)$m[1]; $q = (int)$m[2];
                $month = ($q - 1) * 3 + 1;
                return sprintf('%04d-%02d-01', $year, $month);
            }
            // Month (e.g., "2020-01")
            if (preg_match('~^(\d{4})-(\d{2})$~', $s, $m)) {
                $year = (int)$m[1]; $mon = (int)$m[2];
                if ($mon >= 1 && $mon <= 12) {
                    return sprintf('%04d-%02d-01', $year, $mon);
                }
            }
            // Fallback
            $ts = strtotime($s);
            if ($ts === false) return null;
            return gmdate('Y-m-d', $ts);
        }
        
        private static function to_number($v) {
            if ($v === null) return null;
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
        
        public static function test_connection($dataset_code, $query = '') {
            $data = self::fetch_eurostat_series($dataset_code, $query);
            if (is_wp_error($data)) return $data;
            return array('success' => true, 'message' => sprintf(__('Eurostat OK (%d points)', 'zc-dmt'), count($data)));
        }
    }
}
