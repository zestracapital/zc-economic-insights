<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * UK ONS Data Source Adapter
 *
 * Supports fetching UK data primarily from Office for National Statistics (ONS).
 * Methods supported (pick any one in source_config):
 *  - json_url: Direct ONS API JSON (or other UK source) URL
 *  - csv_url:  Direct CSV download URL
 *  - timeseries: Structured timeseries input:
 *        {
 *          "dataset_id": "pn2",
 *          "series_id": "mgsx",
 *          "query": "time=from+2010"  // optional extra query string to append
 *        }
 *    This will build: https://api.ons.gov.uk/timeseries/{series_id}/dataset/{dataset_id}/data[?{query}]
 *
 * Output normalized to: [ [Y-m-d, float|null], ... ] sorted by date asc
 * Caching: 20 minutes via transients
 */
if (!class_exists('ZC_DMT_DataSource_UK_ONS')) {
    class ZC_DMT_DataSource_UK_ONS {

        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;

            $cache_key = 'zc_dmt_ukons_';
            $series = null;

            if (isset($cfg['json_url'])) {
                $cache_key .= 'json_' . md5($cfg['json_url']);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $series = self::fetch_from_json_url($cfg['json_url']);
                    if (is_wp_error($series)) return $series;
                    set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
                }
            } elseif (isset($cfg['csv_url'])) {
                $cache_key .= 'csv_' . md5($cfg['csv_url']);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $series = self::fetch_from_csv_url($cfg['csv_url']);
                    if (is_wp_error($series)) return $series;
                    set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
                }
            } else {
                // timeseries path mode
                $dataset = $cfg['timeseries']['dataset_id'];
                $seriesId = $cfg['timeseries']['series_id'];
                $query = $cfg['timeseries']['query'];
                $url = 'https://api.ons.gov.uk/timeseries/' . rawurlencode($seriesId) . '/dataset/' . rawurlencode($dataset) . '/data';
                if ($query !== '') {
                    $url .= (strpos($url, '?') === false ? '?' : '&') . ltrim($query, '?&');
                }

                $cache_key .= 'ts_' . md5($url);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $series = self::fetch_from_json_url($url);
                    if (is_wp_error($series)) return $series;
                    set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
                }
            }

            // apply optional date filter
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
                return new WP_Error('invalid_config', __('Invalid UK ONS configuration.', 'zc-dmt'));
            }
            $json_url = !empty($cfg['json_url']) ? esc_url_raw((string)$cfg['json_url']) : '';
            $csv_url  = !empty($cfg['csv_url'])  ? esc_url_raw((string)$cfg['csv_url'])  : '';

            // timeseries structured object or shorthand
            $timeseries = null;
            if (!empty($cfg['timeseries']) && is_array($cfg['timeseries'])) {
                $dataset = isset($cfg['timeseries']['dataset_id']) ? trim((string)$cfg['timeseries']['dataset_id']) : '';
                $series  = isset($cfg['timeseries']['series_id']) ? trim((string)$cfg['timeseries']['series_id']) : '';
                $query   = isset($cfg['timeseries']['query']) ? trim((string)$cfg['timeseries']['query']) : '';
                if ($dataset !== '' && $series !== '') {
                    $timeseries = array('dataset_id' => $dataset, 'series_id' => $series, 'query' => $query);
                }
            } elseif (!empty($cfg['dataset_id']) && !empty($cfg['series_id'])) {
                $timeseries = array(
                    'dataset_id' => trim((string)$cfg['dataset_id']),
                    'series_id'  => trim((string)$cfg['series_id']),
                    'query'      => isset($cfg['query']) ? trim((string)$cfg['query']) : ''
                );
            }

            if ($json_url) return array('json_url' => $json_url);
            if ($csv_url)  return array('csv_url' => $csv_url);
            if ($timeseries) return array('timeseries' => $timeseries);

            return new WP_Error('missing_config', __('Provide a UK ONS JSON URL, CSV URL, or Timeseries (dataset_id + series_id).', 'zc-dmt'));
        }

        /**
         * Fetch from a JSON URL (ONS API or similar) and normalize to [[date, value], ...]
         */
        private static function fetch_from_json_url($url) {
            $args = array(
                'timeout'     => 15,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'application/json'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('UK ONS JSON fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('UK ONS JSON HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return new WP_Error('invalid_json', __('UK ONS invalid JSON response.', 'zc-dmt'));
            }

            // Try several common shapes:

            // Shape A: timeseries with "observations": [ { "date": "...", "value": "..." }, ... ]
            if (isset($json['observations']) && is_array($json['observations'])) {
                $pairs = array();
                foreach ($json['observations'] as $row) {
                    $date = null;
                    $val = null;
                    if (isset($row['date'])) $date = self::normalize_period((string)$row['date']);
                    if ($date === null && isset($row['time'])) $date = self::normalize_period((string)$row['time']);
                    if ($date === null && isset($row['label'])) $date = self::normalize_period((string)$row['label']);
                    if (array_key_exists('value', $row)) $val = $row['value'];
                    elseif (array_key_exists('observation', $row)) $val = $row['observation'];
                    if ($date === null) continue;
                    $pairs[] = array($date, self::to_number($val));
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            // Shape B: "months", "quarters", or "years" maps: { "2019 AUG": "123.4", ... }
            $keys = array('months','quarters','years','dates','series');
            foreach ($keys as $k) {
                if (isset($json[$k]) && is_array($json[$k])) {
                    $pairs = array();
                    foreach ($json[$k] as $period => $value) {
                        // sometimes inner is array/object with 'value'
                        if (is_array($value)) {
                            if (isset($value['value'])) {
                                $value = $value['value'];
                            } elseif (isset($value['observation'])) {
                                $value = $value['observation'];
                            }
                        }
                        $date = self::normalize_period((string)$period);
                        if ($date === null) continue;
                        $pairs[] = array($date, self::to_number($value));
                    }
                    if (!empty($pairs)) {
                        usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                        return $pairs;
                    }
                }
            }

            // Shape C: "data" array with { period|date|time, value }
            if (isset($json['data']) && is_array($json['data'])) {
                $pairs = array();
                foreach ($json['data'] as $row) {
                    $label = null;
                    if (isset($row['period'])) $label = (string)$row['period'];
                    elseif (isset($row['date'])) $label = (string)$row['date'];
                    elseif (isset($row['time'])) $label = (string)$row['time'];
                    $date = self::normalize_period((string)$label);
                    if ($date === null) continue;
                    $value = null;
                    if (array_key_exists('value', $row)) $value = $row['value'];
                    elseif (array_key_exists('observation', $row)) $value = $row['observation'];
                    $pairs[] = array($date, self::to_number($value));
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            return new WP_Error('unexpected_format', __('UK ONS JSON URL: unexpected structure.', 'zc-dmt'));
        }

        private static function fetch_from_csv_url($url) {
            $args = array(
                'timeout'     => 20,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'text/csv,application/octet-stream'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('UK ONS CSV fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('UK ONS CSV HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            if (!is_string($body) || $body === '') {
                return new WP_Error('empty_csv', __('UK ONS CSV: empty body.', 'zc-dmt'));
            }

            $lines = preg_split('/\r\n|\r|\n/', $body);
            if (!is_array($lines) || empty($lines)) {
                return new WP_Error('invalid_csv', __('UK ONS CSV: invalid content.', 'zc-dmt'));
            }

            $pairs = array();
            $header = null;
            foreach ($lines as $i => $line) {
                $line = trim($line);
                if ($line === '') continue;
                $cols = str_getcsv($line);

                if ($i === 0) {
                    $header = array_map('strtolower', $cols);
                    continue;
                }
                if (!$header) continue;

                // Try common headers for period/date/time and value columns
                $idxT = self::find_header_index($header, array('date','time','period','month','quarter','year','obstime','obs_time','time_period'));
                $idxV = self::find_header_index($header, array('value','obs_value','observation','v'));
                if ($idxT === null) $idxT = 0;
                if ($idxV === null) $idxV = 1;

                $period = isset($cols[$idxT]) ? $cols[$idxT] : '';
                $value  = isset($cols[$idxV]) ? $cols[$idxV] : null;

                $date = self::normalize_period((string)$period);
                if ($date === null) continue;
                $pairs[] = array($date, self::to_number($value));
            }

            if (empty($pairs)) {
                return new WP_Error('no_observations', __('UK ONS CSV: no observations parsed.', 'zc-dmt'));
            }
            usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        private static function find_header_index($header, $candidates) {
            foreach ($candidates as $h) {
                $pos = array_search(strtolower($h), $header);
                if ($pos !== false) return (int)$pos;
            }
            return null;
        }

        private static function normalize_period($p) {
            $p = trim((string)$p);
            if ($p === '') return null;

            // Standard ISO dates
            if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $p)) return $p;
            if (preg_match('~^\d{4}-\d{2}$~', $p)) return $p . '-01';
            if (preg_match('~^\d{4}$~', $p)) return $p . '-01-01';

            // ONS often uses "YYYY MMM" (e.g., "2023 AUG"); allow mixed case
            if (preg_match('~^(\d{4})\s+([A-Za-z]{3})$~', $p, $m)) {
                $year = (int)$m[1];
                $monMap = array(
                    'JAN'=>1,'FEB'=>2,'MAR'=>3,'APR'=>4,'MAY'=>5,'JUN'=>6,
                    'JUL'=>7,'AUG'=>8,'SEP'=>9,'OCT'=>10,'NOV'=>11,'DEC'=>12
                );
                $monStr = strtoupper($m[2]);
                if (isset($monMap[$monStr])) {
                    return sprintf('%04d-%02d-01', $year, $monMap[$monStr]);
                }
            }

            // Quarters like "2020 Q1" or "2020-Q1"
            if (preg_match('~^(\d{4})[\s\-]?Q([1-4])$~i', $p, $m)) {
                $year = (int)$m[1];
                $q = (int)$m[2];
                $month = ($q - 1) * 3 + 1;
                return sprintf('%04d-%02d-01', $year, $month);
            }

            // Try fallback parsing
            $ts = strtotime($p);
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

        public static function test_connection($dataset_id, $series_id, $query = '') {
            $url = 'https://api.ons.gov.uk/timeseries/' . rawurlencode($series_id) . '/dataset/' . rawurlencode($dataset_id) . '/data';
            if ($query !== '') $url .= (strpos($url, '?') === false ? '?' : '&') . ltrim($query, '?&');
            $data = self::fetch_from_json_url($url);
            if (is_wp_error($data)) return $data;
            return array('success' => true, 'message' => sprintf(__('UK ONS OK (%d points)', 'zc-dmt'), count($data)));
        }
    }
}
