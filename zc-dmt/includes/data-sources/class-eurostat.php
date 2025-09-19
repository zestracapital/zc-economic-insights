<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Eurostat Data Source Adapter (JSON-stat)
 *
 * Fetches time series from Eurostat "dissemination" API.
 * - Open API (no key)
 * - Input:
 *    dataset_code (e.g., "nama_10_gdp")
 *    query (optional raw query string, e.g., "geo=EU27_2020&na_item=B1GQ&unit=CP_MEUR")
 * - Endpoint:
 *    https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/{dataset_code}?{query}
 * - Response: JSON-stat 2.0
 * - Strategy:
 *    - Parse JSON-stat "value" and "dimension"
 *    - Identify "time" dimension (often id "time")
 *    - Use index 0 for non-time dimensions unless specific query filters them
 *    - Normalize time labels (YYYY, YYYY-Qn, YYYY-MM) to Y-m-d
 * - Caching: 20 minutes
 */
if (!class_exists('ZC_DMT_DataSource_Eurostat')) {
    class ZC_DMT_DataSource_Eurostat {

        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;

            $dataset = $cfg['dataset_code'];
            $query   = $cfg['query'];

            $cache_key = 'zc_dmt_euro_' . md5($dataset . '|' . $query);
            $cached = get_transient($cache_key);
            if ($cached && is_array($cached) && isset($cached['series'])) {
                $series = $cached['series'];
            } else {
                $series = self::fetch_eurostat_series($dataset, $query);
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
            $dataset = isset($cfg['dataset_code']) ? trim((string)$cfg['dataset_code']) : '';
            $query   = isset($cfg['query']) ? trim((string)$cfg['query']) : '';
            if ($dataset === '') {
                return new WP_Error('missing_dataset', __('Eurostat: dataset_code is required.', 'zc-dmt'));
            }
            return array('dataset_code' => $dataset, 'query' => $query);
        }

        private static function fetch_eurostat_series($dataset_code, $query = '') {
            $base = 'https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/' . rawurlencode($dataset_code);
            $url = $base;
            if ($query !== '') {
                // Query is provided by user, pass through as-is (basic validation)
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

            // JSON-stat expected fields: value (array), dimension (object), id (array of dim ids), size (array of sizes)
            // Example structure documented at https://json-stat.org/format/
            if (!isset($json['value']) || !isset($json['dimension']) || !isset($json['id']) || !isset($json['size'])) {
                return new WP_Error('unexpected_format', __('Eurostat: unexpected JSON-stat shape.', 'zc-dmt'));
            }

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
            $timeLabels   = array_keys($timeIndexMap);
            // Some datasets order time ascending/descending; no guarantee; we'll use category index order
            // We'll build an array mapping position -> label
            $timeLabelsByPos = array();
            foreach ($timeIndexMap as $label => $pos) {
                $timeLabelsByPos[(int)$pos] = $label;
            }
            ksort($timeLabelsByPos);

            // Compute linear index strides for each dimension to read the proper series (fix other dims to 0)
            $strides = array();
            $stride = 1;
            for ($i = count($sizes) - 1; $i >= 0; $i--) {
                $strides[$i] = $stride;
                $stride *= (int)$sizes[$i];
            }

            // Fix all non-time dims, but prefer EU/EZ aggregates for 'geo' when query doesn't force it.
            $fixed = array();
            $queryLower = strtolower($query);
            $preferredGeo = array('EU27_2020','EA20','EA19','EU28','EU27','EA18','EA17','EU','EA');
            foreach ($sizes as $i => $sz) {
                if ($i === $timeIndex) { $fixed[$i] = 0; continue; }
                $fixed[$i] = 0; // default
                // If this dimension looks like geography and user didn't already specify geo=... in query
                $dimId = isset($dims[$i]) ? (string)$dims[$i] : '';
                if ($dimId !== '' && strpos(strtolower($dimId), 'geo') !== false && strpos($queryLower, 'geo=') === false) {
                    // Try to locate preferred EU/EZ aggregate code position within this dimension's category index
                    if (isset($dimMeta[$dimId]['category']['index']) && is_array($dimMeta[$dimId]['category']['index'])) {
                        $indexMap = $dimMeta[$dimId]['category']['index']; // code => pos
                        foreach ($preferredGeo as $code) {
                            if (isset($indexMap[$code])) {
                                $fixed[$i] = (int)$indexMap[$code];
                                break;
                            }
                        }
                    }
                }
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
                // Value array in JSON-stat can be a dense array or dictionary (Eurostat sometimes sparse with string keys)
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
