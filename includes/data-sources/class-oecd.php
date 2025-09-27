<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * OECD Data Source Adapter (SDMX-JSON)
 *
 * Fetches time series from OECD SDMX-JSON API.
 * - Open API (no key)
 * - Input:
 *    path (dataset/key string), e.g., "QNA/USA.B1_GE.CQRSA.Q/all"
 * - Endpoint:
 *    https://stats.oecd.org/SDMX-JSON/data/{path}
 * - Response: SDMX-JSON
 * - Strategy:
 *    - Parse dataSets[0].observations and structure.dimensions.observation
 *    - Identify position of the time dimension (usually last), read time labels from structure.dimensions.observation
 *    - Normalize time labels (YYYY, YYYY-Qn, YYYY-MM) to Y-m-d
 * - Caching: 20 minutes
 */
if (!class_exists('ZC_DMT_DataSource_OECD')) {
    class ZC_DMT_DataSource_OECD {

        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;

            $series = null;
            // caching key varies by input method
            if (isset($cfg['json_url'])) {
                $cache_key = 'zc_dmt_oecd_json_' . md5($cfg['json_url']);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $series = self::fetch_oecd_from_json_url($cfg['json_url']);
                    if (is_wp_error($series)) return $series;
                    set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
                }
            } elseif (isset($cfg['csv_url'])) {
                $cache_key = 'zc_dmt_oecd_csv_' . md5($cfg['csv_url']);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $series = self::fetch_oecd_from_csv_url($cfg['csv_url']);
                    if (is_wp_error($series)) return $series;
                    set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
                }
            } else {
                $path = $cfg['path'];
                $cache_key = 'zc_dmt_oecd_path_' . md5($path);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $series = self::fetch_oecd_series($path);
                    if (is_wp_error($series)) return $series;
                    set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
                }
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
                return new WP_Error('invalid_config', __('Invalid OECD configuration.', 'zc-dmt'));
            }
            $json_url = !empty($cfg['json_url']) ? esc_url_raw((string)$cfg['json_url']) : '';
            $csv_url  = !empty($cfg['csv_url'])  ? esc_url_raw((string)$cfg['csv_url'])  : '';
            $path     = isset($cfg['path']) ? trim((string)$cfg['path']) : '';

            if ($json_url) {
                return array('json_url' => $json_url);
            }
            if ($csv_url) {
                return array('csv_url' => $csv_url);
            }
            if ($path !== '') {
                return array('path' => $path);
            }
            return new WP_Error('missing_path', __('OECD: provide a JSON URL, CSV URL, or dataset/key path.', 'zc-dmt'));
        }

        private static function fetch_oecd_series($path) {
            // Example: https://stats.oecd.org/SDMX-JSON/data/QNA/USA.B1_GE.CQRSA.Q/all
            $url = 'https://stats.oecd.org/SDMX-JSON/data/' . ltrim($path, '/');
            $args = array(
                'timeout'     => 20,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('OECD fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('OECD HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return new WP_Error('invalid_json', __('OECD invalid JSON response.', 'zc-dmt'));
            }

            return self::parse_sdmx_json($json);
        }

        private static function fetch_oecd_from_json_url($url) {
            // Ensure JSON format is requested for sdmx.oecd.org REST if missing
            $url = self::ensure_oecd_json_url($url);

            $args = array(
                'timeout'     => 15,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'application/json'),
            );

            // Attempt primary JSON fetch
            $res = wp_remote_get($url, $args);
            $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
            $body = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);

            // If not OK, try alternates (JSON -> CSV -> stats JSON)
            if (is_wp_error($res) || $code < 200 || $code >= 300) {
                // Try adding/forcing format=sdmx-json
                $altJson = self::guess_oecd_alt_url($url, 'json');
                if ($altJson && $altJson !== $url) {
                    $res = wp_remote_get($altJson, $args);
                    $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
                    $body = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
                }
                // If still not OK, try CSV variant
                if (is_wp_error($res) || $code < 200 || $code >= 300) {
                    $csvUrl = self::guess_oecd_alt_url($url, 'csv');
                    if ($csvUrl) {
                        $csv = self::fetch_oecd_from_csv_url($csvUrl);
                        if (!is_wp_error($csv)) {
                            return $csv;
                        }
                    }
                }
                // As last resort, transform to legacy stats.oecd.org SDMX-JSON endpoint
                if (is_wp_error($res) || $code < 200 || $code >= 300) {
                    $statsUrl = self::transform_to_stats_json_url($url);
                    if ($statsUrl) {
                        $res = wp_remote_get($statsUrl, $args);
                        $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
                        $body = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
                    }
                }
            }

            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('OECD JSON fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('OECD JSON HTTP error: %d', 'zc-dmt'), $code));
            }

            $json = json_decode($body, true);
            if (!is_array($json)) {
                // If body isn't JSON, try CSV one last time if not already tried
                $csvUrl = self::guess_oecd_alt_url($url, 'csv');
                if ($csvUrl) {
                    $csv = self::fetch_oecd_from_csv_url($csvUrl);
                    if (!is_wp_error($csv)) {
                        return $csv;
                    }
                }
                return new WP_Error('invalid_json', __('OECD invalid JSON (URL) response.', 'zc-dmt'));
            }
            return self::parse_sdmx_json($json);
        }

        private static function fetch_oecd_from_csv_url($url) {
            $args = array(
                'timeout'     => 20,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'text/csv,application/octet-stream'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('OECD CSV fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('OECD CSV HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            if (!is_string($body) || $body === '') {
                return new WP_Error('empty_csv', __('OECD CSV: empty body.', 'zc-dmt'));
            }

            $lines = preg_split('/\r\n|\r|\n/', $body);
            if (!is_array($lines) || empty($lines)) {
                return new WP_Error('invalid_csv', __('OECD CSV: invalid content.', 'zc-dmt'));
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

                // Try common headers: TIME_PERIOD or TIME or PERIOD; value: OBS_VALUE or VALUE
                $timeIdx = null; $valIdx = null;
                $candidatesTime = array('time_period','time','period','obs_time');
                $candidatesVal  = array('obs_value','value','obsvalue');
                foreach ($candidatesTime as $h) {
                    $pos = array_search($h, $header);
                    if ($pos !== false) { $timeIdx = $pos; break; }
                }
                foreach ($candidatesVal as $h) {
                    $pos = array_search($h, $header);
                    if ($pos !== false) { $valIdx = $pos; break; }
                }
                if ($timeIdx === null || $valIdx === null) {
                    // fallback assume first two columns
                    $timeIdx = 0; $valIdx = 1;
                }

                $period = isset($cols[$timeIdx]) ? $cols[$timeIdx] : '';
                $value  = isset($cols[$valIdx])  ? $cols[$valIdx]  : null;

                $date = self::normalize_period_label((string)$period);
                $num  = self::to_number($value);
                if ($date !== null) {
                    $pairs[] = array($date, $num);
                }
            }

            if (empty($pairs)) {
                return new WP_Error('no_observations', __('OECD CSV: no observations parsed.', 'zc-dmt'));
            }
            usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        /**
         * If URL is sdmx.oecd.org REST without explicit format, add format=sdmx-json.
         */
        private static function ensure_oecd_json_url($url) {
            $parts = wp_parse_url($url);
            if (!$parts) return $url;
            $host = isset($parts['host']) ? $parts['host'] : '';
            $query = array();
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
            }
            if (strpos($host, 'sdmx.oecd.org') !== false) {
                if (empty($query['format']) && empty($query['contentType'])) {
                    $query['format'] = 'sdmx-json';
                }
            }
            $built = (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
                   . (isset($parts['host']) ? $parts['host'] : '')
                   . (isset($parts['path']) ? $parts['path'] : '');
            $qs = http_build_query($query);
            if ($qs !== '') $built .= '?' . $qs;
            return $built;
        }

        /**
         * Toggle between JSON and CSV variants on OECD endpoints while preserving other params.
         * $target: 'json' | 'csv'
         */
        private static function guess_oecd_alt_url($url, $target) {
            $parts = wp_parse_url($url);
            if (!$parts) return null;
            $query = array();
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
            }
            // Drop both, then set one
            unset($query['format'], $query['contentType']);
            if ($target === 'json') {
                $query['format'] = 'sdmx-json';
            } elseif ($target === 'csv') {
                $query['contentType'] = 'csv';
            } else {
                return null;
            }
            $built = (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
                   . (isset($parts['host']) ? $parts['host'] : '')
                   . (isset($parts['path']) ? $parts['path'] : '');
            $qs = http_build_query($query);
            if ($qs !== '') $built .= '?' . $qs;
            return $built;
        }

        /**
         * Convert sdmx.oecd.org/public/rest/data/... to stats.oecd.org/SDMX-JSON/data/... keeping query.
         */
        private static function transform_to_stats_json_url($url) {
            $parts = wp_parse_url($url);
            if (!$parts || empty($parts['path'])) return null;
            $path = $parts['path'];
            $needle = '/public/rest/data/';
            $pos = strpos($path, $needle);
            if ($pos === false) return null;
            $rest = ltrim(substr($path, $pos + strlen($needle)), '/');
            $query = array();
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
            }
            // Remove explicit content type flags
            unset($query['format'], $query['contentType']);
            $built = 'https://stats.oecd.org/SDMX-JSON/data/' . $rest;
            $qs = http_build_query($query);
            if ($qs !== '') $built .= '?' . $qs;
            return $built;
        }

        private static function parse_sdmx_json($json) {
            // SDMX-JSON core fields (common for both OECD endpoints)
            if (!isset($json['dataSets'][0]['observations']) || !isset($json['structure']['dimensions']['observation'])) {
                return new WP_Error('unexpected_format', __('OECD: unexpected SDMX-JSON shape.', 'zc-dmt'));
            }
            $observations = $json['dataSets'][0]['observations'];
            $obsDims = $json['structure']['dimensions']['observation'];

            // Detect the time dimension index by ID (TIME_PERIOD/TIME). Fall back to last if not found.
            $timeIndex = null;
            foreach ($obsDims as $idx => $dim) {
                $id = isset($dim['id']) ? strtoupper($dim['id']) : '';
                if ($id === 'TIME_PERIOD' || $id === 'TIME' || strpos($id, 'TIME') !== false) {
                    $timeIndex = (int)$idx;
                    break;
                }
            }
            if ($timeIndex === null) {
                $timeIndex = count($obsDims) - 1;
            }

            $timeDim = isset($obsDims[$timeIndex]) ? $obsDims[$timeIndex] : null;
            if (!$timeDim || !isset($timeDim['values']) || !is_array($timeDim['values'])) {
                return new WP_Error('no_time_dim', __('OECD: time dimension not found.', 'zc-dmt'));
            }
            $timeValues = $timeDim['values']; // array of {id: "2019-Q1" or "2019" etc}
            // Build index => label map for time
            $timeLabelsByPos = array();
            foreach ($timeValues as $idx => $obj) {
                if (!isset($obj['id'])) continue;
                $timeLabelsByPos[(int)$idx] = (string)$obj['id'];
            }

            // observations keys are like "0:0:4:12" referencing positions in each dimension
            // We only extract along time positions; if multiple series exist (country/measure), we take the first key per time
            $pairsByTimeIndex = array();
            foreach ($observations as $key => $valArr) {
                // valArr is like [ value, flags? ] but often [ value ]
                $parts = explode(':', $key);
                if (!isset($parts[$timeIndex])) {
                    continue;
                }
                $timePos = (int) $parts[$timeIndex];
                $val = is_array($valArr) ? (isset($valArr[0]) ? $valArr[0] : null) : $valArr;
                // Only keep the first occurrence per time index (or overwrite if needed)
                $pairsByTimeIndex[$timePos] = $val;
            }

            $pairs = array();
            ksort($pairsByTimeIndex);
            foreach ($pairsByTimeIndex as $tPos => $val) {
                $label = isset($timeLabelsByPos[$tPos]) ? $timeLabelsByPos[$tPos] : null;
                if ($label === null) continue;
                $date = self::normalize_period_label($label);
                $num  = self::to_number($val);
                if ($date !== null) {
                    $pairs[] = array($date, $num);
                }
            }

            if (empty($pairs)) {
                return new WP_Error('no_observations', __('OECD: no observations parsed.', 'zc-dmt'));
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

        public static function test_connection($path) {
            $data = self::fetch_oecd_series($path);
            if (is_wp_error($data)) return $data;
            return array('success' => true, 'message' => sprintf(__('OECD OK (%d points)', 'zc-dmt'), count($data)));
        }
    }
}
