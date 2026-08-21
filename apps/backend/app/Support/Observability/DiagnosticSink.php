<?php

namespace App\Support\Observability;

interface DiagnosticSink
{
    /** @param array<string, mixed> $event */
    public function write(array $event): void;
}
