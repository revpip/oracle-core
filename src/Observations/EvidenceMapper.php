<?php
namespace OracleCore\Observations;

if (!defined('ABSPATH')) {
    exit;
}

final class EvidenceMapper
{
    private array $map = [
        'attention' => ['mind', 'attention', 'attention_regulation', 'challenge'],
        'organisation' => ['mind', 'organisation', 'everyday_organisation', 'challenge'],
        'sensory' => ['body', 'sensory_processing', 'sensory_load', 'challenge'],
        'masking' => ['relationships', 'social_energy', 'masking', 'challenge'],
        'emotional' => ['mind', 'emotional_regulation', 'emotional_intensity', 'challenge'],
        'burnout' => ['body', 'energy', 'burnout_load', 'challenge'],
        'sleep' => ['body', 'sleep', 'sleep_impact', 'challenge'],
        'relationships' => ['relationships', 'communication', 'feeling_misunderstood', 'challenge'],
        'compulsive' => ['mind', 'self_regulation', 'repeated_behaviours', 'challenge'],
        'mood' => ['mind', 'mood', 'motivation_drop', 'challenge'],
        'creativity' => ['growth', 'creativity', 'pattern_recognition', 'strength'],
    ];

    public function mapAnswer(object $question, int $answerValue, string $sessionUuid, ?int $userId = null): ?array
    {
        if ($answerValue < 3) {
            return null;
        }

        $dimension = sanitize_key($question->dimension ?: 'general');
        $mapped = $this->map[$dimension] ?? ['mind', $dimension, $dimension, 'challenge'];
        $strength = $this->normaliseStrength($answerValue, (float) $question->weight);

        return [
            'session_uuid' => $sessionUuid,
            'user_id' => $userId,
            'source_type' => 'assessment',
            'source_uuid' => $question->uuid,
            'domain' => $mapped[0],
            'capability' => $mapped[1],
            'behaviour' => $mapped[2],
            'polarity' => $mapped[3],
            'strength' => $strength,
            'confidence' => $strength >= 0.75 ? 'growing' : 'early',
            'observation_text' => $this->observationText($question->question_text, $mapped[3]),
        ];
    }

    private function normaliseStrength(int $answerValue, float $weight): float
    {
        $answerStrength = $answerValue / 4;
        $weightBoost = min(1.25, max(0.5, $weight / 2));
        return round(min(1, $answerStrength * $weightBoost), 4);
    }

    private function observationText(string $questionText, string $polarity): string
    {
        if ($polarity === 'strength') {
            return 'User endorsed a possible strength: ' . $questionText;
        }

        return 'User endorsed a recurring experience: ' . $questionText;
    }
}
