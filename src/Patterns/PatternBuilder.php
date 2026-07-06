<?php
namespace OracleCore\Patterns;

use OracleCore\Observations\ObservationRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class PatternBuilder
{
    private ObservationRepository $observations;
    private ConfidenceCalculator $confidence;

    public function __construct(?ObservationRepository $observations = null, ?ConfidenceCalculator $confidence = null)
    {
        $this->observations = $observations ?: new ObservationRepository();
        $this->confidence = $confidence ?: new ConfidenceCalculator();
    }

    public function buildForSession(string $sessionUuid, ?int $userId = null, float $qualityScore = 100): array
    {
        $items = $this->observations->forSession($sessionUuid);
        $grouped = [];

        foreach ($items as $item) {
            $key = $item['domain'] . '|' . $item['capability'] . '|' . $item['polarity'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'domain' => $item['domain'],
                    'capability' => $item['capability'],
                    'polarity' => $item['polarity'],
                    'count' => 0,
                    'strength_total' => 0,
                    'examples' => [],
                ];
            }

            $grouped[$key]['count']++;
            $grouped[$key]['strength_total'] += (float) $item['strength'];
            if (count($grouped[$key]['examples']) < 3) {
                $grouped[$key]['examples'][] = $item['observation_text'];
            }
        }

        $patterns = [];
        foreach ($grouped as $group) {
            $avg = $group['count'] > 0 ? $group['strength_total'] / $group['count'] : 0;
            $confidence = $this->confidence->calculate((int) $group['count'], (float) $avg, $qualityScore);

            $patterns[] = [
                'session_uuid' => $sessionUuid,
                'user_id' => $userId,
                'domain' => $group['domain'],
                'capability' => $group['capability'],
                'pattern_name' => $this->name($group['capability'], $group['polarity']),
                'pattern_summary' => $this->summary($group['capability'], $group['polarity'], (int) $group['count'], $confidence['label']),
                'polarity' => $group['polarity'],
                'evidence_count' => (int) $group['count'],
                'average_strength' => round($avg, 4),
                'confidence_score' => $confidence['score'],
                'confidence_label' => $confidence['label'],
            ];
        }

        usort($patterns, static function ($a, $b) {
            return $b['confidence_score'] <=> $a['confidence_score'];
        });

        return $patterns;
    }

    private function name(string $capability, string $polarity): string
    {
        $label = ucwords(str_replace('_', ' ', $capability));
        return $polarity === 'strength' ? $label . ' strength' : $label . ' pattern';
    }

    private function summary(string $capability, string $polarity, int $count, string $confidence): string
    {
        $label = strtolower(str_replace('_', ' ', $capability));
        $tone = $polarity === 'strength' ? 'possible strength' : 'recurring experience';

        return sprintf(
            'Oracle found a %s around %s, supported by %d observation%s. Current confidence is %s, which means this should be treated as a self-reflection pattern rather than a conclusion.',
            $tone,
            $label,
            $count,
            $count === 1 ? '' : 's',
            $confidence
        );
    }
}
