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
];
