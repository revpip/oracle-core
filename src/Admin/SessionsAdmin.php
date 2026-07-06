<?php
namespace OracleCore\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class SessionsAdmin
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
    }

    public function menu(): void
    {
        add_submenu_page(
            'oracle-core',
            __('Sessions', 'oracle-core'),
            __('Sessions', 'oracle-core'),
            'manage_options',
            'oracle-core-sessions',
            [$this, 'page']
        );
    }

    public function page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }

        global $wpdb;
        $sessionUuid = isset($_GET['session']) ? sanitize_text_field(wp_unslash($_GET['session'])) : '';

        if ($sessionUuid) {
            $this->detail($sessionUuid);
            return;
        }

        $sessions = $wpdb->get_results(
            "SELECT s.*,
                (SELECT COUNT(*) FROM {$wpdb->prefix}oracle_answers a WHERE a.session_uuid = s.uuid) AS answer_count,
                (SELECT COUNT(*) FROM {$wpdb->prefix}oracle_observations o WHERE o.session_uuid = s.uuid) AS observation_count,
                (SELECT COUNT(*) FROM {$wpdb->prefix}oracle_patterns p WHERE p.session_uuid = s.uuid) AS pattern_count
             FROM {$wpdb->prefix}oracle_sessions s
             ORDER BY s.id DESC
             LIMIT 100"
        );
        ?>
        <div class="wrap oracle-admin">
            <h1><?php esc_html_e('Oracle Sessions', 'oracle-core'); ?></h1>
            <p class="oracle-muted">Review completed assessments, quality signals, evidence counts and generated patterns.</p>

            <div class="oracle-panel">
                <table class="widefat striped oracle-table">
                    <thead>
                        <tr>
                            <th>Completed</th>
                            <th>Email</th>
                            <th>Quality</th>
                            <th>Flags</th>
                            <th>Answers</th>
                            <th>Observations</th>
                            <th>Patterns</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$sessions): ?>
                        <tr><td colspan="8">No sessions yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td><?php echo esc_html($session->completed_at ?: $session->started_at); ?></td>
                            <td><?php echo esc_html($session->email ?: '—'); ?></td>
                            <td><strong><?php echo esc_html(round((float) $session->quality_score)); ?>%</strong></td>
                            <td><?php echo $this->flags((string) $session->fraud_flags); ?></td>
                            <td><?php echo esc_html((int) $session->answer_count); ?></td>
                            <td><?php echo esc_html((int) $session->observation_count); ?></td>
                            <td><?php echo esc_html((int) $session->pattern_count); ?></td>
                            <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=oracle-core-sessions&session=' . rawurlencode($session->uuid))); ?>">Review</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function detail(string $sessionUuid): void
    {
        global $wpdb;
        $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oracle_sessions WHERE uuid = %s", $sessionUuid));
        if (!$session) {
            echo '<div class="wrap oracle-admin"><h1>Session not found</h1></div>';
            return;
        }

        $patterns = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oracle_patterns WHERE session_uuid = %s ORDER BY confidence_score DESC", $sessionUuid));
        $observations = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oracle_observations WHERE session_uuid = %s ORDER BY strength DESC LIMIT 50", $sessionUuid));
        $answers = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, q.question_text, q.category, q.dimension
             FROM {$wpdb->prefix}oracle_answers a
             LEFT JOIN {$wpdb->prefix}oracle_questions q ON q.uuid = a.question_uuid
             WHERE a.session_uuid = %s
             ORDER BY a.id ASC",
            $sessionUuid
        ));
        ?>
        <div class="wrap oracle-admin">
            <h1><?php esc_html_e('Session Review', 'oracle-core'); ?></h1>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=oracle-core-sessions')); ?>">← Back to sessions</a></p>

            <div class="oracle-grid">
                <div class="oracle-card"><strong><?php echo esc_html(round((float) $session->quality_score)); ?>%</strong><span>Response quality</span></div>
                <div class="oracle-card"><strong><?php echo esc_html(count($answers)); ?></strong><span>Answers</span></div>
                <div class="oracle-card"><strong><?php echo esc_html(count($observations)); ?></strong><span>Observations</span></div>
                <div class="oracle-card"><strong><?php echo esc_html(count($patterns)); ?></strong><span>Patterns</span></div>
            </div>

            <div class="oracle-panel">
                <h2>Quality / fraud flags</h2>
                <p><?php echo $this->flags((string) $session->fraud_flags); ?></p>
            </div>

            <div class="oracle-panel">
                <h2>Generated patterns</h2>
                <table class="widefat striped">
                    <thead><tr><th>Pattern</th><th>Confidence</th><th>Evidence</th><th>Summary</th></tr></thead>
                    <tbody>
                    <?php foreach ($patterns as $pattern): ?>
                        <tr>
                            <td><?php echo esc_html($pattern->pattern_name); ?></td>
                            <td><?php echo esc_html($pattern->confidence_label . ' / ' . round((float) $pattern->confidence_score) . '%'); ?></td>
                            <td><?php echo esc_html((int) $pattern->evidence_count); ?></td>
                            <td><?php echo esc_html($pattern->pattern_summary); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="oracle-panel">
                <h2>Observations</h2>
                <table class="widefat striped">
                    <thead><tr><th>Capability</th><th>Behaviour</th><th>Strength</th><th>Confidence</th><th>Observation</th></tr></thead>
                    <tbody>
                    <?php foreach ($observations as $observation): ?>
                        <tr>
                            <td><?php echo esc_html($observation->capability); ?></td>
                            <td><?php echo esc_html($observation->behaviour); ?></td>
                            <td><?php echo esc_html(round((float) $observation->strength * 100)); ?>%</td>
                            <td><?php echo esc_html($observation->confidence); ?></td>
                            <td><?php echo esc_html($observation->observation_text); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="oracle-panel">
                <h2>Raw answers</h2>
                <table class="widefat striped">
                    <thead><tr><th>Question</th><th>Category</th><th>Dimension</th><th>Answer</th></tr></thead>
                    <tbody>
                    <?php foreach ($answers as $answer): ?>
                        <tr>
                            <td><?php echo esc_html($answer->question_text ?: $answer->question_uuid); ?></td>
                            <td><?php echo esc_html($answer->category ?: '—'); ?></td>
                            <td><?php echo esc_html($answer->dimension ?: '—'); ?></td>
                            <td><?php echo esc_html($this->answerLabel((int) $answer->answer_value)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function flags(string $json): string
    {
        if (!$json) {
            return '<span class="oracle-status-on">Clear</span>';
        }

        $flags = json_decode($json, true);
        if (!$flags || !is_array($flags)) {
            return '<span class="oracle-status-off">Flagged</span>';
        }

        return implode(' ', array_map(static function ($flag) {
            return '<code>' . esc_html((string) $flag) . '</code>';
        }, $flags));
    }

    private function answerLabel(int $value): string
    {
        $labels = [
            0 => 'Strongly disagree',
            1 => 'Disagree',
            2 => 'Not sure',
            3 => 'Agree',
            4 => 'Strongly agree',
        ];

        return $labels[$value] ?? (string) $value;
    }
}
