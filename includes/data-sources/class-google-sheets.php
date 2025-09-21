<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Google Sheets Data Source Adapter
 *
 * Fetches a CSV export from Google Sheets and auto-detects date/value columns.
 * - Accepts either a published CSV URL (output=csv) or a standard share URL.
 * - Normalizes dates to Y-m-d and numbers to float (empty -> null).
 * - Caches parsed series with a transient for performance on shared hosting.
 */
if (!class_exists('ZC_DMT_DataSource_Google_Sheets')) {
    class ZC_DMT_DataSource_Google_Sheets {

        /**
         * Get series for an indicator that uses Google Sheets.
         * @param object $indicator Row from zc_dmt_indicators (must have source_config)
         * @param string|null $start Y-m-d
         * @param string|null $end Y-m-d
         * @return array|WP_Error { indicator: {...}, series: [[date, value], ...] }
         */
        public static function get_series_for_indicator($indicator, $start = null, $end = null) {
            $config = self::parse_config($indicator);
            if (is_wp_error($config)) {
                return $config;
            }
            $url = $config['url'];

            // Use a 10-minute cache per URL to minimize external calls.
            $cache_key = 'zc_dmt_gs_' . md5($url);
            $cached = get_transient($cache_key);
            if ($cached && is_array($cached) && isset($cached['series'])) {
                $series = $cached['series'];
            } else {
                $csv_url = self::normalize_to_csv_url($url);
                if (is_wp_error($csv_url)) {
                    return $csv_url;
                }
                $series = self::download_and_parse_csv($csv_url);
                if (is_wp_error($series)) {
                    return $series;
                }
                // Cache raw series (unfiltered) for reuse
                set_transient($cache_key, array('series' => $series), 10 * MINUTE_IN_SECONDS);
            }

            // Apply date filtering if provided
            $series = self::filter_series_by_range($series, $start, $end);

            // Build indicator response
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

        /**
         * Parse indicator->source_config to extract the url.
         */
        private static function parse_config($indicator) {
            $cfg = null;
            if (!empty($indicator->source_config)) {
                $cfg = json_decode($indicator->source_config, true);
            }
            if (!is_array($cfg)) {
                return new WP_Error('invalid_config', __('Invalid Google Sheets configuration.', 'zc-dmt'));
            }
            $url = '';
            if (!empty($cfg['url'])) {
                $url = trim((string) $cfg['url']);
            } elseif (!empty($cfg['google_sheets_url'])) {
                $url = trim((string) $cfg['google_sheets_url']);
            }
            if (empty($url)) {
                return new WP_Error('missing_url', __('Missing Google Sheets URL.', 'zc-dmt'));
            }
            return array('url' => $url);
        }

        /**
         * Convert a share URL to a CSV export URL when possible.
         * If it already looks like a CSV export, return as-is.
         */
        private static function normalize_to_csv_url($url) {
            // Already a CSV export (common: .../pub?gid=0&single=true&output=csv or .../export?format=csv&gid=)
            if (strpos($url, 'output=csv') !== false || strpos($url, 'format=csv') !== false) {
                return $url;
            }

            // Try to extract spreadsheet ID and gid to craft a CSV export
            // Share URLs often look like: https://docs.google.com/spreadsheets/d/FILE_ID/edit#gid=GID
            if (preg_match('~docs\.google\.com/spreadsheets/d/([^/]+)/~i', $url, $m)) {
                $file_id = $m[1];
                $gid = 0;
                // Try to find gid in URL fragment or query
                if (preg_match('~[#?&]gid=(\d+)~', $url, $gm)) {
                    $gid = $gm[1];
                }
                return sprintf('https://docs.google.com/spreadsheets/d/%s/export?format=csv&gid=%s', rawurlencode($file_id), rawurlencode($gid));
            }

            // Fallback to original; may work if Google serves CSV anyway
            return $url;
        }

        /**
         * Download CSV content and parse into series [[Y-m-d, float|null], ...]
         * Auto-detect date and value columns by scanning the first rows.
         */
        private static function download_and_parse_csv($csv_url) {
            $args = array(
                'timeout'     => 12,
                'redirection' => 3,
                'sslverify'   => true,
            );
            $res = wp_remote_get($csv_url, $args);
            if (is_wp_error($res)) {
                return new WP_Error('fetch_failed', sprintf(__('Failed to fetch Google Sheets: %s', 'zc-dmt'), $res->get_error_message()));
            }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                return new WP_Error('http_error', sprintf(__('Google Sheets HTTP error: %d', 'zc-dmt')), array('status' => $code));
            }
            $body = wp_remote_retrieve_body($res);
            if ($body === '' || $body === null) {
                return new WP_Error('empty_body', __('Empty Google Sheets response.', 'zc-dmt'));
            }

            // Robust CSV parsing using a temporary stream (handles quoted fields/newlines)
            $rows = array();
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $body);
            rewind($stream);
            while (($row = fgetcsv($stream)) !== false) {
                if (!is_array($row)) { continue; }
                // Trim cells and skip fully empty rows
                $allEmpty = true;
                foreach ($row as $k => $v) {
                    $row[$k] = is_string($v) ? trim($v) : $v;
                    if ($row[$k] !== '' && $row[$k] !== null) { $allEmpty = false; }
                }
                if ($allEmpty) { continue; }
                $rows[] = $row;
            }
            fclose($stream);
            if (empty($rows)) {
                return new WP_Error('parse_error', __('No rows found in Google Sheets CSV.', 'zc-dmt'));
            }

            // Auto-detect columns
            list($date_col, $value_col) = self::detect_columns($rows);

            if ($date_col === null || $value_col === null) {
                return new WP_Error('detect_failed', __('Failed to auto-detect date/value columns.', 'zc-dmt'));
            }

            // Build series
            $series = array();
            // If first row looks like a header (non-date/non-numeric), skip it
            $start_index = 0;
            if (!self::looks_like_date($rows[0][$date_col]) && !is_numeric(self::to_number($rows[0][$value_col]))) {
                $start_index = 1;
            }

            $count = count($rows);
            for ($i = $start_index; $i < $count; $i++) {
                $row = $rows[$i];
                if (!is_array($row)) continue;
                $date_raw = isset($row[$date_col]) ? trim((string) $row[$date_col]) : '';
                if ($date_raw === '') continue;
                $ymd = self::normalize_date($date_raw);
                if ($ymd === null) continue;

                $val_raw = isset($row[$value_col]) ? (string) $row[$value_col] : '';
                $num = self::to_number($val_raw);
                $val = ($num === '' || $num === null) ? null : (float) $num;

                $series[] = array($ymd, $val);
            }

            // Sort ascending by date (string compare works for Y-m-d)
            usort($series, function($a, $b){
                return strcmp($a[0], $b[0]);
            });

            return $series;
        }

        /**
         * Determine which columns are date and value by sampling first rows.
         * Returns array(date_col_index|null, value_col_index|null)
         */
        private static function detect_columns($rows) {
            $max_scan = min(12, count($rows));
            $col_scores = array(); // idx => ['date_hits'=>n, 'num_hits'=>n]
            $max_cols = 0;
            for ($i = 0; $i < $max_scan; $i++) {
                $row = $rows[$i];
                if (!is_array($row)) continue;
                $max_cols = max($max_cols, count($row));
            }
            for ($c = 0; $c < $max_cols; $c++) {
                $col_scores[$c] = array('date_hits' => 0, 'num_hits' => 0);
            }

            for ($i = 0; $i < $max_scan; $i++) {
                $row = $rows[$i];
                if (!is_array($row)) continue;
                for ($c = 0; $c < $max_cols; $c++) {
                    $val = isset($row[$c]) ? trim((string) $row[$c]) : '';
                    if ($val === '') continue;
                    if (self::looks_like_date($val)) {
                        $col_scores[$c]['date_hits']++;
                    } else {
                        // number heuristic
                        $num = self::to_number($val);
                        if ($num !== '' && $num !== null && is_numeric($num)) {
                            $col_scores[$c]['num_hits']++;
                        }
                    }
                }
            }

            // Pick date column as the one with highest date_hits
            $date_col = null;
            $date_max = -1;
            foreach ($col_scores as $idx => $score) {
                if ($score['date_hits'] > $date_max) {
                    $date_max = $score['date_hits'];
                    $date_col = $idx;
                }
            }
            if ($date_max <= 0) {
                $date_col = null;
            }

            // Pick value column as the numeric-rich column excluding date_col
            $value_col = null;
            $num_max = -1;
            foreach ($col_scores as $idx => $score) {
                if ($idx === $date_col) continue;
                if ($score['num_hits'] > $num_max) {
                    $num_max = $score['num_hits'];
                    $value_col = $idx;
                }
            }
            if ($num_max <= 0) {
                $value_col = null;
            }

            return array($date_col, $value_col);
        }

        /**
         * Quick heuristic to see if a string could be a date.
         */
        private static function looks_like_date($s) {
            if ($s === '' || $s === null) return false;
            // Rely on normalize_date to support many formats (YYYY, YYYY-MM, YYYY-MM-DD, d/m/Y, m/d/Y, "Jan 2024", etc.)
            return self::normalize_date($s) !== null;
        }

        /**
         * Normalize a date string to Y-m-d. Returns null if invalid.
         */
        private static function normalize_date($s) {
            $s = trim((string) $s);
            if ($s === '') return null;
 
            // YYYY (year only) => Jan 1
            if (preg_match('~^[0-9]{4}$~', $s)) {
                return $s . '-01-01';
            }
            // YYYY-MM (monthly) => first day of month
            if (preg_match('~^([0-9]{4})-([0-9]{1,2})$~', $s, $mm)) {
                $year = (int)$mm[1];
                $mon  = (int)$mm[2];
                if ($mon >= 1 && $mon <= 12) {
                    return sprintf('%04d-%02d-01', $year, $mon);
                }
            }
 
            // Try strtotime for flexible formats (e.g., "Jan 2024", "2024/01/31", "31.01.2024")
            $ts = strtotime($s);
            if ($ts === false) {
                // Try replacing '.' with '-' and re-parse
                $s2 = str_replace('.', '-', $s);
                $ts = strtotime($s2);
                if ($ts === false) return null;
            }
            return gmdate('Y-m-d', $ts);
        }

        /**
         * Convert number-like string to canonical numeric representation.
         * Removes thousands separators and handles commas in decimals.
         */
        private static function to_number($s) {
            $s = trim((string) $s);
            if ($s === '') return '';
            // Remove percentage sign and common non-numeric suffixes/prefixes
            $s = str_replace('%', '', $s);
            // Normalize thousand separators and decimal marks
            if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
                // Likely commas are thousands separators
                $s = str_replace(',', '', $s);
            } elseif (strpos($s, ',') !== false && strpos($s, '.') === false) {
                // Comma decimal
                $s = str_replace(',', '.', $s);
            }
            // Strip spaces
            $s = str_replace(' ', '', $s);
            // Remove any characters except digits, sign, decimal point and exponent
            // Keep: 0-9 . - + e E
            $s = preg_replace('/[^0-9\.\-\+eE]/', '', $s);
            if ($s === '-' || $s === '+') return '';
            return $s;
        }

        /**
         * Filter a series by start/end (Y-m-d)
         */
        private static function filter_series_by_range($series, $start, $end) {
            if (!$series || (!($start) && !($end))) return $series;
            $start = $start ? self::normalize_date($start) : null;
            $end   = $end ? self::normalize_date($end) : null;
            $out = array();
            foreach ($series as $row) {
                $d = $row[0];
                if ($start && strcmp($d, $start) < 0) continue;
                if ($end && strcmp($d, $end) > 0) continue;
                $out[] = $row;
            }
            return $out;
        }
    }
}
