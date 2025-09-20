<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Statistics Canada (StatCan) Data Source Adapter
 *
 * Supported methods in source_config (choose ONE):
 *  - json_url: Direct JSON URL (e.g., StatCan Web Data Service/WDS JSON)
 *  - csv_url:  Direct CSV URL (StatCan downloads or Open Data portal CSV)
 *
 * Notes:
 * - StatCan WDS example (JSON):
 *   https://www150.statcan.gc.ca/t1/wds/en/grp/{TABLE_ID}?pid={PRODUCT_ID}
 *   https://www150.statcan.gc.ca/t1/wds/en/grp/{TABLE_ID}/all
 *   https://www150.statcan.gc.ca/t1/wds/en/grp/v1/getDataObject/productId/{PRODUCT_ID}
 *   The exact WDS endpoints vary; we normalize from any JSON shape that exposes date/period and value.
 *
 * Output normalized to: [ [Y-m-d, float|null], ... ] sorted asc
 * Caching: 20 minutes
 */
if (!class_exists('ZC_DMT_DataSource_StatCan')) {
    class ZC_DMT_DataSource_StatCan {

        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;

            $cache_key = 'zc_dmt_statcan_';
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
                return new WP_Error('missing_config', __('StatCan: provide a JSON URL or CSV URL.', 'zc-dmt'));
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
                return new WP_Error('invalid_config', __('Invalid Statistics Canada configuration.', 'zc-dmt'));
            }
            $json_url = !empty($cfg['json_url']) ? esc_url_raw((string)$cfg['json_url']) : '';
            $csv_url  = !empty($cfg['csv_url'])  ? esc_url_raw((string)$cfg['csv_url'])  : '';

            if ($json_url) return array('json_url' => $json_url);
            if ($csv_url)  return array('csv_url' => $csv_url);

            return new WP_Error('missing_config', __('Provide a StatCan JSON URL or CSV URL.', 'zc-dmt'));
        }

        private static function fetch_from_json_url($url) {
            $args = array(
                'timeout' => 20,
                'headers' => array('Accept' => 'application/json', 'User-Agent' => 'ZC-DMT-Plugin/1.0'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) return new WP_Error('fetch_failed', sprintf(__('StatCan JSON fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) return new WP_Error('http_error', sprintf(__('StatCan JSON HTTP error: %d', 'zc-dmt'), $code));
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) return new WP_Error('invalid_json', __('StatCan invalid JSON response.', 'zc-dmt'));

            // Attempt to parse common shapes:

            // 1) observations array with date + value
            if (isset($json['observations']) && is_array($json['observations'])) {
                $pairs = array();
                foreach ($json['observations'] as $row) {
                    if (!is_array($row)) continue;
                    $date = self::extract_date_from_row($row);
                    $val  = self::extract_value_from_row($row);
                    if ($date !== null) $pairs[] = array($date, $val);
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            // 2) generic array of rows with period/value keys
            if (isset($json[0]) && is_array($json[0])) {
                $pairs = array();
                foreach ($json as $row) {
                    $date = self::extract_date_from_row($row);
                    $val  = self::extract_value_from_row($row);
                    if ($date !== null) $pairs[] = array($date, $val);
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            // 3) WDS dataObjects/dataPoints style
            foreach (array('dataObjects','data','series','result') as $k) {
                if (isset($json[$k]) && is_array($json[$k])) {
                    $pairs = array();
                    foreach ($json[$k] as $row) {
                        if (!is_array($row)) continue;
                        $date = self::extract_date_from_row($row);
                        $val  = self::extract_value_from_row($row);
                        if ($date !== null) $pairs[] = array($date, $val);
                    }
                    if (!empty($pairs)) {
                        usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
                        return $pairs;
                    }
                }
            }

            return new WP_Error('unexpected_format', __('StatCan JSON: unexpected structure.', 'zc-dmt'));
        }

        private static function fetch_from_csv_url($url) {
            $args = array(
                'timeout' => 20,
                'headers' => array('Accept' => 'text/csv', 'User-Agent' => 'ZC-DMT-Plugin/1.0'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) return new WP_Error('fetch_failed', sprintf(__('StatCan CSV fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) return new WP_Error('http_error', sprintf(__('StatCan CSV HTTP error: %d', 'zc-dmt'), $code));
            $body = wp_remote_retrieve_body($res);
            if (!is_string($body) || $body === '') return new WP_Error('empty_csv', __('StatCan CSV: empty body.', 'zc-dmt'));

            $lines = preg_split('/\r\n|\r|\n/', $body);
            if (!is_array($lines) || empty($lines)) return new WP_Error('invalid_csv', __('StatCan CSV: invalid content.', 'zc-dmt'));

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

                $idxDate = self::find_header_index($header, array('ref_date','date','time','period'));
                // choose first numeric-like column for value
                $idxVal  = null;
                foreach ($cols as $j => $vv) {
                    if ($j === $idxDate) continue;
                    if ($vv === '' || $vv === null) continue;
                    $num = self::to_number($vv);
                    if ($num !== null) { $idxVal = $j; break; }
                }
                if ($idxDate === null) $idxDate = 0;
                if ($idxVal === null)  $idxVal  = (count($cols) > 1 ? 1 : 0);

                $date = isset($cols[$idxDate]) ? $cols[$idxDate] : '';
                $val  = isset($cols[$idxVal])  ? $cols[$idxVal]  : null;

                $date = self::normalize_date($date);
                $val  = self::to_number($val);
                if ($date) $pairs[] = array($date, $val);
            }

            if (empty($pairs)) return new WP_Error('no_observations', __('StatCan CSV: no observations parsed.', 'zc-dmt'));
            usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        private static function extract_date_from_row($row) {
            foreach (array('ref_date','d','date','time','period') as $k) {
                if (isset($row[$k])) {
                    $d = self::normalize_date($row[$k]);
                    if ($d !== null) return $d;
                }
            }
            return null;
        }
        private static function extract_value_from_row($row) {
            // Common StatCan uses 'value'
            foreach (array('value','val','v','close','price') as $k) {
                if (isset($row[$k])) {
                    return self::to_number($row[$k]);
                }
            }
            // Or search nested objects
            foreach ($row as $k => $v) {
                if (in_array($k, array('ref_date','d','date','time','period'), true)) continue;
                if (is_array($v)) {
                    foreach (array('value','val','v','close','price') as $kk) {
                        if (isset($v[$kk])) return self::to_number($v[$kk]);
                    }
                } else {
                    $num = self::to_number($v);
                    if ($num !== null) return $num;
                }
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
