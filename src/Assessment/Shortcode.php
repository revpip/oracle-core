<?php
namespace OracleCore\Assessment;

use OracleCore\Events\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class Shortcode
{
    public function register(): void
    {
        add_shortcode('oracle_assessment', [$this, 'render']);
        add_action('admin_post_nopriv_oracle_submit_assessment', [$this, 'submit']);
        add_action('admin_post_oracle_submit_assessment', [$this, 'submit']);
    }

    public function render(): string
    {
        global $wpdb;
        wp_enqueue_style('oracle-core-frontend');
        wp_enqueue_script('oracle-core-frontend');

        $questions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}oracle_questions WHERE is_active = 1 ORDER BY sort_order ASC");
        if (!$questions) {
            return '<div class="oracle-assessment"><p>No Oracle questions are currently active.</p></div>';
        }

        ob_start();
        ?>
        <div class="oracle-assessment" data-oracle-assessment>
            <div class="oracle-hero">
                <p class="oracle-kicker">Oracle Core</p>
                <h2>Understand your patterns more clearly</h2>
                <p>This self-reflection experience highlights areas that may be worth exploring further. It does not diagnose medical, mental health or neurodevelopmental conditions.</p>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="oracle-form">
                <input type="hidden" name="action" value="oracle_submit_assessment">
                <input type="hidden" name="oracle_started_at" value="<?php echo esc_attr(time()); ?>">
                <input type="hidden" name="oracle_mouse_events" value="0" data-oracle-mouse-events>
                <?php wp_nonce_field('oracle_submit_assessment', 'oracle_nonce'); ?>

                <div class="oracle-profile">
                    <label>Your email, optional
                        <input type="email" name="oracle_email" placeholder="you@example.com">
                    </label>
                </div>

                <?php foreach ($questions as $i => $question): ?>
                    <fieldset class="oracle-question" data-oracle-question>
                        <legend><span><?php echo esc_html($i + 1); ?></span><?php echo esc_html($question->question_text); ?></legend>
                        <div class="oracle-options">
                            <?php foreach ([0 => 'Strongly disagree', 1 => 'Disagree', 2 => 'Not sure', 3 => 'Agree', 4 => 'Strongly agree'] as $value => $label): ?>
                                <label>
                                    <input type="radio" name="answers[<?php echo esc_attr($question->uuid); ?>]" value="<?php echo esc_attr($value); ?>" required>
                                    <span><?php echo esc_html($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>

                <div class="oracle-disclaimer">
                    <?php echo wp_kses_post(get_option('oracle_core_disclaimer', 'Oracle Core is not a diagnostic tool. It highlights self-reported patterns and may suggest areas worth discussing with an appropriately qualified professional.')); ?>
                </div>

                <button type="submit" class="oracle-submit">Reveal my pattern map</button>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function submit(): void
    {
        if (!isset($_POST['oracle_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['oracle_nonce'])), 'oracle_submit_assessment')) {
            wp_die('Security check failed.');
        }

        global $wpdb;
        $session_uuid = wp_generate_uuid4();
        $started_at = isset($_POST['oracle_started_at']) ? absint($_POST['oracle_started_at']) : time();
        $elapsed = max(1, time() - $started_at);
        $mouse_events = isset($_POST['oracle_mouse_events']) ? absint($_POST['oracle_mouse_events']) : 0;
        $answers = isset($_POST['answers']) && is_array($_POST['answers']) ? wp_unslash($_POST['answers']) : [];
        $email = isset($_POST['oracle_email']) ? sanitize_email(wp_unslash($_POST['oracle_email'])) : '';

        $quality = 100;
        $flags = [];

        if ($elapsed < 35) {
            $quality -= 35;
            $flags[] = 'very_fast_completion';
        }
        if ($mouse_events < 3) {
            $quality -= 20;
            $flags[] = 'low_interaction';
        }
        if (count(array_unique(array_map('intval', $answers))) <= 1) {
            $quality -= 25;
            $flags[] = 'same_answer_pattern';
        }

        $quality = max(0, $quality);

        $wpdb->insert($wpdb->prefix . 'oracle_sessions', [
            'uuid' => $session_uuid,
            'user_id' => get_current_user_id() ?: null,
            'email' => $email ?: null,
            'quality_score' => $quality,
            'fraud_flags' => $flags ? wp_json_encode($flags) : null,
            'status' => 'completed',
            'started_at' => gmdate('Y-m-d H:i:s', $started_at),
            'completed_at' => current_time('mysql'),
        ]);

        foreach ($answers as $question_uuid => $answer) {
            $wpdb->insert($wpdb->prefix . 'oracle_answers', [
                'session_uuid' => $session_uuid,
                'question_uuid' => sanitize_text_field($question_uuid),
                'answer_value' => max(0, min(4, (int) $answer)),
                'reading_time_ms' => 0,
                'changed_count' => 0,
                'created_at' => current_time('mysql'),
            ]);
        }

        (new Logger())->event('assessment_completed', $session_uuid, [
            'elapsed_seconds' => $elapsed,
            'quality_score' => $quality,
            'flags' => $flags,
        ]);

        $url = add_query_arg(['oracle_result' => $session_uuid], wp_get_referer() ?: home_url('/'));
        wp_safe_redirect($url);
        exit;
    }
}
