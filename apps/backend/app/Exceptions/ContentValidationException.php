<?php

namespace App\Exceptions;

use RuntimeException;

class ContentValidationException extends RuntimeException
{
    /**
     * @param  list<array{pointer: string, code: string, message: string}>  $errors
     * @param  null|array<string, mixed>  $manifest
     */
    public function __construct(
        public readonly array $errors,
        public readonly ?array $manifest = null,
    ) {
        parent::__construct($errors[0]['message'] ?? 'The returned content archive is invalid.');
    }
}
