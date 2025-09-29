<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reserve Bank of Australia (RBA) Data Source Adapter
 *
 * Supported methods in source_config (choose ONE):
 *  - csv_url:  Direct CSV URL (recommended)
 *  - json_url: Any JSON endpoint that returns period/date and value
 *
 * Notes:
 * - RBA publishes statistical tables; many are downloadable as CSV. Paste the direct CSV link.
 * - This adapter is format-tolerant and will try to auto-detect the date and value columns.
 *
 * Output normalized to: [ [Y-m-d, float|null], ... ] sorted asc
 * Caching: 20 minutes
 */
if (!class_exists('ZC_DMT_DataSource_Australia_RBA')) {
    class ZC_DMT_DataSource_Australia_RBA {

        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;

            $cache_key = 'zc_dmt_rba_';
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
            } else {
                return new WP_Error('missing_config', __('RBA: provide a CSV URL (recommended) or a JSON URL.', 'zc-dmt'));
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
                return new WP_Error('invalid_config', __('Invalid RBA configuration.', 'zc-dmt'));
            }
            $csv_url  = !empty($cfg['csv_url'])  ? esc_url_raw((string)$cfg['csv_url'])  : '';
            $json_url = !empty($cfg['json_url']) ? esc_url_raw((string)$cfg['json_url']) : '';

            if ($csv_url)  return array('csv_url' => $csv_url);
            if ($json_url) return array('json_url' => $json_url);

            return new WP_Error('missing_config', __('Provide an RBA CSV URL or JSON URL.', 'zc-dmt'));
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
                return new WP_Error('fetch_failed', sprintf(__('RBA CSV fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('RBA CSV HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            if (!is_string($body) || $body === '') {
                return new WP_Error('empty_csv', __('RBA CSV: empty body.', 'zc-dmt'));
            }

            $lines = preg_split('/\r\n|\r|\n/', $body);
            if (!is_array($lines) || empty($lines)) {
                return new WP_Error('invalid_csv', __('RBA CSV: invalid content.', 'zc-dmt'));
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

                // Try common header names; fallback to first and second column.
                $idxDate = self::find_header_index($header, array('date','time','period','ref_date'));
                $idxVal  = self::find_header_index($header, array('value','close','price','obs_value','series_value'));
                if ($idxDate === null) $idxDate = 0;
                if ($idxVal === null)  $idxVal  = (count($cols) > 1 ? 1 : 0);

                $date = isset($cols[$idxDate]) ? $cols[$idxDate] : '';
                $value = isset($cols[$idxVal]) ? $cols[$idxVal] : null;
                $dateNorm = self::normalize_date((string)$date);
                if ($dateNorm === null) continue;
                $pairs[] = array($dateNorm, self::to_number($value));
            }

            if (empty($pairs)) {
                return new WP_Error('no_observations', __('RBA CSV: no observations parsed.', 'zc-dmt'));
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
                return new WP_Error('fetch_failed', sprintf(__('RBA JSON fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('RBA JSON HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return new WP_Error('invalid_json', __('RBA invalid JSON response.', 'zc-dmt'));
            }

            // Supported JSON shapes:
            // A) { "data": [ { "date|time|period": "...", "value|close|price": ... }, ... ] }
            // B) { "observations": { "YYYY-MM-DD": value, ... } } or array of { date, value }
            $pairs = array();

            if (isset($json['data']) && is_array($json['data'])) {
                foreach ($json['data'] as $row) {
                    if (!is_array($row)) continue;
                    $dateLabel = null;
                    foreach (array('date','time','period') as $k) {
                        if (isset($row[$k])) { $dateLabel = (string)$row[$k]; break; }
                    }
                    $date = self::normalize_date((string)$dateLabel);
                    if ($date === null) continue;

                    $value = null;
                    foreach (array('value','close','price') as $k) {
                        if (array_key_exists($k, $row)) { $value = $row[$k]; break; }
                    }
                    $pairs[] = array($date, self::to_number($value));
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            if (isset($json['observations']) && is_array($json['observations'])) {
                foreach ($json['observations'] as $period => $val) {
                    $date = self::normalize_date((string)$period);
                    if ($date === null) continue;
                    if (is_array($val)) {
                        foreach (array('value','close','price') as $k) {
                            if (array_key_exists($k, $val)) { $val = $val[$k]; break; }
                        }
                    }
                    $pairs[] = array($date, self::to_number($val));
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            if (isset($json[0]) && is_array($json[0])) {
                foreach ($json as $row) {
                    $dateLabel = null;
                    foreach (array('date','time','period') as $k) {
                        if (isset($row[$k])) { $dateLabel = (string)$row[$k]; break; }
                    }
                    $date = self::normalize_date((string)$dateLabel);
                    if ($date === null) continue;

                    $value = null;
                    foreach (array('value','close','price') as $k) {
                        if (array_key_exists($k, $row)) { $value = $row[$k]; break; }
                    }
                    $pairs[] = array($date, self::to_number($value));
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            return new WP_Error('unexpected_format', __('RBA JSON URL: unexpected structure.', 'zc-dmt'));
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
