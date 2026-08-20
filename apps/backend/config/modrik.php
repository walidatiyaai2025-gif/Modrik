<?php

$diagnosticRetentionDays = env('MODRIK_DIAGNOSTIC_RETENTION_DAYS');

return [
    'fixture' => [
        'enabled' => (bool) env('MODRIK_FIXTURE_MODE', false),
        'bearer_token' => (string) env('MODRIK_FIXTURE_BEARER_TOKEN', ''),
        'user_id' => '01J00000000000000000000030',
        'lesson_id' => '01J00000000000000000000003',
        'quiz_id' => '01J00000000000000000000020',
    ],
    'auth' => [
        'hash_secret' => (string) env('MODRIK_AUTH_HASH_SECRET', env('APP_KEY', '')),
        'session_ttl_minutes' => (int) env('MODRIK_AUTH_SESSION_TTL_MINUTES', 43_200),
        'recent_seconds' => (int) env('MODRIK_AUTH_RECENT_SECONDS', 600),
        'verification_ttl_minutes' => (int) env('MODRIK_AUTH_VERIFICATION_TTL_MINUTES', 60),
        'recovery_ttl_minutes' => (int) env('MODRIK_AUTH_RECOVERY_TTL_MINUTES', 30),
        'provider_intent_ttl_minutes' => (int) env('MODRIK_AUTH_PROVIDER_INTENT_TTL_MINUTES', 10),
        'login_max_attempts' => (int) env('MODRIK_AUTH_LOGIN_MAX_ATTEMPTS', 8),
        'login_decay_seconds' => (int) env('MODRIK_AUTH_LOGIN_DECAY_SECONDS', 900),
        'resend_max_attempts' => (int) env('MODRIK_AUTH_RESEND_MAX_ATTEMPTS', 3),
        'resend_decay_seconds' => (int) env('MODRIK_AUTH_RESEND_DECAY_SECONDS', 900),
        'recovery_max_attempts' => (int) env('MODRIK_AUTH_RECOVERY_MAX_ATTEMPTS', 3),
        'recovery_decay_seconds' => (int) env('MODRIK_AUTH_RECOVERY_DECAY_SECONDS', 900),
        'providers' => [
            'google' => [
                'client_id' => (string) env('MODRIK_GOOGLE_CLIENT_ID', ''),
                'client_secret' => (string) env('MODRIK_GOOGLE_CLIENT_SECRET', ''),
                'callback_url' => (string) env('MODRIK_GOOGLE_CALLBACK_URL', ''),
            ],
            'apple' => [
                'client_id' => (string) env('MODRIK_APPLE_CLIENT_ID', ''),
                'team_id' => (string) env('MODRIK_APPLE_TEAM_ID', ''),
                'key_id' => (string) env('MODRIK_APPLE_KEY_ID', ''),
                'private_key' => (string) env('MODRIK_APPLE_PRIVATE_KEY', ''),
                'callback_url' => (string) env('MODRIK_APPLE_CALLBACK_URL', ''),
            ],
        ],
    ],
    'idempotency' => [
        'secret' => (string) env('MODRIK_IDEMPOTENCY_SECRET', env('APP_KEY', '')),
        'retention_hours' => 24,
    ],
    'content_import' => [
        'schema_version' => '1.0.0',
        'maximum_archive_bytes' => 524_288_000,
        'maximum_file_count' => 5_000,
        'maximum_entry_bytes' => 104_857_600,
        'maximum_manifest_bytes' => 1_048_576,
        'maximum_compression_ratio' => 100,
    ],
    'outbox' => [
        'maximum_attempts' => 5,
        'initial_backoff_seconds' => 60,
        'maximum_backoff_seconds' => 3_600,
    ],
    'observability' => [
        'enabled' => (bool) env('MODRIK_OBSERVABILITY_ENABLED', true),
        'storage_enabled' => (bool) env('MODRIK_DIAGNOSTIC_STORAGE_ENABLED', true),
        'inspector_enabled' => (bool) env('MODRIK_RUNTIME_INSPECTOR_ENABLED', true),
        'maximum_metadata_bytes' => (int) env('MODRIK_DIAGNOSTIC_METADATA_MAX_BYTES', 4_096),
        'maximum_query_events' => (int) env('MODRIK_DIAGNOSTIC_QUERY_MAX_EVENTS', 200),
        'maximum_export_events' => (int) env('MODRIK_DIAGNOSTIC_EXPORT_MAX_EVENTS', 500),
        'maximum_export_bytes' => (int) env('MODRIK_DIAGNOSTIC_EXPORT_MAX_BYTES', 262_144),
        'retention_days' => $diagnosticRetentionDays === null || $diagnosticRetentionDays === ''
            ? null
            : max(1, (int) $diagnosticRetentionDays),
        'build_ref' => (string) env('MODRIK_BUILD_REF', ''),
        'release_version' => (string) env('MODRIK_RELEASE_VERSION', ''),
    ],
    'paid_ai' => [
        'enabled' => (bool) env('MODRIK_PAID_AI_ENABLED', false),
        'allowed_context_fields' => [
            'locale',
            'subject_reference',
            'lesson_reference',
        ],
    ],
];
