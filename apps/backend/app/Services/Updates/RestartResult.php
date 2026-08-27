<?php

namespace App\Services\Updates;

final readonly class RestartResult
{
    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REQUIRES_HOST_ACTION = 'requires_host_action';

    public const STATUS_UNKNOWN = 'unknown';

    /** @param array<string,mixed> $details */
    public function __construct(
        public string $status,
        public string $message,
        public array $details = [],
    ) {}

    /** @param array<string,mixed> $details */
    public static function success(string $message = 'The host runtime restart and activation health checks succeeded.', array $details = []): self
    {
        return new self(self::STATUS_SUCCEEDED, $message, $details);
    }

    /** @param array<string,mixed> $details */
    public static function failed(string $message = 'The host runtime restart failed.', array $details = []): self
    {
        return new self(self::STATUS_FAILED, $message, $details);
    }

    /** @param array<string,mixed> $details */
    public static function requiresHostAction(string $message = 'The host runtime restart was not confirmed.', array $details = []): self
    {
        return new self(self::STATUS_REQUIRES_HOST_ACTION, $message, $details);
    }

    /** @param array<string,mixed> $details */
    public static function unknown(string $message = 'The host runtime restart outcome is unknown.', array $details = []): self
    {
        return new self(self::STATUS_UNKNOWN, $message, $details);
    }

    public function successful(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }
}
