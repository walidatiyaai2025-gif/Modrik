<?php

namespace App\Services;

use DomainException;

final class OptionalAiBoundary
{
    /**
     * Build the only context shape an optional AI adapter may receive.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function prepareContext(array $context): array
    {
        if (! (bool) config('modrik.paid_ai.enabled', false)) {
            throw new DomainException('PAID_AI_DISABLED');
        }

        /** @var list<string> $allowedFields */
        $allowedFields = config('modrik.paid_ai.allowed_context_fields', []);
        $prepared = [];
        foreach ($allowedFields as $field) {
            $value = $context[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $prepared[$field] = $value;
            }
        }

        return $prepared;
    }
}
