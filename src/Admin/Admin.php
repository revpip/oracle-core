<?php
namespace OracleCore\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class Admin
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
    }

    public function menu(): void
    {
        add_menu_page(
            __('Oracle Core', 'oracle-core'),
            __('Oracle Core', 'oracle-core'),
            'manage_options',
            'oracle-core',
            [$this, 'dashboard'],
            'dashicons-chart-area',
            58
        );

        add_submenu_page('oracle-core', __('Settings', 'oracle-core'), __('Settings', 'oracle-core'), 'manage_options', 'oracle-core-settings', [$this, 'settingsPage']);
    }

    public function settings(): void
    {
        register_setting('oracle_core_settings', 'oracle_core_openai_api_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('oracle_core_settings', 'oracle_core_premium_url', ['sanitize_callback' => 'esc_url_raw']);
        register_setting('oracle_core_settings', 'oracle_core_disclaimer', ['sanitize_callback' => 'wp_kses_post']);
    }

    public function dashboard(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix . 'oracle_';
        $questions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}questions WHERE is_active = 1");
        $sessions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}sessions");
        $completed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}sessions WHERE status = 'completed'");
        $flags = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}sessions WHERE fraud_flags IS NOT NULL AND fraud_flags != ''");
        ?>
        <div class="wrap oracle-admin">
            <h1><?php esc_html_e('Oracle Core', 'oracle-core'); ?></h1>
            <p class="oracle-muted">A launchable foundation for structured self-reflection, evidence mapping and future AI-powered insight.</p>

            <div class="oracle-grid">
                <div class="oracle-card"><strong><?php echo esc_html($questions); ?></strong><span>Active questions</span></div>
                <div class="oracle-card"><strong><?php echo esc_html($sessions); ?></strong><span>Sessions started</span></div>
                <div class="oracle-card"><strong><?php echo esc_html($completed); ?></strong><span>Reports completed</span></div>
                <div class="oracle-card"><strong><?php echo esc_html($flags); ?></strong><span>Quality flags</span></div>
            </div>

            <div class="oracle-panel">
                <h2>Launch shortcode</h2>
                <code>[oracle_assessment]</code>
                <p>Add this shortcode to any WordPress page to launch the first assessment.</p>
            </div>

            <div class="oracle-panel">
                <h2>Current build</h2>
                <p><strong>Version:</strong> <?php echo esc_html(ORACLE_CORE_VERSION); ?></p>
                <p><strong>Status:</strong> Sprint 0 foundation. Safe self-reflection wording only. No diagnostic claims.</p>
            </div>
        </div>
        <?php
    }

    public function settingsPage(): void
    {
        $default_disclaimer = 'Oracle Core is not a diagnostic tool. It highlights self-reported patterns and may suggest areas worth discussing with an appropriately qualified professional.';
        ?>
        <div class="wrap oracle-admin">
            <h1><?php esc_html_e('Oracle Core Settings', 'oracle-core'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('oracle_core_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="oracle_core_openai_api_key">OpenAI API Key</label></th>
                        <td><input type="password" class="regular-text" id="oracle_core_openai_api_key" name="oracle_core_openai_api_key" value="<?php echo esc_attr(get_option('oracle_core_openai_api_key', '')); ?>" autocomplete="off"><p class="description">Stored for the future AI Reflection Engine. Not used in Sprint 0.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oracle_core_premium_url">Premium Report URL</label></th>
                        <td><input type="url" class="regular-text" id="oracle_core_premium_url" name="oracle_core_premium_url" value="<?php echo esc_attr(get_option('oracle_core_premium_url', '')); ?>"><p class="description">Optional button shown after results.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oracle_core_disclaimer">Disclaimer</label></th>
                        <td><textarea class="large-text" rows="4" id="oracle_core_disclaimer" name="oracle_core_disclaimer"><?php echo esc_textarea(get_option('oracle_core_disclaimer', $default_disclaimer)); ?></textarea></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
