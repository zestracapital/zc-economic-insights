<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bank of Canada (Valet) Data Source Adapter
 *
 * Supported methods (choose ONE in source_config):
 *  - json_url: Direct JSON URL to Valet or any JSON endpoint
 *      Example: https://www.bankofcanada.ca/valet/observations/V39079/json?start_date=2019-01-01
 *  - csv_url:  Direct CSV URL
 *      Example: https://www.bankofcanada.ca/valet/observations/V39079/csv?start_date=2019-01-01
 *  - series:   {
 *        "series": "V39079",
 *        "start_date": "YYYY-MM-DD",
 *        "end_date": "YYYY-MM-DD"
 *     }
 *
 * Normalizes to: [ [Y-m-d, float|null], ... ] sorted asc
 * Caching: 20 minutes
 *
 * Parsing notes:
 * - Valet JSON usually returns:
 *   { "observations": [ { "d": "YYYY-MM-DD", "SERIES" or "Vxxxx": { "v": "value" } or "value" }, ... ] }
 *   We detect date key 'd' or 'date' and use the first non-date field as value.
 * - CSV parsing falls back to generic header detection.
 */
if (!class_exists('ZC_DMT_DataSource_BankOfCanada')) {
    class ZC_DMT_DataSource_BankOfCanada {

        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;

            $cache_key = 'zc_dmt_boc_';
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
                $sid = $cfg['series']['series'];
                $s = isset($cfg['series']['start_date']) ? $cfg['series']['start_date'] : '';
                $e = isset($cfg['series']['end_date']) ? $cfg['series']['end_date'] : '';
                $url = 'https://www.bankofcanada.ca/valet/observations/' . rawurlencode($sid) . '/json';
                $qs = array();
                if ($s) $qs['start_date'] = $s;
                if ($e) $qs['end_date'] = $e;
                if (!empty($qs)) $url .= '?' . http_build_query($qs);

                $cache_key .= 'sid_' . md5($url);
                $series = self::maybe_from_cache($cache_key);
                if (!$series) {
                    $series = self::fetch_from_json_url($url);
                    if (is_wp_error($series)) {
                        // Fallback to CSV
                        $urlCsv = 'https://www.bankofcanada.ca/valet/observations/' . rawurlencode($sid) . '/csv';
                        if (!empty($qs)) $urlCsv .= '?' . http_build_query($qs);
                        $series = self::fetch_from_csv_url($urlCsv);
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
                return new WP_Error('invalid_config', __('Invalid Bank of Canada configuration.', 'zc-dmt'));
            }
            $json_url = !empty($cfg['json_url']) ? esc_url_raw((string)$cfg['json_url']) : '';
            $csv_url  = !empty($cfg['csv_url'])  ? esc_url_raw((string)$cfg['csv_url'])  : '';
            $series   = null;
            if (!empty($cfg['series']) && is_array($cfg['series'])) {
                $sid = isset($cfg['series']['series']) ? sanitize_text_field($cfg['series']['series']) : '';
                if ($sid !== '') {
                    $series = array(
                        'series'     => $sid,
                        'start_date' => isset($cfg['series']['start_date']) ? sanitize_text_field((string)$cfg['series']['start_date']) : '',
                        'end_date'   => isset($cfg['series']['end_date']) ? sanitize_text_field((string)$cfg['series']['end_date']) : '',
                    );
                }
            }

            if ($json_url) return array('json_url' => $json_url);
            if ($csv_url)  return array('csv_url' => $csv_url);
            if ($series)   return array('series' => $series);

            return new WP_Error('missing_config', __('Provide a Bank of Canada JSON URL, CSV URL, or Series code.', 'zc-dmt'));
        }

        private static function fetch_from_json_url($url) {
            $args = array(
                'timeout' => 20,
                'headers' => array('Accept' => 'application/json', 'User-Agent' => 'ZC-DMT-Plugin/1.0'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) return new WP_Error('fetch_failed', sprintf(__('Bank of Canada JSON fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) return new WP_Error('http_error', sprintf(__('Bank of Canada JSON HTTP error: %d', 'zc-dmt'), $code));
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) return new WP_Error('invalid_json', __('Bank of Canada invalid JSON response.', 'zc-dmt'));

            // Expect observations array
            if (!isset($json['observations']) || !is_array($json['observations'])) {
                // Some custom JSON may return plain arrays; support minimal expected forms
                if (isset($json[0])) {
                    $pairs = array();
                    foreach ($json as $row) {
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
                return new WP_Error('unexpected_format', __('Bank of Canada JSON: unexpected structure.', 'zc-dmt'));
            }

            $pairs = array();
            foreach ($json['observations'] as $obs) {
                if (!is_array($obs)) continue;
                $date = null;
                if (isset($obs['d'])) $date = self::normalize_date($obs['d']);
                elseif (isset($obs['date'])) $date = self::normalize_date($obs['date']);
                if ($date === null) continue;

                // Find first non-date key and get numeric value
                $val = null;
                foreach ($obs as $k => $v) {
                    if ($k === 'd' || $k === 'date') continue;
                    if (is_array($v)) {
                        // Often { "v": "1.23" }
                        if (array_key_exists('v', $v)) {
                            $val = self::to_number($v['v']);
                            break;
                        }
                        // Or nested structure; attempt common property names
                        foreach (array('value','close','price') as $kk) {
                            if (array_key_exists($kk, $v)) { $val = self::to_number($v[$kk]); break 2; }
                        }
                    } else {
                        $val = self::to_number($v);
                        break;
                    }
                }
                $pairs[] = array($date, $val);
            }

            if (empty($pairs)) return new WP_Error('no_observations', __('Bank of Canada JSON: no observations parsed.', 'zc-dmt'));
            usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        private static function fetch_from_csv_url($url) {
            $args = array(
                'timeout' => 20,
                'headers' => array('Accept' => 'text/csv', 'User-Agent' => 'ZC-DMT-Plugin/1.0'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) return new WP_Error('fetch_failed', sprintf(__('Bank of Canada CSV fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) return new WP_Error('http_error', sprintf(__('Bank of Canada CSV HTTP error: %d', 'zc-dmt'), $code));
            $body = wp_remote_retrieve_body($res);
            if (!is_string($body) || $body === '') return new WP_Error('empty_csv', __('Bank of Canada CSV: empty body.', 'zc-dmt'));

            $lines = preg_split('/\r\n|\r|\n/', $body);
            if (!is_array($lines) || empty($lines)) return new WP_Error('invalid_csv', __('Bank of Canada CSV: invalid content.', 'zc-dmt'));

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

                $idxDate = self::find_header_index($header, array('date','d','ref_date','time','period'));
                // choose first numeric-like column for value
                $idxVal  = null;
                foreach ($cols as $j => $vv) {
                    if ($j === $idxDate) continue;
                    if ($vv === '' || $vv === null) continue;
                    $num = self::to_number($vv);
                    if ($num !== null) { $idxVal = $j; break; }
                }
                if ($idxDate === null) $idxDate = 0;
                if ($idxVal === null) $idxVal = (count($cols) > 1 ? 1 : 0);

                $date = isset($cols[$idxDate]) ? $cols[$idxDate] : '';
                $val  = isset($cols[$idxVal])  ? $cols[$idxVal]  : null;

                $date = self::normalize_date($date);
                $val  = self::to_number($val);
                if ($date) $pairs[] = array($date, $val);
            }

            if (empty($pairs)) return new WP_Error('no_observations', __('Bank of Canada CSV: no observations parsed.', 'zc-dmt'));
            usort($pairs, function($a,$b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        private static function extract_date_from_row($row) {
            foreach (array('d','date','ref_date','time','period') as $k) {
                if (isset($row[$k])) {
                    $d = self::normalize_date($row[$k]);
                    if ($d !== null) return $d;
                }
            }
            return null;
        }

        private static function extract_value_from_row($row) {
            foreach ($row as $k => $v) {
                if ($k === 'd' || $k === 'date' || $k === 'ref_date' || $k === 'time' || $k === 'period') continue;
                if (is_array($v) && array_key_exists('v', $v)) return self::to_number($v['v']);
                if (is_array($v)) {
                    foreach (array('value','close','price') as $kk) {
                        if (array_key_exists($kk, $v)) return self::to_number($v[$kk]);
                    }
                } else {
                    $num = self::to_number($v);
                    if ($num !== null) return $num;
                }
            }
            return null;
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
