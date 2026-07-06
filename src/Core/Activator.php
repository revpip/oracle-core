<?php
namespace OracleCore\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class Activator
{
    public static function activate(): void
    {
        self::installTables();
        self::seedQuestions();
        update_option('oracle_core_version', ORACLE_CORE_VERSION);
    }

    public static function deactivate(): void
    {
        // Keep data by default. Future setting can control cleanup on uninstall.
    }

    private static function installTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'oracle_';

        $sql = [];

        $sql[] = "CREATE TABLE {$prefix}questions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(64) NOT NULL,
            category VARCHAR(100) NOT NULL,
            dimension VARCHAR(100) NOT NULL,
            question_text TEXT NOT NULL,
            help_text TEXT NULL,
            weight DECIMAL(6,2) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            version INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY category (category),
            KEY dimension (dimension),
            KEY is_active (is_active)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(190) NULL,
            quality_score DECIMAL(6,2) NOT NULL DEFAULT 100,
            fraud_flags TEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'started',
            started_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY user_id (user_id),
            KEY status (status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}answers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_uuid VARCHAR(64) NOT NULL,
            question_uuid VARCHAR(64) NOT NULL,
            answer_value INT NOT NULL,
            reading_time_ms INT NOT NULL DEFAULT 0,
            changed_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY session_uuid (session_uuid),
            KEY question_uuid (question_uuid)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}observations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(64) NOT NULL,
            session_uuid VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            source_type VARCHAR(40) NOT NULL DEFAULT 'assessment',
            source_uuid VARCHAR(64) NULL,
            domain VARCHAR(100) NOT NULL DEFAULT 'mind',
            capability VARCHAR(100) NOT NULL,
            behaviour VARCHAR(150) NOT NULL,
            observation_text TEXT NOT NULL,
            strength DECIMAL(6,4) NOT NULL DEFAULT 0,
            confidence VARCHAR(40) NOT NULL DEFAULT 'early',
            polarity VARCHAR(40) NOT NULL DEFAULT 'challenge',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY session_uuid (session_uuid),
            KEY user_id (user_id),
            KEY capability (capability),
            KEY behaviour (behaviour),
            KEY confidence (confidence)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}patterns (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(64) NOT NULL,
            session_uuid VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            domain VARCHAR(100) NOT NULL DEFAULT 'mind',
            capability VARCHAR(100) NOT NULL,
            pattern_name VARCHAR(190) NOT NULL,
            pattern_summary TEXT NOT NULL,
            polarity VARCHAR(40) NOT NULL DEFAULT 'challenge',
            evidence_count INT NOT NULL DEFAULT 0,
            average_strength DECIMAL(6,4) NOT NULL DEFAULT 0,
            confidence_score DECIMAL(6,2) NOT NULL DEFAULT 0,
            confidence_label VARCHAR(40) NOT NULL DEFAULT 'early',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY session_uuid (session_uuid),
            KEY user_id (user_id),
            KEY capability (capability),
            KEY confidence_label (confidence_label)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_uuid VARCHAR(64) NOT NULL,
            session_uuid VARCHAR(64) NULL,
            event_type VARCHAR(80) NOT NULL,
            event_payload LONGTEXT NULL,
            ip_hash VARCHAR(128) NULL,
            user_agent_hash VARCHAR(128) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_uuid (event_uuid),
            KEY session_uuid (session_uuid),
            KEY event_type (event_type)
        ) {$charset};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
    }

    private static function seedQuestions(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'oracle_questions';
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($count > 0) {
            return;
        }

        $questions = [
            ['Executive Function', 'attention', 'I often struggle to begin tasks even when I know they matter.', 2],
            ['Executive Function', 'organisation', 'I frequently misplace everyday items such as keys, cards or my phone.', 2],
            ['Attention', 'attention', 'My mind jumps between ideas before I have finished the first one.', 2],
            ['Sensory', 'sensory', 'Noise, light, textures or busy places can feel unusually draining.', 2],
            ['Social Energy', 'masking', 'I often act differently around others to avoid standing out.', 2],
            ['Emotional Regulation', 'emotional', 'Small setbacks can affect me more deeply than people realise.', 2],
            ['Stress & Burnout', 'burnout', 'I feel exhausted even after resting.', 2],
            ['Sleep', 'sleep', 'My sleep pattern affects my focus, mood or patience.', 1.5],
            ['Relationships', 'relationships', 'I sometimes feel misunderstood in close relationships.', 1.5],
            ['Compulsive Patterns', 'compulsive', 'I sometimes repeat behaviours even when part of me wants to stop.', 1.5],
            ['Mood', 'mood', 'My motivation can drop sharply for reasons I cannot always explain.', 1.5],
            ['Strengths', 'creativity', 'I often notice patterns, possibilities or ideas other people miss.', 1]
        ];

        foreach ($questions as $index => $q) {
            $wpdb->insert($table, [
                'uuid' => wp_generate_uuid4(),
                'category' => $q[0],
                'dimension' => $q[1],
                'question_text' => $q[2],
                'help_text' => '',
                'weight' => $q[3],
                'sort_order' => $index + 1,
                'is_active' => 1,
                'version' => 1,
                'created_at' => current_time('mysql'),
            ]);
        }
    }
}
