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
        $items = DB::table('student_notifications')
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($boundedLimit)
            ->get(['id', 'kind', 'title', 'body', 'action', 'occurred_at', 'read_at'])
            ->map(fn (object $row): array => $this->serialize($row))
            ->all();

        $unreadCount = DB::table('student_notifications')
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->whereNull('read_at')
            ->count();

        return [
            'items' => $items,
            'unread_count' => $unreadCount,
        ];
    }

    /** @return array<string, mixed>|null */
    public function markRead(User $user, string $notificationId): ?array
    {
        $row = DB::transaction(function () use ($user, $notificationId): ?object {
            $query = DB::table('student_notifications')
                ->where('id', $notificationId)
                ->where('user_id', (string) $user->getAuthIdentifier());

            $row = $query->lockForUpdate()->first(['id', 'kind', 'title', 'body', 'action', 'occurred_at', 'read_at']);
            if ($row === null) {
                return null;
            }

            if ($row->read_at === null) {
                $readAt = now();
                $query->update([
                    'read_at' => $readAt,
                    'updated_at' => $readAt,
                ]);
                $row->read_at = $readAt;
            }

            return $row;
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

    /** @return array<string, mixed> */
    private function serialize(object $row): array
    {
        $action = $row->action === null ? null : (string) $row->action;
        if ($action !== null && ! in_array($action, self::ACTIONS, true)) {
            $action = null;
        }

        return [
            'id' => (string) $row->id,
            'kind' => (string) $row->kind,
            'title' => $this->localizedMap((string) $row->title),
            'body' => $this->localizedMap((string) $row->body),
            'action' => $action,
            'occurred_at' => (string) $row->occurred_at,
            'read_at' => $row->read_at === null ? null : (string) $row->read_at,
            'is_read' => $row->read_at !== null,
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
