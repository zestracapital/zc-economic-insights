<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Universal JSON Data Source Adapter
 *
 * Purpose: Fetch time-series from ANY JSON URL on the internet with minimal config.
 *
 * Supported source_config:
 *  {
 *    "json_url": "https://example.com/data.json",   // required
 *    "root": "data.items",                          // optional: dot-path to array root; if omitted, will attempt auto-detect
 *    "date_key": "date",                            // optional: key name for date in each item (fallback auto)
 *    "value_key": "value",                          // optional: key name for value in each item (fallback auto)
 *    "map": {                                       // optional: map keys if your JSON uses different names
 *      "date": "d",                                 // e.g., rename 'd' to 'date'
 *      "value": "v"                                 // e.g., rename 'v' to 'value'
 *    }
 *  }
 *
 * Behavior:
 * - Tries generic patterns if keys are not provided:
 *   - Each item: { "date|time|period": "...", "value|val|close|price|obs_value": ... }
 *   - Or observations object: { "observations": { "YYYY-MM-DD": val, ... } }
 *   - Or arbitrary array at root with those fields.
 * - Supports "root" dot-path to drill into nested JSON arrays (e.g., "data.items").
 * - Normalizes dates to Y-m-d using strtotime (handles YYYY, YYYY-MM, YYYY-MM-DD, "YYYY Qn" etc.).
 * - Returns normalized series: [ [Y-m-d, float|null], ... ] ascending by date.
 * - Caches results for 20 minutes.
 */
