<?php
/**
 * Google Sheets Data Source Form - FINAL FIX
 * Path: admin/sources-forms/google-sheets-form.php
 */

if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

// Handle notices
$notice = '';
if (!empty($_POST['zc_source_action'])) {
    if (!isset($_POST['zc_source_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['zc_source_nonce']), 'zc_source_action')) {
        $notice = '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'zc-dmt') . '</p></div>';
    } else {
        $action = sanitize_text_field($_POST['zc_source_action']);
        if ($action === 'add_indicator') {
            $name        = sanitize_text_field($_POST['name'] ?? '');
            $slug        = sanitize_title($_POST['slug'] ?? '');
            $description = wp_kses_post($_POST['description'] ?? '');
            $csv_url     = esc_url_raw($_POST['gs_csv_url'] ?? '');
            
            if (!$name || !$slug) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Name and Slug required.', 'zc-dmt') . '</p></div>';
            } elseif (!$csv_url) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('CSV URL required.', 'zc-dmt') . '</p></div>';
            } else {
                // Use the exact 'csv_url' key expected by the adapter
                $cfg = ['csv_url' => $csv_url];
                $res = ZC_DMT_Indicators::create_indicator($name, $slug, $description, 'google-sheets', $cfg, 1);
                if (is_wp_error($res)) {
                    $notice = '<div class="notice notice-error"><p>' . esc_html($res->get_error_message()) . '</p></div>';
                } else {
                    $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Google Sheets indicator created!', 'zc-dmt') . '</p></div>';
                    $_POST = [];
                }
            }
        }
    }
}

// Pre-fill form values
$g_name = esc_attr($_POST['name'] ?? '');
$g_slug = esc_attr($_POST['slug'] ?? '');
$g_desc = esc_textarea($_POST['description'] ?? '');
$g_csv  = esc_attr($_POST['gs_csv_url'] ?? '');
?>

<div class="wrap zc-dmt-source-form">
    <h1><?php esc_html_e('Google Sheets Data Source', 'zc-dmt'); ?></h1>

    <div class="zc-notice-container"><?php echo $notice; ?></div>

    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action"><?php esc_html_e('← Back','zc-dmt'); ?></a>
    <hr>

    <div class="zc-source-info" style="background:#f8fafc;border:1px solid #e2e8f0;padding:16px;border-radius:6px;margin-bottom:20px;">
        <h3><?php esc_html_e('About Google Sheets Source','zc-dmt'); ?></h3>
        <p><?php esc_html_e('Fetches data from Google Sheets via published CSV link or share URL. Adapter auto-detects headers and date/value columns.', 'zc-dmt'); ?></p>
    </div>

    <div class="zc-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <!-- Add Indicator Form -->
        <div class="zc-card" style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:6px;">
            <h2><?php esc_html_e('Add New Google Sheets Indicator','zc-dmt'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('zc_source_action','zc_source_nonce'); ?>
                <input type="hidden" name="zc_source_action" value="add_indicator">
                <table class="form-table"><tbody>
                    <tr>
                        <th><?php esc_html_e('Name','zc-dmt'); ?></th>
                        <td><input id="gs-name" name="name" class="regular-text" value="<?php echo $g_name; ?>" required></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Slug','zc-dmt'); ?></th>
                        <td><input id="gs-slug" name="slug" class="regular-text" value="<?php echo $g_slug; ?>" required></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Description','zc-dmt'); ?></th>
                        <td><textarea name="description" class="large-text" rows="3"><?php echo $g_desc; ?></textarea></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('CSV URL / Share URL','zc-dmt'); ?></th>
                        <td><input name="gs_csv_url" class="regular-text" value="<?php echo $g_csv; ?>" placeholder="https://docs.google.com/.../gviz/tq?tqx=out:csv" required></td>
                    </tr>
                </tbody></table>
                <p><button class="button button-primary"><?php esc_html_e('Create Indicator','zc-dmt'); ?></button></p>
            </form>
        </div>

        <!-- Test Connection Form -->
        <div class="zc-card" style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:6px;">
            <h2><?php esc_html_e('Test Connection','zc-dmt'); ?></h2>
            <form id="zc-test-form">
                <table class="form-table"><tbody>
                    <tr>
                        <th><?php esc_html_e('CSV URL to Test','zc-dmt'); ?></th>
                        <td><input name="test_csv_url" class="regular-text" placeholder="Paste URL to test"></td>
                    </tr>
                </tbody></table>
                <p><button type="button" class="button button-secondary zc-test-button"><?php esc_html_e('Test Connection','zc-dmt'); ?></button></p>
            </form>
            <div id="zc-test-results"></div>
        </div>
    </div>
</div>

<script>
jQuery(function($){
    // Slug auto-fill
    $('#gs-name').on('input', function(){
        var slug = $(this).val().toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-');
        $('#gs-slug').val(slug);
    });

    // AJAX Test Connection
    $('.zc-test-button').on('click', function(){
        var url = $('input[name="test_csv_url"]').val();
        if (!url) {
            alert('<?php esc_html_e('Enter a URL to test', 'zc-dmt'); ?>');
            return;
        }
        var $btn = $(this), $res = $('#zc-test-results');
        $btn.prop('disabled', true).text('<?php esc_html_e('Testing...', 'zc-dmt'); ?>');
        $res.html('<p>Testing connection...</p>');

        $.post(ajaxurl, {
            action: 'zc_dmt_test_source',
            nonce: '<?php echo wp_create_nonce('zc_dmt_test_source'); ?>',
            source: 'google-sheets',
            csv_url: url
        }).done(function(resp){
            if (resp.success) {
                $res.html('<div class="notice notice-success"><p><strong>✓ Success!</strong> '+resp.data+' data points retrieved.</p></div>');
            } else {
                $res.html('<div class="notice notice-error"><p><strong>✗ Test Failed:</strong> '+resp.data+'</p></div>');
            }
        }).fail(function(){
            $res.html('<div class="notice notice-error"><p><strong>✗ Connection Error:</strong> Unable to test connection.</p></div>');
        }).always(function(){
            $btn.prop('disabled', false).text('<?php esc_html_e('Test Connection','zc-dmt'); ?>');
        });
    });
});
</script>
