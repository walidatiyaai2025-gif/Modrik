<?php

return [
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
    'demo' => [
        'student' => [
            'email' => (string) env('MODRIK_DEMO_STUDENT_EMAIL', ''),
            'password' => (string) env('MODRIK_DEMO_STUDENT_PASSWORD', ''),
        ],
        'admin' => [
            'email' => (string) env('MODRIK_DEMO_ADMIN_EMAIL', ''),
            'password' => (string) env('MODRIK_DEMO_ADMIN_PASSWORD', ''),
        ],
    ],
    'firebase' => [
        'project_id' => (string) env('MODRIK_FIREBASE_PROJECT_ID', ''),
        'web_app_id' => (string) env('MODRIK_FIREBASE_WEB_APP_ID', ''),
        'android_app_id' => (string) env('MODRIK_FIREBASE_ANDROID_APP_ID', ''),
        'ios_app_id' => (string) env('MODRIK_FIREBASE_IOS_APP_ID', ''),
        'credentials_reference' => (string) env('MODRIK_FIREBASE_CREDENTIALS_REFERENCE', ''),
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
    'paid_ai' => [
        'enabled' => (bool) env('MODRIK_PAID_AI_ENABLED', false),
        'allowed_context_fields' => [
            'locale',
            'subject_reference',
            'lesson_reference',
        ],
    ],
];
