<?php

return [
    'fixture' => [
        'enabled' => (bool) env('MODRIK_FIXTURE_MODE', false),
        'bearer_token' => (string) env('MODRIK_FIXTURE_BEARER_TOKEN', ''),
        'user_id' => '01J00000000000000000000030',
        'lesson_id' => '01J00000000000000000000003',
        'quiz_id' => '01J00000000000000000000020',
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
];
