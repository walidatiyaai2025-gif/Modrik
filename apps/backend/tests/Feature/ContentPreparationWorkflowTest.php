<?php

namespace Tests\Feature;

use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use ZipArchive;

class ContentPreparationWorkflowTest extends TestCase
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
            'modrik.idempotency.secret' => 'test-only-idempotency-secret',
        ]);
        $this->seed(LearningSliceSeeder::class);
    }

    public function test_only_content_roles_can_create_deterministic_idempotent_preparation_requests(): void
    {
        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'preparation-role-denied-0001')
            ->postJson('/v1/admin/preparation-requests', $this->requestPayload())
            ->assertForbidden()
            ->assertJsonPath('code', 'CONTENT_ROLE_REQUIRED');
        $this->assertDatabaseCount('preparation_requests', 0);

        $this->grantContentRole();
        $key = 'preparation-create-command-0001';
        $created = $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/admin/preparation-requests', $this->requestPayload())
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.schema_version', '1.0.0')
            ->assertJsonPath('data.settings_hash', 'b23dfe0e6b57b63f3003dd463ae060d8d0a22197c8213ea8ae95d2a7693edff5')
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.bundle.settings.generation.paid_ai_required', false);

        $requestId = (string) $created->json('data.preparation_request_id');
        $prompt = (string) $created->json('data.prompt');
        $this->assertStringContainsString($requestId, $prompt);
        $this->assertStringContainsString((string) $created->json('data.settings_hash'), $prompt);
        $this->assertStringNotContainsString('fixture.student@modrik.invalid', $prompt);
        $this->assertDatabaseHas('preparation_requests', [
            'id' => $requestId,
            'created_by' => LearningSliceSeeder::USER_ID,
            'status' => 'ready',
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'aggregate_id' => $requestId,
            'event_type' => 'content.preparation_requested',
        ]);

        $replay = $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/admin/preparation-requests', $this->requestPayload())
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($created->json(), $replay->json());
        $this->assertDatabaseCount('preparation_requests', 1);

        $changed = $this->requestPayload();
        $changed['settings']['generation']['maximum_questions_per_quiz'] = 9;
        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/v1/admin/preparation-requests', $changed)
            ->assertConflict()
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');

        $invalid = $this->requestPayload();
        $invalid['settings']['generation']['paid_ai_required'] = true;
        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'preparation-paid-ai-rejected-0001')
            ->postJson('/v1/admin/preparation-requests', $invalid)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'PREPARATION_GENERATION_INVALID');

        $invalidScope = $this->requestPayload();
        $invalidScope['settings']['academic_scope']['subject_references'][] = $invalidScope['settings']['academic_scope']['subject_references'][0];
        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'preparation-invalid-scope-0001')
            ->postJson('/v1/admin/preparation-requests', $invalidScope)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'PREPARATION_ACADEMIC_SCOPE_INVALID');
    }

    public function test_valid_synthetic_zip_is_hash_bound_staged_and_exactly_replayed_without_publication(): void
    {
        $this->grantContentRole();
        $created = $this->createRequest();
        $archiveBytes = $this->validArchiveBytes(
            (string) $created->json('data.preparation_request_id'),
            (string) $created->json('data.settings_hash'),
        );
        $curriculumCounts = $this->curriculumCounts();
        $key = 'preparation-import-valid-0001';

        $staged = $this->upload($archiveBytes, $key)
            ->assertStatus(202)
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.status', 'staged')
            ->assertJsonPath('data.validated_file_count', 1)
            ->assertJsonPath('data.validation_summary.valid', true);
        $importId = (string) $staged->json('data.preparation_import_id');
        $this->assertGreaterThan(0, (int) $staged->json('data.validated_record_count'));
        $this->assertDatabaseHas('preparation_imports', [
            'id' => $importId,
            'status' => 'staged',
            'rights_status' => 'synthetic_fixture',
        ]);
        $this->assertDatabaseHas('preparation_import_files', [
            'preparation_import_id' => $importId,
            'path' => 'content-pack.json',
            'status' => 'validated',
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'aggregate_id' => $importId,
            'event_type' => 'content.preparation_imported',
        ]);
        $this->assertSame($curriculumCounts, $this->curriculumCounts(), 'Staging must not publish or replace curriculum rows.');

        $replay = $this->upload($archiveBytes, $key)
            ->assertStatus(202)
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($staged->json(), $replay->json());
        $this->assertDatabaseCount('preparation_imports', 1);

        $deduplicated = $this->upload($archiveBytes, 'preparation-import-valid-deduplicated-0001')
            ->assertStatus(202)
            ->assertHeader('Idempotency-Replayed', 'false');
        $this->assertSame($importId, $deduplicated->json('data.preparation_import_id'));
        $this->assertDatabaseCount('preparation_imports', 1);

        $semanticArchive = $this->semanticInvalidArchiveBytes(
            (string) $created->json('data.preparation_request_id'),
            (string) $created->json('data.settings_hash'),
        );
        $this->upload($semanticArchive, $key)
            ->assertConflict()
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
    }

    public function test_binding_and_semantic_failures_are_persisted_replayed_and_never_publish(): void
    {
        $this->grantContentRole();
        $created = $this->createRequest();
        $requestId = (string) $created->json('data.preparation_request_id');
        $settingsHash = (string) $created->json('data.settings_hash');
        $curriculumCounts = $this->curriculumCounts();

        $bindingManifest = $this->fixtureJson('invalid-binding/manifest.json');
        $bindingManifest['preparation_request_id'] = $requestId;
        $bindingArchive = $this->archiveBytes($bindingManifest, $this->fixtureJson('valid/content-pack.json'));
        $bindingKey = 'preparation-import-binding-0001';
        $rejected = $this->upload($bindingArchive, $bindingKey)
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('code', 'CONTENT_PREPARATION_IMPORT_REJECTED')
            ->assertJsonPath('errors.0.code', 'CONTENT_SETTINGS_HASH_MISMATCH');
        $this->assertDatabaseHas('preparation_imports', ['status' => 'rejected']);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'content.preparation_import_rejected']);
        $this->assertDatabaseCount('preparation_import_files', 0);
        $this->assertSame($curriculumCounts, $this->curriculumCounts());

        $replay = $this->upload($bindingArchive, $bindingKey)
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($rejected->json(), $replay->json());

        $this->upload(
            $this->semanticInvalidArchiveBytes($requestId, $settingsHash),
            'preparation-import-semantic-0001',
        )
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'CONTENT_REFERENCE_INVALID');
        $this->assertSame(2, DB::table('preparation_imports')->where('status', 'rejected')->count());
        $this->assertSame($curriculumCounts, $this->curriculumCounts());

        $this->upload(
            $this->hashMismatchArchiveBytes($requestId, $settingsHash),
            'preparation-import-hash-0001',
        )
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'CONTENT_FILE_HASH_MISMATCH');
        $this->assertSame(3, DB::table('preparation_imports')->where('status', 'rejected')->count());
        $this->assertSame($curriculumCounts, $this->curriculumCounts());

        $eventPayloads = DB::table('outbox_events')
            ->where('event_type', 'content.preparation_import_rejected')
            ->pluck('payload')
            ->all();
        foreach ($eventPayloads as $payload) {
            $this->assertIsString($payload);
            $this->assertStringNotContainsString('answer_contract', $payload);
            $this->assertStringNotContainsString('fixture.student@modrik.invalid', $payload);
        }
    }

    public function test_traversal_symlink_and_compression_bomb_entries_fail_closed(): void
    {
        $this->grantContentRole();

        $cases = [
            ['bytes' => $this->unsafePathArchiveBytes(), 'code' => 'CONTENT_ARCHIVE_PATH_UNSAFE'],
            ['bytes' => $this->symlinkArchiveBytes(), 'code' => 'CONTENT_ARCHIVE_SYMLINK_REJECTED'],
            ['bytes' => $this->compressionBombArchiveBytes(), 'code' => 'CONTENT_ARCHIVE_RATIO_EXCEEDED'],
        ];
        foreach ($cases as $index => $case) {
            $this->upload($case['bytes'], 'preparation-archive-abuse-'.$index.'-0001')
                ->assertUnprocessable()
                ->assertJsonPath('errors.0.code', $case['code']);
        }

        $this->assertSame(3, DB::table('preparation_imports')->where('status', 'rejected')->count());
        $this->assertDatabaseCount('preparation_import_files', 0);
    }

    public function test_manifest_and_content_pack_schema_violations_are_rejected_before_staging(): void
    {
        $this->grantContentRole();
        $created = $this->createRequest();
        $requestId = (string) $created->json('data.preparation_request_id');
        $settingsHash = (string) $created->json('data.settings_hash');
        $curriculumCounts = $this->curriculumCounts();

        $invalidManifest = $this->fixtureJson('valid/manifest.json');
        $invalidManifest['preparation_request_id'] = $requestId;
        $invalidManifest['settings_hash'] = $settingsHash;
        $invalidManifest['generator']['unsupported'] = true;
        $this->upload(
            $this->archiveBytes($invalidManifest, $this->fixtureJson('valid/content-pack.json')),
            'preparation-invalid-manifest-schema-0001',
        )
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'CONTENT_SCHEMA_INVALID')
            ->assertJsonPath('errors.0.pointer', '/generator');

        $manifest = $this->fixtureJson('valid/manifest.json');
        $manifest['preparation_request_id'] = $requestId;
        $manifest['settings_hash'] = $settingsHash;
        $invalidPack = $this->fixtureJson('valid/content-pack.json');
        $invalidPack['lessons'][0]['title']['de'] = 'Nicht erlaubt';
        $this->upload(
            $this->archiveBytes($manifest, $invalidPack),
            'preparation-invalid-pack-schema-0001',
        )
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'CONTENT_SCHEMA_INVALID')
            ->assertJsonPath('errors.0.pointer', '/lessons/0/title');

        $this->assertSame(2, DB::table('preparation_imports')->where('status', 'rejected')->count());
        $this->assertDatabaseCount('preparation_import_files', 0);
        $this->assertSame($curriculumCounts, $this->curriculumCounts());
    }

    private function grantContentRole(): void
    {
        DB::table('users')->where('id', LearningSliceSeeder::USER_ID)->update(['role' => 'content_team']);
    }

    /** @return TestResponse<Response> */
    private function createRequest(): TestResponse
    {
        return $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'preparation-create-for-import-0001')
            ->postJson('/v1/admin/preparation-requests', $this->requestPayload())
            ->assertCreated();
    }

    /** @return array<string, mixed> */
    private function requestPayload(): array
    {
        return [
            'schema_version' => '1.0.0',
            'settings' => $this->fixtureJson('valid/preparation-settings.json'),
        ];
    }

    /** @return array<string, mixed> */
    private function fixtureJson(string $relativePath): array
    {
        $path = base_path('../../tests/fixtures/content-pack/v1/'.$relativePath);
        $json = file_get_contents($path);
        $this->assertIsString($json);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function validArchiveBytes(string $requestId, string $settingsHash): string
    {
        $manifest = $this->fixtureJson('valid/manifest.json');
        $manifest['preparation_request_id'] = $requestId;
        $manifest['settings_hash'] = $settingsHash;

        return $this->archiveBytes($manifest, $this->fixtureJson('valid/content-pack.json'));
    }

    private function semanticInvalidArchiveBytes(string $requestId, string $settingsHash): string
    {
        $manifest = $this->fixtureJson('valid/manifest.json');
        $manifest['preparation_request_id'] = $requestId;
        $manifest['settings_hash'] = $settingsHash;
        $pack = $this->fixtureJson('valid/content-pack.json');
        $pack['questions'][0]['answer_contract']['correct_option_id'] = 'missing-option';

        return $this->archiveBytes($manifest, $pack);
    }

    private function hashMismatchArchiveBytes(string $requestId, string $settingsHash): string
    {
        $manifest = $this->fixtureJson('valid/manifest.json');
        $manifest['preparation_request_id'] = $requestId;
        $manifest['settings_hash'] = $settingsHash;
        $packJson = $this->encodeJson($this->fixtureJson('valid/content-pack.json'));
        $manifest['archive_limits'] = [
            'declared_uncompressed_bytes' => strlen($packJson),
            'declared_file_count' => 1,
        ];
        $manifest['files'] = [[
            'path' => 'content-pack.json',
            'media_type' => 'application/json',
            'sha256' => str_repeat('0', 64),
            'bytes' => strlen($packJson),
        ]];

        return $this->zipBytes([
            'manifest.json' => $this->encodeJson($manifest),
            'content-pack.json' => $packJson,
        ]);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $pack
     */
    private function archiveBytes(array $manifest, array $pack): string
    {
        $packJson = $this->encodeJson($pack);
        $manifest['archive_limits'] = [
            'declared_uncompressed_bytes' => strlen($packJson),
            'declared_file_count' => 1,
        ];
        $manifest['files'] = [[
            'path' => 'content-pack.json',
            'media_type' => 'application/json',
            'sha256' => hash('sha256', $packJson),
            'bytes' => strlen($packJson),
        ]];

        return $this->zipBytes([
            'manifest.json' => $this->encodeJson($manifest),
            'content-pack.json' => $packJson,
        ]);
    }

    private function unsafePathArchiveBytes(): string
    {
        return $this->zipBytes(['../escape.json' => '{}']);
    }

    private function symlinkArchiveBytes(): string
    {
        $path = $this->temporaryPath();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $this->assertTrue($zip->addFromString('link', 'target'));
        $this->assertTrue($zip->setExternalAttributesName('link', ZipArchive::OPSYS_UNIX, 0120777 << 16));
        $this->assertTrue($zip->close());

        return $this->readAndRemove($path);
    }

    private function compressionBombArchiveBytes(): string
    {
        return $this->zipBytes(['bomb.txt' => str_repeat('A', 200_000)]);
    }

    /** @param array<string, string> $entries */
    private function zipBytes(array $entries): string
    {
        $path = $this->temporaryPath();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($entries as $name => $contents) {
            $this->assertTrue($zip->addFromString($name, $contents));
        }
        $this->assertTrue($zip->close());

        return $this->readAndRemove($path);
    }

    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'modrik-content-');
        $this->assertIsString($path);

        return $path;
    }

    private function readAndRemove(string $path): string
    {
        $bytes = file_get_contents($path);
        $this->assertIsString($bytes);
        $this->assertTrue(unlink($path));

        return $bytes;
    }

    /** @return TestResponse<Response> */
    private function upload(string $archiveBytes, string $idempotencyKey): TestResponse
    {
        return $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post('/v1/admin/preparation-imports/validate', [
                'archive' => UploadedFile::fake()->createWithContent('returned-content.zip', $archiveBytes),
            ], ['Accept' => 'application/json']);
    }

    /** @return array<string, int> */
    private function curriculumCounts(): array
    {
        return [
            'tracks' => DB::table('academic_tracks')->count(),
            'nodes' => DB::table('curriculum_nodes')->count(),
            'lessons' => DB::table('lessons')->count(),
            'questions' => DB::table('questions')->count(),
            'quizzes' => DB::table('quizzes')->count(),
        ];
    }

    /** @throws JsonException */
    private function encodeJson(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
