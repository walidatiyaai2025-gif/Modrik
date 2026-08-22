<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final class AdvertisingEligibilityService
{
    /**
     * Placement-to-zone ownership is deliberately backend-only. A client cannot
     * relabel an assessment surface as a general surface to bypass a no-ad zone.
     *
     * @var array<string, string>
     */
    private const PLACEMENT_ZONES = [
        'account_sidebar' => 'account',
        'assessment_interstitial' => 'assessment',
        'dashboard_sidebar' => 'dashboard',
        'help_sidebar' => 'help',
        'lesson_inline' => 'lesson',
        'onboarding_sidebar' => 'onboarding',
        'progress_sidebar' => 'progress',
    ];

    /** @var list<string> */
    private const IMMUTABLE_NO_AD_ZONES = [
        'account',
        'assessment',
        'help',
        'lesson',
        'onboarding',
        'progress',
    ];

    /** @return array<string, string> */
    public function placementZones(): array
    {
        return self::PLACEMENT_ZONES;
    }

    /** @return list<string> */
    public function immutableNoAdZones(): array
    {
        return self::IMMUTABLE_NO_AD_ZONES;
    }

    /**
     * @return null|array{id: string, version: int, global_enabled: bool, effective_at: string, expires_at: string}
     */
    public function policyStatus(): ?array
    {
        $policy = $this->latestPolicy();
        if ($policy === null) {
            return null;
        }

        return [
            'id' => (string) $policy['id'],
            'version' => (int) $policy['version'],
            'global_enabled' => (bool) $policy['global_enabled'],
            'effective_at' => (string) $policy['effective_at'],
            'expires_at' => (string) $policy['expires_at'],
        ];
    }

    /**
     * @return array{
     *   placement_code: string,
     *   zone_code: ?string,
     *   advertising_allowed: bool,
     *   reason_code: string,
     *   policy_version: ?int,
     *   evaluated_at: string
     * }
     *
     * @throws JsonException
     */
    public function decide(User $user, string $placementCode): array
    {
        return DB::transaction(function () use ($user, $placementCode): array {
            $evaluatedAt = now();
            $zoneCode = self::PLACEMENT_ZONES[$placementCode] ?? null;
            $policy = $this->latestPolicy();
            $policyId = $policy === null ? null : (string) $policy['id'];
            $policyVersion = $policy === null ? null : (int) $policy['version'];

            [$allowed, $reason] = $this->evaluate(
                $user,
                $placementCode,
                $zoneCode,
                $policy,
                $evaluatedAt,
            );

            $decision = [
                'placement_code' => $placementCode,
                'zone_code' => $zoneCode,
                'advertising_allowed' => $allowed,
                'reason_code' => $reason,
                'policy_version' => $policyVersion,
                'evaluated_at' => $evaluatedAt->toIso8601String(),
            ];
            $this->audit($user, $policyId, $decision, $evaluatedAt);

            return $decision;
        });
    }

    /**
     * @param  null|array{id: string, version: int, global_enabled: int|bool, effective_at: string, expires_at: string}  $policy
     * @return array{bool, string}
     */
    private function evaluate(
        User $user,
        string $placementCode,
        ?string $zoneCode,
        ?array $policy,
        Carbon $evaluatedAt,
    ): array {
        if ($zoneCode === null) {
            return [false, 'PLACEMENT_UNKNOWN'];
        }
        if (in_array($zoneCode, self::IMMUTABLE_NO_AD_ZONES, true)) {
            return [false, 'NO_AD_ZONE'];
        }

        // GOV-SURFACE-001 may make the operator surface more restrictive, never
        // weaker than the immutable Backend safety policy. The registry switch
        // therefore acts as an additional kill switch before policy evaluation.
        $operatorEnabled = app(SystemSettingsRegistry::class)
            ->current('ads.global.enabled', app()->environment())['value'];
        if ($operatorEnabled !== true) {
            return [false, 'GLOBAL_KILL_SWITCH'];
        }

        if ($policy === null) {
            return [false, 'CONFIG_MISSING'];
        }
        if (! (bool) $policy['global_enabled']) {
            return [false, 'GLOBAL_KILL_SWITCH'];
        }
        $policyEffectiveAt = $this->parseTime($policy['effective_at']);
        $policyExpiresAt = $this->parseTime($policy['expires_at']);
        if ($policyEffectiveAt === null || $policyExpiresAt === null || $policyExpiresAt->lte($policyEffectiveAt)) {
            return [false, 'CONFIG_INVALID'];
        }
        if ($evaluatedAt->lt($policyEffectiveAt)) {
            return [false, 'CONFIG_NOT_EFFECTIVE'];
        }
        if ($evaluatedAt->gte($policyExpiresAt)) {
            return [false, 'CONFIG_STALE'];
        }

        $placementEnabled = DB::table('advertising_placements')
            ->where('advertising_policy_id', $policy['id'])
            ->where('placement_code', $placementCode)
            ->value('enabled');
        if ($placementEnabled === null || ! (bool) $placementEnabled) {
            return [false, 'PLACEMENT_DISABLED'];
        }

        $assurance = DB::table('user_age_assurances')
            ->where('user_id', $user->getKey())
            ->first(['age_band', 'assured_at', 'expires_at']);
        if ($assurance === null) {
            return [false, 'AGE_UNKNOWN'];
        }
        /** @var array{age_band: string, assured_at: string, expires_at: string} $age */
        $age = (array) $assurance;
        $assuredAt = $this->parseTime($age['assured_at']);
        $assuranceExpiresAt = $this->parseTime($age['expires_at']);
        if ($assuredAt === null || $assuranceExpiresAt === null || $assuranceExpiresAt->lte($assuredAt)
            || ! in_array($age['age_band'], ['under_13', 'minor', 'adult'], true)) {
            return [false, 'AGE_ASSURANCE_INVALID'];
        }
        if ($evaluatedAt->lt($assuredAt) || $evaluatedAt->gte($assuranceExpiresAt)) {
            return [false, 'AGE_ASSURANCE_STALE'];
        }
        if ($age['age_band'] !== 'adult') {
            return [false, 'AGE_NOT_ADULT'];
        }

        return [true, 'ELIGIBLE'];
    }

    /** @return null|array{id: string, version: int, global_enabled: int|bool, effective_at: string, expires_at: string} */
    private function latestPolicy(): ?array
    {
        $policy = DB::table('advertising_policies')
            ->orderByDesc('version')
            ->first(['id', 'version', 'global_enabled', 'effective_at', 'expires_at']);
        if ($policy === null) {
            return null;
        }

        /** @var array{id: string, version: int, global_enabled: int|bool, effective_at: string, expires_at: string} $row */
        $row = (array) $policy;

        return $row;
    }

    private function parseTime(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array{placement_code: string, zone_code: ?string, advertising_allowed: bool, reason_code: string, policy_version: ?int, evaluated_at: string}  $decision
     *
     * @throws JsonException
     */
    private function audit(User $user, ?string $policyId, array $decision, Carbon $decidedAt): void
    {
        $auditId = (string) Str::ulid();
        DB::table('advertising_decision_audits')->insert([
            'id' => $auditId,
            'user_id' => $user->getKey(),
            'advertising_policy_id' => $policyId,
            'placement_code' => $decision['placement_code'],
            'zone_code' => $decision['zone_code'],
            'advertising_allowed' => $decision['advertising_allowed'],
            'reason_code' => $decision['reason_code'],
            'policy_version' => $decision['policy_version'],
            'decided_at' => $decidedAt,
            'created_at' => $decidedAt,
            'updated_at' => $decidedAt,
        ]);
        DB::table('outbox_events')->insert([
            'id' => (string) Str::ulid(),
            'event_type' => 'safety.advertising_decision_evaluated',
            'aggregate_type' => 'advertising_decision',
            'aggregate_id' => $auditId,
            'payload' => json_encode([
                'placement_code' => $decision['placement_code'],
                'advertising_allowed' => $decision['advertising_allowed'],
                'reason_code' => $decision['reason_code'],
                'policy_version' => $decision['policy_version'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'occurred_at' => $decidedAt,
            'created_at' => $decidedAt,
            'updated_at' => $decidedAt,
        ]);
    }
}
