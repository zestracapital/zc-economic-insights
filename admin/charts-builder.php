<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Charts Builder Admin Page - Simple & User Friendly
 * Visual shortcode builder for economic dashboard
 */

function zc_dmt_render_charts_builder_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'zc-dmt'));
    }

    // Get all active indicators for the builder
    global $wpdb;
    $indicators_table = $wpdb->prefix . 'zc_dmt_indicators';
    $indicators = $wpdb->get_results("SELECT * FROM {$indicators_table} WHERE is_active = 1 ORDER BY name ASC");
    ?>

    <div class="wrap zc-dmt-charts">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php _e('Charts Builder', 'zc-dmt'); ?>
        </h1>
        <p class="description">
            <?php _e('Create powerful economic charts using your indicators. Simple, fast, and user-friendly.', 'zc-dmt'); ?>
        </p>

        <!-- Charts Introduction -->
        <div class="zc-charts-intro">
            <div class="zc-intro-grid">
                <div class="zc-intro-card">
                    <div class="zc-intro-icon">📊</div>
                    <h3><?php _e('Dynamic Charts', 'zc-dmt'); ?></h3>
                    <p><?php _e('Interactive charts with search, comparison, and timeframe features.', 'zc-dmt'); ?></p>
                </div>
                <div class="zc-intro-card">
                    <div class="zc-intro-icon">📈</div>
                    <h3><?php _e('Static Charts', 'zc-dmt'); ?></h3>
                    <p><?php _e('Fixed charts showing specific data. Great for posts and articles.', 'zc-dmt'); ?></p>
                </div>
                <div class="zc-intro-card">
                    <div class="zc-intro-icon">🎯</div>
                    <h3><?php _e('Card Charts', 'zc-dmt'); ?></h3>
                    <p><?php _e('Simple chart cards with minimal features. Perfect for dashboards.', 'zc-dmt'); ?></p>
                </div>
            </div>
        </div>

        <!-- Shortcode Builder Form -->
        <div class="zc-builder-main">
            <div class="zc-builder-form">
                <h2><?php _e('🔧 Build Your Chart Shortcode', 'zc-dmt'); ?></h2>
                
                <form id="zc-charts-builder-form">
                    <div class="zc-form-grid">
                        <!-- Chart Type Selection -->
                        <div class="zc-form-group">
                            <label for="zc_chart_type"><?php _e('Chart Type', 'zc-dmt'); ?></label>
                            <select id="zc_chart_type" name="chart_type" class="zc-select">
                                <option value="dynamic"><?php _e('📊 Dynamic (Full Functionality)', 'zc-dmt'); ?></option>
                                <option value="static"><?php _e('📈 Static (Partial Functionality)', 'zc-dmt'); ?></option>
                                <option value="card"><?php _e('🎯 Card (Simple Chart)', 'zc-dmt'); ?></option>
                            </select>
                            <p class="zc-description" id="chart-type-desc"><?php _e('Dynamic charts include search, comparison, and timeframe controls.', 'zc-dmt'); ?></p>
                        </div>

                        <!-- Search Bar for Indicators -->
                        <div class="zc-form-group">
                            <label for="zc_indicator_search"><?php _e('Search & Select Indicator', 'zc-dmt'); ?></label>
                            <div class="zc-search-container">
                                <input type="text" id="zc_indicator_search" class="zc-search-input" placeholder="<?php _e('🔍 Search indicators...', 'zc-dmt'); ?>" autocomplete="off">
                                <input type="hidden" id="selected_indicator_slug" name="indicator_slug" value="">
                                
                                <div id="zc_indicator_dropdown" class="zc-dropdown" style="display: none;">
                                    <?php if (!empty($indicators)): ?>
                                        <?php foreach ($indicators as $indicator): ?>
                                            <div class="zc-dropdown-item" data-slug="<?php echo esc_attr($indicator->slug); ?>" data-name="<?php echo esc_attr($indicator->name); ?>" data-source="<?php echo esc_attr($indicator->source_type); ?>">
                                                <div class="zc-indicator-info">
                                                    <strong><?php echo esc_html($indicator->name); ?></strong>
                                                    <div class="zc-indicator-meta">
                                                        <code><?php echo esc_html($indicator->slug); ?></code>
                                                        <span class="zc-source-tag"><?php echo esc_html(ucwords(str_replace(['_', '-'], ' ', $indicator->source_type))); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="zc-dropdown-item zc-no-indicators">
                                            <div class="zc-no-data">
                                                <span class="dashicons dashicons-info"></span>
                                                <?php _e('No active indicators found.', 'zc-dmt'); ?>
