<?php

namespace App\Services\Updates;

interface BackendReleaseOperator
{
    public function prepareSharedState(string $releasePath, string $sharedPath): void;

    public function migrate(string $releasePath): bool;

    public function rebuildCaches(string $releasePath): bool;
}
