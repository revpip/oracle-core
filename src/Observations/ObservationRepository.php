<?php
namespace OracleCore\Observations;

if (!defined('ABSPATH')) {
    exit;
}

final class ObservationRepository
{
    public function create(array $data): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'oracle_observations', [
            'uuid' => $data['uuid'] ?? wp_generate_uuid4(),
            'session_uuid' => sanitize_text_field($data['session_uuid'] ?? ''),
            'user_id' => !empty($data['user_id']) ? absint($data['user_id']) : null,
            'source_type' => sanitize_key($data['source_type'] ?? 'assessment'),
            'source_uuid' => sanitize_text_field($data['source_uuid'] ?? ''),
            'domain' => sanitize_key($data['domain'] ?? 'mind'),
            'capability' => sanitize_key($data['capability'] ?? 'general'),
            'behaviour' => sanitize_key($data['behaviour'] ?? 'general'),
            'observation_text' => sanitize_textarea_field($data['observation_text'] ?? ''),
            'strength' => max(0, min(1, (float) ($data['strength'] ?? 0))),
            'confidence' => sanitize_key($data['confidence'] ?? 'early'),
            'polarity' => sanitize_key($data['polarity'] ?? 'challenge'),
            'created_at' => current_time('mysql'),
        ]);
    }

    public function forSession(string $sessionUuid): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oracle_observations WHERE session_uuid = %s ORDER BY strength DESC, id ASC",
            $sessionUuid
        ), ARRAY_A) ?: [];
    }

    public function groupedByCapability(string $sessionUuid): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT capability, polarity, COUNT(*) AS evidence_count, AVG(strength) AS average_strength
             FROM {$wpdb->prefix}oracle_observations
             WHERE session_uuid = %s
             GROUP BY capability, polarity
             ORDER BY average_strength DESC, evidence_count DESC",
            $sessionUuid
        ), ARRAY_A) ?: [];
    }
}
