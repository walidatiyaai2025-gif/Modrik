<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AdvertisingEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'modrik-local-fixture-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'modrik.fixture.enabled' => true,
            'modrik.fixture.bearer_token' => self::TOKEN,
            'modrik.fixture.user_id' => LearningSliceSeeder::USER_ID,
        ]);
        $this->seed(LearningSliceSeeder::class);
    }

    public function test_missing_unknown_and_immutable_no_ad_decisions_fail_closed_and_are_audited(): void
    {
        $this->getJson('/v1/advertising/decisions/dashboard_sidebar')
            ->assertUnauthorized();
        $this->assertDatabaseCount('advertising_decision_audits', 0);

        $this->decision('dashboard_sidebar')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'CONFIG_MISSING')
            ->assertJsonPath('data.zone_code', 'dashboard')
            ->assertJsonPath('data.policy_version', null);

        $this->withToken(self::TOKEN)
            ->getJson('/v1/advertising/decisions/dashboard_sidebar?age_band=adult&zone_code=dashboard')
            ->assertOk()
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'CONFIG_MISSING')
            ->assertJsonMissingPath('data.age_band')
            ->assertJsonMissingPath('data.assurance_source');

        $this->decision('unknown_sidebar')
            ->assertOk()
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'PLACEMENT_UNKNOWN')
            ->assertJsonPath('data.zone_code', null);

        $this->decision('lesson_inline')
            ->assertOk()
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'NO_AD_ZONE')
            ->assertJsonPath('data.zone_code', 'lesson');

        $this->withToken(self::TOKEN)
            ->getJson('/v1/advertising/decisions/INVALID')
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'ADVERTISING_PLACEMENT_INVALID');

        $this->assertDatabaseCount('advertising_decision_audits', 4);
        $this->assertDatabaseCount('outbox_events', 4);
        $payloads = DB::table('outbox_events')->pluck('payload')->implode('\n');
        $this->assertStringNotContainsString('learner@fixture.invalid', $payloads);
        $this->assertStringNotContainsString('age_band', $payloads);
        $this->assertStringNotContainsString('assurance_source', $payloads);
    }

    public function test_kill_switch_future_and_stale_configuration_override_placement_and_age(): void
    {
        $this->insertAgeAssurance('adult', now()->addDay());

        $this->insertPolicy(version: 1, globallyEnabled: false, effectiveAt: now()->subHour(), expiresAt: now()->addDay(), placementEnabled: true);
        $this->decision('dashboard_sidebar')
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'GLOBAL_KILL_SWITCH')
            ->assertJsonPath('data.policy_version', 1);

        $this->insertPolicy(version: 2, globallyEnabled: true, effectiveAt: now()->addHour(), expiresAt: now()->addDay(), placementEnabled: true);
        $this->decision('dashboard_sidebar')
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'CONFIG_NOT_EFFECTIVE')
            ->assertJsonPath('data.policy_version', 2);

        $this->insertPolicy(version: 3, globallyEnabled: true, effectiveAt: now()->subHour(), expiresAt: now()->subDay(), placementEnabled: true);
        $this->decision('dashboard_sidebar')
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'CONFIG_INVALID')
            ->assertJsonPath('data.policy_version', 3);

        $this->insertPolicy(version: 4, globallyEnabled: true, effectiveAt: now()->subDays(2), expiresAt: now()->subDay(), placementEnabled: true);
        $this->decision('dashboard_sidebar')
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'CONFIG_STALE')
            ->assertJsonPath('data.policy_version', 4);

        $this->insertPolicy(version: 5, globallyEnabled: true, effectiveAt: now()->subHour(), expiresAt: now()->addDay(), placementEnabled: false);
        $this->decision('dashboard_sidebar')
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'PLACEMENT_DISABLED')
            ->assertJsonPath('data.policy_version', 5);
    }

    public function test_only_current_adult_assurance_can_pass_an_enabled_general_placement(): void
    {
        $policyId = $this->insertPolicy(
            version: 1,
            globallyEnabled: true,
            effectiveAt: now()->subHour(),
            expiresAt: now()->addDay(),
            placementEnabled: true,
        );

        $otherUser = User::query()->create([
            'name' => 'Other Fixture User',
            'email' => 'other@fixture.invalid',
            'locale' => 'en',
            'role' => 'student',
            'password' => Hash::make(Str::random(64)),
        ]);
        $this->insertAgeAssurance('adult', now()->addDay(), (string) $otherUser->getKey());
        $this->decision('dashboard_sidebar')
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'AGE_UNKNOWN');

        $this->insertAgeAssurance('under_13', now()->addDay());
        $this->decision('dashboard_sidebar')
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'AGE_NOT_ADULT');

        DB::table('user_age_assurances')->where('user_id', LearningSliceSeeder::USER_ID)->update([
            'age_band' => 'untrusted_value',
            'updated_at' => now(),
        ]);
        $this->decision('dashboard_sidebar')
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'AGE_ASSURANCE_INVALID');

        DB::table('user_age_assurances')->where('user_id', LearningSliceSeeder::USER_ID)->update([
            'age_band' => 'adult',
            'assured_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
            'updated_at' => now(),
        ]);
        $this->decision('dashboard_sidebar')
            ->assertJsonPath('data.advertising_allowed', false)
            ->assertJsonPath('data.reason_code', 'AGE_ASSURANCE_STALE');

        DB::table('user_age_assurances')->where('user_id', LearningSliceSeeder::USER_ID)->update([
            'assured_at' => now()->subMinute(),
            'expires_at' => now()->addDay(),
            'updated_at' => now(),
        ]);
        $this->decision('dashboard_sidebar')
            ->assertJsonPath('data.advertising_allowed', true)
            ->assertJsonPath('data.reason_code', 'ELIGIBLE')
            ->assertJsonPath('data.policy_version', 1);

        foreach (['account_sidebar', 'assessment_interstitial', 'help_sidebar', 'lesson_inline', 'onboarding_sidebar', 'progress_sidebar'] as $placement) {
            DB::table('advertising_placements')->insert([
                'id' => (string) Str::ulid(),
                'advertising_policy_id' => $policyId,
                'placement_code' => $placement,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->decision($placement)
                ->assertJsonPath('data.advertising_allowed', false)
                ->assertJsonPath('data.reason_code', 'NO_AD_ZONE');
        }

        $this->assertSame(11, DB::table('advertising_decision_audits')->count());
        $this->assertSame(1, DB::table('advertising_decision_audits')->where('advertising_allowed', true)->count());
    }

    private function insertPolicy(
        int $version,
        bool $globallyEnabled,
        mixed $effectiveAt,
        mixed $expiresAt,
        bool $placementEnabled,
    ): string {
        $policyId = (string) Str::ulid();
        DB::table('advertising_policies')->insert([
            'id' => $policyId,
            'version' => $version,
            'global_enabled' => $globallyEnabled,
            'effective_at' => $effectiveAt,
            'expires_at' => $expiresAt,
            'created_by' => null,
            'change_reference' => 'synthetic-test-policy-'.$version,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('advertising_placements')->insert([
            'id' => (string) Str::ulid(),
            'advertising_policy_id' => $policyId,
            'placement_code' => 'dashboard_sidebar',
            'enabled' => $placementEnabled,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $policyId;
    }

    private function insertAgeAssurance(string $ageBand, mixed $expiresAt, string $userId = LearningSliceSeeder::USER_ID): void
    {
        DB::table('user_age_assurances')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $userId,
            'age_band' => $ageBand,
            'assurance_source' => 'synthetic_fixture',
            'assured_at' => now()->subMinute(),
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return TestResponse<Response> */
    private function decision(string $placementCode): TestResponse
    {
        return $this->withToken(self::TOKEN)
            ->getJson('/v1/advertising/decisions/'.$placementCode);
    }
}
