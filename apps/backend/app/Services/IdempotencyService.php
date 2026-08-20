<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class IdempotencyService
{
    /**
     * @param  callable(): array{status: int, body: array<string, mixed>, headers?: array<string, string>}  $command
     *
     * @throws JsonException
     */
    public function execute(Request $request, string $operation, callable $command): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || preg_match('/^[\x21-\x7E]{16,128}$/D', $key) !== 1) {
            throw new ApiProblemException(
                status: 400,
                problemCode: 'IDEMPOTENCY_KEY_REQUIRED',
                problemTitle: 'Valid idempotency key required',
                detail: 'Idempotency-Key must contain 16–128 visible ASCII characters.',
            );
        }

        $user = $request->user();
        if (! $user instanceof User) {
            throw new ApiProblemException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required', 'A valid session is required.');
        }

        $secret = (string) config('modrik.idempotency.secret');
        if ($secret === '') {
            throw new ApiProblemException(500, 'IDEMPOTENCY_CONFIGURATION_INVALID', 'Idempotency unavailable', 'The idempotency digest is not configured.', true);
        }

        $keyDigest = hash_hmac('sha256', $key, $secret);
        $requestHash = $this->requestHash($request);

        return DB::transaction(function () use ($user, $operation, $keyDigest, $requestHash, $command): JsonResponse {
            $record = DB::table('idempotency_keys')
                ->where('actor_id', $user->getKey())
                ->where('operation', $operation)
                ->where('key_digest', $keyDigest)
                ->lockForUpdate()
                ->first();

            if ($record !== null) {
                /** @var array<string, mixed> $stored */
                $stored = (array) $record;
                if ($stored['request_hash'] !== $requestHash) {
                    throw new ApiProblemException(409, 'IDEMPOTENCY_KEY_REUSED', 'Idempotency key reused', 'The idempotency key was already used for a different request.');
                }

                if ($stored['state'] !== 'completed' || ! is_string($stored['response_body'])) {
                    throw new ApiProblemException(409, 'IDEMPOTENCY_REQUEST_IN_PROGRESS', 'Request already in progress', 'The original request is still being processed.', true);
                }

                /** @var array<string, mixed> $body */
                $body = json_decode($stored['response_body'], true, flags: JSON_THROW_ON_ERROR);

                $headers = ['Idempotency-Replayed' => 'true'];
                if (isset($body['type'], $body['code'], $body['status'])) {
                    $headers['Content-Type'] = 'application/problem+json';
                }

                return response()->json($body, (int) $stored['response_status'], $headers);
            }

            $id = (string) Str::ulid();
            DB::table('idempotency_keys')->insert([
                'id' => $id,
                'actor_id' => $user->getKey(),
                'operation' => $operation,
                'key_digest' => $keyDigest,
                'request_hash' => $requestHash,
                'state' => 'in_progress',
                'expires_at' => now()->addHours((int) config('modrik.idempotency.retention_hours', 24)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $result = $command();
            DB::table('idempotency_keys')->where('id', $id)->update([
                'state' => 'completed',
                'response_status' => $result['status'],
                'response_body' => json_encode($result['body'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(
                $result['body'],
                $result['status'],
                ['Idempotency-Replayed' => 'false', ...($result['headers'] ?? [])],
            );
        }, 3);
    }

    /**
     * @throws JsonException
     */
    private function requestHash(Request $request): string
    {
        $canonical = [
            'method' => strtoupper($request->method()),
            'path' => '/'.$request->path(),
            'media_type' => strtolower(trim(explode(';', (string) $request->header('Content-Type', 'application/json'))[0])),
            'body' => $request->isJson()
                ? $this->canonicalize($request->json()->all())
                : $this->canonicalize($request->request->all()),
            'files' => $this->canonicalFiles($request->allFiles()),
        ];

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }

    /**
     * @param  array<string, UploadedFile|array<array-key, UploadedFile>>  $files
     * @return array<string, mixed>
     */
    private function canonicalFiles(array $files): array
    {
        $canonical = [];
        foreach ($files as $field => $file) {
            if (is_array($file)) {
                $canonical[$field] = $this->canonicalFiles(array_combine(array_map('strval', array_keys($file)), array_values($file)) ?: []);

                continue;
            }

            $path = $file->getRealPath();
            if ($path === false) {
                throw new ApiProblemException(400, 'UPLOAD_UNREADABLE', 'Upload unreadable', 'The uploaded file could not be read.');
            }
            $canonical[$field] = [
                'bytes' => $file->getSize(),
                'sha256' => hash_file('sha256', $path),
            ];
        }
        ksort($canonical, SORT_STRING);

        return $canonical;
    }
}
