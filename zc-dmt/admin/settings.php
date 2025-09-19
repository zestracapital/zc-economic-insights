<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the ZC DMT Settings page (API Keys management and basic info).
 * - Generate API keys (one-time display)
 * - List existing keys (preview, status, created, last used)
 * - Revoke keys
 */
function zc_dmt_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $generated_key_notice = '';
    // Handle actions
    if (!empty($_POST['zc_dmt_action'])) {
        if (!isset($_POST['zc_dmt_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['zc_dmt_nonce']), 'zc_dmt_settings_action')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed. Please try again.', 'zc-dmt') . '</p></div>';
        } else {
            $action = sanitize_text_field($_POST['zc_dmt_action']);
    
            if ($action === 'generate_key') {
                $key_name = isset($_POST['key_name']) ? sanitize_text_field($_POST['key_name']) : '';
                $new_key = ZC_DMT_Security::generate_key($key_name);
                if (is_wp_error($new_key)) {
                    echo '<div class="notice notice-error"><p>' . esc_html($new_key->get_error_message()) . '</p></div>';
                } else {
                    // Show one-time key to copy
                    $generated_key_notice = $new_key;
                    echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__('New API Key generated (copy and store it safely):', 'zc-dmt') . '</strong><br><code style="user-select:all;">' . esc_html($new_key) . '</code></p></div>';
                }
            } elseif ($action === 'revoke_key' && isset($_POST['key_id'])) {
                $key_id = intval($_POST['key_id']);
                $ok = ZC_DMT_Security::revoke_key($key_id);
                if ($ok) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('API Key revoked.', 'zc-dmt') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Failed to revoke the key.', 'zc-dmt') . '</p></div>';
                }
            } elseif ($action === 'save_data_sources') {
                // Save FRED API key
                $fred_key = isset($_POST['zc_fred_api_key']) ? sanitize_text_field($_POST['zc_fred_api_key']) : '';
                update_option('zc_fred_api_key', $fred_key);
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Data sources settings saved.', 'zc-dmt') . '</p></div>';
            } elseif ($action === 'save_charts_settings') {
                // Save Charts defaults used by shortcodes and loader
                $library   = isset($_POST['zc_charts_default_library']) ? sanitize_text_field($_POST['zc_charts_default_library']) : 'chartjs';
                $timeframe = isset($_POST['zc_charts_default_timeframe']) ? sanitize_text_field($_POST['zc_charts_default_timeframe']) : '1y';
                $height    = isset($_POST['zc_charts_default_height']) ? sanitize_text_field($_POST['zc_charts_default_height']) : '300px';
                $controls  = !empty($_POST['zc_charts_enable_controls']) ? 1 : 0;
                $fallback  = !empty($_POST['zc_charts_enable_fallback']) ? 1 : 0;
    
                update_option('zc_charts_default_library', in_array($library, array('chartjs','highcharts'), true) ? $library : 'chartjs');
                update_option('zc_charts_default_timeframe', $timeframe);
                update_option('zc_charts_default_height', $height);
                update_option('zc_charts_enable_controls', (int)$controls);
                update_option('zc_charts_enable_fallback', (int)$fallback);
    
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Charts settings saved.', 'zc-dmt') . '</p></div>';
            }
        }
    }

    // Fetch keys for listing
    $keys = ZC_DMT_Security::list_keys();
    $rest_base = esc_url_raw( rest_url( defined('ZC_DMT_REST_NS') ? ZC_DMT_REST_NS : 'zc-dmt/v1' ) );
    ?>
    <div class="wrap zc-dmt-settings">
        <h1><?php echo esc_html__('ZC DMT Settings', 'zc-dmt'); ?></h1>

        <div class="zc-dmt-settings__section">
            <h2><?php echo esc_html__('Data Sources', 'zc-dmt'); ?></h2>
            <p><?php echo esc_html__('Configure API keys and settings for external data sources.', 'zc-dmt'); ?></p>
            
            <?php
            $fred_api_key = get_option('zc_fred_api_key', '');
            ?>
            <form method="post">
                <?php wp_nonce_field('zc_dmt_settings_action', 'zc_dmt_nonce'); ?>
                <input type="hidden" name="zc_dmt_action" value="save_data_sources" />
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="zc_fred_api_key"><?php echo esc_html__('FRED API Key', 'zc-dmt'); ?></label></th>
                            <td>
                                <input type="text" name="zc_fred_api_key" id="zc_fred_api_key" class="regular-text" value="<?php echo esc_attr($fred_api_key); ?>" placeholder="<?php echo esc_attr__('Enter your FRED API key', 'zc-dmt'); ?>" />
                                <p class="description">
                                    <?php echo esc_html__('Get a free API key from', 'zc-dmt'); ?> 
                                    <a href="https://fred.stlouisfed.org/docs/api/api_key.html" target="_blank"><?php echo esc_html__('FRED API Documentation', 'zc-dmt'); ?></a>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p><button type="submit" class="button button-primary"><?php echo esc_html__('Save Data Sources', 'zc-dmt'); ?></button></p>
            </form>
        </div>

        <div class="zc-dmt-settings__section">
            <h2><?php echo esc_html__('API Keys', 'zc-dmt'); ?></h2>
            <p><?php echo esc_html__('Generate and manage API keys for external access to your data.', 'zc-dmt'); ?></p>

            <form method="post" class="zc-dmt-generate-key-form">
                <?php wp_nonce_field('zc_dmt_settings_action', 'zc_dmt_nonce'); ?>
                <input type="hidden" name="zc_dmt_action" value="generate_key" />
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="key_name"><?php echo esc_html__('Key Name (label)', 'zc-dmt'); ?></label></th>
                            <td>
                                <input name="key_name" id="key_name" type="text" class="regular-text" placeholder="<?php echo esc_attr__('e.g., Production Site', 'zc-dmt'); ?>" />
                                <p class="description"><?php echo esc_html__('For your reference only. The actual secret will be shown once.', 'zc-dmt'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p>
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Generate New API Key', 'zc-dmt'); ?></button>
                </p>
            </form>
        </div>

        <div class="zc-dmt-settings__section">
            <h2><?php echo esc_html__('Existing Keys', 'zc-dmt'); ?></h2>
            <?php if (empty($keys)) : ?>
                <p><?php echo esc_html__('No API keys created yet.', 'zc-dmt'); ?></p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Preview', 'zc-dmt'); ?></th>
                            <th><?php echo esc_html__('Name', 'zc-dmt'); ?></th>
                            <th><?php echo esc_html__('Status', 'zc-dmt'); ?></th>
                            <th><?php echo esc_html__('Created', 'zc-dmt'); ?></th>
                            <th><?php echo esc_html__('Last Used', 'zc-dmt'); ?></th>
                            <th><?php echo esc_html__('Actions', 'zc-dmt'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($keys as $row): ?>
                            <tr>
                                <td><code><?php echo esc_html($row->key_preview); ?></code></td>
                                <td><?php echo esc_html($row->key_name); ?></td>
                                <td>
                                    <?php
                                    $is_active = intval($row->is_active) === 1;
                                    if ($is_active) {
                                        echo '<span class="status-active" style="color:#155724;background:#d4edda;padding:2px 6px;border-radius:3px;">' . esc_html__('Active', 'zc-dmt') . '</span>';
                                    } else {
                                        echo '<span class="status-inactive" style="color:#721c24;background:#f8d7da;padding:2px 6px;border-radius:3px;">' . esc_html__('Revoked', 'zc-dmt') . '</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($row->created_at); ?></td>
                                <td><?php echo esc_html($row->last_used ? $row->last_used : '—'); ?></td>
                                <td>
                                    <?php if (intval($row->is_active) === 1): ?>
                                        <form method="post" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_js(__('Revoke this API key?', 'zc-dmt')); ?>');">
                                            <?php wp_nonce_field('zc_dmt_settings_action', 'zc_dmt_nonce'); ?>
                                            <input type="hidden" name="zc_dmt_action" value="revoke_key" />
                                            <input type="hidden" name="key_id" value="<?php echo esc_attr($row->id); ?>" />
                                            <button type="submit" class="button button-secondary"><?php echo esc_html__('Revoke', 'zc-dmt'); ?></button>
                                        </form>
                                    <?php else: ?>
                                        <em><?php echo esc_html__('No actions', 'zc-dmt'); ?></em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
 
        <div class="zc-dmt-settings__section">
            <h2><?php echo esc_html__('Charts Settings', 'zc-dmt'); ?></h2>
            <p class="description"><?php echo esc_html__('Defaults for chart shortcodes and frontend rendering.', 'zc-dmt'); ?></p>
            <?php
            $library   = get_option('zc_charts_default_library', 'chartjs');
            $timeframe = get_option('zc_charts_default_timeframe', '1y');
            $height    = get_option('zc_charts_default_height', '300px');
            $controls  = (bool) get_option('zc_charts_enable_controls', true);
            $fallback  = (bool) get_option('zc_charts_enable_fallback', true);
            ?>
            <form method="post">
                <?php wp_nonce_field('zc_dmt_settings_action', 'zc_dmt_nonce'); ?>
                <input type="hidden" name="zc_dmt_action" value="save_charts_settings" />
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="zc_charts_default_library"><?php echo esc_html__('Default Library', 'zc-dmt'); ?></label></th>
                            <td>
                                <select name="zc_charts_default_library" id="zc_charts_default_library">
                                    <option value="chartjs" <?php selected($library, 'chartjs'); ?>><?php echo esc_html__('Chart.js', 'zc-dmt'); ?></option>
                                    <option value="highcharts" <?php selected($library, 'highcharts'); ?>><?php echo esc_html__('Highcharts (planned)', 'zc-dmt'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zc_charts_default_timeframe"><?php echo esc_html__('Default Timeframe (dynamic)', 'zc-dmt'); ?></label></th>
                            <td>
                                <select name="zc_charts_default_timeframe" id="zc_charts_default_timeframe">
                                    <?php
                                    $ranges = array('3m','6m','1y','2y','3y','5y','10y','15y','20y','25y','all');
                                    foreach ($ranges as $r) {
                                        echo '<option value="' . esc_attr($r) . '" ' . selected($timeframe, $r, false) . '>' . esc_html(strtoupper($r)) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zc_charts_default_height"><?php echo esc_html__('Default Height', 'zc-dmt'); ?></label></th>
                            <td><input type="text" name="zc_charts_default_height" id="zc_charts_default_height" class="regular-text" value="<?php echo esc_attr($height); ?>" placeholder="300px" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Controls (dynamic)', 'zc-dmt'); ?></th>
                            <td>
                                <label><input type="checkbox" name="zc_charts_enable_controls" <?php checked($controls, true); ?> /> <?php echo esc_html__('Show timeframe controls by default', 'zc-dmt'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Fallback', 'zc-dmt'); ?></th>
                            <td>
                                <label><input type="checkbox" name="zc_charts_enable_fallback" <?php checked($fallback, true); ?> /> <?php echo esc_html__('Enable Google Drive fallback (stubbed for now)', 'zc-dmt'); ?></label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p><button type="submit" class="button button-primary"><?php echo esc_html__('Save Charts Settings', 'zc-dmt'); ?></button></p>
            </form>
        </div>
 
        <div class="zc-dmt-settings__section">
            <h2><?php echo esc_html__('Shortcode Builder', 'zc-dmt'); ?></h2>
            <p class="description"><?php echo esc_html__('Build a chart shortcode. Pick an indicator or type slug, choose options, then copy.', 'zc-dmt'); ?></p>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Indicator', 'zc-dmt'); ?></th>
                        <td>
                            <select id="zc_sb_indicator" style="min-width:280px;">
                                <option value=""><?php echo esc_html__('-- Click "Load Indicators" if empty --', 'zc-dmt'); ?></option>
                            </select>
                            <div style="margin-top:6px;">
                                <button type="button" class="button" id="zc_sb_load"><?php echo esc_html__('Load Indicators', 'zc-dmt'); ?></button>
                            </div>
                            <div style="margin-top:6px;">
                                <label for="zc_sb_slug"><?php echo esc_html__('Or enter slug manually:', 'zc-dmt'); ?></label>
                                <input type="text" id="zc_sb_slug" class="regular-text" placeholder="<?php echo esc_attr__('e.g., gdp_us', 'zc-dmt'); ?>" />
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Library', 'zc-dmt'); ?></th>
                        <td>
                            <select id="zc_sb_library">
                                <option value="chartjs" <?php selected($library, 'chartjs'); ?>><?php echo esc_html__('Chart.js', 'zc-dmt'); ?></option>
                                <option value="highcharts" <?php selected($library, 'highcharts'); ?>><?php echo esc_html__('Highcharts', 'zc-dmt'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Timeframe (dynamic only)', 'zc-dmt'); ?></th>
                        <td>
                            <select id="zc_sb_timeframe">
                                <?php
                                $ranges = array('3m','6m','1y','2y','3y','5y','10y','15y','20y','25y','all');
                                foreach ($ranges as $r) {
                                    echo '<option value="' . esc_attr($r) . '" ' . selected($timeframe, $r, false) . '>' . esc_html(strtoupper($r)) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Height', 'zc-dmt'); ?></th>
                        <td><input type="text" id="zc_sb_height" class="regular-text" value="<?php echo esc_attr($height); ?>" placeholder="300px" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Controls (dynamic only)', 'zc-dmt'); ?></th>
                        <td><label><input type="checkbox" id="zc_sb_controls" <?php checked($controls, true); ?> /> <?php echo esc_html__('Show timeframe controls', 'zc-dmt'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Output', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" id="zc_sb_output" class="large-text" readonly placeholder='[zc_chart_dynamic id="slug"]' />
                            <div style="margin-top:8px;">
                                <button type="button" class="button button-primary" id="zc_sb_build"><?php echo esc_html__('Build Shortcode', 'zc-dmt'); ?></button>
                                <button type="button" class="button" id="zc_sb_copy"><?php echo esc_html__('Copy', 'zc-dmt'); ?></button>
                                <button type="button" class="button" id="zc_sb_test"><?php echo esc_html__('Test Fetch', 'zc-dmt'); ?></button>
                                <span id="zc_sb_msg" style="margin-left:8px; opacity:0.8;"></span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
 
        <div class="zc-dmt-settings__section">
            <h2><?php echo esc_html__('Developer Info (Read-Only)', 'zc-dmt'); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('REST Base', 'zc-dmt'); ?></th>
                        <td><code><?php echo esc_html($rest_base); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Validate Key Endpoint (POST)', 'zc-dmt'); ?></th>
                        <td><code><?php echo esc_html( trailingslashit($rest_base) . 'validate-key' ); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Data Endpoint (GET)', 'zc-dmt'); ?></th>
                        <td><code><?php echo esc_html( trailingslashit($rest_base) . 'data/{slug}?access_key=YOUR_KEY' ); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Backup Endpoint (GET, stub)', 'zc-dmt'); ?></th>
                        <td><code><?php echo esc_html( trailingslashit($rest_base) . 'backup/{slug}?access_key=YOUR_KEY' ); ?></code></td>
                    </tr>
                </tbody>
            </table>
            <p class="description">
                <?php echo esc_html__('Use these endpoints from the ZC Charts plugin. Charts will not render without a valid API key.', 'zc-dmt'); ?>
            </p>
        </div>
    </div>
    <?php
}
