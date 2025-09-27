<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Universal CSV Data Source Adapter
 *
 * Purpose: Fetch time-series from ANY CSV URL on the internet with minimal config.
 *
 * Supported source_config:
 *  {
 *    "csv_url": "https://example.com/data.csv",            // required
 *    "date_col": "date" | 0,                                // optional: header name or zero-based index
 *    "value_col": "value" | 1,                              // optional: header name or zero-based index
 *    "delimiter": ",",                                      // optional: default auto, can be ",", ";", "\t", "|"
 *    "skip_rows": 0                                         // optional: rows to skip before header (comments, etc.)
 *  }
 *
 * Behavior:
 * - Tries to auto-detect header and date/value columns if not provided.
 * - Normalizes dates to Y-m-d using strtotime (handles YYYY, YYYY-MM, YYYY-MM-DD, "YYYY Qn" etc.).
 * - Returns normalized series: [ [Y-m-d, float|null], ... ] ascending by date.
 * - Caches results for 20 minutes.
 */
if (!class_exists('ZC_DMT_DataSource_Universal_CSV')) {
    class ZC_DMT_DataSource_Universal_CSV {

        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;

            $cache_key = 'zc_dmt_unicsv_' . md5(wp_json_encode($cfg));
            $cached = get_transient($cache_key);
            if ($cached && is_array($cached) && isset($cached['series'])) {
                $series = $cached['series'];
            } else {
                $series = self::fetch_from_csv($cfg['csv_url'], $cfg);
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
                return new WP_Error('invalid_config', __('Universal CSV: invalid configuration.', 'zc-dmt'));
            }
            $url = !empty($cfg['csv_url']) ? esc_url_raw((string)$cfg['csv_url']) : '';
            if (!$url) {
                return new WP_Error('missing_csv_url', __('Universal CSV: csv_url is required.', 'zc-dmt'));
            }
            $out = array('csv_url' => $url);
            if (isset($cfg['date_col'])) $out['date_col'] = $cfg['date_col'];
            if (isset($cfg['value_col'])) $out['value_col'] = $cfg['value_col'];
            if (isset($cfg['delimiter'])) $out['delimiter'] = (string)$cfg['delimiter'];
            if (isset($cfg['skip_rows'])) $out['skip_rows'] = max(0, (int)$cfg['skip_rows']);
            return $out;
        }

        private static function fetch_from_csv($url, $cfg) {
            $args = array(
                'timeout'     => 20,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'text/csv,application/octet-stream'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('Universal CSV fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('Universal CSV HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            if (!is_string($body) || $body === '') {
                return new WP_Error('empty_csv', __('Universal CSV: empty body.', 'zc-dmt'));
            }

            $lines = preg_split('/\r\n|\r|\n/', $body);
            if (!is_array($lines) || empty($lines)) {
                return new WP_Error('invalid_csv', __('Universal CSV: invalid content.', 'zc-dmt'));
            }

            $skip = isset($cfg['skip_rows']) ? (int)$cfg['skip_rows'] : 0;
            $i = 0;
            $header = null;
            $pairs = array();

            $delimiter = isset($cfg['delimiter']) ? (string)$cfg['delimiter'] : null;
            $autoDelims = array(',', ';', "\t", '|');

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') { $i++; continue; }
                if ($skip > 0) { $skip--; $i++; continue; }

                // choose delimiter
                $cols = null;
                if ($delimiter === null) {
                    foreach ($autoDelims as $d) {
                        $trial = str_getcsv($line, $d);
                        if (count($trial) > 1) { $cols = $trial; $delimiter = $d; break; }
                    }
                    if ($cols === null) $cols = str_getcsv($line);
                } else {
                    $cols = str_getcsv($line, $delimiter);
                }

                if ($header === null) {
                    $header = array_map('strtolower', $cols);
                    $i++;
                    continue;
                }

                // Resolve date/value indices
                list($idxDate, $idxVal) = self::resolve_columns($header, $cfg);

                $date = isset($cols[$idxDate]) ? $cols[$idxDate] : '';
                $value = isset($cols[$idxVal]) ? $cols[$idxVal] : null;

                $dateNorm = self::normalize_date((string)$date);
                if ($dateNorm === null) { $i++; continue; }
                $pairs[] = array($dateNorm, self::to_number($value));
                $i++;
            }

            if (empty($pairs)) {
                return new WP_Error('no_observations', __('Universal CSV: no observations parsed.', 'zc-dmt'));
            }
            usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        private static function resolve_columns($header, $cfg) {
            $idxDate = null;
            $idxVal  = null;

            // Allow numeric index or header name
            if (isset($cfg['date_col'])) {
                if (is_numeric($cfg['date_col'])) {
                    $idxDate = (int)$cfg['date_col'];
                } else {
                    $idxDate = self::find_header_index($header, array(strtolower((string)$cfg['date_col'])));
                }
            }
            if (isset($cfg['value_col'])) {
                if (is_numeric($cfg['value_col'])) {
                    $idxVal = (int)$cfg['value_col'];
                } else {
                    $idxVal = self::find_header_index($header, array(strtolower((string)$cfg['value_col'])));
                }
            }

            // Auto-detect if not provided
            if ($idxDate === null) {
                $idxDate = self::find_header_index($header, array('date','time','period','ref_date','timestamp'));
                if ($idxDate === null) $idxDate = 0;
            }
            if ($idxVal === null) {
                $idxVal  = self::find_header_index($header, array('value','val','obs_value','close','price'));
                if ($idxVal === null)  $idxVal  = (count($header) > 1 ? 1 : 0);
            }
            return array($idxDate, $idxVal);
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
