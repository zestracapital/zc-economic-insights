<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * DBnomics Data Source Adapter
 *
 * Supports fetching a single series using DBnomics v22 API.
 * - Open API (no key)
 * - Input: series_id string like "AMECO/ZUTN/EA19.1.0.0.0.ZUTN"
 * - Endpoint: https://api.db.nomics.world/v22/series?series_ids=PROVIDER/DATASET/SERIES
 * - Response: JSON with series array; each series has observations with time periods and values
 * - Caching: 20 minutes
 */
if (!class_exists('ZC_DMT_DataSource_DBnomics')) {
    class ZC_DMT_DataSource_DBnomics {

        /**
         * Get series for an indicator configured for DBnomics
         * @param object $indicator
         * @param string|null $start
         * @param string|null $end
         * @return array|WP_Error
         */
        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $config = self::parse_config($indicator);
            if (is_wp_error($config)) {
                return $config;
            }

            $series = null;
            $cache_key = 'zc_dmt_dbn_';

            // Helper to set cache and series
            $cache_and_return = function($key, $data) use (&$series) {
                $series = $data;
                set_transient($key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
                return $series;
            };

            if (!empty($config['json_url'])) {
                $cache_key .= 'json_' . md5($config['json_url']);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    // Try JSON first
                    $result = self::fetch_dbnomics_from_json_url($config['json_url']);
                    if (is_wp_error($result)) {
                        // Derive CSV alt and try
                        $alt = self::guess_alternate_url($config['json_url'], 'csv');
                        if ($alt) {
                            $result = self::fetch_dbnomics_from_csv_url($alt);
                        }
                        // As a last resort, try direct series_id if we can extract it
                        if (is_wp_error($result)) {
                            $sid = self::extract_series_id_from_url($config['json_url']);
                            if ($sid) {
                                $result = self::fetch_dbnomics_series($sid);
                            }
                        }
                        if (is_wp_error($result)) {
                            return $result;
                        }
                    }
                    $cache_and_return($cache_key, $result);
                }
            } elseif (!empty($config['csv_url'])) {
                $cache_key .= 'csv_' . md5($config['csv_url']);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    // Try CSV first
                    $result = self::fetch_dbnomics_from_csv_url($config['csv_url']);
                    if (is_wp_error($result)) {
                        // Derive JSON alt and try
                        $alt = self::guess_alternate_url($config['csv_url'], 'json');
                        if ($alt) {
                            $result = self::fetch_dbnomics_from_json_url($alt);
                        }
                        // Last resort try series_id
                        if (is_wp_error($result)) {
                            $sid = self::extract_series_id_from_url($config['csv_url']);
                            if ($sid) {
                                $result = self::fetch_dbnomics_series($sid);
                            }
                        }
                        if (is_wp_error($result)) {
                            return $result;
                        }
                    }
                    $cache_and_return($cache_key, $result);
                }
            } else {
                $series_id = $config['series_id'];
                $cache_key .= 'sid_' . md5($series_id);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $result = self::fetch_dbnomics_series($series_id);
                    if (is_wp_error($result)) {
                        return $result;
                    }
                    $cache_and_return($cache_key, $result);
                }
            }

            // Filter by date range if provided
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
                return new WP_Error('invalid_config', __('Invalid DBnomics configuration.', 'zc-dmt'));
            }

            // Accept any ONE of: json_url, csv_url, series_id
            $json_url = !empty($cfg['json_url']) ? esc_url_raw((string)$cfg['json_url']) : '';
            $csv_url  = !empty($cfg['csv_url'])  ? esc_url_raw((string)$cfg['csv_url'])  : '';

            $series_id = '';
            if (!empty($cfg['series_id'])) {
                $series_id = trim((string)$cfg['series_id']);
            } elseif (!empty($cfg['dbnomics_series_id'])) {
                $series_id = trim((string)$cfg['dbnomics_series_id']);
            }

            if ($json_url) {
                return array('json_url' => $json_url);
            }
            if ($csv_url) {
                return array('csv_url' => $csv_url);
            }
            if ($series_id !== '') {
                return array('series_id' => $series_id);
            }

            return new WP_Error('missing_config', __('Provide a DBnomics JSON URL, CSV URL, or Series ID.', 'zc-dmt'));
        }

        /**
         * Fetch series from DBnomics API and convert to [[Y-m-d, float|null], ...]
         */
        private static function fetch_dbnomics_series($series_id) {
            $url = 'https://api.db.nomics.world/v22/series?series_ids=' . rawurlencode($series_id);
            $args = array(
                'timeout'     => 20,
                'redirection' => 5,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'application/json'),
            );
            $res = self::http_get_with_retry($url, $args, 2);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('DBnomics fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('DBnomics HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return new WP_Error('invalid_json', __('DBnomics invalid JSON response.', 'zc-dmt'));
            }

            $series_list = isset($json['series']) && is_array($json['series']) ? $json['series'] : null;
            if (!$series_list || !isset($series_list[0])) {
                return new WP_Error('no_series', __('DBnomics: series not found.', 'zc-dmt'));
            }
            $s = $series_list[0];

            return self::normalize_dbnomics_json_shape($s);
        }

        /**
         * Fetch from a direct DBnomics JSON URL (v22), e.g.
         * https://api.db.nomics.world/v22/series/BOC/ECO_PROJECTIONS/C.1982Q2.Q?format=json&observations=1
         */
        private static function fetch_dbnomics_from_json_url($url) {
            $args = array(
                'timeout'     => 20,
                'redirection' => 5,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'application/json'),
            );
            $res = self::http_get_with_retry($url, $args, 2);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('DBnomics JSON fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('DBnomics JSON HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return new WP_Error('invalid_json', __('DBnomics invalid JSON (URL) response.', 'zc-dmt'));
            }

            // Handle both "series: [ {...} ]" and "series: { ... }" shapes
            if (isset($json['series'])) {
                $node = $json['series'];
                if (is_array($node)) {
                    // If it's a list, take first; if it's an associative array (single series), use directly
                    if (array_key_exists(0, $node)) {
                        $s = $node[0];
                    } else {
                        $s = $node;
                    }
                    return self::normalize_dbnomics_json_shape($s);
                }
            }

            // Some endpoints expose observations at the root
            if (isset($json['observations']) && is_array($json['observations'])) {
                $pairs = array();
                foreach ($json['observations'] as $period => $value) {
                    $date = self::normalize_period($period);
                    if ($date === null) continue;
                    $pairs[] = array($date, self::to_number($value));
                }
                if (empty($pairs)) {
                    return new WP_Error('no_observations', __('DBnomics: no observations parsed (JSON URL).', 'zc-dmt'));
                }
                usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
                return $pairs;
            }

            // Generic "data" fallback
            if (isset($json['data']) && is_array($json['data'])) {
                $pairs = array();
                foreach ($json['data'] as $row) {
                    if (!isset($row['period']) || !array_key_exists('value', $row)) continue;
                    $date = self::normalize_period((string)$row['period']);
                    if ($date === null) continue;
                    $pairs[] = array($date, self::to_number($row['value']));
                }
                if (!empty($pairs)) {
                    usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
                    return $pairs;
                }
            }

            return new WP_Error('unexpected_format', __('DBnomics JSON URL: unexpected structure.', 'zc-dmt'));
        }

        /**
         * Fetch from a direct DBnomics CSV URL (v22), e.g.
         * https://api.db.nomics.world/v22/series/BEA/NIUnderlyingDetail-U001BC/N239RC-M?format=csv
         */
        private static function fetch_dbnomics_from_csv_url($url) {
            $args = array(
                'timeout'     => 25,
                'redirection' => 5,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'text/csv,application/octet-stream'),
            );
            $res = self::http_get_with_retry($url, $args, 2);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('DBnomics CSV fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('DBnomics CSV HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            if (!is_string($body) || $body === '') {
                return new WP_Error('empty_csv', __('DBnomics CSV: empty body.', 'zc-dmt'));
            }

            $lines = preg_split('/\r\n|\r|\n/', $body);
            if (!is_array($lines) || empty($lines)) {
                return new WP_Error('invalid_csv', __('DBnomics CSV: invalid content.', 'zc-dmt'));
            }

            // Detect header (look for "period" and "value" or first two columns)
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

                $idxPeriod = array_search('period', $header);
                $idxValue  = array_search('value', $header);
                if ($idxPeriod === false || $idxValue === false) {
                    // Fallback: assume first two columns are period,value
                    $idxPeriod = 0;
                    $idxValue  = 1;
                }
                $period = isset($cols[$idxPeriod]) ? $cols[$idxPeriod] : '';
                $value  = isset($cols[$idxValue])  ? $cols[$idxValue]  : null;

                $date = self::normalize_period((string)$period);
                if ($date === null) continue;
                $pairs[] = array($date, self::to_number($value));
            }

            if (empty($pairs)) {
                return new WP_Error('no_observations', __('DBnomics CSV: no observations parsed.', 'zc-dmt'));
            }

            usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        /**
         * Try to guess the alternate URL (json<=>csv), preserving query where possible.
         */
        private static function guess_alternate_url($url, $targetFormat) {
            $parts = wp_parse_url($url);
            if (!$parts) return null;
            $query = array();
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
            }
            $query['format'] = $targetFormat;
            if ($targetFormat === 'json' && !isset($query['observations'])) {
                $query['observations'] = '1';
            }
            $built = (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
                   . (isset($parts['host']) ? $parts['host'] : '')
                   . (isset($parts['path']) ? $parts['path'] : '');
            $qs = http_build_query($query);
            if ($qs !== '') $built .= '?' . $qs;
            return $built;
        }

        /**
         * Extract series id from a v22 URL like:
         *  https://api.db.nomics.world/v22/series/PROV/DATASET/SER?format=json...
         */
        private static function extract_series_id_from_url($url) {
            $parts = wp_parse_url($url);
            if (!$parts || empty($parts['path'])) return null;
            $path = $parts['path'];
            $needle = '/v22/series/';
            $pos = strpos($path, $needle);
            if ($pos === false) return null;
            $rest = substr($path, $pos + strlen($needle));
            $rest = ltrim($rest, '/');
            return $rest !== '' ? $rest : null;
        }

        /**
         * GET with small retry/backoff for transient 404/429/5xx glitches.
         */
        private static function http_get_with_retry($url, $args, $retries = 1) {
            $attempts = max(1, intval($retries) + 1);
            $last = null;
            for ($i = 0; $i < $attempts; $i++) {
                $res = wp_remote_get($url, $args);
                if (!is_wp_error($res)) {
                    $code = wp_remote_retrieve_response_code($res);
                    if ($code >= 200 && $code < 300) {
                        return $res;
                    }
                }
                $last = $res;
                // small backoff (300-700ms)
                usleep(300000 + rand(0, 400000));
            }
            return is_wp_error($last) ? $last : new WP_Error('http_error', __('HTTP request failed after retries.', 'zc-dmt'));
        }

        /**
         * Normalize different JSON shapes returned by DBnomics "series" endpoints into [[date,value],...]
         */
        private static function normalize_dbnomics_json_shape($s) {
            $pairs = array();

            // Case 1: observations map (preferred)
            if (isset($s['observations']) && is_array($s['observations'])) {
                foreach ($s['observations'] as $period => $value) {
                    $date = self::normalize_period($period);
                    if ($date === null) continue;
                    $pairs[] = array($date, self::to_number($value));
                }
            }
            // Case 2: aligned arrays period[] and values[]
            elseif (isset($s['periods']) && isset($s['values']) && is_array($s['periods']) && is_array($s['values'])) {
                $len = min(count($s['periods']), count($s['values']));
                for ($i = 0; $i < $len; $i++) {
                    $date = self::normalize_period((string)$s['periods'][$i]);
                    if ($date === null) continue;
                    $pairs[] = array($date, self::to_number($s['values'][$i]));
                }
            } elseif (isset($s['data']) && is_array($s['data'])) {
                foreach ($s['data'] as $row) {
                    if (!isset($row['period']) || !array_key_exists('value', $row)) continue;
                    $date = self::normalize_period((string)$row['period']);
                    if ($date === null) continue;
                    $pairs[] = array($date, self::to_number($row['value']));
                }
            }

            if (empty($pairs)) {
                return new WP_Error('no_observations', __('DBnomics: no observations parsed.', 'zc-dmt'));
            }

            usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        /**
         * Normalize DBnomics period labels to Y-m-d
         * Examples: "2020", "2020-Q1", "2020-01", "2020-01-31"
         */
        private static function normalize_period($p) {
            $p = trim((string)$p);
            if ($p === '') return null;

            // Year only
            if (preg_match('~^\d{4}$~', $p)) {
                return $p . '-01-01';
            }

            // Year-Quarter (YYYY-Qn)
            if (preg_match('~^(\d{4})-Q([1-4])$~', $p, $m)) {
                $year = (int) $m[1];
                $q = (int) $m[2];
                $month = ($q - 1) * 3 + 1; // Q1->01, Q2->04, Q3->07, Q4->10
                return sprintf('%04d-%02d-01', $year, $month);
            }

            // Year-Month (YYYY-MM)
            if (preg_match('~^(\d{4})-(\d{2})$~', $p, $m)) {
                $year = (int) $m[1]; $mon = (int) $m[2];
                if ($mon >= 1 && $mon <= 12) {
                    return sprintf('%04d-%02d-01', $year, $mon);
                }
            }

            // Fallback to strtotime
            $ts = strtotime($p);
            if ($ts === false) {
                return null;
            }
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

        /**
         * Simple connectivity test
         */
        public static function test_connection($series_id) {
            $data = self::fetch_dbnomics_series($series_id);
            if (is_wp_error($data)) return $data;
            return array('success' => true, 'message' => sprintf(__('DBnomics OK (%d points)', 'zc-dmt'), count($data)));
        }
    }
}
