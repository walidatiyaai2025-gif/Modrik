<?php

namespace Tests\Feature;

use App\Filament\Pages\AccountOperations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminAccountOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admin_can_access_account_operations(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $content = User::factory()->create(['role' => 'content_team', 'account_status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active']);

        $this->actingAs($admin)->get('/admin/account-operations')
            ->assertOk()
            ->assertSee('data-testid="modrik-account-operations"', false);

        $this->actingAs($content)->get('/admin/account-operations')->assertForbidden();
        $this->actingAs($student)->get('/admin/account-operations')->assertForbidden();
    }

    public function test_tombstoned_accounts_remain_visible_as_safe_operational_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $target = User::factory()->create([
            'name' => 'Archived Learner',
            'role' => 'student',
            'account_status' => 'deleted',
            'deleted_at' => now(),
        ]);

        $this->actingAs($admin);
        Livewire::test(AccountOperations::class)
            ->set('statusFilter', 'deleted')
            ->assertSee('Archived Learner')
            ->call('selectAccount', (string) $target->id)
            ->assertSee('data-testid="modrik-account-detail"', false)
            ->assertSee('deleted');
    }

    public function test_account_detail_never_renders_session_hashes_or_provider_subjects(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $target = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
            'email_normalized' => 'learner@example.test',
            'password_enabled' => true,
        ]);
        $sessionId = (string) Str::ulid();
        $tokenHash = str_repeat('a', 64);
        $ipHash = str_repeat('b', 64);
        $userAgentHash = str_repeat('c', 64);
        $providerSubject = 'provider-subject-must-never-render';
        $now = now();

        DB::table('auth_sessions')->insert([
            'id' => $sessionId,
            'user_id' => $target->id,
            'token_hash' => $tokenHash,
            'name' => 'password',
            'ip_hash' => $ipHash,
            'user_agent_hash' => $userAgentHash,
            'authenticated_at' => $now,
            'last_used_at' => $now,
            'expires_at' => $now->copy()->addHour(),
            'revoked_at' => null,
            'revoke_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('auth_provider_identities')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $target->id,
            'provider' => 'google',
            'provider_subject' => $providerSubject,
            'provider_email_normalized' => 'private-relay@example.test',
            'provider_email_verified' => true,
            'provider_email_is_relay' => true,
            'linked_at' => $now,
            'last_seen_at' => $now,
            'revoked_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($admin);
        Livewire::test(AccountOperations::class)
            ->call('selectAccount', (string) $target->id)
            ->assertSee('Google')
            ->assertSee('active')
            ->assertDontSee($tokenHash)
            ->assertDontSee($ipHash)
            ->assertDontSee($userAgentHash)
            ->assertDontSee($providerSubject)
            ->assertDontSee('private-relay@example.test');
    }

    public function test_admin_can_revoke_all_sessions_only_with_reason_and_audit_is_safe(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $target = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
            'email_normalized' => 'target@example.test',
        ]);
        $now = now();

        foreach (['password', 'google'] as $name) {
            DB::table('auth_sessions')->insert([
                'id' => (string) Str::ulid(),
                'user_id' => $target->id,
                'token_hash' => hash('sha256', $name.'-raw-secret-material'),
                'name' => $name,
                'ip_hash' => hash('sha256', 'ip-'.$name),
                'user_agent_hash' => hash('sha256', 'ua-'.$name),
                'authenticated_at' => $now,
                'last_used_at' => $now,
                'expires_at' => $now->copy()->addHour(),
                'revoked_at' => null,
                'revoke_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->actingAs($admin);
        Livewire::test(AccountOperations::class)
            ->set('selectedUserId', (string) $target->id)
            ->set('revokeReason', 'Suspected account compromise reported by support.')
            ->call('revokeAllSessions')
            ->assertHasNoErrors();

        $this->assertSame(0, DB::table('auth_sessions')
            ->where('user_id', $target->id)
            ->whereNull('revoked_at')
            ->count());
        $this->assertSame(2, DB::table('auth_sessions')
            ->where('user_id', $target->id)
            ->where('revoke_reason', 'admin_security_recovery')
            ->count());

        $audit = DB::table('admin_account_operation_audits')->sole();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame($target->id, $audit->target_user_id);
        $this->assertSame('sessions.revoke_all', $audit->action);
        $this->assertSame('Suspected account compromise reported by support.', $audit->reason);
        $before = json_decode((string) $audit->before, true, 512, JSON_THROW_ON_ERROR);
        $after = json_decode((string) $audit->after, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $before['active_session_count']);
        $this->assertSame(0, $after['active_session_count']);

        $serializedAudit = json_encode((array) $audit, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('raw-secret-material', $serializedAudit);
    }

    public function test_short_revoke_reason_fails_closed_without_mutating_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $target = User::factory()->create(['role' => 'student', 'account_status' => 'active']);
        $now = now();
        DB::table('auth_sessions')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $target->id,
            'token_hash' => str_repeat('d', 64),
            'name' => 'password',
            'ip_hash' => null,
            'user_agent_hash' => null,
            'authenticated_at' => $now,
            'last_used_at' => $now,
            'expires_at' => $now->copy()->addHour(),
            'revoked_at' => null,
            'revoke_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($admin);
        Livewire::test(AccountOperations::class)
            ->set('selectedUserId', (string) $target->id)
            ->set('revokeReason', 'short')
            ->call('revokeAllSessions')
            ->assertHasErrors(['revokeReason']);

        $this->assertSame(1, DB::table('auth_sessions')->where('user_id', $target->id)->whereNull('revoked_at')->count());
        $this->assertSame(0, DB::table('admin_account_operation_audits')->count());
    }

    public function test_admin_can_manually_verify_active_student_with_reason_and_revoke_stale_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $student = User::factory()->unverified()->create([
            'role' => 'student',
            'account_status' => 'active',
        ]);
        $now = now();

        DB::table('auth_tokens')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $student->id,
            'purpose' => 'email_verification',
            'token_hash' => hash('sha256', 'old-verification-token'),
            'expires_at' => $now->copy()->addHour(),
            'consumed_at' => null,
            'revoked_at' => null,
            'created_at' => $now,
        ]);

        $this->actingAs($admin);
        Livewire::test(AccountOperations::class)
            ->call('selectAccount', (string) $student->id)
            ->assertSee('data-testid="modrik-manual-email-verification"', false)
            ->set('verifyReason', 'Parent confirmed this child account after email delivery failed.')
            ->call('verifySelectedEmail')
            ->assertHasNoErrors();

        $student->refresh();
        $this->assertNotNull($student->email_verified_at);
        $this->assertNotNull(DB::table('auth_tokens')->where('user_id', $student->id)->value('revoked_at'));

        $audit = DB::table('admin_account_operation_audits')->where('target_user_id', $student->id)->sole();
        $this->assertSame('email.verify_manual', $audit->action);
        $this->assertSame($admin->id, $audit->actor_id);
        $before = json_decode((string) $audit->before, true, 512, JSON_THROW_ON_ERROR);
        $after = json_decode((string) $audit->after, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($before['verified']);
        $this->assertTrue($after['verified']);
    }

    public function test_manual_verification_fails_closed_for_non_student_or_short_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $student = User::factory()->unverified()->create(['role' => 'student', 'account_status' => 'active']);
        $content = User::factory()->unverified()->create(['role' => 'content_team', 'account_status' => 'active']);

        $this->actingAs($admin);
        Livewire::test(AccountOperations::class)
            ->set('selectedUserId', (string) $student->id)
            ->set('verifyReason', 'short')
            ->call('verifySelectedEmail')
            ->assertHasErrors(['verifyReason']);

        $this->assertNull($student->fresh()->email_verified_at);

        Livewire::test(AccountOperations::class)
            ->set('selectedUserId', (string) $content->id)
            ->set('verifyReason', 'Operator attempted to bypass verification for a staff account.')
            ->call('verifySelectedEmail')
            ->assertHasErrors(['selectedUserId']);

        $this->assertNull($content->fresh()->email_verified_at);
    }

    public function test_role_matrix_is_read_only_and_arabic_surface_is_rtl(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $this->actingAs($admin);
        App::setLocale('ar');

        Livewire::test(AccountOperations::class)
            ->assertSee('مصفوفة الأدوار الحالية')
            ->assertSee('dir="rtl"', false)
            ->assertSee('admin')
            ->assertSee('content team')
            ->assertSee('student')
            ->assertDontSee('permission editor');
    }
}
