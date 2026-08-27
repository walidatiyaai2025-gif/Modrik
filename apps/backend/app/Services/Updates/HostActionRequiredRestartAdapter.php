<?php

namespace App\Services\Updates;

final class HostActionRequiredRestartAdapter implements WebRestartAdapter
{
    public function restart(string $releasePath): RestartResult
    {
        return RestartResult::requiresHostAction();
    }
}
