<?php
namespace OracleCore\Events;

if (!defined('ABSPATH')) {
    exit;
}

final class Logger
{
    public function event(string $type, ?string $sessionUuid = null, array $payload = []): void
    {
        global $wpdb;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $wpdb->insert($wpdb->prefix . 'oracle_events', [
            'event_uuid' => wp_generate_uuid4(),
            'session_uuid' => $sessionUuid,
            'event_type' => sanitize_key($type),
            'event_payload' => wp_json_encode($payload),
            'ip_hash' => $ip ? hash('sha256', $ip . wp_salt()) : null,
            'user_agent_hash' => $ua ? hash('sha256', $ua . wp_salt()) : null,
            'created_at' => current_time('mysql'),
        ]);
    }
}
