<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StudentNotificationService
{
    private const ACTIONS = ['study', 'practice', 'progress', 'academic', 'account'];

    /**
     * @return array{items: list<array<string, mixed>>, unread_count: int}
     */
    public function inbox(User $user, int $limit = 100): array
    {
        $boundedLimit = max(1, min($limit, 100));
        $items = array_values(
            DB::table('student_notifications')
                ->where('user_id', (string) $user->getAuthIdentifier())
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit($boundedLimit)
                ->get(['id', 'kind', 'title', 'body', 'action', 'occurred_at', 'read_at'])
                ->map(fn (object $row): array => $this->serialize((array) $row))
                ->all(),
        );

        $unreadCount = DB::table('student_notifications')
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->whereNull('read_at')
            ->count();

        return [
            'items' => $items,
            'unread_count' => max(0, (int) $unreadCount),
        ];
    }

    /** @return array<string, mixed>|null */
    public function markRead(User $user, string $notificationId): ?array
    {
        $row = DB::transaction(function () use ($user, $notificationId): ?array {
            $query = DB::table('student_notifications')
                ->where('id', $notificationId)
                ->where('user_id', (string) $user->getAuthIdentifier());

            $record = $query->lockForUpdate()->first(['id', 'kind', 'title', 'body', 'action', 'occurred_at', 'read_at']);
            if ($record === null) {
                return null;
            }

            /** @var array<string, mixed> $payload */
            $payload = (array) $record;
            if (($payload['read_at'] ?? null) === null) {
                $readAt = now();
                $query->update([
                    'read_at' => $readAt,
                    'updated_at' => $readAt,
                ]);
                $payload['read_at'] = $readAt;
            }

            return $payload;
        });

        return $row === null ? null : $this->serialize($row);
    }

    public function markAllRead(User $user): int
    {
        $now = now();

        return DB::table('student_notifications')
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->whereNull('read_at')
            ->update([
                'read_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function serialize(array $row): array
    {
        $rawAction = $row['action'] ?? null;
        $action = is_string($rawAction) ? $rawAction : null;
        if ($action !== null && ! in_array($action, self::ACTIONS, true)) {
            $action = null;
        }

        $readAt = $row['read_at'] ?? null;

        return [
            'id' => (string) ($row['id'] ?? ''),
            'kind' => (string) ($row['kind'] ?? ''),
            'title' => $this->localizedMap((string) ($row['title'] ?? '{}')),
            'body' => $this->localizedMap((string) ($row['body'] ?? '{}')),
            'action' => $action,
            'occurred_at' => (string) ($row['occurred_at'] ?? ''),
            'read_at' => $readAt === null ? null : (string) $readAt,
            'is_read' => $readAt !== null,
        ];
    }

    /** @return array{ar: string, en: string, fr: string} */
    private function localizedMap(string $json): array
    {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            return ['ar' => '', 'en' => '', 'fr' => ''];
        }

        return [
            'ar' => is_string($decoded['ar'] ?? null) ? trim($decoded['ar']) : '',
            'en' => is_string($decoded['en'] ?? null) ? trim($decoded['en']) : '',
            'fr' => is_string($decoded['fr'] ?? null) ? trim($decoded['fr']) : '',
        ];
    }
}
