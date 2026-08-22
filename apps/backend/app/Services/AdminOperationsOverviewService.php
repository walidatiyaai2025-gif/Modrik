<?php

namespace App\Services;

use App\Support\Observability\RuntimeInspectorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class AdminOperationsOverviewService
{
    public function __construct(
        private readonly RuntimeInspectorService $runtimeInspector,
        private readonly IntegrationStatusService $integrations,
    ) {}

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $runtime = $this->runtimeInspector->runtimeSummary();
        $environment = (string) app()->environment();

        return [
            'environment' => $environment,
            'build_identity' => $runtime['build_identity'] ?? null,
            'backend' => $this->databaseHealth(),
            'queue' => $this->queueHealth(),
            'storage' => $this->storageHealth(),
            'scheduler' => [
                'status' => 'not_observable',
                'detail' => 'No durable scheduler-heartbeat contract is implemented; no freshness is fabricated.',
            ],
            'runtime' => [
                'diagnostics_enabled' => (bool) ($runtime['diagnostics_enabled'] ?? false),
                'inspector_enabled' => (bool) ($runtime['inspector_enabled'] ?? false),
                'diagnostic_events' => (int) ($runtime['diagnostic_events'] ?? 0),
                'outbox_events' => (int) ($runtime['outbox_events'] ?? 0),
                'outbox_delivery_attempts' => (int) ($runtime['outbox_delivery_attempts'] ?? 0),
            ],
            'integrations' => $this->integrationHealth($environment),
        ];
    }

    /** @return array{status: string, detail: string} */
    private function databaseHealth(): array
    {
        try {
            DB::select('SELECT 1');

            return ['status' => 'healthy', 'detail' => 'Application database query succeeded.'];
        } catch (Throwable) {
            return ['status' => 'degraded', 'detail' => 'Application database query failed. Inspect protected runtime diagnostics.'];
        }
    }

    /** @return array{status: string, queued: int, failed: int, detail: string} */
    private function queueHealth(): array
    {
        try {
            $queued = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $status = $failed > 0 ? 'degraded' : 'healthy';

            return [
                'status' => $status,
                'queued' => $queued,
                'failed' => $failed,
                'detail' => $failed > 0
                    ? 'Failed jobs require investigation through the authorized queue/runbook path.'
                    : 'No failed queue job is currently recorded.',
            ];
        } catch (Throwable) {
            return ['status' => 'degraded', 'queued' => 0, 'failed' => 0, 'detail' => 'Queue tables could not be inspected safely.'];
        }
    }

    /** @return array{status: string, writable: bool, detail: string} */
    private function storageHealth(): array
    {
        $path = storage_path('app');
        $writable = is_dir($path) && is_writable($path);

        return [
            'status' => $writable ? 'healthy' : 'degraded',
            'writable' => $writable,
            'detail' => $writable
                ? 'Application storage path is writable. Capacity is not reported because no governed capacity contract exists.'
                : 'Application storage path is not writable.',
        ];
    }

    /** @return array<string, mixed> */
    private function integrationHealth(string $environment): array
    {
        try {
            $auth = $this->integrations->authentication($environment);
            $firebase = $this->integrations->firebase($environment);

            $pendingProviders = 0;
            foreach ($auth as $provider) {
                if (($provider['transport_status'] ?? '') === 'pending') {
                    $pendingProviders++;
                }
            }

            $fcmEnabled = (bool) ($firebase['fcm_enabled'] ?? false);
            $fcmStatus = (string) ($firebase['fcm_transport_status'] ?? 'unknown');
            $firebaseTransportUnavailable = $fcmEnabled && $fcmStatus !== 'available';
            $attentionRequired = $pendingProviders > 0 || $firebaseTransportUnavailable;

            return [
                'status' => $attentionRequired ? 'attention' : 'healthy',
                'pending_auth_transports' => $pendingProviders,
                'firebase_fcm_status' => $fcmStatus,
                'firebase_credentials_reference_set' => (bool) ($firebase['credential_reference_set'] ?? false),
                'detail' => $attentionRequired
                    ? 'One or more configured integration transports are not available; no success is fabricated.'
                    : 'Only safe integration status is summarized; credential material remains external.',
            ];
        } catch (Throwable) {
            return [
                'status' => 'degraded',
                'pending_auth_transports' => 0,
                'firebase_fcm_status' => 'unknown',
                'firebase_credentials_reference_set' => false,
                'detail' => 'Integration status could not be resolved safely.',
            ];
        }
    }
}
