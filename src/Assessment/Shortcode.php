<?php
namespace OracleCore\Assessment;

use OracleCore\Events\Logger;
use OracleCore\Observations\EvidenceMapper;
use OracleCore\Observations\ObservationRepository;

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

        if (isset($_GET['oracle_result'])) {
            return $this->renderResult(sanitize_text_field(wp_unslash($_GET['oracle_result'])));
        }

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

    private function renderResult(string $sessionUuid): string
    {
        global $wpdb;
        $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oracle_sessions WHERE uuid = %s", $sessionUuid));
        if (!$session) {
            return '<div class="oracle-assessment"><p>We could not find that Oracle result.</p></div>';
        }

        $result = (new Scorer())->score($sessionUuid);
        $observations = (new ObservationRepository())->forSession($sessionUuid);
        $groups = (new ObservationRepository())->groupedByCapability($sessionUuid);
        $premiumUrl = get_option('oracle_core_premium_url', '');

        ob_start();
        ?>
        <div class="oracle-assessment oracle-results">
            <div class="oracle-hero">
                <p class="oracle-kicker">Your Oracle Pattern Map</p>
                <h2>Your self-reflection report is ready</h2>
                <p>This is an early pattern map based on your answers. It is not a diagnosis, but it can help you decide what may be worth exploring further.</p>
            </div>

            <div class="oracle-result-meta">
                <div><strong><?php echo esc_html($result['overall_confidence']); ?></strong><span>Evidence confidence</span></div>
                <div><strong><?php echo esc_html(round((float) $session->quality_score)); ?>%</strong><span>Response quality</span></div>
            </div>

            <h3>What stood out</h3>
            <div class="oracle-score-list">
                <?php foreach (array_slice($result['scores'], 0, 6) as $score): ?>
                    <div class="oracle-score-card">
                        <div class="oracle-score-top">
                            <strong><?php echo esc_html($score['label']); ?></strong>
                            <span><?php echo esc_html($score['score']); ?>%</span>
                        </div>
                        <div class="oracle-bar"><span style="width: <?php echo esc_attr($score['score']); ?>%"></span></div>
                        <p><?php echo esc_html($score['level']); ?> · <?php echo esc_html($score['evidence_count']); ?> supporting answer(s)</p>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($groups): ?>
                <div class="oracle-panel">
                    <h3>Evidence map</h3>
                    <p>These are structured observations created from your answers. They are evidence markers, not diagnoses.</p>
                    <ul class="oracle-evidence-list">
                        <?php foreach (array_slice($groups, 0, 8) as $group): ?>
                            <li><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $group['capability']))); ?></strong> — <?php echo esc_html((int) $group['evidence_count']); ?> observation(s), <?php echo esc_html(round((float) $group['average_strength'] * 100)); ?>% average strength</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($observations)): ?>
                <div class="oracle-panel">
                    <h3>Why Oracle noticed these patterns</h3>
                    <ul>
                        <?php foreach (array_slice($observations, 0, 6) as $observation): ?>
                            <li><?php echo esc_html($observation['observation_text']); ?> <em>(<?php echo esc_html($observation['confidence']); ?> confidence)</em></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="oracle-panel oracle-safe-note">
                <h3>Suggested next step</h3>
                <p>If these patterns feel familiar or affect your daily life, consider discussing them with a GP, therapist, counsellor or qualified assessor. This report can help you describe what you have noticed.</p>
            </div>

            <div class="oracle-actions">
                <button onclick="window.print()" class="oracle-submit" type="button">Print or save report</button>
                <?php if ($premiumUrl): ?>
                    <a class="oracle-submit oracle-secondary" href="<?php echo esc_url($premiumUrl); ?>">Unlock advanced report</a>
                <?php endif; ?>
            </div>
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
        $userId = get_current_user_id() ?: null;

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
            'user_id' => $userId,
            'email' => $email ?: null,
            'quality_score' => $quality,
            'fraud_flags' => $flags ? wp_json_encode($flags) : null,
            'status' => 'completed',
            'started_at' => gmdate('Y-m-d H:i:s', $started_at),
            'completed_at' => current_time('mysql'),
        ]);

        $mapper = new EvidenceMapper();
        $observationRepo = new ObservationRepository();

        foreach ($answers as $question_uuid => $answer) {
            $questionUuid = sanitize_text_field($question_uuid);
            $answerValue = max(0, min(4, (int) $answer));

            $wpdb->insert($wpdb->prefix . 'oracle_answers', [
                'session_uuid' => $session_uuid,
                'question_uuid' => $questionUuid,
                'answer_value' => $answerValue,
                'reading_time_ms' => 0,
                'changed_count' => 0,
                'created_at' => current_time('mysql'),
            ]);

            $question = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oracle_questions WHERE uuid = %s", $questionUuid));
            if ($question) {
                $observation = $mapper->mapAnswer($question, $answerValue, $session_uuid, $userId);
                if ($observation) {
                    $observationRepo->create($observation);
                }
            }
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
