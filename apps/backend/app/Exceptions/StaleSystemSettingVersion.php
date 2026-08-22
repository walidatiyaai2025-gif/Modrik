<?php

namespace App\Exceptions;

use RuntimeException;

final class StaleSystemSettingVersion extends RuntimeException
{
    public function __construct(
        public readonly string $settingKey,
        public readonly int $expectedVersion,
        public readonly int $currentVersion,
    ) {
        parent::__construct(sprintf(
            'System setting "%s" changed from expected version %d to version %d.',
            $settingKey,
            $expectedVersion,
            $currentVersion,
        ));
    }
}
