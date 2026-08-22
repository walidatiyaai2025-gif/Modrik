<?php

namespace App\Services;

use App\Auth\PendingProviderIdentityVerifier;
use App\Auth\ProviderIdentityVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class IntegrationStatusService
{
    public function __construct(
        private readonly SystemSettingsRegistry $settings,
        private readonly AdvertisingEligibilityService $advertising,
        private readonly ProviderIdentityVerifier $providerVerifier,
    ) {}

    /** @return array<string, array<string, mixed>> */
    public function authentication(string $environment): array
    {
        $google = (array) config('modrik.auth.providers.google', []);
        $apple = (array) config('modrik.auth.providers.apple', []);
        $providerTransportPending = $this->providerVerifier instanceof PendingProviderIdentityVerifier;

        return [
            'email' => [
                'enabled' => $this->boolSetting('auth.email.enabled', $environment),
                'verification' => true,
                'recovery' => true,
                'transport_status' => 'available',
                'secret_set' => true,
            ],
            'google' => [
                'enabled' => $this->boolSetting('auth.google.enabled', $environment),
                'client_reference' => $this->safeReference((string) ($google['client_id'] ?? '')),
                'callback_reference' => $this->safeUrl((string) ($google['callback_url'] ?? '')),
                'secret_set' => $this->isSet((string) ($google['client_secret'] ?? '')),
                'transport_status' => $providerTransportPending ? 'pending' : 'available',
            ],
            'apple' => [
                'enabled' => $this->boolSetting('auth.apple.enabled', $environment),
                'client_reference' => $this->safeReference((string) ($apple['client_id'] ?? '')),
                'team_reference' => $this->safeReference((string) ($apple['team_id'] ?? '')),
                'key_reference' => $this->safeReference((string) ($apple['key_id'] ?? '')),
                'callback_reference' => $this->safeUrl((string) ($apple['callback_url'] ?? '')),
                'secret_set' => $this->isSet((string) ($apple['private_key'] ?? '')),
                'transport_status' => $providerTransportPending ? 'pending' : 'available',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function notifications(string $environment): array
    {
        return [
            'enabled' => $this->boolSetting('notifications.enabled', $environment),
            'quiet_hours_enabled' => $this->boolSetting('notifications.quiet_hours.enabled', $environment),
            'quiet_hours_start' => $this->settings->current('notifications.quiet_hours.start', $environment)['value'],
            'quiet_hours_end' => $this->settings->current('notifications.quiet_hours.end', $environment)['value'],
            'email_verification_channel' => 'available',
            'password_recovery_channel' => 'available',
            'student_notification_center' => 'audit_required',
            'push_channel' => $this->boolSetting('firebase.fcm.enabled', $environment) ? 'enabled_pending_transport' : 'disabled',
        ];
    }

    /** @return array<string, mixed> */
    public function firebase(string $environment): array
    {
        $firebase = (array) config('modrik.firebase', []);
        $projectSet = $this->isSet((string) ($firebase['project_id'] ?? ''));
        $credentialReferenceSet = $this->isSet((string) ($firebase['credentials_reference'] ?? ''));

        return [
            'core_dependency' => false,
            'project_reference' => $this->safeReference((string) ($firebase['project_id'] ?? '')),
            'web_app_reference' => $this->safeReference((string) ($firebase['web_app_id'] ?? '')),
            'android_app_reference' => $this->safeReference((string) ($firebase['android_app_id'] ?? '')),
            'ios_app_reference' => $this->safeReference((string) ($firebase['ios_app_id'] ?? '')),
            'credential_reference_set' => $credentialReferenceSet,
            'fcm_enabled' => $this->boolSetting('firebase.fcm.enabled', $environment),
            'remote_config_enabled' => $this->boolSetting('firebase.remote_config.enabled', $environment),
            'fcm_transport_status' => $projectSet && $credentialReferenceSet ? 'pending_adapter' : 'configuration_incomplete',
            'remote_config_transport_status' => $projectSet && $credentialReferenceSet ? 'pending_adapter' : 'configuration_incomplete',
            'last_test' => $this->lastIntegrationOperation('firebase', 'test_push', $environment),
            'firebase_auth' => 'disabled_by_architecture',
            'firestore' => 'disabled_by_architecture',
            'realtime_database' => 'disabled_by_architecture',
            'storage' => 'disabled_by_architecture',
        ];
    }

    /** @return array<string, mixed> */
    public function advertising(string $environment): array
    {
        return [
            'operator_enabled' => $this->boolSetting('ads.global.enabled', $environment),
            'test_mode' => $this->boolSetting('ads.test_mode.enabled', $environment),
            'policy' => $this->advertising->policyStatus(),
            'immutable_no_ad_zones' => $this->advertising->immutableNoAdZones(),
            'placement_zones' => $this->advertising->placementZones(),
        ];
    }

    /**
     * Boundary-only action until an approved Firebase transport adapter exists.
     * It validates that operators cannot provide an arbitrary raw registration
     * token and persists only a SHA-256 target fingerprint plus stable result.
     * No network send is claimed before a transport adapter is approved.
     *
     * @return array{sent: false, code: string}
     */
    public function firebaseTestPushBoundary(
        string $environment,
        string $targetType,
        string $targetReference,
        ?string $actorId = null,
    ): array {
        if (! in_array($targetType, ['test_user', 'test_device'], true)) {
            throw new InvalidArgumentException('Test push target must be a designated test user or test device reference.');
        }
        if ($targetReference === '' || mb_strlen($targetReference) > 160) {
            throw new InvalidArgumentException('A bounded designated test target reference is required.');
        }

        if (! $this->boolSetting('firebase.fcm.enabled', $environment)) {
            $code = 'FCM_DISABLED';
        } else {
            $firebase = $this->firebase($environment);
            $code = $firebase['fcm_transport_status'] === 'configuration_incomplete'
                ? 'FCM_CONFIGURATION_INCOMPLETE'
                : 'FCM_TRANSPORT_PENDING';
        }

        $this->auditIntegrationOperation(
            $environment,
            'firebase',
            'test_push',
            $targetType,
            $targetReference,
            $code,
            $actorId,
        );

        return ['sent' => false, 'code' => $code];
    }

    /** @return null|array{result_code: string, target_type: string|null, target_fingerprint: string|null, actor_id: string|null, occurred_at: string} */
    public function lastIntegrationOperation(string $integration, string $operation, string $environment): ?array
    {
        $row = DB::table('integration_operation_audits')
            ->where('integration', $integration)
            ->where('operation', $operation)
            ->where('environment', $environment)
            ->orderByDesc('occurred_at')
            ->first(['result_code', 'target_type', 'target_fingerprint', 'actor_id', 'occurred_at']);

        if ($row === null) {
            return null;
        }

        return [
            'result_code' => (string) $row->result_code,
            'target_type' => $row->target_type === null ? null : (string) $row->target_type,
            'target_fingerprint' => $row->target_fingerprint === null ? null : (string) $row->target_fingerprint,
            'actor_id' => $row->actor_id === null ? null : (string) $row->actor_id,
            'occurred_at' => (string) $row->occurred_at,
        ];
    }

    private function auditIntegrationOperation(
        string $environment,
        string $integration,
        string $operation,
        string $targetType,
        string $targetReference,
        string $resultCode,
        ?string $actorId,
    ): void {
        $now = now();
        DB::table('integration_operation_audits')->insert([
            'id' => (string) Str::ulid(),
            'actor_id' => $actorId,
            'environment' => $environment,
            'integration' => $integration,
            'operation' => $operation,
            'target_type' => $targetType,
            'target_fingerprint' => hash('sha256', $targetType."\0".$targetReference),
            'result_code' => $resultCode,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function boolSetting(string $key, string $environment): bool
    {
        return $this->settings->current($key, $environment)['value'] === true;
    }

    private function isSet(string $value): bool
    {
        return trim($value) !== '';
    }

    private function safeReference(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Not Set';
        }
        if (mb_strlen($value) <= 12) {
            return $value;
        }

        return mb_substr($value, 0, 8).'…'.mb_substr($value, -4);
    }

    private function safeUrl(string $value): string
    {
        $value = trim($value);

        return $value === '' ? 'Not Set' : $value;
    }
}
