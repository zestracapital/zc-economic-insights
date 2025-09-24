<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Yahoo Finance Data Source Adapter
 *
 * Supported methods (pick ONE in source_config):
 *  - json_url: Direct Yahoo chart JSON URL (e.g., https://query1.finance.yahoo.com/v8/finance/chart/AAPL?interval=1d&range=1y)
 *  - csv_url:  Direct Yahoo historical CSV URL (e.g., https://query1.finance.yahoo.com/v7/finance/download/AAPL?...&interval=1d&events=history)
 *  - symbol:   { "symbol": "AAPL", "range": "1y", "interval": "1d" }  (uses chart JSON, falls back to CSV if needed)
 *
 * Output normalized to: [ [Y-m-d, float|null], ... ] sorted by date asc
 * Caching: 20 minutes via transients
 */
if (!class_exists('ZC_DMT_DataSource_YahooFinance')) {
    class ZC_DMT_DataSource_YahooFinance {

        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $cfg = self::parse_config($indicator);
            if (is_wp_error($cfg)) return $cfg;

            $cache_key = 'zc_dmt_yf_';
            $series = null;

            if (isset($cfg['json_url'])) {
                $cache_key .= 'json_' . md5($cfg['json_url']);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $series = self::fetch_from_yahoo_json($cfg['json_url']);
                    if (is_wp_error($series)) return $series;
                    set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
                }
            } elseif (isset($cfg['csv_url'])) {
                $cache_key .= 'csv_' . md5($cfg['csv_url']);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $series = self::fetch_from_yahoo_csv($cfg['csv_url']);
                    if (is_wp_error($series)) return $series;
                    set_transient($cache_key, array('series' => $series), 20 * MINUTE_IN_SECONDS);
                }
            } else {
                // symbol route
                $symbol = $cfg['symbol']['symbol'];
                $range = $cfg['symbol']['range'];
                $interval = $cfg['symbol']['interval'];
                $jsonUrl = 'https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($symbol) . '?interval=' . rawurlencode($interval) . '&range=' . rawurlencode($range);

                $cache_key .= 'sym_' . md5($jsonUrl);
                $cached = get_transient($cache_key);
                if ($cached && is_array($cached) && isset($cached['series'])) {
                    $series = $cached['series'];
                } else {
                    $series = self::fetch_from_yahoo_json($jsonUrl);
                    if (is_wp_error($series)) {
                        // Fallback to CSV historical download for last 2y
                        $period2 = time();
                        $period1 = $period2 - 60 * 60 * 24 * 365 * 2;
                        $csvUrl = sprintf('https://query1.finance.yahoo.com/v7/finance/download/%s?period1=%d&period2=%d&interval=%s&events=history&includeAdjustedClose=true',
                            rawurlencode($symbol), $period1, $period2, rawurlencode($interval)
                        );
                        $series = self::fetch_from_yahoo_csv($csvUrl);
                        if (is_wp_error($series)) return $series;
                    }
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
                return new WP_Error('invalid_config', __('Invalid Yahoo Finance configuration.', 'zc-dmt'));
            }
            $json_url = !empty($cfg['json_url']) ? esc_url_raw((string)$cfg['json_url']) : '';
            $csv_url  = !empty($cfg['csv_url'])  ? esc_url_raw((string)$cfg['csv_url'])  : '';
            $symbol   = null;
            if (!empty($cfg['symbol'])) {
                if (is_array($cfg['symbol'])) {
                    $sym = isset($cfg['symbol']['symbol']) ? trim((string)$cfg['symbol']['symbol']) : '';
                    $range = isset($cfg['symbol']['range']) ? trim((string)$cfg['symbol']['range']) : '1y';
                    $interval = isset($cfg['symbol']['interval']) ? trim((string)$cfg['symbol']['interval']) : '1d';
                    if ($sym !== '') $symbol = array('symbol' => $sym, 'range' => $range, 'interval' => $interval);
                } elseif (is_string($cfg['symbol'])) {
                    $symbol = array('symbol' => trim($cfg['symbol']), 'range' => '1y', 'interval' => '1d');
                }
            }

            if ($json_url) return array('json_url' => $json_url);
            if ($csv_url)  return array('csv_url' => $csv_url);
            if ($symbol)   return array('symbol' => $symbol);

            return new WP_Error('missing_config', __('Provide a Yahoo Finance JSON URL, CSV URL, or {symbol, range, interval}.', 'zc-dmt'));
        }

        private static function fetch_from_yahoo_json($url) {
            $args = array(
                'timeout'     => 15,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'application/json'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('Yahoo JSON fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('Yahoo JSON HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return new WP_Error('invalid_json', __('Yahoo invalid JSON response.', 'zc-dmt'));
            }

            // Expected chart JSON shape
            if (!isset($json['chart']['result'][0])) {
                return new WP_Error('unexpected_format', __('Yahoo JSON: unexpected structure.', 'zc-dmt'));
            }
            $res0 = $json['chart']['result'][0];
            if (!isset($res0['timestamp']) || !isset($res0['indicators']['quote'][0]['close'])) {
                return new WP_Error('no_observations', __('Yahoo JSON: missing series timestamps or close prices.', 'zc-dmt'));
            }
            $timestamps = $res0['timestamp'];
            $closes = $res0['indicators']['quote'][0]['close'];

            $pairs = array();
            $len = min(count($timestamps), count($closes));
            for ($i = 0; $i < $len; $i++) {
                $ts = $timestamps[$i];
                $val = $closes[$i];
                if (!is_numeric($ts)) continue;
                $date = gmdate('Y-m-d', intval($ts));
                $pairs[] = array($date, self::to_number($val));
            }
            if (empty($pairs)) {
                return new WP_Error('no_observations', __('Yahoo JSON: no observations parsed.', 'zc-dmt'));
            }
            usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        private static function fetch_from_yahoo_csv($url) {
            $args = array(
                'timeout'     => 20,
                'redirection' => 3,
                'sslverify'   => true,
                'headers'     => array('User-Agent' => 'ZC-DMT-Plugin/1.0', 'Accept' => 'text/csv,application/octet-stream'),
            );
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('Yahoo CSV fetch failed: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('Yahoo CSV HTTP error: %d', 'zc-dmt'), $code));
            }
            $body = wp_remote_retrieve_body($res);
            if (!is_string($body) || $body === '') {
                return new WP_Error('empty_csv', __('Yahoo CSV: empty body.', 'zc-dmt'));
            }

            $lines = preg_split('/\r\n|\r|\n/', $body);
            if (!is_array($lines) || empty($lines)) {
                return new WP_Error('invalid_csv', __('Yahoo CSV: invalid content.', 'zc-dmt'));
            }

            $pairs = array();
            $header = null;
            foreach ($lines as $i => $line) {
                $line = trim($line);
                if ($line === '' || stripos($line, 'Date') === 0) {
                    // header; will be handled below
                }
                $cols = str_getcsv($line);
                if ($i === 0) {
                    $header = array_map('strtolower', $cols);
                    continue;
                }
                if (!$header) continue;

                $idxDate = array_search('date', $header);
                $idxClose = array_search('close', $header);
                if ($idxDate === false || $idxClose === false) continue;

                $date = isset($cols[$idxDate]) ? $cols[$idxDate] : '';
                $close = isset($cols[$idxClose]) ? $cols[$idxClose] : null;
                $dateNorm = self::normalize_date((string)$date);
                if ($dateNorm === null) continue;
                $pairs[] = array($dateNorm, self::to_number($close));
            }

            if (empty($pairs)) {
                return new WP_Error('no_observations', __('Yahoo CSV: no observations parsed.', 'zc-dmt'));
            }
            usort($pairs, function($a, $b){ return strcmp($a[0], $b[0]); });
            return $pairs;
        }

        private static function normalize_date($s) {
            $s = trim((string)$s);
            if ($s === '') return null;
            // Expect Y-m-d
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

        public static function test_connection_symbol($symbol, $range = '1y', $interval = '1d') {
            $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($symbol) . '?interval=' . rawurlencode($interval) . '&range=' . rawurlencode($range);
            $data = self::fetch_from_yahoo_json($url);
            if (is_wp_error($data)) return $data;
            return array('success' => true, 'message' => sprintf(__('Yahoo OK (%d points)', 'zc-dmt'), count($data)));
        }
    }
}
