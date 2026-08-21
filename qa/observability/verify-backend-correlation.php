<?php

declare(strict_types=1);

use App\Support\Observability\RuntimeInspectorService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);
$backend = $root.'/apps/backend';
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$webPath = getenv('OBS_WEB_EVIDENCE') ?: '/tmp/modrik-observability-web.json';
$mobilePath = getenv('OBS_MOBILE_EVIDENCE') ?: '/tmp/modrik-observability-mobile.json';
$outputPath = getenv('OBS_BACKEND_EVIDENCE') ?: '/tmp/modrik-observability-backend.json';
$mainSha = getenv('ACCEPTANCE_MAIN_SHA') ?: 'unknown';

/** @return array<string, mixed> */
function readEvidence(string $path): array
{
    $raw = file_get_contents($path);
    if (! is_string($raw)) {
        throw new RuntimeException("Unable to read evidence: {$path}");
    }
    $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($decoded)) {
        throw new RuntimeException("Invalid evidence: {$path}");
    }
    return $decoded;
}

function requiredString(array $document, array $path): string
{
    $value = $document;
    foreach ($path as $segment) {
        if (! is_array($value) || ! array_key_exists($segment, $value)) {
            throw new RuntimeException('Missing evidence path: '.implode('.', $path));
        }
        $value = $value[$segment];
    }
    if (! is_string($value) || $value === '') {
        throw new RuntimeException('Expected non-empty string at: '.implode('.', $path));
    }
    return $value;
}

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$web = readEvidence($webPath);
$mobile = readEvidence($mobilePath);
$service = $app->make(RuntimeInspectorService::class);

$correlations = [
    'A' => requiredString($web, ['cases', 'A_web_learning_backend_failure', 'correlation_id']),
    'B' => requiredString($web, ['cases', 'B_web_auth_backend_failure', 'correlation_id']),
    'C' => requiredString($mobile, ['cases', 'C_mobile_learning_backend_failure', 'correlation_id']),
    'E' => requiredString($web, ['cases', 'E_success_control', 'correlation_id']),
];
$timeoutCorrelation = requiredString($web, ['cases', 'D_client_only_timeout', 'correlation_id']);

$backendMatrix = [];
foreach ($correlations as $case => $correlationId) {
    $events = $service->events([
        'correlation_id' => $correlationId,
        'hours' => 24,
    ]);
    assertTrue($events !== [], "{$case}: Backend RuntimeInspectorService returned no event for {$correlationId}");
    foreach ($events as $event) {
        assertTrue(
            ($event['correlation_id'] ?? null) === $correlationId,
            "{$case}: Backend Inspector returned a mismatched correlation",
        );
    }

    $bundle = $service->exportBundle([
        'correlation_id' => $correlationId,
        'hours' => 24,
    ]);
    $bundleJson = $bundle['json'] ?? '';
    assertTrue(is_string($bundleJson) && $bundleJson !== '', "{$case}: Backend export is empty");
    assertTrue(str_contains($bundleJson, $correlationId), "{$case}: Backend export omitted correlation");

    $backendMatrix[$case] = [
        'correlation_id' => $correlationId,
        'runtime_inspector_event_count' => count($events),
        'sanitized_export_bytes' => strlen($bundleJson),
    ];
}

$timeoutEvents = $service->events([
    'correlation_id' => $timeoutCorrelation,
    'hours' => 24,
]);
assertTrue($timeoutEvents === [], 'D: client-only timeout fabricated a Backend diagnostic event');

$sentinels = [
    getenv('MODRIK_FIXTURE_BEARER_TOKEN') ?: 'SENTINEL_BEARER_101_FIXTURE_ONLY',
    'SENTINEL_COOKIE_101_FIXTURE_ONLY',
    'SENTINEL_PASSWORD_101_FIXTURE_ONLY',
    'SENTINEL_PROVIDER_SECRET_101_FIXTURE_ONLY',
    'SENTINEL_LEARNER_ANSWER_101_FIXTURE_ONLY',
    'SENTINEL_QUESTION_TEXT_101_FIXTURE_ONLY',
    'sentinel.person.101@example.test',
    'SENTINEL_NAME_101_FIXTURE_ONLY',
];

$rows = DB::table('runtime_diagnostic_events')->get();
$serializedRows = json_encode(
    $rows->map(static fn (object $row): array => (array) $row)->all(),
    JSON_THROW_ON_ERROR,
);
$auditRows = $rows->filter(static fn (object $row): bool => ($row->data_class ?? null) === 'audit');
$serializedAudits = json_encode(
    $auditRows->map(static fn (object $row): array => (array) $row)->all(),
    JSON_THROW_ON_ERROR,
);
$webSerialized = file_get_contents($webPath) ?: '';
$mobileSerialized = file_get_contents($mobilePath) ?: '';

foreach ($sentinels as $sentinel) {
    assertTrue(! str_contains($serializedRows, $sentinel), "privacy: sentinel persisted in Backend diagnostics: {$sentinel}");
    assertTrue(! str_contains($serializedAudits, $sentinel), "privacy: sentinel persisted in audit payload: {$sentinel}");
    assertTrue(! str_contains($webSerialized, $sentinel), "privacy: sentinel leaked to Web evidence/export: {$sentinel}");
    assertTrue(! str_contains($mobileSerialized, $sentinel), "privacy: sentinel leaked to Mobile evidence/export: {$sentinel}");
}

$result = [
    'main_sha' => $mainSha,
    'backend_admin_lookup' => $backendMatrix,
    'D_client_only_timeout' => [
        'correlation_id' => $timeoutCorrelation,
        'backend_event_count' => count($timeoutEvents),
    ],
    'privacy' => [
        'sentinel_count' => count($sentinels),
        'persisted_diagnostic_row_count' => count($rows),
        'audit_row_count' => count($auditRows),
        'all_sentinels_absent' => true,
    ],
];
$encoded = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
file_put_contents($outputPath, $encoded);

echo "BACKEND/ADMIN OBSERVABILITY CORRELATION ACCEPTANCE: PASS\n";
echo $encoded."\n";
