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
        add_action('admin_post_oracle_save_question', [$this, 'saveQuestion']);
        add_action('admin_post_oracle_toggle_question', [$this, 'toggleQuestion']);
        add_action('admin_post_oracle_export_questions', [$this, 'exportQuestions']);
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

        add_submenu_page('oracle-core', __('Questions', 'oracle-core'), __('Questions', 'oracle-core'), 'manage_options', 'oracle-core-questions', [$this, 'questionsPage']);
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
                <p><strong>Status:</strong> Sprint 1 question management foundation. Safe self-reflection wording only. No diagnostic claims.</p>
            </div>
        </div>
        <?php
    }

    public function questionsPage(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'oracle_questions';
        $editing = null;

        if (isset($_GET['edit'])) {
            $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", absint($_GET['edit'])));
        }

        $questions = $wpdb->get_results("SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC");
        ?>
        <div class="wrap oracle-admin">
            <h1><?php esc_html_e('Oracle Questions', 'oracle-core'); ?></h1>
            <p class="oracle-muted">Manage the launch question bank. Every question maps to a dimension and can be weighted for scoring.</p>

            <div class="oracle-panel">
                <h2><?php echo $editing ? 'Edit question' : 'Add question'; ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="oracle_save_question">
                    <input type="hidden" name="question_id" value="<?php echo esc_attr($editing->id ?? 0); ?>">
                    <?php wp_nonce_field('oracle_save_question', 'oracle_question_nonce'); ?>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><label for="question_text">Question</label></th><td><textarea class="large-text" rows="3" id="question_text" name="question_text" required><?php echo esc_textarea($editing->question_text ?? ''); ?></textarea></td></tr>
                        <tr><th scope="row"><label for="category">Category</label></th><td><input class="regular-text" id="category" name="category" value="<?php echo esc_attr($editing->category ?? ''); ?>" required placeholder="Executive Function"></td></tr>
                        <tr><th scope="row"><label for="dimension">Dimension</label></th><td><input class="regular-text" id="dimension" name="dimension" value="<?php echo esc_attr($editing->dimension ?? ''); ?>" required placeholder="attention"></td></tr>
                        <tr><th scope="row"><label for="weight">Weight</label></th><td><input type="number" step="0.1" min="0" max="10" id="weight" name="weight" value="<?php echo esc_attr($editing->weight ?? '1'); ?>"></td></tr>
                        <tr><th scope="row"><label for="sort_order">Sort order</label></th><td><input type="number" id="sort_order" name="sort_order" value="<?php echo esc_attr($editing->sort_order ?? '0'); ?>"></td></tr>
                        <tr><th scope="row"><label for="help_text">Help text</label></th><td><textarea class="large-text" rows="2" id="help_text" name="help_text"><?php echo esc_textarea($editing->help_text ?? ''); ?></textarea></td></tr>
                    </table>
                    <?php submit_button($editing ? 'Update question' : 'Add question'); ?>
                </form>
            </div>

            <div class="oracle-panel">
                <h2>Question bank</h2>
                <p><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=oracle_export_questions'), 'oracle_export_questions')); ?>">Export CSV</a></p>
                <table class="widefat striped oracle-table">
                    <thead><tr><th>Order</th><th>Question</th><th>Category</th><th>Dimension</th><th>Weight</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($questions as $q): ?>
                        <tr>
                            <td><?php echo esc_html($q->sort_order); ?></td>
                            <td><?php echo esc_html($q->question_text); ?></td>
                            <td><?php echo esc_html($q->category); ?></td>
                            <td><code><?php echo esc_html($q->dimension); ?></code></td>
                            <td><?php echo esc_html($q->weight); ?></td>
                            <td><?php echo $q->is_active ? '<span class="oracle-status-on">Active</span>' : '<span class="oracle-status-off">Inactive</span>'; ?></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=oracle-core-questions&edit=' . absint($q->id))); ?>">Edit</a>
                                <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=oracle_toggle_question&id=' . absint($q->id)), 'oracle_toggle_question')); ?>"><?php echo $q->is_active ? 'Deactivate' : 'Activate'; ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function saveQuestion(): void
    {
        if (!current_user_can('manage_options') || !isset($_POST['oracle_question_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['oracle_question_nonce'])), 'oracle_save_question')) {
            wp_die('Security check failed.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oracle_questions';
        $id = isset($_POST['question_id']) ? absint($_POST['question_id']) : 0;
        $data = [
            'category' => sanitize_text_field(wp_unslash($_POST['category'] ?? 'General')),
            'dimension' => sanitize_key(wp_unslash($_POST['dimension'] ?? 'general')),
            'question_text' => sanitize_textarea_field(wp_unslash($_POST['question_text'] ?? '')),
            'help_text' => sanitize_textarea_field(wp_unslash($_POST['help_text'] ?? '')),
            'weight' => (float) ($_POST['weight'] ?? 1),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'updated_at' => current_time('mysql'),
        ];

        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $data['uuid'] = wp_generate_uuid4();
            $data['is_active'] = 1;
            $data['version'] = 1;
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
        }

        wp_safe_redirect(admin_url('admin.php?page=oracle-core-questions&saved=1'));
        exit;
    }

    public function toggleQuestion(): void
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'oracle_toggle_question')) {
            wp_die('Security check failed.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oracle_questions';
        $id = absint($_GET['id'] ?? 0);
        $current = (int) $wpdb->get_var($wpdb->prepare("SELECT is_active FROM {$table} WHERE id = %d", $id));
        $wpdb->update($table, ['is_active' => $current ? 0 : 1, 'updated_at' => current_time('mysql')], ['id' => $id]);

        wp_safe_redirect(admin_url('admin.php?page=oracle-core-questions'));
        exit;
    }

    public function exportQuestions(): void
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'oracle_export_questions')) {
            wp_die('Security check failed.');
        }

        global $wpdb;
        $rows = $wpdb->get_results("SELECT uuid, category, dimension, question_text, help_text, weight, sort_order, is_active, version FROM {$wpdb->prefix}oracle_questions ORDER BY sort_order ASC, id ASC", ARRAY_A);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=oracle-questions-' . gmdate('Ymd-His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['uuid', 'category', 'dimension', 'question_text', 'help_text', 'weight', 'sort_order', 'is_active', 'version']);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
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
