<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Google Finance Data Source Adapter
 *
 * There is no official public JSON API from Google Finance. Recommended methods:
 *  - csv_url: Published CSV (e.g., from Google Sheets using GOOGLEFINANCE() then File > Publish to the web)
 *  - json_url: Any JSON endpoint you control that returns period/value data
 *
 * Output normalized to: [ [Y-m-d, float|null], ... ] sorted by date asc
 * Caching: 20 minutes via transients
 */
if (!class_exists('ZC_DMT_DataSource_GoogleFinance')) {
    class ZC_DMT_DataSource_GoogleFinance {

        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;

            $cache_key = 'zc_dmt_gf_';
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
                return new WP_Error('missing_config', __('Google Finance: provide CSV URL (preferred) or JSON URL.', 'zc-dmt'));
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
                return new WP_Error('invalid_config', __('Invalid Google Finance configuration.', 'zc-dmt'));
            }
            $csv_url  = !empty($cfg['csv_url'])  ? esc_url_raw((string)$cfg['csv_url'])  : '';
            $json_url = !empty($cfg['json_url']) ? esc_url_raw((string)$cfg['json_url']) : '';

            if ($csv_url)  return array('csv_url' => $csv_url);
            if ($json_url) return array('json_url' => $json_url);

            return new WP_Error('missing_config', __('Provide a Google Finance CSV URL (recommended) or JSON URL.', 'zc-dmt'));
        }

        /**
         * CSV format expectations:
         * - A header row that includes at least a date/time column and a value column.
         * - Common headers: Date, Close, Value, Price. Adapter will try to detect.
         */
        private static function fetch_from_csv_url($url) {
            $args = array(
                'timeout'     => 20,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'text/csv,application/octet-stream'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('Google Finance CSV fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('Google Finance CSV HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            if (!is_string($body) || $body === '') {
                return new WP_Error('empty_csv', __('Google Finance CSV: empty body.', 'zc-dmt'));
            }

            $lines = preg_split('/\r\n|\r|\n/', $body);
            if (!is_array($lines) || empty($lines)) {
                return new WP_Error('invalid_csv', __('Google Finance CSV: invalid content.', 'zc-dmt'));
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

                $idxDate = self::find_header_index($header, array('date','time','period','timestamp'));
                $idxVal  = self::find_header_index($header, array('close','value','price','adj close','adjusted_close','adjclose'));
                if ($idxDate === null) $idxDate = 0;
                if ($idxVal === null)  $idxVal  = (count($cols) > 1 ? 1 : 0);

                $date = isset($cols[$idxDate]) ? $cols[$idxDate] : '';
                $value = isset($cols[$idxVal]) ? $cols[$idxVal] : null;

                $dateNorm = self::normalize_date((string)$date);
                if ($dateNorm === null) continue;
                $pairs[] = array($dateNorm, self::to_number($value));
            }

            if (empty($pairs)) {
                return new WP_Error('no_observations', __('Google Finance CSV: no observations parsed.', 'zc-dmt'));
            }
            usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        /**
         * JSON expectations:
         * - Either:
         *    A) { "data": [ { "date"|"time"|"period": "...", "value"|"close"|"price": ... }, ... ] }
         *    B) { "observations": { "YYYY-MM-DD": value, ... } } or array of { date, value }
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
                return new WP_Error('fetch_failed', sprintf(__('Google Finance JSON fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('Google Finance JSON HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return new WP_Error('invalid_json', __('Google Finance invalid JSON response.', 'zc-dmt'));
            }

            // A) data array of rows
            if (isset($json['data']) && is_array($json['data'])) {
                $pairs = array();
                foreach ($json['data'] as $row) {
                    $label = null;
                    if (isset($row['date']))   $label = (string)$row['date'];
                    elseif (isset($row['time']))   $label = (string)$row['time'];
                    elseif (isset($row['period'])) $label = (string)$row['period'];
                    $date = self::normalize_date((string)$label);
                    if ($date === null) continue;

                    $value = null;
                    if (array_key_exists('value', $row)) $value = $row['value'];
                    elseif (array_key_exists('close', $row)) $value = $row['close'];
                    elseif (array_key_exists('price', $row)) $value = $row['price'];

                    $pairs[] = array($date, self::to_number($value));
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            // B) observations map
            if (isset($json['observations']) && is_array($json['observations'])) {
                $pairs = array();
                foreach ($json['observations'] as $period => $val) {
                    $date = self::normalize_date((string)$period);
                    if ($date === null) continue;
                    // If nested object, try read value/close/price
                    if (is_array($val)) {
                        if (array_key_exists('value', $val)) $val = $val['value'];
                        elseif (array_key_exists('close', $val)) $val = $val['close'];
                        elseif (array_key_exists('price', $val)) $val = $val['price'];
                    }
                    $pairs[] = array($date, self::to_number($val));
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            // Or array of rows at root
            if (isset($json[0]) && is_array($json[0])) {
                $pairs = array();
                foreach ($json as $row) {
                    $label = null;
                    if (isset($row['date']))   $label = (string)$row['date'];
                    elseif (isset($row['time']))   $label = (string)$row['time'];
                    elseif (isset($row['period'])) $label = (string)$row['period'];
                    $date = self::normalize_date((string)$label);
                    if ($date === null) continue;

                    $value = null;
                    if (array_key_exists('value', $row)) $value = $row['value'];
                    elseif (array_key_exists('close', $row)) $value = $row['close'];
                    elseif (array_key_exists('price', $row)) $value = $row['price'];

                    $pairs[] = array($date, self::to_number($value));
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            return new WP_Error('unexpected_format', __('Google Finance JSON URL: unexpected structure.', 'zc-dmt'));
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
