<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Indicators admin page (simple UI):
 * - List existing indicators
 * - Add new indicator (name, slug, description)
 * - Quick add/replace one data point (date, value) for an indicator
 */

function zc_dmt_render_indicators_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = '';

    // Handle form submissions
    if (!empty($_POST['zc_dmt_indicators_action'])) {
        if (!isset($_POST['zc_dmt_indicators_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['zc_dmt_indicators_nonce']), 'zc_dmt_indicators_action')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed. Please try again.', 'zc-dmt') . '</p></div>';
        } else {
            $action = sanitize_text_field($_POST['zc_dmt_indicators_action']);

            if ($action === 'add_indicator') {
                $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
                $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
                $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';

                if (!$name || !$slug) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Name and Slug are required.', 'zc-dmt') . '</p></div>';
                } else {
                    $source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : 'manual';
                    $google_url  = isset($_POST['google_sheets_url']) ? sanitize_text_field($_POST['google_sheets_url']) : '';
                    $source_config = null;

                    if ($source_type === 'google_sheets') {
                        if (empty($google_url)) {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please paste a Google Sheets URL for this source type.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        } else {
                            $source_config = array('url' => $google_url);
                        }
                    } elseif ($source_type === 'fred') {
                        $fred_series_id = isset($_POST['fred_series_id']) ? sanitize_text_field($_POST['fred_series_id']) : '';
                        if (empty($fred_series_id)) {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please enter a FRED series ID for this source type.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        } else {
                            $source_config = array('series_id' => $fred_series_id);
                        }
                    } elseif ($source_type === 'world_bank') {
                        $wb_country = isset($_POST['wb_country_code']) ? strtoupper(sanitize_text_field($_POST['wb_country_code'])) : '';
                        $wb_indicator = isset($_POST['wb_indicator_code']) ? sanitize_text_field($_POST['wb_indicator_code']) : '';
                        if (empty($wb_country) || empty($wb_indicator)) {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please enter both World Bank country code and indicator code.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        } else {
                            $source_config = array('country_code' => $wb_country, 'indicator_code' => $wb_indicator);
                        }
                    } elseif ($source_type === 'dbnomics') {
                        $json_url = isset($_POST['dbnomics_json_url']) ? esc_url_raw($_POST['dbnomics_json_url']) : '';
                        $csv_url  = isset($_POST['dbnomics_csv_url']) ? esc_url_raw($_POST['dbnomics_csv_url']) : '';
                        $dbn      = isset($_POST['dbnomics_series_id']) ? sanitize_text_field($_POST['dbnomics_series_id']) : '';

                        if (!empty($json_url)) {
                            $source_config = array('json_url' => $json_url);
                        } elseif (!empty($csv_url)) {
                            $source_config = array('csv_url' => $csv_url);
                        } elseif (!empty($dbn)) {
                            $source_config = array('series_id' => $dbn);
                        } else {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide a DBnomics Series ID, JSON URL, or CSV URL.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        }
                    } elseif ($source_type === 'eurostat') {
                        $ds = isset($_POST['eurostat_dataset_code']) ? sanitize_text_field($_POST['eurostat_dataset_code']) : '';
                        $qr = isset($_POST['eurostat_query']) ? sanitize_text_field($_POST['eurostat_query']) : '';
                        if (empty($ds)) {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please enter a Eurostat dataset code.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        } else {
                            $source_config = array('dataset_code' => $ds, 'query' => $qr);
                        }
                    } elseif ($source_type === 'oecd') {
                        // Accept any ONE: JSON URL (Developer API), CSV URL (Download), or dataset/key path (stats endpoint)
                        $json_url = isset($_POST['oecd_json_url']) ? esc_url_raw($_POST['oecd_json_url']) : '';
                        $csv_url  = isset($_POST['oecd_csv_url']) ? esc_url_raw($_POST['oecd_csv_url']) : '';
                        $path     = isset($_POST['oecd_path']) ? sanitize_text_field($_POST['oecd_path']) : '';

                        if (!empty($json_url)) {
                            $source_config = array('json_url' => $json_url);
                        } elseif (!empty($csv_url)) {
                            $source_config = array('csv_url' => $csv_url);
                        } elseif (!empty($path)) {
                            $source_config = array('path' => $path);
                        } else {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide an OECD JSON URL, CSV URL, or dataset/key path.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        }
                     } elseif ($source_type === 'uk_ons') {
                        // Accept ONE: JSON URL, CSV URL, or Timeseries (dataset_id + series_id)
                        $uk_json = isset($_POST['uk_json_url']) ? esc_url_raw($_POST['uk_json_url']) : '';
                        $uk_csv  = isset($_POST['uk_csv_url']) ? esc_url_raw($_POST['uk_csv_url']) : '';
                        $uk_ds   = isset($_POST['uk_dataset_id']) ? sanitize_text_field($_POST['uk_dataset_id']) : '';
                        $uk_sid  = isset($_POST['uk_series_id']) ? sanitize_text_field($_POST['uk_series_id']) : '';
                        $uk_q    = isset($_POST['uk_query']) ? sanitize_text_field($_POST['uk_query']) : '';

                        if (!empty($uk_json)) {
                            $source_config = array('json_url' => $uk_json);
                        } elseif (!empty($uk_csv)) {
                            $source_config = array('csv_url' => $uk_csv);
                        } elseif (!empty($uk_ds) && !empty($uk_sid)) {
                            $ts = array('dataset_id' => $uk_ds, 'series_id' => $uk_sid);
                            if (!empty($uk_q)) $ts['query'] = $uk_q;
                            $source_config = array('timeseries' => $ts);
                        } else {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide a UK ONS JSON URL, CSV URL, or Timeseries (Dataset + Series).', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        }
                    } elseif ($source_type === 'yahoo_finance') {
                        // Accept: either symbol (with range, interval) OR json_url OR csv_url
                        $yf_symbol   = isset($_POST['yf_symbol']) ? sanitize_text_field($_POST['yf_symbol']) : '';
                        $yf_range    = isset($_POST['yf_range']) ? sanitize_text_field($_POST['yf_range']) : '1y';
                        $yf_interval = isset($_POST['yf_interval']) ? sanitize_text_field($_POST['yf_interval']) : '1d';
                        $yf_json     = isset($_POST['yf_json_url']) ? esc_url_raw($_POST['yf_json_url']) : '';
                        $yf_csv      = isset($_POST['yf_csv_url']) ? esc_url_raw($_POST['yf_csv_url']) : '';

                        if (!empty($yf_json)) {
                            $source_config = array('json_url' => $yf_json);
                        } elseif (!empty($yf_csv)) {
                            $source_config = array('csv_url' => $yf_csv);
                        } elseif (!empty($yf_symbol)) {
                            $source_config = array('symbol' => array(
                                'symbol' => $yf_symbol,
                                'range' => $yf_range ? $yf_range : '1y',
                                'interval' => $yf_interval ? $yf_interval : '1d',
                            ));
                        } else {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide a Yahoo symbol, JSON URL, or CSV URL.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        }
                    } elseif ($source_type === 'google_finance') {
                        // Accept: CSV URL (recommended) or JSON URL
                        $gf_csv  = isset($_POST['gf_csv_url']) ? esc_url_raw($_POST['gf_csv_url']) : '';
                        $gf_json = isset($_POST['gf_json_url']) ? esc_url_raw($_POST['gf_json_url']) : '';

                        if (!empty($gf_csv)) {
                            $source_config = array('csv_url' => $gf_csv);
                        } elseif (!empty($gf_json)) {
                            $source_config = array('json_url' => $gf_json);
                        } else {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide a Google Finance CSV URL or JSON URL.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        }
                    } elseif ($source_type === 'quandl') {
                        // Accept: JSON URL, CSV URL, or Dataset (database + dataset + optional api_key/collapse/dates)
                        $qd_json = isset($_POST['quandl_json_url']) ? esc_url_raw($_POST['quandl_json_url']) : '';
                        $qd_csv  = isset($_POST['quandl_csv_url']) ? esc_url_raw($_POST['quandl_csv_url']) : '';
                        $qd_db   = isset($_POST['quandl_db']) ? sanitize_text_field($_POST['quandl_db']) : '';
                        $qd_ds   = isset($_POST['quandl_ds']) ? sanitize_text_field($_POST['quandl_ds']) : '';
                        $qd_key  = isset($_POST['quandl_api_key']) ? sanitize_text_field($_POST['quandl_api_key']) : '';
                        $qd_col  = isset($_POST['quandl_collapse']) ? sanitize_text_field($_POST['quandl_collapse']) : '';
                        $qd_sd   = isset($_POST['quandl_start_date']) ? sanitize_text_field($_POST['quandl_start_date']) : '';
                        $qd_ed   = isset($_POST['quandl_end_date']) ? sanitize_text_field($_POST['quandl_end_date']) : '';

                        if (!empty($qd_json)) {
                            $source_config = array('json_url' => $qd_json);
                        } elseif (!empty($qd_csv)) {
                            $source_config = array('csv_url' => $qd_csv);
                        } elseif (!empty($qd_db) && !empty($qd_ds)) {
                            $dataset = array('database' => $qd_db, 'dataset' => $qd_ds);
                            if (!empty($qd_key)) $dataset['api_key'] = $qd_key;
                            if (!empty($qd_col)) $dataset['collapse'] = $qd_col;
                            if (!empty($qd_sd))  $dataset['start_date'] = $qd_sd;
                            if (!empty($qd_ed))  $dataset['end_date'] = $qd_ed;
                            $source_config = array('dataset' => $dataset);
                        } else {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide a Quandl JSON URL, CSV URL, or Dataset (database + dataset).', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        }
                    } elseif ($source_type === 'bank_of_canada') {
                        // Accept: JSON URL, CSV URL, or Series code with optional dates
                        $boc_json = isset($_POST['boc_json_url']) ? esc_url_raw($_POST['boc_json_url']) : '';
                        $boc_csv  = isset($_POST['boc_csv_url']) ? esc_url_raw($_POST['boc_csv_url']) : '';
                        $boc_sid  = isset($_POST['boc_series']) ? sanitize_text_field($_POST['boc_series']) : '';
                        $boc_sd   = isset($_POST['boc_start_date']) ? sanitize_text_field($_POST['boc_start_date']) : '';
                        $boc_ed   = isset($_POST['boc_end_date']) ? sanitize_text_field($_POST['boc_end_date']) : '';

                        if (!empty($boc_json)) {
                            $source_config = array('json_url' => $boc_json);
                        } elseif (!empty($boc_csv)) {
                            $source_config = array('csv_url' => $boc_csv);
                        } elseif (!empty($boc_sid)) {
                            $series = array('series' => $boc_sid);
                            if (!empty($boc_sd)) $series['start_date'] = $boc_sd;
                            if (!empty($boc_ed)) $series['end_date'] = $boc_ed;
                            $source_config = array('series' => $series);
                        } else {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide a Bank of Canada JSON URL, CSV URL, or Series code.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        }
                    } elseif ($source_type === 'statcan') {
                        // Accept: JSON URL or CSV URL
                        $sc_json = isset($_POST['statcan_json_url']) ? esc_url_raw($_POST['statcan_json_url']) : '';
                        $sc_csv  = isset($_POST['statcan_csv_url']) ? esc_url_raw($_POST['statcan_csv_url']) : '';
                        if (!empty($sc_json)) {
                            $source_config = array('json_url' => $sc_json);
                        } elseif (!empty($sc_csv)) {
                            $source_config = array('csv_url' => $sc_csv);
                        } else {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide a StatCan JSON URL or CSV URL.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        }
                    } elseif ($source_type === 'australia_rba') {
                        // Accept: CSV URL (recommended) or JSON URL
                        $rba_csv  = isset($_POST['rba_csv_url']) ? esc_url_raw($_POST['rba_csv_url']) : '';
                        $rba_json = isset($_POST['rba_json_url']) ? esc_url_raw($_POST['rba_json_url']) : '';
                        if (!empty($rba_csv)) {
                            $source_config = array('csv_url' => $rba_csv);
                        } elseif (!empty($rba_json)) {
                            $source_config = array('json_url' => $rba_json);
                        } else {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide an RBA CSV URL or JSON URL.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        }
                    } elseif ($source_type === 'ecb') {
                        // Accept: CSV URL, JSON URL, or ECB SDW path
                        $ecb_csv  = isset($_POST['ecb_csv_url']) ? esc_url_raw($_POST['ecb_csv_url']) : '';
                        $ecb_json = isset($_POST['ecb_json_url']) ? esc_url_raw($_POST['ecb_json_url']) : '';
                        $ecb_path = isset($_POST['ecb_path']) ? sanitize_text_field($_POST['ecb_path']) : '';
                        if (!empty($ecb_csv)) {
                            $source_config = array('csv_url' => $ecb_csv);
                        } elseif (!empty($ecb_json)) {
                            $source_config = array('json_url' => $ecb_json);
                        } elseif (!empty($ecb_path)) {
                            $source_config = array('path' => $ecb_path);
                        } else {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide an ECB CSV URL, JSON URL, or SDW path.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        }
                    } elseif ($source_type === 'universal_csv') {
                        // Accept: CSV URL with optional column mapping
                        $ucsv_url  = isset($_POST['universal_csv_url']) ? esc_url_raw($_POST['universal_csv_url']) : '';
                        $ucsv_date = isset($_POST['uni_date_col']) ? sanitize_text_field($_POST['uni_date_col']) : '';
                        $ucsv_val  = isset($_POST['uni_value_col']) ? sanitize_text_field($_POST['uni_value_col']) : '';
                        $ucsv_delim= isset($_POST['uni_delimiter']) ? sanitize_text_field($_POST['uni_delimiter']) : '';
                        $ucsv_skip = isset($_POST['uni_skip_rows']) ? intval($_POST['uni_skip_rows']) : 0;

                        if (!empty($ucsv_url)) {
                            $cfg = array('csv_url' => $ucsv_url);
                            if ($ucsv_date !== '') $cfg['date_col']  = is_numeric($ucsv_date) ? (int)$ucsv_date : $ucsv_date;
                            if ($ucsv_val  !== '') $cfg['value_col'] = is_numeric($ucsv_val)  ? (int)$ucsv_val  : $ucsv_val;
                            if ($ucsv_delim !== '') $cfg['delimiter'] = $ucsv_delim;
                            if ($ucsv_skip > 0)      $cfg['skip_rows'] = $ucsv_skip;
                            $source_config = $cfg;
                        } else {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide a Universal CSV URL.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        }
                    } elseif ($source_type === 'universal_json') {
                        // Accept: JSON URL with optional root/date_key/value_key/map
                        $uj_url      = isset($_POST['universal_json_url']) ? esc_url_raw($_POST['universal_json_url']) : '';
                        $uj_root     = isset($_POST['uni_root']) ? sanitize_text_field($_POST['uni_root']) : '';
                        $uj_date_key = isset($_POST['uni_date_key']) ? sanitize_text_field($_POST['uni_date_key']) : '';
                        $uj_value_key= isset($_POST['uni_value_key']) ? sanitize_text_field($_POST['uni_value_key']) : '';
                        $uj_map_json = isset($_POST['uni_map_json']) ? (string) wp_unslash($_POST['uni_map_json']) : '';

                        if (!empty($uj_url)) {
                            $cfg = array('json_url' => $uj_url);
                            if ($uj_root !== '')      $cfg['root']      = $uj_root;
                            if ($uj_date_key !== '')  $cfg['date_key']  = $uj_date_key;
                            if ($uj_value_key !== '') $cfg['value_key'] = $uj_value_key;
                            if ($uj_map_json !== '') {
                                $map = json_decode($uj_map_json, true);
                                if (is_array($map)) $cfg['map'] = $map;
                            }
                            $source_config = $cfg;
                        } else {
                            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide a Universal JSON URL.', 'zc-dmt') . '</p></div>';
                            $source_type = 'manual';
                        }
                    }

                    $res = ZC_DMT_Indicators::create_indicator($name, $slug, $description, $source_type, $source_config, 1);
                    if (is_wp_error($res)) {
                        echo '<div class="notice notice-error"><p>' . esc_html($res->get_error_message()) . '</p></div>';
                    } else {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Indicator created successfully.', 'zc-dmt') . '</p></div>';
                    }
                }
            }

            if ($action === 'add_datapoint') {
                $indicator_id = isset($_POST['indicator_id']) ? intval($_POST['indicator_id']) : 0;
                $date = isset($_POST['obs_date']) ? sanitize_text_field($_POST['obs_date']) : '';
                $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';

                if ($indicator_id && $date !== '') {
                    $res = ZC_DMT_Indicators::add_data_point($indicator_id, $date, $value);
                    if (is_wp_error($res)) {
                        echo '<div class="notice notice-error"><p>' . esc_html($res->get_error_message()) . '</p></div>';
                    } else {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Data point saved.', 'zc-dmt') . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Please provide date and value.', 'zc-dmt') . '</p></div>';
                }
            } elseif ($action === 'bulk_datapoints') {
                $indicator_id = isset($_POST['indicator_id']) ? intval($_POST['indicator_id']) : 0;
                $csv_lines = isset($_POST['csv_lines']) ? (string) wp_unslash($_POST['csv_lines']) : '';
                $inserted = 0;
                $failed = 0;

                if ($indicator_id && !empty($csv_lines)) {
                    $lines = preg_split('/\r\n|\r|\n/', $csv_lines);
                    if (is_array($lines)) {
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if ($line === '') {
                                continue;
                            }
                            // Accept separators: comma, semicolon, tab
                            $parts = preg_split('/[,\t;]+/', $line);
                            if (count($parts) < 1) {
                                continue;
                            }
                            $date = trim($parts[0]);
                            $value = isset($parts[1]) ? trim($parts[1]) : '';
                            $res = ZC_DMT_Indicators::add_data_point($indicator_id, $date, $value);
                            if (is_wp_error($res)) {
                                $failed++;
                            } else {
                                $inserted++;
                            }
                        }
                    }
                    if ($inserted > 0) {
                        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('%d data point(s) processed successfully.', 'zc-dmt'), $inserted) . '</p></div>';
                    }
                    if ($failed > 0) {
                        echo '<div class="notice notice-warning is-dismissible"><p>' . sprintf(esc_html__('%d line(s) failed to process. Please verify date/value format (YYYY-MM-DD, value).', 'zc-dmt'), $failed) . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Please select an indicator and paste CSV lines.', 'zc-dmt') . '</p></div>';
                }
            } elseif ($action === 'delete_indicator') {
                // Delete indicator and its data points
                $indicator_id = isset($_POST['indicator_id']) ? intval($_POST['indicator_id']) : 0;
                if ($indicator_id > 0) {
                    global $wpdb;
                    $table_i = $wpdb->prefix . 'zc_dmt_indicators';
                    $table_d = $wpdb->prefix . 'zc_dmt_data_points';
                    // Remove datapoints first
                    $wpdb->delete($table_d, array('indicator_id' => $indicator_id), array('%d'));
                    // Then remove indicator
                    $deleted = $wpdb->delete($table_i, array('id' => $indicator_id), array('%d'));
                    if ($deleted !== false) {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Indicator deleted successfully.', 'zc-dmt') . '</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>' . esc_html__('Failed to delete indicator. It may not exist.', 'zc-dmt') . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Invalid indicator id.', 'zc-dmt') . '</p></div>';
                }
            } elseif ($action === 'edit_indicator') {
                // Inline edit an indicator (quick edit)
                $indicator_id = isset($_POST['indicator_id']) ? intval($_POST['indicator_id']) : 0;
                if ($indicator_id > 0) {
                    $fields = array();
                    if (isset($_POST['edit_name'])) {
                        $fields['name'] = sanitize_text_field(wp_unslash($_POST['edit_name']));
                    }
                    if (isset($_POST['edit_slug'])) {
                        $fields['slug'] = sanitize_title(wp_unslash($_POST['edit_slug']));
                    }
                    if (isset($_POST['edit_description'])) {
                        $fields['description'] = wp_kses_post(wp_unslash($_POST['edit_description']));
                    }
                    $fields['is_active'] = isset($_POST['edit_is_active']) ? 1 : 0;

                    // Optional: raw JSON for source_config
                    if (isset($_POST['edit_source_config_json'])) {
                        $raw = trim((string) wp_unslash($_POST['edit_source_config_json']));
                        if ($raw === '') {
                            $fields['source_config'] = null;
                        } else {
                            $decoded = json_decode($raw, true);
                            if (is_array($decoded)) {
                                $fields['source_config'] = $decoded;
                            } else {
                                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Source config is not valid JSON. Skipped updating source_config.', 'zc-dmt') . '</p></div>';
                            }
                        }
                    }

                    $res = ZC_DMT_Indicators::update_indicator($indicator_id, $fields);
                    if (is_wp_error($res)) {
                        echo '<div class="notice notice-error"><p>' . esc_html($res->get_error_message()) . '</p></div>';
                    } else {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Indicator updated.', 'zc-dmt') . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Invalid indicator id.', 'zc-dmt') . '</p></div>';
                }
            }
        }
    }

    // Fetch indicators
    $indicators = ZC_DMT_Indicators::list_indicators(200, 0);
    $rest_base = esc_url_raw( rest_url( defined('ZC_DMT_REST_NS') ? ZC_DMT_REST_NS : 'zc-dmt/v1' ) );
    // Link to merged Charts settings within this plugin
    $charts_settings_url = esc_url( admin_url('admin.php?page=zc-dmt-settings') );
    ?>
    <div class="wrap zc-dmt-indicators">
        <h1 class="wp-heading-inline"><?php echo esc_html__('Indicators', 'zc-dmt'); ?></h1>
        <hr class="wp-header-end" />
        <div class="zc-dmt-toolbar" style="margin:10px 0 20px 0;">
            <a href="<?php echo $charts_settings_url; ?>" class="button"><?php echo esc_html__('Go to Charts Settings', 'zc-dmt'); ?></a>
            <?php if (!empty($rest_base)): ?>
                <span style="margin-left:12px;opacity:0.8;">
                    <?php echo esc_html__('REST Base:', 'zc-dmt'); ?> <code><?php echo esc_html($rest_base); ?></code>
                </span>
            <?php endif; ?>
        </div>

        <div class="zc-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
            <div class="zc-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:16px;">
                <h2><?php echo esc_html__('Add New Indicator', 'zc-dmt'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('zc_dmt_indicators_action', 'zc_dmt_indicators_nonce'); ?>
                    <input type="hidden" name="zc_dmt_indicators_action" value="add_indicator" />
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="zc_name"><?php echo esc_html__('Name', 'zc-dmt'); ?></label></th>
                                <td><input id="zc_name" name="name" type="text" class="regular-text" placeholder="<?php echo esc_attr__('e.g., US GDP (Quarterly)', 'zc-dmt'); ?>" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="zc_slug"><?php echo esc_html__('Slug', 'zc-dmt'); ?></label></th>
                                <td><input id="zc_slug" name="slug" type="text" class="regular-text" placeholder="<?php echo esc_attr__('e.g., gdp_us', 'zc-dmt'); ?>" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="zc_desc"><?php echo esc_html__('Short Description', 'zc-dmt'); ?></label></th>
                                <td><textarea id="zc_desc" name="description" class="large-text" rows="3" placeholder="<?php echo esc_attr__('Optional description (up to 50 words).', 'zc-dmt'); ?>"></textarea></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="zc_source_type"><?php echo esc_html__('Source Type', 'zc-dmt'); ?></label></th>
                                <td>
                                     <select id="zc_source_type" name="source_type">
                                        <option value="manual"><?php echo esc_html__('Manual (store in DB)', 'zc-dmt'); ?></option>
                                        <option value="google_sheets"><?php echo esc_html__('Google Sheets (live CSV)', 'zc-dmt'); ?></option>
                                        <option value="fred"><?php echo esc_html__('FRED API (live data)', 'zc-dmt'); ?></option>
                                         <option value="world_bank"><?php echo esc_html__('World Bank API (open data)', 'zc-dmt'); ?></option>
                                         <option value="dbnomics"><?php echo esc_html__('DBnomics (open data)', 'zc-dmt'); ?></option>
                                         <option value="eurostat"><?php echo esc_html__('Eurostat (open data)', 'zc-dmt'); ?></option>
                                         <option value="oecd"><?php echo esc_html__('OECD (open data)', 'zc-dmt'); ?></option>
                                         <option value="uk_ons"><?php echo esc_html__('UK ONS (open data)', 'zc-dmt'); ?></option>
                                         <option value="yahoo_finance"><?php echo esc_html__('Yahoo Finance (market data)', 'zc-dmt'); ?></option>
                                         <option value="google_finance"><?php echo esc_html__('Google Finance (CSV/JSON)', 'zc-dmt'); ?></option>
                                         <option value="quandl"><?php echo esc_html__('Quandl / Nasdaq Data Link', 'zc-dmt'); ?></option>
                                         <option value="bank_of_canada"><?php echo esc_html__('Bank of Canada (Valet)', 'zc-dmt'); ?></option>
                                         <option value="statcan"><?php echo esc_html__('Statistics Canada (JSON/CSV)', 'zc-dmt'); ?></option>
                                         <option value="australia_rba"><?php echo esc_html__('Australia RBA (CSV/JSON)', 'zc-dmt'); ?></option>
                                         <option value="ecb"><?php echo esc_html__('European Central Bank (ECB SDW)', 'zc-dmt'); ?></option>
                                         <option value="universal_csv"><?php echo esc_html__('Universal CSV (any URL)', 'zc-dmt'); ?></option>
                                         <option value="universal_json"><?php echo esc_html__('Universal JSON (any URL)', 'zc-dmt'); ?></option>
                                     </select>
                                     <p class="description"><?php echo esc_html__('Manual keeps data in your site DB. All other sources fetch live data with caching. Open APIs require no keys.', 'zc-dmt'); ?></p>
                                 </td>
                             </tr>
                            <tr id="zc_google_sheets_row">
                                <th scope="row"><label for="zc_google_sheets_url"><?php echo esc_html__('Google Sheets URL', 'zc-dmt'); ?></label></th>
                                <td>
                                    <input id="zc_google_sheets_url" name="google_sheets_url" type="url" class="regular-text" placeholder="https://docs.google.com/...&output=csv" />
                                    <p class="description"><?php echo esc_html__('Paste a Published CSV link (recommended) or a normal share link. The plugin auto-detects date/value columns and normalizes dates to Y-m-d.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_fred_row">
                                <th scope="row"><label for="zc_fred_series_id"><?php echo esc_html__('FRED Series ID', 'zc-dmt'); ?></label></th>
                                <td>
                                    <input id="zc_fred_series_id" name="fred_series_id" type="text" class="regular-text" placeholder="GDP, UNRATE, CPIAUCSL" />
                                    <p class="description"><?php echo esc_html__('Enter a FRED series ID (e.g., GDP for Gross Domestic Product, UNRATE for Unemployment Rate). Requires FRED API key in Settings.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_world_bank_row">
                                <th scope="row"><label for="zc_wb_country_code"><?php echo esc_html__('World Bank Country / Indicator', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                        <label><?php echo esc_html__('Country Code:', 'zc-dmt'); ?>
                                            <input id="zc_wb_country_code" name="wb_country_code" type="text" class="regular-text" style="width:100px" value="US" />
                                        </label>
                                        <label><?php echo esc_html__('Indicator Code:', 'zc-dmt'); ?>
                                            <input id="zc_wb_indicator_code" name="wb_indicator_code" list="zc_wb_indicator_list" type="text" class="regular-text" style="min-width:260px" placeholder="NY.GDP.MKTP.CD" />
                                        </label>
                                    </div>
                                    <datalist id="zc_wb_indicator_list">
                                        <option value="NY.GDP.MKTP.CD">GDP (current US$)</option>
                                        <option value="NY.GDP.PCAP.CD">GDP per capita (current US$)</option>
                                        <option value="SL.UEM.TOTL.ZS">Unemployment, total (% of labor force)</option>
                                        <option value="FP.CPI.TOTL.ZG">Inflation, consumer prices (annual %)</option>
                                        <option value="SP.POP.TOTL">Population, total</option>
                                    </datalist>
                                    <p class="description"><?php echo esc_html__('Open API (no key). Example: Country=US, Indicator=NY.GDP.MKTP.CD. Use WLD for world data.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_dbnomics_row">
                                <th scope="row"><label for="zc_dbn_series"><?php echo esc_html__('DBnomics (choose one method)', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:260px;">
                                            <?php echo esc_html__('Series ID', 'zc-dmt'); ?>
                                            <input id="zc_dbn_series" name="dbnomics_series_id" type="text" class="regular-text" placeholder="AMECO/ZUTN/EA19.1.0.0.0.ZUTN" />
                                        </label>
                                        <label style="min-width:340px;">
                                            <?php echo esc_html__('JSON URL', 'zc-dmt'); ?>
                                            <input id="zc_dbn_json_url" name="dbnomics_json_url" type="url" class="regular-text" placeholder="https://api.db.nomics.world/v22/series/...?...&amp;observations=1&amp;format=json" />
                                        </label>
                                        <label style="min-width:340px;">
                                            <?php echo esc_html__('CSV URL', 'zc-dmt'); ?>
                                            <input id="zc_dbn_csv_url" name="dbnomics_csv_url" type="url" class="regular-text" placeholder="https://api.db.nomics.world/v22/series/...?...&amp;format=csv" />
                                        </label>
                                    </div>
                                    <p class="description"><?php echo esc_html__('Fill any ONE: Series ID, JSON URL, or CSV URL. JSON/CSV links are available on the DBnomics series page under Links/Download.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_eurostat_row">
                                <th scope="row"><label for="zc_euro_dataset"><?php echo esc_html__('Eurostat Dataset / Query', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                        <label><?php echo esc_html__('Dataset Code:', 'zc-dmt'); ?>
                                            <input id="zc_euro_dataset" name="eurostat_dataset_code" type="text" class="regular-text" style="min-width:220px" placeholder="nama_10_gdp" />
                                        </label>
                                        <label><?php echo esc_html__('Optional Query:', 'zc-dmt'); ?>
                                            <input id="zc_euro_query" name="eurostat_query" type="text" class="regular-text" style="min-width:260px" placeholder="geo=EU27_2020&amp;na_item=B1GQ&amp;unit=CP_MEUR" />
                                        </label>
                                    </div>
                                    <p class="description"><?php echo esc_html__('Open API (no key). Start with dataset code only; you can add a query to filter later.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_oecd_row">
                                <th scope="row"><label for="zc_oecd_path"><?php echo esc_html__('OECD (choose one method)', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:280px;">
                                            <?php echo esc_html__('Dataset/Key Path', 'zc-dmt'); ?>
                                            <input id="zc_oecd_path" name="oecd_path" type="text" class="regular-text" placeholder="QNA/USA.B1_GE.CQRSA.Q/all" />
                                        </label>
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('Developer API JSON URL', 'zc-dmt'); ?>
                                            <input id="zc_oecd_json_url" name="oecd_json_url" type="url" class="regular-text" placeholder="https://sdmx.oecd.org/public/rest/data/...?...&amp;dimensionAtObservation=AllDimensions" />
                                        </label>
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('Download CSV URL', 'zc-dmt'); ?>
                                            <input id="zc_oecd_csv_url" name="oecd_csv_url" type="url" class="regular-text" placeholder="https://sdmx.oecd.org/public/rest/data/...?...&amp;contentType=csv" />
                                        </label>
                                    </div>
                                    <p class="description"><?php echo esc_html__('Fill any ONE: Dataset/Key path, Developer API JSON URL, or Download CSV URL. The adapter will parse SDMX-JSON or CSV and normalize dates.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_ukons_row">
                                <th scope="row"><label><?php echo esc_html__('UK ONS (choose one method)', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('JSON URL', 'zc-dmt'); ?>
                                            <input name="uk_json_url" type="url" class="regular-text" placeholder="https://api.ons.gov.uk/timeseries/{series_id}/dataset/{dataset_id}/data?time=from+2010" />
                                        </label>
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('CSV URL', 'zc-dmt'); ?>
                                            <input name="uk_csv_url" type="url" class="regular-text" placeholder="https://.../download.csv" />
                                        </label>
                                    </div>
                                    <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                        <label><?php echo esc_html__('Dataset ID', 'zc-dmt'); ?>
                                            <input name="uk_dataset_id" type="text" class="regular-text" style="width:120px" placeholder="pn2" />
                                        </label>
                                        <label><?php echo esc_html__('Series ID', 'zc-dmt'); ?>
                                            <input name="uk_series_id" type="text" class="regular-text" style="width:140px" placeholder="mgsx" />
                                        </label>
                                        <label><?php echo esc_html__('Extra Query (optional)', 'zc-dmt'); ?>
                                            <input name="uk_query" type="text" class="regular-text" style="min-width:260px" placeholder="time=from+2010" />
                                        </label>
                                    </div>
                                     <p class="description"><?php echo esc_html__('Fill ONE: JSON URL, CSV URL, or Timeseries (Dataset + Series). JSON URL can be any service returning period/value JSON.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_yahoo_row">
                                <th scope="row"><label><?php echo esc_html__('Yahoo Finance (choose one method)', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:280px;">
                                            <?php echo esc_html__('Symbol', 'zc-dmt'); ?>
                                            <input name="yf_symbol" type="text" class="regular-text" placeholder="AAPL, ^GSPC, EURUSD=X" />
                                        </label>
                                        <label style="min-width:180px;">
                                            <?php echo esc_html__('Range', 'zc-dmt'); ?>
                                            <input name="yf_range" type="text" class="regular-text" placeholder="1y (e.g. 1mo, 3mo, 6mo, 1y, 2y, 5y, ytd, max)" />
                                        </label>
                                        <label style="min-width:180px;">
                                            <?php echo esc_html__('Interval', 'zc-dmt'); ?>
                                            <input name="yf_interval" type="text" class="regular-text" placeholder="1d (e.g. 1d, 1wk, 1mo)" />
                                        </label>
                                    </div>
                                    <div style="margin-top:8px;display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('Yahoo JSON URL (optional)', 'zc-dmt'); ?>
                                            <input name="yf_json_url" type="url" class="regular-text" placeholder="https://query1.finance.yahoo.com/v8/finance/chart/AAPL?interval=1d&amp;range=1y" />
                                        </label>
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('Yahoo CSV URL (optional)', 'zc-dmt'); ?>
                                            <input name="yf_csv_url" type="url" class="regular-text" placeholder="https://query1.finance.yahoo.com/v7/finance/download/AAPL?...&amp;interval=1d&amp;events=history" />
                                        </label>
                                    </div>
                                    <p class="description"><?php echo esc_html__('Provide EITHER a Symbol (recommended) OR direct JSON/CSV URL. Symbol uses Yahoo chart JSON and falls back to CSV.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_google_row">
                                <th scope="row"><label><?php echo esc_html__('Google Finance (choose one method)', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('CSV URL (recommended)', 'zc-dmt'); ?>
                                            <input name="gf_csv_url" type="url" class="regular-text" placeholder="Published CSV URL (e.g., from Google Sheets using GOOGLEFINANCE then Publish)" />
                                        </label>
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('JSON URL (optional)', 'zc-dmt'); ?>
                                            <input name="gf_json_url" type="url" class="regular-text" placeholder="Any JSON endpoint with date/value data" />
                                        </label>
                                    </div>
                                    <p class="description"><?php echo esc_html__('Google Finance has no official public JSON API. Recommended: publish CSV via Google Sheets. Or provide your own JSON endpoint.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_quandl_row">
                                <th scope="row"><label><?php echo esc_html__('Quandl / Nasdaq Data Link (choose one method)', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:340px;">
                                            <?php echo esc_html__('JSON URL', 'zc-dmt'); ?>
                                            <input name="quandl_json_url" type="url" class="regular-text" placeholder="https://data.nasdaq.com/api/v3/datasets/FRED/GDP.json?api_key=..." />
                                        </label>
                                        <label style="min-width:340px;">
                                            <?php echo esc_html__('CSV URL', 'zc-dmt'); ?>
                                            <input name="quandl_csv_url" type="url" class="regular-text" placeholder="https://data.nasdaq.com/api/v3/datasets/FRED/GDP.csv?api_key=..." />
                                        </label>
                                    </div>
                                    <div style="margin-top:8px;display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:180px;">
                                            <?php echo esc_html__('Database', 'zc-dmt'); ?>
                                            <input name="quandl_db" type="text" class="regular-text" placeholder="FRED" />
                                        </label>
                                        <label style="min-width:220px;">
                                            <?php echo esc_html__('Dataset', 'zc-dmt'); ?>
                                            <input name="quandl_ds" type="text" class="regular-text" placeholder="GDP" />
                                        </label>
                                        <label style="min-width:220px;">
                                            <?php echo esc_html__('API Key (optional)', 'zc-dmt'); ?>
                                            <input name="quandl_api_key" type="text" class="regular-text" placeholder="Your API key (optional)" />
                                        </label>
                                    </div>
                                    <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                        <label>
                                            <?php echo esc_html__('Collapse (optional)', 'zc-dmt'); ?>
                                            <input name="quandl_collapse" type="text" class="regular-text" style="width:160px" placeholder="monthly|quarterly|annual" />
                                        </label>
                                        <label>
                                            <?php echo esc_html__('Start Date (optional)', 'zc-dmt'); ?>
                                            <input name="quandl_start_date" type="date" class="regular-text" />
                                        </label>
                                        <label>
                                            <?php echo esc_html__('End Date (optional)', 'zc-dmt'); ?>
                                            <input name="quandl_end_date" type="date" class="regular-text" />
                                        </label>
                                    </div>
                                    <p class="description"><?php echo esc_html__('Fill ONE: JSON URL, CSV URL, or Dataset (database + dataset + optional key/dates). Adapter normalizes the series.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_boc_row">
                                <th scope="row"><label><?php echo esc_html__('Bank of Canada (Valet) (choose one method)', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('JSON URL', 'zc-dmt'); ?>
                                            <input name="boc_json_url" type="url" class="regular-text" placeholder="https://www.bankofcanada.ca/valet/observations/V39079/json?start_date=2019-01-01" />
                                        </label>
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('CSV URL', 'zc-dmt'); ?>
                                            <input name="boc_csv_url" type="url" class="regular-text" placeholder="https://www.bankofcanada.ca/valet/observations/V39079/csv?start_date=2019-01-01" />
                                        </label>
                                    </div>
                                    <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                        <label><?php echo esc_html__('Series Code', 'zc-dmt'); ?>
                                            <input name="boc_series" type="text" class="regular-text" style="width:140px" placeholder="V39079" />
                                        </label>
                                        <label><?php echo esc_html__('Start Date', 'zc-dmt'); ?>
                                            <input name="boc_start_date" type="date" class="regular-text" />
                                        </label>
                                        <label><?php echo esc_html__('End Date', 'zc-dmt'); ?>
                                            <input name="boc_end_date" type="date" class="regular-text" />
                                        </label>
                                    </div>
                                    <p class="description"><?php echo esc_html__('Provide JSON URL or CSV URL, or Series code with optional date range. Adapter auto-detects value.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_statcan_row">
                                <th scope="row"><label><?php echo esc_html__('Statistics Canada (choose one method)', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('JSON URL', 'zc-dmt'); ?>
                                            <input name="statcan_json_url" type="url" class="regular-text" placeholder="https://www150.statcan.gc.ca/t1/wds/en/... (WDS JSON)" />
                                        </label>
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('CSV URL', 'zc-dmt'); ?>
                                            <input name="statcan_csv_url" type="url" class="regular-text" placeholder="https://.../download.csv" />
                                        </label>
                                    </div>
                                    <p class="description"><?php echo esc_html__('Fill ONE: JSON URL (WDS or compatible) or CSV URL. Adapter normalizes period/value.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_rba_row">
                                <th scope="row"><label><?php echo esc_html__('Australia RBA (choose one method)', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('CSV URL (recommended)', 'zc-dmt'); ?>
                                            <input name="rba_csv_url" type="url" class="regular-text" placeholder="Direct CSV link to RBA statistical table" />
                                        </label>
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('JSON URL (optional)', 'zc-dmt'); ?>
                                            <input name="rba_json_url" type="url" class="regular-text" placeholder="Any JSON endpoint with date/value" />
                                        </label>
                                    </div>
                                    <p class="description"><?php echo esc_html__('Use direct CSV links from RBA tables where possible. Adapter auto-detects date/value columns.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_ecb_row">
                                <th scope="row"><label><?php echo esc_html__('European Central Bank (ECB SDW) — choose one', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('CSV URL (recommended)', 'zc-dmt'); ?>
                                            <input name="ecb_csv_url" type="url" class="regular-text" placeholder="https://sdw-wsrest.ecb.europa.eu/service/data/EXR/D.USD.EUR.SP00.A?startPeriod=2000&amp;format=csvdata" />
                                        </label>
                                        <label style="min-width:360px;">
                                            <?php echo esc_html__('JSON URL (optional)', 'zc-dmt'); ?>
                                            <input name="ecb_json_url" type="url" class="regular-text" placeholder="SDMX-JSON or any JSON with date/value (CSV preferred)" />
                                        </label>
                                    </div>
                                    <div style="margin-top:8px;">
                                        <label style="min-width:480px;display:block;">
                                            <?php echo esc_html__('PATH (auto CSV)', 'zc-dmt'); ?>
                                            <input name="ecb_path" type="text" class="regular-text" placeholder="EXR/D.USD.EUR.SP00.A?startPeriod=2000" />
                                        </label>
                                        <p class="description"><?php echo esc_html__('Use ECB SDW path like EXR/D.USD.EUR.SP00.A?startPeriod=2000. The adapter will fetch CSV automatically.', 'zc-dmt'); ?></p>
                                    </div>
                                </td>
                            </tr>
                            <tr id="zc_universal_csv_row">
                                <th scope="row"><label><?php echo esc_html__('Universal CSV (any URL)', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:420px;">
                                            <?php echo esc_html__('CSV URL', 'zc-dmt'); ?>
                                            <input name="universal_csv_url" type="url" class="regular-text" placeholder="https://example.com/file.csv" />
                                        </label>
                                        <label style="min-width:200px;">
                                            <?php echo esc_html__('Date Column (name or index)', 'zc-dmt'); ?>
                                            <input name="uni_date_col" type="text" class="regular-text" placeholder="date or 0" />
                                        </label>
                                        <label style="min-width:200px;">
                                            <?php echo esc_html__('Value Column (name or index)', 'zc-dmt'); ?>
                                            <input name="uni_value_col" type="text" class="regular-text" placeholder="value or 1" />
                                        </label>
                                    </div>
                                    <div style="margin-top:8px;display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:180px;">
                                            <?php echo esc_html__('Delimiter (optional)', 'zc-dmt'); ?>
                                            <input name="uni_delimiter" type="text" class="regular-text" placeholder=", ; \t |" />
                                        </label>
                                        <label style="min-width:180px;">
                                            <?php echo esc_html__('Skip Rows (optional)', 'zc-dmt'); ?>
                                            <input name="uni_skip_rows" type="number" class="regular-text" min="0" placeholder="0" />
                                        </label>
                                    </div>
                                    <p class="description"><?php echo esc_html__('Provide just CSV URL for auto-detection, or specify date/value column by header name or index.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                            <tr id="zc_universal_json_row">
                                <th scope="row"><label><?php echo esc_html__('Universal JSON (any URL)', 'zc-dmt'); ?></label></th>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:420px;">
                                            <?php echo esc_html__('JSON URL', 'zc-dmt'); ?>
                                            <input name="universal_json_url" type="url" class="regular-text" placeholder="https://example.com/data.json" />
                                        </label>
                                        <label style="min-width:260px;">
                                            <?php echo esc_html__('Root (dot path, optional)', 'zc-dmt'); ?>
                                            <input name="uni_root" type="text" class="regular-text" placeholder="data.items" />
                                        </label>
                                    </div>
                                    <div style="margin-top:8px;display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                        <label style="min-width:200px;">
                                            <?php echo esc_html__('Date Key (optional)', 'zc-dmt'); ?>
                                            <input name="uni_date_key" type="text" class="regular-text" placeholder="date | time | period" />
                                        </label>
                                        <label style="min-width:200px;">
                                            <?php echo esc_html__('Value Key (optional)', 'zc-dmt'); ?>
                                            <input name="uni_value_key" type="text" class="regular-text" placeholder="value | close | price | obs_value" />
                                        </label>
                                    </div>
                                    <div style="margin-top:8px;">
                                        <label style="display:block;">
                                            <?php echo esc_html__('Map JSON (optional)', 'zc-dmt'); ?>
                                            <textarea name="uni_map_json" class="large-text" rows="3" placeholder='{"d":"date","v":"value"}'></textarea>
                                        </label>
                                        <p class="description"><?php echo esc_html__('Optional key remapping for custom JSON shapes. Paste a simple JSON object to rename keys before parsing.', 'zc-dmt'); ?></p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p><button type="submit" class="button button-primary"><?php echo esc_html__('Save Indicator', 'zc-dmt'); ?></button></p>
                </form>
            </div>

            <div class="zc-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:16px;">
                <h2><?php echo esc_html__('Quick Add Data Point', 'zc-dmt'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('zc_dmt_indicators_action', 'zc_dmt_indicators_nonce'); ?>
                    <input type="hidden" name="zc_dmt_indicators_action" value="add_datapoint" />
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="zc_indicator_id"><?php echo esc_html__('Indicator', 'zc-dmt'); ?></label></th>
                                <td>
                                    <select id="zc_indicator_id" name="indicator_id" required>
                                        <option value=""><?php echo esc_html__('-- Select --', 'zc-dmt'); ?></option>
                                        <?php
                                        if (!empty($indicators)) {
                                            foreach ($indicators as $ind) {
                                                echo '<option value="' . esc_attr($ind->id) . '">' . esc_html($ind->name . ' (' . $ind->slug . ')') . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="zc_obs_date"><?php echo esc_html__('Date', 'zc-dmt'); ?></label></th>
                                <td><input id="zc_obs_date" name="obs_date" type="date" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="zc_value"><?php echo esc_html__('Value', 'zc-dmt'); ?></label></th>
                                <td><input id="zc_value" name="value" type="number" step="any" placeholder="<?php echo esc_attr__('e.g., 100.25', 'zc-dmt'); ?>"></td>
                            </tr>
                        </tbody>
                    </table>
                    <p><button type="submit" class="button button-primary"><?php echo esc_html__('Save Data Point', 'zc-dmt'); ?></button></p>
                    <p class="description"><?php echo esc_html__('Idempotent: If a point exists for that date, its value will be updated.', 'zc-dmt'); ?></p>
                </form>

                <hr style="margin:16px 0;" />

                <h2 style="margin-top:0;"><?php echo esc_html__('Bulk CSV Paste', 'zc-dmt'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('zc_dmt_indicators_action', 'zc_dmt_indicators_nonce'); ?>
                    <input type="hidden" name="zc_dmt_indicators_action" value="bulk_datapoints" />
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="zc_indicator_id_bulk"><?php echo esc_html__('Indicator', 'zc-dmt'); ?></label></th>
                                <td>
                                    <select id="zc_indicator_id_bulk" name="indicator_id" required>
                                        <option value=""><?php echo esc_html__('-- Select --', 'zc-dmt'); ?></option>
                                        <?php
                                        if (!empty($indicators)) {
                                            foreach ($indicators as $ind) {
                                                echo '<option value="' . esc_attr($ind->id) . '">' . esc_html($ind->name . ' (' . $ind->slug . ')') . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="zc_csv_lines"><?php echo esc_html__('CSV Lines', 'zc-dmt'); ?></label></th>
                                <td>
                                    <textarea id="zc_csv_lines" name="csv_lines" class="large-text" rows="6" placeholder="YYYY-MM-DD,123.45&#10;YYYY-MM-DD,67.89"></textarea>
                                    <p class="description"><?php echo esc_html__('Paste date,value pairs (one per line). Accepts comma, semicolon, or tab as separator. Existing dates will be updated.', 'zc-dmt'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p><button type="submit" class="button"><?php echo esc_html__('Bulk Insert CSV Lines', 'zc-dmt'); ?></button></p>
                </form>
            </div>
        </div>

        <div class="zc-card" style="margin-top:24px;background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:16px;">
            <h2><?php echo esc_html__('All Indicators', 'zc-dmt'); ?></h2>
            <?php if (empty($indicators)) : ?>
                <p><?php echo esc_html__('No indicators yet. Add one using the form above.', 'zc-dmt'); ?></p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('ID', 'zc-dmt'); ?></th>
                            <th><?php echo esc_html__('Name', 'zc-dmt'); ?></th>
                            <th><?php echo esc_html__('Slug', 'zc-dmt'); ?></th>
                            <th><?php echo esc_html__('Active', 'zc-dmt'); ?></th>
                            <th><?php echo esc_html__('Created', 'zc-dmt'); ?></th>
                            <th><?php echo esc_html__('Actions', 'zc-dmt'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($indicators as $ind): ?>
                            <tr>
                                <td><?php echo esc_html($ind->id); ?></td>
                                <td><?php echo esc_html($ind->name); ?></td>
                                <td><code><?php echo esc_html($ind->slug); ?></code></td>
                                <td><?php echo intval($ind->is_active) === 1 ? 'Yes' : 'No'; ?></td>
                                <td><?php echo esc_html($ind->created_at); ?></td>
                                <td>
                                    <?php
                                    $slug = $ind->slug;
                                    $shortcode = '[zc_chart_dynamic id="' . esc_attr($slug) . '"]';
                                    $data_url = trailingslashit($rest_base) . 'data/' . rawurlencode($slug) . '?access_key=YOUR_KEY';
                                    $backup_url = trailingslashit($rest_base) . 'backup/' . rawurlencode($slug) . '?access_key=YOUR_KEY';
                                    ?>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                        <button type="button" class="button zc-copy-btn" data-clip="<?php echo esc_attr($shortcode); ?>"><?php echo esc_html__('Copy Shortcode', 'zc-dmt'); ?></button>
                                        <button type="button" class="button zc-copy-btn" data-clip="<?php echo esc_attr($data_url); ?>"><?php echo esc_html__('Copy Data URL', 'zc-dmt'); ?></button>
                                        <button type="button" class="button zc-copy-btn" data-clip="<?php echo esc_attr($backup_url); ?>"><?php echo esc_html__('Copy Backup URL', 'zc-dmt'); ?></button>

                                        <details style="margin-left:8px;">
                                            <summary class="button"><?php echo esc_html__('Edit', 'zc-dmt'); ?></summary>
                                            <div style="margin-top:10px;padding:10px;border:1px solid #e2e8f0;border-radius:6px;background:#fafafa;min-width:520px;">
                                                <form method="post">
                                                    <?php wp_nonce_field('zc_dmt_indicators_action', 'zc_dmt_indicators_nonce'); ?>
                                                    <input type="hidden" name="zc_dmt_indicators_action" value="edit_indicator" />
                                                    <input type="hidden" name="indicator_id" value="<?php echo esc_attr($ind->id); ?>" />
                                                    <table class="form-table" role="presentation" style="margin:0;">
                                                        <tbody>
                                                            <tr>
                                                                <th scope="row"><?php echo esc_html__('Name', 'zc-dmt'); ?></th>
                                                                <td><input name="edit_name" type="text" class="regular-text" value="<?php echo esc_attr($ind->name); ?>" /></td>
                                                            </tr>
                                                            <tr>
                                                                <th scope="row"><?php echo esc_html__('Slug', 'zc-dmt'); ?></th>
                                                                <td><input name="edit_slug" type="text" class="regular-text" value="<?php echo esc_attr($ind->slug); ?>" /></td>
                                                            </tr>
                                                            <tr>
                                                                <th scope="row"><?php echo esc_html__('Active', 'zc-dmt'); ?></th>
                                                                <td><label><input type="checkbox" name="edit_is_active" <?php checked(intval($ind->is_active) === 1); ?> /> <?php echo esc_html__('Enabled', 'zc-dmt'); ?></label></td>
                                                            </tr>
                                                            <tr>
                                                                <th scope="row"><?php echo esc_html__('Description', 'zc-dmt'); ?></th>
                                                                <td><textarea name="edit_description" class="large-text" rows="3"><?php echo esc_textarea($ind->description); ?></textarea></td>
                                                            </tr>
                                                            <tr>
                                                                <th scope="row"><?php echo esc_html__('Source Config (JSON) - optional', 'zc-dmt'); ?></th>
                                                                <td><textarea name="edit_source_config_json" class="large-text" rows="6" placeholder="{ }"><?php echo esc_textarea($ind->source_config); ?></textarea>
                                                                    <p class="description"><?php echo esc_html__('Advanced: Edit raw source_config JSON if needed. Leave empty to keep unchanged.', 'zc-dmt'); ?></p>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                    <p><button type="submit" class="button button-primary"><?php echo esc_html__('Save Changes', 'zc-dmt'); ?></button></p>
                                                </form>
                                            </div>
                                        </details>

                                        <form method="post" onsubmit="return confirm('Delete this indicator and all its data points?');" style="display:inline;">
                                            <?php wp_nonce_field('zc_dmt_indicators_action', 'zc_dmt_indicators_nonce'); ?>
                                            <input type="hidden" name="zc_dmt_indicators_action" value="delete_indicator" />
                                            <input type="hidden" name="indicator_id" value="<?php echo esc_attr($ind->id); ?>" />
                                            <button type="submit" class="button button-link-delete" style="color:#b32d2e;"><?php echo esc_html__('Delete', 'zc-dmt'); ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div style="margin-top:16px;">
            <p class="description">
                <?php echo esc_html__('Next: Go to Settings and use the Shortcode Builder to copy a shortcode, then paste it into any page. Example:', 'zc-dmt'); ?>
                <code>[zc_chart_dynamic id="your-indicator-slug"]</code>
            </p>
        </div>
    </div>
    <?php
}
