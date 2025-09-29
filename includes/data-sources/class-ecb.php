<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * European Central Bank (ECB SDW) Data Source Adapter
 *
 * Supported methods in source_config (choose ONE):
 *  - csv_url: Direct CSV download URL (RECOMMENDED)
 *  - json_url: Any JSON endpoint that returns period/date and value (note: SDMX-JSON is complex; prefer CSV)
 *  - path: ECB SDW path "FLOW/KEY?params" (we will build a CSV URL automatically)
 *
 * Examples:
 *  - path: "EXR/D.USD.EUR.SP00.A?startPeriod=2000"
 *    -> fetches https://sdw-wsrest.ecb.europa.eu/service/data/EXR/D.USD.EUR.SP00.A?startPeriod=2000&format=csvdata
 *
 * Output normalized to: [ [Y-m-d, float|null], ... ] sorted asc
 * Caching: 20 minutes
 */
if (!class_exists('ZC_DMT_DataSource_ECB')) {
    class ZC_DMT_DataSource_ECB {

        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;

            $cache_key = 'zc_dmt_ecb_';
            $series = null;

            if (isset($cfg['csv_url'])) {
                $cache_key .= 'csv_' . md5($cfg['csv_url']);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $series = self::fetch_from_csv_url($cfg['csv_url']);
                    if (is_wp_error($series)) return $series;
                    set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
                }
            } elseif (isset($cfg['json_url'])) {
                $cache_key .= 'json_' . md5($cfg['json_url']);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $series = self::fetch_from_json_url($cfg['json_url']);
                    if (is_wp_error($series)) return $series;
                    set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
                }
            } elseif (isset($cfg['path'])) {
                $csvUrl = self::build_csv_url_from_path($cfg['path']);
                if (is_wp_error($csvUrl)) return $csvUrl;

                $cache_key .= 'path_' . md5($csvUrl);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $series = self::fetch_from_csv_url($csvUrl);
                    if (is_wp_error($series)) return $series;
                    set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
                }
            } else {
                return new WP_Error('missing_config', __('ECB: provide a CSV URL, JSON URL, or a PATH.', 'zc-dmt'));
            }

            // Optional date filter
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
                return new WP_Error('invalid_config', __('Invalid ECB configuration.', 'zc-dmt'));
            }
            $csv_url  = !empty($cfg['csv_url'])  ? esc_url_raw((string)$cfg['csv_url'])  : '';
            $json_url = !empty($cfg['json_url']) ? esc_url_raw((string)$cfg['json_url']) : '';
            $path     = !empty($cfg['path'])     ? trim((string)$cfg['path'])            : '';

            if ($csv_url)  return array('csv_url' => $csv_url);
            if ($json_url) return array('json_url' => $json_url);
            if ($path)     return array('path' => $path);

            return new WP_Error('missing_config', __('Provide an ECB CSV URL, JSON URL, or PATH.', 'zc-dmt'));
        }

        private static function build_csv_url_from_path($path) {
            $base = 'https://sdw-wsrest.ecb.europa.eu/service/data/';
            $path = ltrim($path, '/');
            if (strpos($path, '?') !== false) {
                return $base . $path . '&format=csvdata';
            }
            return $base . $path . '?format=csvdata';
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
                return new WP_Error('fetch_failed', sprintf(__('ECB CSV fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('ECB CSV HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            if (!is_string($body) || $body === '') {
                return new WP_Error('empty_csv', __('ECB CSV: empty body.', 'zc-dmt'));
            }

            // Some ECB CSVs may contain header comments; try to find the actual header row.
            $lines = preg_split('/\r\n|\r|\n/', $body);
            if (!is_array($lines) || empty($lines)) {
                return new WP_Error('invalid_csv', __('ECB CSV: invalid content.', 'zc-dmt'));
            }

            $pairs = array();
            $header = null;
            foreach ($lines as $i => $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) continue; // skip comments
                $cols = str_getcsv($line);
                // detect header row: must have at least 2 columns
                if ($header === null) {
                    if (count($cols) < 2) continue;
                    $header = array_map('strtolower', $cols);
                    continue;
                }

                // Try common header names for time and value
                $idxDate = self::find_header_index($header, array('time_period','time','period','date'));
                $idxVal  = self::find_header_index($header, array('obs_value','value','close','price'));
                if ($idxDate === null) $idxDate = 0;
                if ($idxVal === null)  $idxVal  = (count($cols) > 1 ? 1 : 0);

                $date = isset($cols[$idxDate]) ? $cols[$idxDate] : '';
                $value = isset($cols[$idxVal]) ? $cols[$idxVal] : null;

                $dateNorm = self::normalize_date((string)$date);
                if ($dateNorm === null) continue;
                $pairs[] = array($dateNorm, self::to_number($value));
            }

            if (empty($pairs)) {
                return new WP_Error('no_observations', __('ECB CSV: no observations parsed.', 'zc-dmt'));
            }
            usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        private static function fetch_from_json_url($url) {
            $args = array(
                'timeout'     => 15,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'application/json'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('ECB JSON fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('ECB JSON HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return new WP_Error('invalid_json', __('ECB invalid JSON response.', 'zc-dmt'));
            }

            // Try generic shapes first (this won't decode SDMX series metadata; prefer CSV for SDMX)
            $pairs = array();

            if (isset($json['data']) && is_array($json['data'])) {
                foreach ($json['data'] as $row) {
                    if (!is_array($row)) continue;
                    $date = self::normalize_date((string) self::first_of($row, array('date','time','period')));
                    if (!$date) continue;
                    $val = self::first_of($row, array('obs_value','value','close','price'));
                    $pairs[] = array($date, self::to_number($val));
                }
            } elseif (isset($json['observations']) && is_array($json['observations'])) {
                foreach ($json['observations'] as $period => $val) {
                    $date = self::normalize_date((string)$period);
                    if (!$date) continue;
                    if (is_array($val)) {
                        $val = self::first_of($val, array('obs_value','value','close','price'));
                    }
                    $pairs[] = array($date, self::to_number($val));
                }
            } elseif (isset($json[0]) && is_array($json[0])) {
                foreach ($json as $row) {
                    $date = self::normalize_date((string) self::first_of($row, array('date','time','period')));
                    if (!$date) continue;
                    $val = self::first_of($row, array('obs_value','value','close','price'));
                    $pairs[] = array($date, self::to_number($val));
                }
            }

            if (!empty($pairs)) {
                usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                return $pairs;
            }

            return new WP_Error('unexpected_format', __('ECB JSON URL: unexpected structure (prefer CSV).', 'zc-dmt'));
        }

        private static function first_of($row, $keys) {
            foreach ($keys as $k) {
                if (isset($row[$k])) return $row[$k];
            }
            return null;
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
            // Common ECB formats: YYYY, YYYY-Qn, YYYY-MM, YYYY-MM-DD
            // Handle YYYY
            if (preg_match('/^\d{4}$/', $s)) {
                return $s . '-01-01';
            }
            // Handle YYYY-MM and YYYY-MM-DD by strtotime
            if (preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $s)) {
                $ts = strtotime($s);
                return $ts ? gmdate('Y-m-d', $ts) : null;
            }
            // Handle YYYY-Qn -> map to quarter end
            if (preg_match('/^(\d{4})-?Q([1-4])$/i', $s, $m)) {
                $y = (int)$m[1];
                $q = (int)$m[2];
                $map = array(
                    1 => '-03-31',
                    2 => '-06-30',
                    3 => '-09-30',
                    4 => '-12-31',
                );
                return $y . $map[$q];
            }
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
