<?php

namespace App\Services\Updates;

final readonly class RestartResult
{
    public function __construct(public string $status, public string $message) {}

    public static function requiresHostAction(): self
    {
        return new self('requires_host_action', 'The host runtime restart was not confirmed.');
    }

    public function successful(): bool
    {
        return $this->status === 'succeeded';
    }
}
