<?php

declare(strict_types=1);

use App\Filament\Pages\RuntimeInspector;
use App\Models\User;
use App\Support\Observability\RuntimeInspectorService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
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
$candidateSha = getenv('ACCEPTANCE_HEAD_SHA') ?: 'unknown';

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
assertTrue(($web['main_sha'] ?? null) === $mainSha, 'Web evidence main SHA mismatch');
assertTrue(($mobile['main_sha'] ?? null) === $mainSha, 'Mobile evidence main SHA mismatch');
assertTrue(($web['candidate_sha'] ?? null) === $candidateSha, 'Web evidence candidate SHA mismatch');
assertTrue(($mobile['candidate_sha'] ?? null) === $candidateSha, 'Mobile evidence candidate SHA mismatch');

$service = $app->make(RuntimeInspectorService::class);
$correlations = [
    'A' => requiredString($web, ['cases', 'A_web_learning_backend_failure', 'correlation_id']),
    'B' => requiredString($web, ['cases', 'B_web_auth_backend_failure', 'correlation_id']),
    'C' => requiredString($mobile, ['cases', 'C_mobile_learning_backend_failure', 'correlation_id']),
    'E' => requiredString($web, ['cases', 'E_success_control', 'correlation_id']),
    'P' => requiredString($web, ['privacy', 'live_backend_request', 'correlation_id']),
];
$expectedFailureCodes = [
    'A' => requiredString($web, ['cases', 'A_web_learning_backend_failure', 'code']),
    'B' => requiredString($web, ['cases', 'B_web_auth_backend_failure', 'code']),
    'C' => requiredString($mobile, ['cases', 'C_mobile_learning_backend_failure', 'code']),
];
$timeoutCorrelation = requiredString($web, ['cases', 'D_client_only_timeout', 'correlation_id']);

$backendMatrix = [];
foreach ($correlations as $case => $correlationId) {
    $events = $service->events([
        'correlation_id' => $correlationId,
        'hours' => 24,
    ]);
    assertTrue($events !== [], "{$case}: Backend RuntimeInspectorService returned no event for {$correlationId}");

    $hasTraceableRequestEvent = false;
    $hasExpectedStableCode = ! isset($expectedFailureCodes[$case]);
    foreach ($events as $event) {
        assertTrue(
            ($event['correlation_id'] ?? null) === $correlationId,
            "{$case}: Backend Inspector returned a mismatched correlation",
        );
        $occurredAt = $event['occurred_at'] ?? null;
        assertTrue(is_string($occurredAt) && $occurredAt !== '', "{$case}: missing diagnostic timestamp");

        $route = $event['route'] ?? null;
        $action = $event['action'] ?? null;
        $duration = $event['duration_ms'] ?? null;
        if (
            ((is_string($route) && $route !== '') || (is_string($action) && $action !== ''))
            && is_int($duration)
            && $duration >= 0
            && $duration <= 60000
        ) {
            $hasTraceableRequestEvent = true;
        }

        if (isset($expectedFailureCodes[$case]) && ($event['stable_code'] ?? null) === $expectedFailureCodes[$case]) {
            $hasExpectedStableCode = true;
        }
    }
    assertTrue($hasTraceableRequestEvent, "{$case}: no bounded route/action/timestamp/duration request event");
    assertTrue($hasExpectedStableCode, "{$case}: expected stable error code missing from Backend diagnostics");

    $bundle = $service->exportBundle([
        'correlation_id' => $correlationId,
        'hours' => 24,
    ]);
    $bundleJson = $bundle['json'] ?? '';
    assertTrue(is_string($bundleJson) && $bundleJson !== '', "{$case}: Backend export is empty");
    assertTrue(str_contains($bundleJson, $correlationId), "{$case}: Backend export omitted correlation");
    assertTrue(
        strlen($bundleJson) <= (int) config('observability.export_max_bytes'),
        "{$case}: Backend export exceeded configured byte bound",
    );

    $backendMatrix[$case] = [
        'correlation_id' => $correlationId,
        'runtime_inspector_event_count' => count($events),
        'sanitized_export_bytes' => strlen($bundleJson),
        'traceable_request_event' => true,
        'expected_stable_code_present' => $hasExpectedStableCode,
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
    'SENTINEL_RECOVERY_SECRET_101_FIXTURE_ONLY',
    'SENTINEL_PROVIDER_SECRET_101_FIXTURE_ONLY',
    'SENTINEL_LEARNER_ANSWER_101_FIXTURE_ONLY',
    'SENTINEL_QUESTION_TEXT_101_FIXTURE_ONLY',
    'SENTINEL_ASSESSMENT_CONTENT_101_FIXTURE_ONLY',
    'SENTINEL_REQUEST_BODY_101_FIXTURE_ONLY',
    'SENTINEL_RESPONSE_BODY_101_FIXTURE_ONLY',
    'sentinel.person.101@example.test',
    'SENTINEL_NAME_101_FIXTURE_ONLY',
];

// Exercise the real privileged Inspector export/audit path with an invalid
// secret-shaped filter. The filter must be discarded from both download and audit.
$admin = User::factory()->create(['role' => 'admin']);
Auth::login($admin);
$page = $app->make(RuntimeInspector::class);
$page->correlationId = $sentinels[4];
$downloadResponse = $page->downloadDiagnosticBundle();
ob_start();
$downloadResponse->sendContent();
$privilegedDownload = ob_get_clean();
assertTrue(is_string($privilegedDownload) && $privilegedDownload !== '', 'privileged Inspector export was empty');
foreach ($sentinels as $sentinel) {
    assertTrue(! str_contains($privilegedDownload, $sentinel), "privacy: sentinel leaked to privileged Inspector export: {$sentinel}");
}

$rows = DB::table('runtime_diagnostic_events')->get();
$serializedRows = json_encode(
    $rows->map(static fn (object $row): array => (array) $row)->all(),
    JSON_THROW_ON_ERROR,
);
$auditRows = $rows->filter(static fn (object $row): bool => ($row->data_class ?? null) === 'audit');
assertTrue($auditRows->isNotEmpty(), 'privacy: privileged Inspector export produced no audit event');
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

$resourceBounds = [
    'max_events' => (int) config('observability.max_events'),
    'query_limit' => (int) config('observability.query_limit'),
    'export_max_events' => (int) config('observability.export_max_events'),
    'export_max_bytes' => (int) config('observability.export_max_bytes'),
];
foreach ($resourceBounds as $key => $value) {
    assertTrue($value > 0, "Backend observability bound {$key} is not configuration-driven to a positive value");
}

$result = [
    'main_sha' => $mainSha,
    'candidate_sha' => $candidateSha,
    'backend_admin_lookup' => $backendMatrix,
    'D_client_only_timeout' => [
        'correlation_id' => $timeoutCorrelation,
        'backend_event_count' => count($timeoutEvents),
    ],
    'privacy' => [
        'sentinel_count' => count($sentinels),
        'persisted_diagnostic_row_count' => count($rows),
        'audit_row_count' => count($auditRows),
        'privileged_export_exercised' => true,
        'all_sentinels_absent' => true,
    ],
    'resource_bounds' => $resourceBounds,
];
$encoded = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
file_put_contents($outputPath, $encoded);

echo "BACKEND/ADMIN OBSERVABILITY CORRELATION ACCEPTANCE: PASS\n";
echo $encoded."\n";
