<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StudentNotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'modrik-notification-fixture-token';

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

    public function test_student_reads_only_owned_notifications_with_unread_count(): void
    {
        $firstId = $this->insertNotification(
            LearningSliceSeeder::USER_ID,
            kind: 'learning_reminder',
            action: 'study',
            title: ['ar' => 'تابع الدرس', 'en' => 'Continue your lesson', 'fr' => 'Continuez votre leçon'],
            body: ['ar' => 'لديك درس جاهز.', 'en' => 'Your lesson is ready.', 'fr' => 'Votre leçon est prête.'],
        );
        $secondId = $this->insertNotification(
            LearningSliceSeeder::USER_ID,
            kind: 'progress_update',
            action: 'progress',
            readAt: now(),
        );

        $other = User::factory()->create(['role' => 'student', 'account_status' => 'active']);
        $foreignId = $this->insertNotification((string) $other->id, kind: 'private_other_user');

        $response = $this->withToken(self::TOKEN)->getJson('/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id', $secondId)
            ->assertJsonPath('data.items.1.id', $firstId)
            ->assertJsonPath('data.items.1.title.en', 'Continue your lesson')
            ->assertJsonPath('data.items.1.action', 'study')
            ->assertJsonPath('data.items.1.is_read', false);

        $this->assertStringNotContainsString($foreignId, $response->getContent());
        $this->assertStringNotContainsString('private_other_user', $response->getContent());
    }

    public function test_read_mutations_are_idempotent_and_cross_account_access_is_not_disclosed(): void
    {
        $ownedId = $this->insertNotification(LearningSliceSeeder::USER_ID, kind: 'practice_ready', action: 'practice');
        $other = User::factory()->create(['role' => 'student', 'account_status' => 'active']);
        $foreignId = $this->insertNotification((string) $other->id, kind: 'foreign');

        $this->withToken(self::TOKEN)->putJson('/v1/notifications/'.$ownedId.'/read')
            ->assertOk()
            ->assertJsonPath('data.id', $ownedId)
            ->assertJsonPath('data.is_read', true);

        $firstReadAt = DB::table('student_notifications')->where('id', $ownedId)->value('read_at');
        $this->withToken(self::TOKEN)->putJson('/v1/notifications/'.$ownedId.'/read')
            ->assertOk()
            ->assertJsonPath('data.is_read', true);
        $this->assertSame(
            (string) $firstReadAt,
            (string) DB::table('student_notifications')->where('id', $ownedId)->value('read_at'),
        );

        $this->withToken(self::TOKEN)->putJson('/v1/notifications/'.$foreignId.'/read')
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
        $this->assertNull(DB::table('student_notifications')->where('id', $foreignId)->value('read_at'));
    }

    public function test_mark_all_read_affects_only_authenticated_student(): void
    {
        $this->insertNotification(LearningSliceSeeder::USER_ID, kind: 'one');
        $this->insertNotification(LearningSliceSeeder::USER_ID, kind: 'two');
        $other = User::factory()->create(['role' => 'student', 'account_status' => 'active']);
        $foreignId = $this->insertNotification((string) $other->id, kind: 'foreign');

        $this->withToken(self::TOKEN)->putJson('/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated_count', 2)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(
            0,
            DB::table('student_notifications')
                ->where('user_id', LearningSliceSeeder::USER_ID)
                ->whereNull('read_at')
                ->count(),
        );
        $this->assertNull(DB::table('student_notifications')->where('id', $foreignId)->value('read_at'));
    }

    public function test_inbox_requires_authentication_and_invalid_ids_fail_closed(): void
    {
        $this->getJson('/v1/notifications')->assertUnauthorized();
        $this->withToken(self::TOKEN)
            ->putJson('/v1/notifications/not-an-ulid/read')
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
    }

    private function insertNotification(
        string $userId,
        string $kind,
        ?string $action = null,
        ?array $title = null,
        ?array $body = null,
        mixed $readAt = null,
    ): string {
        $id = (string) Str::ulid();
        $now = now();

        DB::table('student_notifications')->insert([
            'id' => $id,
            'user_id' => $userId,
            'kind' => $kind,
            'title' => json_encode($title ?? ['ar' => 'إشعار', 'en' => 'Notification', 'fr' => 'Notification'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'body' => json_encode($body ?? ['ar' => 'تحديث داخل مُدرك.', 'en' => 'An update inside MODRIK.', 'fr' => 'Une mise à jour dans MODRIK.'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'action' => $action,
            'occurred_at' => $now,
            'read_at' => $readAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }
}
