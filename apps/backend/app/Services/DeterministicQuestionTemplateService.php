<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;

final class DeterministicQuestionTemplateService
{
    /**
     * @param  array<string, mixed>  $contract
     * @param  array<string, mixed>  $prompt
     * @return array{prompt: array<string, mixed>, grading_contract: array<string, mixed>, instance: array<string, int|string>}
     */
    public function materialize(array $contract, array $prompt, string $seed, string $questionId): array
    {
        if (($contract['kind'] ?? null) !== 'arithmetic_v1') {
            throw $this->invalid('Only arithmetic_v1 templates are currently supported.');
        }

        $operator = $contract['operator'] ?? null;
        if (!is_string($operator) || !in_array($operator, ['add', 'subtract', 'multiply'], true)) {
            throw $this->invalid('Template operator must be add, subtract, or multiply.');
        }

        [$leftMin, $leftMax] = $this->range($contract['left'] ?? null, 'left');
        [$rightMin, $rightMax] = $this->range($contract['right'] ?? null, 'right');
        $left = $this->pick($leftMin, $leftMax, $seed, $questionId.':left');
        $right = $this->pick($rightMin, $rightMax, $seed, $questionId.':right');

        $answer = match ($operator) {
            'add' => $left + $right,
            'subtract' => $left - $right,
            'multiply' => $left * $right,
        };

        $rendered = [];
        foreach ($prompt as $locale => $text) {
            if (!is_string($locale) || !is_string($text)) {
                throw $this->invalid('Template prompt entries must be localized strings.');
            }
            $rendered[$locale] = strtr($text, [
                '{{left}}' => (string) $left,
                '{{right}}' => (string) $right,
            ]);
        }

        return [
            'prompt' => $rendered,
            'grading_contract' => ['value' => $answer, 'tolerance' => 0],
            'instance' => ['kind' => 'arithmetic_v1', 'operator' => $operator, 'left' => $left, 'right' => $right],
        ];
    }

    /** @return array{0: int, 1: int} */
    private function range(mixed $value, string $name): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw $this->invalid("Template {$name} range must be an object.");
        }
        $min = $value['min'] ?? null;
        $max = $value['max'] ?? null;
        if (!is_int($min) || !is_int($max) || $min < -10000 || $max > 10000 || $min > $max) {
            throw $this->invalid("Template {$name} range is invalid.");
        }

        return [$min, $max];
    }

    private function pick(int $min, int $max, string $seed, string $domain): int
    {
        $span = $max - $min + 1;
        $bytes = hash_hmac('sha256', $domain, $seed, true);
        $raw = unpack('Nvalue', substr($bytes, 0, 4));

        return $min + ((int) ($raw['value'] ?? 0) % $span);
    }

    private function invalid(string $detail): ApiProblemException
    {
        return new ApiProblemException(
            409,
            'QUESTION_TEMPLATE_INVALID',
            'Question template is invalid',
            $detail,
        );
    }
}
