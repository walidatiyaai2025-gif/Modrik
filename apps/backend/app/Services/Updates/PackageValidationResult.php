<?php

namespace App\Services\Updates;

final readonly class PackageValidationResult
{
    /**
     * @param  list<array{code:string,message:string,path?:string}>  $errors
     * @param  array<string, mixed>|null  $manifest
     */
    public function __construct(
        public bool $valid,
        public array $errors,
        public ?array $manifest = null,
    ) {}

    /** @return array{valid:bool,errors:list<array{code:string,message:string,path?:string}>,manifest:array<string, mixed>|null} */
    public function toArray(): array
    {
        return ['valid' => $this->valid, 'errors' => $this->errors, 'manifest' => $this->manifest];
    }
}
