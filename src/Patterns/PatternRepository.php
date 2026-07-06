<?php
namespace OracleCore\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class PatternRepository
{
    public function replaceForSession(string $sessionUuid, array $patterns): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'oracle_patterns';

        $wpdb->delete($table, ['session_uuid' => $sessionUuid]);

        foreach ($patterns as $pattern) {
            $wpdb->insert($table, [
                'uuid' => $pattern['uuid'] ?? wp_generate_uuid4(),
                'session_uuid' => sanitize_text_field($pattern['session_uuid'] ?? $sessionUuid),
                'user_id' => !empty($pattern['user_id']) ? absint($pattern['user_id']) : null,
                'domain' => sanitize_key($pattern['domain'] ?? 'mind'),
                'capability' => sanitize_key($pattern['capability'] ?? 'general'),
                'pattern_name' => sanitize_text_field($pattern['pattern_name'] ?? 'General pattern'),
                'pattern_summary' => sanitize_textarea_field($pattern['pattern_summary'] ?? ''),
                'polarity' => sanitize_key($pattern['polarity'] ?? 'challenge'),
                'evidence_count' => absint($pattern['evidence_count'] ?? 0),
                'average_strength' => max(0, min(1, (float) ($pattern['average_strength'] ?? 0))),
                'confidence_score' => max(0, min(100, (float) ($pattern['confidence_score'] ?? 0))),
                'confidence_label' => sanitize_key($pattern['confidence_label'] ?? 'early'),
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    public function forSession(string $sessionUuid): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oracle_patterns WHERE session_uuid = %s ORDER BY confidence_score DESC, evidence_count DESC",
            $sessionUuid
        ), ARRAY_A) ?: [];
    }
}