if (!class_exists('ZC_DMT_DataSource_Universal_JSON')) {
    class ZC_DMT_DataSource_Universal_JSON {

        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;

            $cache_key = 'zc_dmt_unijson_' . md5(wp_json_encode($cfg));
            $cached = get_transient($cache_key);
            if ($cached && is_array($cached) && isset($cached['series'])) {
                $series = $cached['series'];
            } else {
                $series = self::fetch_from_json($cfg);
                if (is_wp_error($series)) return $series;
                set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
            }

            // Optional filter by range
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
                return new WP_Error('invalid_config', __('Universal JSON: invalid configuration.', 'zc-dmt'));
            }
            $url = !empty($cfg['json_url']) ? esc_url_raw((string)$cfg['json_url']) : '';
            if (!$url) {
                return new WP_Error('missing_json_url', __('Universal JSON: json_url is required.', 'zc-dmt'));
            }
            $out = array('json_url' => $url);
            if (!empty($cfg['root'])) $out['root'] = (string)$cfg['root'];
            if (!empty($cfg['date_key'])) $out['date_key'] = (string)$cfg['date_key'];
            if (!empty($cfg['value_key'])) $out['value_key'] = (string)$cfg['value_key'];
            if (!empty($cfg['map']) && is_array($cfg['map'])) $out['map'] = $cfg['map'];
            return $out;
        }

        private static function fetch_from_json($cfg) {
            $args = array(
                'timeout'     => 20,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'application/json'),
            );
            $res = wp_remote_get($cfg['json_url'], $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('Universal JSON fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('Universal JSON HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return new WP_Error('invalid_json', __('Universal JSON: invalid JSON body.', 'zc-dmt'));
            }

            // Apply optional key mappings
            if (!empty($cfg['map']) && is_array($cfg['map'])) {
                $json = self::recursive_key_map($json, $cfg['map']);
            }

            // Drill into root if provided
            if (!empty($cfg['root'])) {
                $root = self::get_by_dot_path($json, $cfg['root']);
                if (is_array($root)) {
                    $json = $root;
                } else {
                    // If provided root doesn't resolve, continue with top-level heuristics
                }
            }

            // If we landed at an array of items, try to parse
            if (isset($json[0]) && is_array($json[0])) {
                $pairs = self::parse_items_array($json, $cfg);
                if (!empty($pairs)) return self::sort_pairs($pairs);
            }

            // Observations shape: { observations: { "YYYY-MM-DD": value, ... } }
            if (isset($json['observations']) && is_array($json['observations'])) {
                $pairs = array();
                foreach ($json['observations'] as $k => $v) {
                    $date = self::normalize_date((string)$k);
                    if (!$date) continue;
                    if (is_array($v)) {
                        $v = self::first_of($v, array('value','val','close','price','obs_value'));
                    }
                    $pairs[] = array($date, self::to_number($v));
                }
                if (!empty($pairs)) return self::sort_pairs($pairs);
            }

            // Generic "data" wrapper: { data: [...] }
            if (isset($json['data']) && is_array($json['data'])) {
                $pairs = self::parse_items_array($json['data'], $cfg);
                if (!empty($pairs)) return self::sort_pairs($pairs);
            }

            // Last resort: try to find first array in object and parse it
            foreach ($json as $k => $v) {
                if (is_array($v)) {
                    if (isset($v[0]) && is_array($v[0])) {
                        $pairs = self::parse_items_array($v, $cfg);
                        if (!empty($pairs)) return self::sort_pairs($pairs);
                    }
                }
            }

            return new WP_Error('unexpected_format', __('Universal JSON: could not parse time series. Provide date_key/value_key or adjust root path.', 'zc-dmt'));
        }

        private static function parse_items_array($items, $cfg) {
            $dateKey = isset($cfg['date_key']) ? $cfg['date_key'] : null;
            $valKey  = isset($cfg['value_key']) ? $cfg['value_key'] : null;

            $pairs = array();
            foreach ($items as $row) {
                if (!is_array($row)) continue;

                $date = null;
                if ($dateKey !== null && isset($row[$dateKey])) {
                    $date = self::normalize_date((string)$row[$dateKey]);
                } else {
                    $date = self::normalize_date((string) self::first_of($row, array('date','time','period','ref_date','timestamp')));
                }
                if (!$date) continue;

                $val = null;
                if ($valKey !== null && array_key_exists($valKey, $row)) {
                    $val = $row[$valKey];
                } else {
                    $val = self::first_of($row, array('value','val','close','price','obs_value'));
                    if ($val === null) {
                        // search nested
                        foreach ($row as $k => $vv) {
                            if (is_array($vv)) {
                                $val = self::first_of($vv, array('value','val','close','price','obs_value'));
                                if ($val !== null) break;
                            }
                        }
                    }
                }

                $pairs[] = array($date, self::to_number($val));
            }
            return $pairs;
        }

        private static function get_by_dot_path($obj, $path) {
            $parts = explode('.', $path);
            $cur = $obj;
            foreach ($parts as $p) {
                if (is_array($cur) && array_key_exists($p, $cur)) {
                    $cur = $cur[$p];
                } else {
                    return null;
                }
            }
            return $cur;
        }

        private static function recursive_key_map($data, $map) {
            if (is_array($data)) {
                $out = array();
                foreach ($data as $k => $v) {
                    $newK = isset($map[$k]) ? $map[$k] : $k;
                    $out[$newK] = self::recursive_key_map($v, $map);
                }
                return $out;
            }
            return $data;
        }

        private static function first_of($row, $keys) {
            foreach ($keys as $k) {
                if (isset($row[$k])) return $row[$k];
            }
            return null;
        }

        private static function sort_pairs($pairs) {
            usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        private static function normalize_date($s) {
            $s = trim((string)$s);
            if ($s === '') return null;
            // Handle YYYY
            if (preg_match('/^\d{4}$/', $s)) return $s . '-01-01';
            // Handle YYYY-Qn -> map to quarter end
            if (preg_match('/^(\d{4})-?Q([1-4])$/i', $s, $m)) {
                $y = (int)$m[1];
                $q = (int)$m[2];
                $map = array(1 => '-03-31', 2 => '-06-30', 3 => '-09-30', 4 => '-12-31');
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
