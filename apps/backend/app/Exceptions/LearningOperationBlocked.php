<?php

namespace App\Exceptions;

use RuntimeException;

final class LearningOperationBlocked extends RuntimeException
{
    public function __construct(
        public readonly string $operationCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
