<?php
namespace OracleCore\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class ConfidenceCalculator
{
    public function calculate(int $evidenceCount, float $averageStrength, float $qualityScore = 100): array
    {
        $evidenceComponent = min(45, $evidenceCount * 12);
        $strengthComponent = min(35, $averageStrength * 35);
        $qualityComponent = min(20, max(0, $qualityScore) / 5);

        $score = round($evidenceComponent + $strengthComponent + $qualityComponent, 2);

        return [
            'score' => $score,
            'label' => $this->label($score),
        ];
    }

    private function label(float $score): string
    {
        if ($score >= 80) {
            return 'strong';
        }
        if ($score >= 60) {
            return 'established';
        }
        if ($score >= 40) {
            return 'growing';
        }
        return 'early';
    }
}
