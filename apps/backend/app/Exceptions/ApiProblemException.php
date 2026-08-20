<?php

namespace App\Exceptions;

use RuntimeException;

class ApiProblemException extends RuntimeException
{
    /**
     * @param  list<array{pointer: string, code: string, message: string}>  $errors
     */
    public function __construct(
        public readonly int $status,
        public readonly string $problemCode,
        public readonly string $problemTitle,
        string $detail,
        public readonly bool $retryable = false,
        public readonly array $errors = [],
    ) {
        parent::__construct($detail);
    }
}
