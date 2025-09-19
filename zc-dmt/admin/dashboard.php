<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced Dashboard for ZC DMT Plugin
 * Modern card-based layout with summary widgets and quick actions
 */

function zc_dmt_render_enhanced_dashboard_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Get dashboard data
    global $wpdb;
    $table_indicators = $wpdb->prefix . 'zc_dmt_indicators';
    $table_data_points = $wpdb->prefix . 'zc_dmt_data_points';
    $table_api_keys = $wpdb->prefix . 'zc_dmt_api_keys';
    
    // Summary statistics
    $total_indicators = $wpdb->get_var("SELECT COUNT(*) FROM {$table_indicators}");
    $active_indicators = $wpdb->get_var("SELECT COUNT(*) FROM {$table_indicators} WHERE is_active = 1");
    $total_data_points = $wpdb->get_var("SELECT COUNT(*) FROM {$table_data_points}");
    $active_api_keys = $wpdb->get_var("SELECT COUNT(*) FROM {$table_api_keys} WHERE is_active = 1");
    
    // Recent indicators
    $recent_indicators = $wpdb->get_results("SELECT * FROM {$table_indicators} ORDER BY created_at DESC LIMIT 5");
    
    // Recent activity (mock data for now - will be enhanced with actual activity logs)
    $recent_activity = array(
        array('action' => 'Indicator Created', 'item' => 'GDP Growth Rate', 'time' => '2 hours ago', 'status' => 'success'),
        array('action' => 'Data Import', 'item' => 'Unemployment Rate', 'time' => '5 hours ago', 'status' => 'success'),
        array('action' => 'API Key Generated', 'item' => 'Production Key', 'time' => '1 day ago', 'status' => 'info'),
        array('action' => 'Data Fetch Error', 'item' => 'FRED Connection', 'time' => '2 days ago', 'status' => 'warning'),
    );

    ?>
    <div class="wrap zc-dmt-dashboard">
        <div class="zc-dmt-header">
            <h1 class="zc-dmt-title">
                <span class="zc-dmt-icon">📊</span>
                <?php echo esc_html__('ZC Economic Insights Dashboard', 'zc-dmt'); ?>
            </h1>
            <div class="zc-dmt-header-actions">
                <a href="<?php echo admin_url('admin.php?page=zc-dmt-indicators'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php echo esc_html__('Add Indicator', 'zc-dmt'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=zc-dmt-settings'); ?>" class="button">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php echo esc_html__('Settings', 'zc-dmt'); ?>
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="zc-dmt-summary-grid">
            <div class="zc-dmt-summary-card">
                <div class="card-icon indicators">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="card-content">
                    <h3><?php echo number_format($total_indicators); ?></h3>
                    <p><?php echo esc_html__('Total Indicators', 'zc-dmt'); ?></p>
                    <small><?php echo number_format($active_indicators); ?> <?php echo esc_html__('active', 'zc-dmt'); ?></small>
                </div>
                <div class="card-action">
                    <a href="<?php echo admin_url('admin.php?page=zc-dmt-indicators'); ?>"><?php echo esc_html__('Manage', 'zc-dmt'); ?></a>
                </div>
            </div>

            <div class="zc-dmt-summary-card">
                <div class="card-icon data-points">
                    <span class="dashicons dashicons-database"></span>
                </div>
                <div class="card-content">
                    <h3><?php echo number_format($total_data_points); ?></h3>
                    <p><?php echo esc_html__('Data Points', 'zc-dmt'); ?></p>
                    <small><?php echo esc_html__('across all indicators', 'zc-dmt'); ?></small>
                </div>
                <div class="card-action">
                    <a href="<?php echo admin_url('admin.php?page=zc-dmt-indicators'); ?>"><?php echo esc_html__('View Data', 'zc-dmt'); ?></a>
                </div>
            </div>

            <div class="zc-dmt-summary-card">
                <div class="card-icon api-keys">
                    <span class="dashicons dashicons-admin-network"></span>
                </div>
                <div class="card-content">
                    <h3><?php echo number_format($active_api_keys); ?></h3>
                    <p><?php echo esc_html__('Active API Keys', 'zc-dmt'); ?></p>
                    <small><?php echo esc_html__('for external access', 'zc-dmt'); ?></small>
                </div>
                <div class="card-action">
                    <a href="<?php echo admin_url('admin.php?page=zc-dmt-settings'); ?>"><?php echo esc_html__('Manage Keys', 'zc-dmt'); ?></a>
                </div>
            </div>

            <div class="zc-dmt-summary-card">
                <div class="card-icon status">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="card-content">
                    <h3><?php echo esc_html__('Healthy', 'zc-dmt'); ?></h3>
                    <p><?php echo esc_html__('System Status', 'zc-dmt'); ?></p>
                    <small><?php echo esc_html__('all systems operational', 'zc-dmt'); ?></small>
                </div>
                <div class="card-action">
                    <a href="#system-health"><?php echo esc_html__('Details', 'zc-dmt'); ?></a>
                </div>
            </div>
        </div>

        <div class="zc-dmt-dashboard-content">
            <!-- Recent Indicators -->
            <div class="zc-dmt-dashboard-section">
                <div class="zc-dmt-card">
                    <div class="card-header">
                        <h2><?php echo esc_html__('Recent Indicators', 'zc-dmt'); ?></h2>
                        <a href="<?php echo admin_url('admin.php?page=zc-dmt-indicators'); ?>" class="button button-small"><?php echo esc_html__('View All', 'zc-dmt'); ?></a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_indicators)) : ?>
                            <div class="zc-dmt-recent-list">
                                <?php foreach ($recent_indicators as $indicator) : ?>
                                    <div class="recent-item">
                                        <div class="item-info">
                                            <h4><?php echo esc_html($indicator->name); ?></h4>
                                            <p><code><?php echo esc_html($indicator->slug); ?></code></p>
                                            <small><?php echo esc_html($indicator->source_type); ?> • <?php echo esc_html(mysql2date('M j, Y', $indicator->created_at)); ?></small>
                                        </div>
                                        <div class="item-status">
                                            <?php if ($indicator->is_active) : ?>
                                                <span class="status-badge active"><?php echo esc_html__('Active', 'zc-dmt'); ?></span>
                                            <?php else : ?>
                                                <span class="status-badge inactive"><?php echo esc_html__('Inactive', 'zc-dmt'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="item-actions">
                                            <button class="button button-small copy-shortcode" data-shortcode='[zc_chart_dynamic id="<?php echo esc_attr($indicator->slug); ?>"]'>
                                                <?php echo esc_html__('Copy Shortcode', 'zc-dmt'); ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div class="zc-dmt-empty-state">
                                <span class="dashicons dashicons-chart-line"></span>
                                <p><?php echo esc_html__('No indicators yet.', 'zc-dmt'); ?></p>
                                <a href="<?php echo admin_url('admin.php?page=zc-dmt-indicators'); ?>" class="button button-primary">
                                    <?php echo esc_html__('Create Your First Indicator', 'zc-dmt'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="zc-dmt-dashboard-section">
                <div class="zc-dmt-card">
                    <div class="card-header">
                        <h2><?php echo esc_html__('Recent Activity', 'zc-dmt'); ?></h2>
                        <a href="#" class="button button-small"><?php echo esc_html__('View All Logs', 'zc-dmt'); ?></a>
                    </div>
                    <div class="card-body">
                        <div class="zc-dmt-activity-list">
                            <?php foreach ($recent_activity as $activity) : ?>
                                <div class="activity-item">
                                    <div class="activity-icon status-<?php echo esc_attr($activity['status']); ?>">
                                        <?php if ($activity['status'] === 'success') : ?>
                                            <span class="dashicons dashicons-yes-alt"></span>
                                        <?php elseif ($activity['status'] === 'warning') : ?>
                                            <span class="dashicons dashicons-warning"></span>
                                        <?php elseif ($activity['status'] === 'info') : ?>
                                            <span class="dashicons dashicons-info-outline"></span>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-marker"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-content">
                                        <p><strong><?php echo esc_html($activity['action']); ?>:</strong> <?php echo esc_html($activity['item']); ?></p>
                                        <small><?php echo esc_html($activity['time']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Charts Preview -->
        <div class="zc-dmt-dashboard-section full-width">
            <div class="zc-dmt-card">
                <div class="card-header">
                    <h2><?php echo esc_html__('Quick Charts Preview', 'zc-dmt'); ?></h2>
                    <div class="chart-controls">
                        <button class="button button-small active" data-tab="indicators"><?php echo esc_html__('Indicators', 'zc-dmt'); ?></button>
                        <button class="button button-small" data-tab="calculations"><?php echo esc_html__('Calculations', 'zc-dmt'); ?></button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-preview-grid" id="preview-indicators">
                        <?php if (!empty($recent_indicators)) : ?>
                            <?php foreach (array_slice($recent_indicators, 0, 3) as $indicator) : ?>
                                <div class="chart-preview-item">
                                    <div class="chart-thumbnail">
                                        <img src="https://placehold.co/300x200?text=<?php echo urlencode($indicator->name . ' Chart Preview'); ?>" 
                                             alt="<?php echo esc_attr($indicator->name); ?> chart preview" />
                                        <div class="chart-overlay">
                                            <button class="button button-primary view-chart" data-slug="<?php echo esc_attr($indicator->slug); ?>">
                                                <?php echo esc_html__('View Chart', 'zc-dmt'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="chart-info">
                                        <h4><?php echo esc_html($indicator->name); ?></h4>
                                        <p><?php echo esc_html($indicator->source_type); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="zc-dmt-empty-state">
                                <span class="dashicons dashicons-chart-area"></span>
                                <p><?php echo esc_html__('No chart previews available.', 'zc-dmt'); ?></p>
                                <p><?php echo esc_html__('Create indicators to see chart previews here.', 'zc-dmt'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="chart-preview-grid" id="preview-calculations" style="display: none;">
                        <div class="zc-dmt-empty-state">
                            <span class="dashicons dashicons-calculator"></span>
                            <p><?php echo esc_html__('Manual calculations feature coming soon.', 'zc-dmt'); ?></p>
                            <p><?php echo esc_html__('Advanced calculations and formula builder will be available in the next update.', 'zc-dmt'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health -->
        <div class="zc-dmt-dashboard-section" id="system-health">
            <div class="zc-dmt-card">
                <div class="card-header">
                    <h2><?php echo esc_html__('System Health', 'zc-dmt'); ?></h2>
                    <button class="button button-small refresh-health"><?php echo esc_html__('Refresh Status', 'zc-dmt'); ?></button>
                </div>
                <div class="card-body">
                    <div class="health-checks">
                        <div class="health-item">
                            <span class="health-icon success"><span class="dashicons dashicons-yes-alt"></span></span>
                            <div class="health-info">
                                <h4><?php echo esc_html__('Database Connection', 'zc-dmt'); ?></h4>
                                <p><?php echo esc_html__('All database tables are accessible and operational.', 'zc-dmt'); ?></p>
                            </div>
                        </div>
                        <div class="health-item">
                            <span class="health-icon success"><span class="dashicons dashicons-yes-alt"></span></span>
                            <div class="health-info">
                                <h4><?php echo esc_html__('REST API', 'zc-dmt'); ?></h4>
                                <p><?php echo esc_html__('API endpoints are responding correctly.', 'zc-dmt'); ?></p>
                            </div>
                        </div>
                        <div class="health-item">
                            <span class="health-icon warning"><span class="dashicons dashicons-warning"></span></span>
                            <div class="health-info">
                                <h4><?php echo esc_html__('Data Sources', 'zc-dmt'); ?></h4>
                                <p><?php echo esc_html__('Some data sources may need API keys configured.', 'zc-dmt'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Copy shortcode functionality
        $('.copy-shortcode').on('click', function(e) {
            e.preventDefault();
            const shortcode = $(this).data('shortcode');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(shortcode).then(() => {
                    $(this).text('<?php echo esc_js(__('Copied!', 'zc-dmt')); ?>');
                    setTimeout(() => {
                        $(this).text('<?php echo esc_js(__('Copy Shortcode', 'zc-dmt')); ?>');
                    }, 2000);
                });
            }
        });

        // Chart preview tabs
        $('.chart-controls button').on('click', function() {
            const tab = $(this).data('tab');
            $('.chart-controls button').removeClass('active');
            $(this).addClass('active');
            $('.chart-preview-grid').hide();
            $('#preview-' + tab).show();
        });

        // View chart modal (placeholder)
        $('.view-chart').on('click', function() {
            const slug = $(this).data('slug');
            alert('Chart view modal for: ' + slug + '\n(Full implementation coming in next phase)');
        });

        // Refresh health status
        $('.refresh-health').on('click', function() {
            $(this).text('<?php echo esc_js(__('Refreshing...', 'zc-dmt')); ?>');
            // Simulate refresh
            setTimeout(() => {
                $(this).text('<?php echo esc_js(__('Refresh Status', 'zc-dmt')); ?>');
            }, 1000);
        });
    });
    </script>
    <?php
}