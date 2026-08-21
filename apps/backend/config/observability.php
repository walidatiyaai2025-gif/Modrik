<?php

return [
    'enabled' => env('MODRIK_OBSERVABILITY_ENABLED', true),
    'inspector_enabled' => env('MODRIK_RUNTIME_INSPECTOR_ENABLED', false),
    'max_events' => (int) env('MODRIK_OBSERVABILITY_MAX_EVENTS', 5000),
    'query_limit' => (int) env('MODRIK_OBSERVABILITY_QUERY_LIMIT', 100),
    'export_max_events' => (int) env('MODRIK_OBSERVABILITY_EXPORT_MAX_EVENTS', 100),
    'export_max_bytes' => (int) env('MODRIK_OBSERVABILITY_EXPORT_MAX_BYTES', 262144),
    'build_identity' => env('MODRIK_BUILD_ID'),
];
