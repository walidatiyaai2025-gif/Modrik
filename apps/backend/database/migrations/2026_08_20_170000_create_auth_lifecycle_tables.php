<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email_normalized')->nullable()->after('email');
            $table->boolean('password_enabled')->default(true)->after('password');
            $table->string('account_status', 24)->default('active')->after('role');
            $table->timestamp('deleted_at')->nullable()->after('email_verified_at');
        });

        $seen = [];
        DB::table('users')->orderBy('id')->get(['id', 'email'])->each(function (object $user) use (&$seen): void {
            $normalized = mb_strtolower(trim((string) $user->email));
            if ($normalized === '') {
                throw new RuntimeException('Existing users must have a non-empty email before the Auth lifecycle migration can run.');
            }
            if (isset($seen[$normalized])) {
                throw new RuntimeException('Case-insensitive duplicate user emails must be resolved before the Auth lifecycle migration can run.');
            }
            $seen[$normalized] = true;
            DB::table('users')->where('id', (string) $user->id)->update(['email_normalized' => $normalized]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('email_normalized', 'users_email_normalized_unique');
        });

        Schema::create('auth_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('name', 80)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamp('authenticated_at');
            $table->timestamp('last_used_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('revoke_reason', 64)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at'], 'auth_sessions_user_active_index');
        });

        Schema::create('auth_tokens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'purpose'], 'auth_tokens_user_purpose_index');
        });

        Schema::create('auth_provider_identities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('provider_subject', 191);
            $table->string('provider_email_normalized')->nullable();
            $table->boolean('provider_email_verified')->default(false);
            $table->boolean('provider_email_is_relay')->default(false);
            $table->timestamp('linked_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_subject'], 'auth_provider_subject_unique');
            $table->index(['user_id', 'revoked_at'], 'auth_provider_user_active_index');
        });

        Schema::create('auth_provider_intents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('purpose', 16);
            $table->string('state_hash', 64)->unique();
            $table->string('nonce_hash', 64);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('auth_security_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->nullable()->index();
            $table->ulid('session_id')->nullable();
            $table->string('event_type', 64)->index();
            $table->string('context_hash', 64)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_security_events');
        Schema::dropIfExists('auth_provider_intents');
        Schema::dropIfExists('auth_provider_identities');
        Schema::dropIfExists('auth_tokens');
        Schema::dropIfExists('auth_sessions');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_normalized_unique');
            $table->dropColumn(['email_normalized', 'password_enabled', 'account_status', 'deleted_at']);
        });
    }
};
