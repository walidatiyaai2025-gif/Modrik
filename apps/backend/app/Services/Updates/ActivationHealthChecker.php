<?php

namespace App\Services\Updates;

interface ActivationHealthChecker
{
    public function healthy(string $releasePath, string $expectedReleaseSha): bool;
}
