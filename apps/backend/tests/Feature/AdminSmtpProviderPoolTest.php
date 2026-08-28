<?php

namespace Tests\Feature;

use App\Filament\Pages\SmtpProviderPool;
use App\Models\User;
use App\Notifications\Channels\RotatingSmtpMailChannel;
use App\Notifications\EmailVerificationTokenNotification;
use App\Notifications\PasswordRecoveryTokenNotification;
use App\Services\SmtpProviderPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSmtpProviderPoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_access_smtp_provider_management(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin);
        $this->assertTrue(SmtpProviderPool::canAccess());

        $this->actingAs($student);
        $this->assertFalse(SmtpProviderPool::canAccess());
    }

    public function test_admin_adds_multiple_providers_and_secrets_are_never_redisplayed(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.test']);
        $this->actingAs($admin);

        foreach ([
            ['Primary cPanel', 'mail-a.example.test', 587, 'starttls', 'smtp-a@example.test', 'secret-a'],
            ['Secondary SMTP', 'mail-b.example.test', 465, 'smtps', 'smtp-b@example.test', 'secret-b'],
        ] as [$name, $host, $port, $security, $username, $password]) {
            Livewire::test(SmtpProviderPool::class)
                ->set('form.name', $name)
                ->set('form.host', $host)
                ->set('form.port', $port)
                ->set('form.security', $security)
                ->set('form.username', $username)
                ->set('form.password', $password)
                ->set('form.from_address', $username)
                ->set('form.from_name', 'MODRIK')
                ->set('form.is_enabled', true)
                ->set('form.reason', 'Add an approved outbound SMTP provider for verification mail.')
                ->call('save')
                ->assertHasNoErrors();
        }

        $this->assertDatabaseCount('smtp_providers', 2);
        $rows = DB::table('smtp_providers')->orderBy('name')->get();
        $this->assertCount(2, $rows);
        $this->assertNotSame('secret-a', (string) $rows[0]->password_ciphertext);
        $this->assertNotSame('secret-b', (string) $rows[1]->password_ciphertext);

        $safeProviders = app(SmtpProviderPoolService::class)->providers();
        foreach ($safeProviders as $provider) {
            $this->assertArrayNotHasKey('password', $provider);
            $this->assertArrayNotHasKey('password_ciphertext', $provider);
            $this->assertTrue($provider['password_set']);
        }

        $auditJson = DB::table('smtp_provider_audits')->pluck('after_state')->implode("\n");
        $this->assertStringNotContainsString('secret-a', $auditJson);
        $this->assertStringNotContainsString('secret-b', $auditJson);

        Livewire::test(SmtpProviderPool::class)
            ->assertDontSee('secret-a')
            ->assertDontSee('secret-b');
    }

    public function test_delivery_pool_excludes_disabled_providers_and_configures_starttls_and_smtps(): void
    {
        $now = now();
        foreach ([
            ['01JSMTP0000000000000000001', 'A', null, 587, true],
            ['01JSMTP0000000000000000002', 'B', 'smtps', 465, true],
            ['01JSMTP0000000000000000003', 'C', null, 587, false],
        ] as [$id, $name, $scheme, $port, $enabled]) {
            DB::table('smtp_providers')->insert([
                'id' => $id,
                'name' => $name,
                'host' => strtolower($name).'.example.test',
                'port' => $port,
                'scheme' => $scheme,
                'username' => strtolower($name).'@example.test',
                'password_ciphertext' => Crypt::encryptString('password-'.$name),
                'from_address' => strtolower($name).'@example.test',
                'from_name' => 'MODRIK',
                'is_enabled' => $enabled,
                'last_tested_at' => null,
                'last_test_status' => null,
                'last_error_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $service = app(SmtpProviderPoolService::class);
        $this->assertSame(2, $service->enabledProviderCount());

        $candidates = $service->deliveryCandidates();
        $this->assertCount(2, $candidates);
        $this->assertEqualsCanonicalizing(
            ['01JSMTP0000000000000000001', '01JSMTP0000000000000000002'],
            array_column($candidates, 'id'),
        );

        foreach ($candidates as $provider) {
            $mailer = $service->configureMailer($provider);
            $config = config('mail.mailers.'.$mailer);
            $this->assertSame('smtp', $config['transport']);
            $this->assertSame($provider['scheme'], $config['scheme']);
            $this->assertSame($provider['host'], $config['host']);
            $this->assertSame($provider['port'], $config['port']);
        }
    }

    public function test_verification_and_recovery_email_use_the_rotating_channel(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            [RotatingSmtpMailChannel::class],
            (new EmailVerificationTokenNotification('verification-token'))->via($user),
        );
        $this->assertSame(
            [RotatingSmtpMailChannel::class],
            (new PasswordRecoveryTokenNotification('recovery-token'))->via($user),
        );
    }
}
