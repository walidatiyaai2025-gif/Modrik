<?php

namespace Tests\Support;

use App\Services\Updates\BackendReleaseOperator;

final class RecordingBackendReleaseOperator implements BackendReleaseOperator
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(private bool $migrationResult = true) {}

    public function prepareSharedState(string $releasePath, string $sharedPath): void {}

    public function migrate(string $releasePath): bool
    {
        $this->calls[] = 'migrate';

        return $this->migrationResult;
    }

    public function rebuildCaches(string $releasePath): bool
    {
        $this->calls[] = 'cache';

        return true;
    }
}
