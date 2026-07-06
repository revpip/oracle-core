<?php
namespace OracleCore\Assessment;

if (!defined('ABSPATH')) {
    exit;
}

final class Scorer
{
    public function score(string $sessionUuid): array
    {
        global $wpdb;
        $answers_table = $wpdb->prefix . 'oracle_answers';
        $questions_table = $wpdb->prefix . 'oracle_questions';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.answer_value, q.dimension, q.category, q.weight, q.question_text
             FROM {$answers_table} a
             INNER JOIN {$questions_table} q ON q.uuid = a.question_uuid
             WHERE a.session_uuid = %s",
            $sessionUuid
        ));

        $dimensions = [];
        $evidence = [];

        foreach ($rows as $row) {
            $dimension = $row->dimension ?: 'general';
            if (!isset($dimensions[$dimension])) {
                $dimensions[$dimension] = ['raw' => 0, 'max' => 0, 'count' => 0, 'category' => $row->category];
            }
            $dimensions[$dimension]['raw'] += ((int) $row->answer_value) * (float) $row->weight;
            $dimensions[$dimension]['max'] += 4 * (float) $row->weight;
            $dimensions[$dimension]['count']++;

            if ((int) $row->answer_value >= 3) {
                $evidence[] = $row->question_text;
            }
        }

        $scores = [];
        foreach ($dimensions as $key => $data) {
            $percent = $data['max'] > 0 ? round(($data['raw'] / $data['max']) * 100) : 0;
            $scores[$key] = [
                'label' => ucwords(str_replace('_', ' ', $key)),
                'category' => $data['category'],
                'score' => $percent,
                'evidence_count' => $data['count'],
                'level' => $this->level($percent),
            ];
        }

        uasort($scores, static function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return [
            'scores' => $scores,
            'evidence' => array_slice($evidence, 0, 6),
            'overall_confidence' => $this->confidence(count($rows)),
        ];
    }

    private function level(int $score): string
    {
        if ($score >= 76) {
            return 'Strong pattern';
        }
        if ($score >= 51) {
            return 'Noticeable pattern';
        }
        if ($score >= 26) {
            return 'Mild pattern';
        }
        return 'Limited pattern';
    }

    private function confidence(int $answerCount): string
    {
        if ($answerCount >= 40) {
            return 'High';
        }
        if ($answerCount >= 18) {
            return 'Medium';
        }
        return 'Early indication';
    }
}
