<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;

final class AssessmentEngine
{
    public const ALGORITHM = 'modrik-fy-v1';

    public const SELECTION_ALGORITHM = 'modrik-blueprint-v1';

    public const OPTION_ORDERING_ALGORITHM = 'modrik-option-fy-v1';

    /**
     * @param  list<array<string, mixed>>  $questions
     * @param  array<string, mixed>|null  $blueprint
     * @param  list<string>  $previousQuestionIds
     * @return array{questions: list<array<string, mixed>>, question_order_policy: string, selection_varied: bool}
     */
    public function buildPlan(array $questions, ?array $blueprint, string $seed, array $previousQuestionIds = []): array
    {
        if (strlen($seed) !== 32) {
            throw new ApiProblemException(500, 'ATTEMPT_SEED_INVALID', 'Attempt cannot be created', 'The server-generated assessment seed is invalid.');
        }

        $policy = $this->questionOrderPolicy($blueprint);
        [$selected, $selectionCanVary] = $this->selectQuestions($questions, $blueprint, $seed);
        $selectionVaried = $selectionCanVary
            && $previousQuestionIds !== []
            && ! $this->sameSet($this->ids($selected), $previousQuestionIds);

        if ($selectionCanVary && $previousQuestionIds !== [] && $this->sameSet($this->ids($selected), $previousQuestionIds)) {
            $selected = $this->forceAlternateSelection($questions, $selected, $blueprint, $seed);
            $selectionVaried = ! $this->sameSet($this->ids($selected), $previousQuestionIds);
        }

        if ($policy === 'fixed') {
            usort($selected, static fn (array $left, array $right): int => ((int) $left['source_position']) <=> ((int) $right['source_position']));
        } else {
            $sourceSelected = $selected;
            usort($sourceSelected, static fn (array $left, array $right): int => ((int) $left['source_position']) <=> ((int) $right['source_position']));
            $selected = $this->shuffle($selected, $seed, 'question-order');

            if (count($selected) > 1 && $this->ids($selected) === $this->ids($sourceSelected)) {
                $selected = $this->rotate($selected, $seed, 'avoid-source-order');
            }

            if (count($selected) > 1 && $previousQuestionIds !== [] && $this->ids($selected) === $previousQuestionIds) {
                $selected = $this->rotate($selected, $seed, 'avoid-previous-order');
            }
        }

        return [
            'questions' => array_values($selected),
            'question_order_policy' => $policy,
            'selection_varied' => $selectionVaried,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @param  array<string, mixed>  $metadata
     * @return list<array<string, mixed>>
     */
    public function orderOptions(array $options, bool $explicitlySafe, array $metadata, string $seed, string $questionId): array
    {
        if (! $explicitlySafe || count($options) < 2 || $this->hasUnsafeOptionSemantics($options, $metadata)) {
            return $options;
        }

        $ordered = $this->shuffle($options, $seed, 'options:'.$questionId);
        if ($this->optionIds($ordered) === $this->optionIds($options)) {
            $ordered = $this->rotate($ordered, $seed, 'avoid-source-options:'.$questionId);
        }

        return array_values($ordered);
    }

    /**
     * @param  array<string, mixed>|null  $blueprint
     */
    private function questionOrderPolicy(?array $blueprint): string
    {
        $policy = $blueprint['question_order'] ?? 'shuffle';
        if (! is_string($policy) || ! in_array($policy, ['shuffle', 'fixed'], true)) {
            throw $this->invalidBlueprint('question_order must be shuffle or fixed.');
        }

        return $policy;
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @param  array<string, mixed>|null  $blueprint
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private function selectQuestions(array $questions, ?array $blueprint, string $seed): array
    {
        $slots = $blueprint['slots'] ?? null;
        if ($slots === null) {
            return [$questions, false];
        }
        if (! is_array($slots) || $slots === []) {
            throw $this->invalidBlueprint('slots must be a non-empty array when supplied.');
        }

        $selected = [];
        $selectedIds = [];
        $canVary = false;

        foreach (array_values($slots) as $slotIndex => $slot) {
            if (! is_array($slot)) {
                throw $this->invalidBlueprint('Each blueprint slot must be an object.');
            }
            $count = $slot['count'] ?? null;
            if (! is_int($count) || $count < 1) {
                throw $this->invalidBlueprint('Each blueprint slot count must be a positive integer.');
            }

            $candidates = array_values(array_filter(
                $questions,
                fn (array $question): bool => ! isset($selectedIds[(string) $question['id']]) && $this->matchesSlot($question, $slot),
            ));
            if (count($candidates) < $count) {
                throw new ApiProblemException(
                    409,
                    'ASSESSMENT_BLUEPRINT_UNSATISFIABLE',
                    'Assessment blueprint cannot be satisfied',
                    'The published question bank cannot satisfy the locked assessment blueprint.',
                );
            }

            if (count($candidates) > $count) {
                $canVary = true;
            }
            $candidates = $this->shuffle($candidates, $seed, 'selection-slot:'.$slotIndex);
            foreach (array_slice($candidates, 0, $count) as $candidate) {
                $candidate['_slot_index'] = $slotIndex;
                $selected[] = $candidate;
                $selectedIds[(string) $candidate['id']] = true;
            }
        }

        return [$selected, $canVary];
    }

    /**
     * @param  list<array<string, mixed>>  $allQuestions
     * @param  list<array<string, mixed>>  $selected
     * @param  array<string, mixed>|null  $blueprint
     * @return list<array<string, mixed>>
     */
    private function forceAlternateSelection(array $allQuestions, array $selected, ?array $blueprint, string $seed): array
    {
        $slots = $blueprint['slots'] ?? null;
        if (! is_array($slots)) {
            return $selected;
        }

        $selectedIds = array_fill_keys($this->ids($selected), true);
        foreach (array_values($slots) as $slotIndex => $slot) {
            if (! is_array($slot)) {
                continue;
            }
            $alternatives = array_values(array_filter(
                $allQuestions,
                fn (array $question): bool => ! isset($selectedIds[(string) $question['id']]) && $this->matchesSlot($question, $slot),
            ));
            if ($alternatives === []) {
                continue;
            }

            $alternatives = $this->shuffle($alternatives, $seed, 'alternate-slot:'.$slotIndex);
            foreach ($selected as $index => $question) {
                if (($question['_slot_index'] ?? null) === $slotIndex) {
                    $replacement = $alternatives[0];
                    $replacement['_slot_index'] = $slotIndex;
                    $selected[$index] = $replacement;
                    return array_values($selected);
                }
            }
        }

        return $selected;
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $slot
     */
    private function matchesSlot(array $question, array $slot): bool
    {
        $metadata = $question['metadata'] ?? [];
        if (! is_array($metadata)) {
            $metadata = [];
        }

        foreach (['section', 'difficulty'] as $key) {
            if (array_key_exists($key, $slot)) {
                if (! is_string($slot[$key]) || ($metadata[$key] ?? null) !== $slot[$key]) {
                    return false;
                }
            }
        }

        if (array_key_exists('marks', $slot)) {
            if (! is_int($slot['marks']) && ! is_float($slot['marks'])) {
                throw $this->invalidBlueprint('Slot marks must be numeric.');
            }
            if (abs(((float) $question['maximum_score']) - (float) $slot['marks']) > 0.0001) {
                return false;
            }
        }

        if (array_key_exists('coverage', $slot)) {
            if (! is_array($slot['coverage'])) {
                throw $this->invalidBlueprint('Slot coverage must be an array of concept identifiers.');
            }
            $concepts = $metadata['concepts'] ?? [];
            if (! is_array($concepts)) {
                return false;
            }
            foreach ($slot['coverage'] as $concept) {
                if (! is_string($concept) || ! in_array($concept, $concepts, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @param  array<string, mixed>  $metadata
     */
    private function hasUnsafeOptionSemantics(array $options, array $metadata): bool
    {
        $semantics = $metadata['option_order_semantics'] ?? null;
        if (is_string($semantics) && in_array($semantics, ['fixed', 'sequence', 'ordered', 'image_letter', 'all_none'], true)) {
            return true;
        }
        foreach (['sequence_sensitive', 'image_letter_mapping', 'all_none_semantics'] as $flag) {
            if (($metadata[$flag] ?? false) === true) {
                return true;
            }
        }

        $phrases = ['all of the above', 'none of the above', 'all of these', 'none of these', 'كل ما سبق', 'جميع ما سبق', 'لا شيء مما سبق', 'toutes les réponses', 'aucune de ces réponses', 'aucune des réponses'];
        foreach ($options as $option) {
            $label = $option['label'] ?? null;
            if (! is_array($label)) {
                continue;
            }
            foreach ($label as $text) {
                if (! is_string($text)) {
                    continue;
                }
                $normalized = mb_strtolower($text);
                foreach ($phrases as $phrase) {
                    if (str_contains($normalized, mb_strtolower($phrase))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @template T of array<string, mixed>
     * @param  list<T>  $items
     * @return list<T>
     */
    private function shuffle(array $items, string $seed, string $domain): array
    {
        $ordered = array_values($items);
        $counter = 0;
        for ($index = count($ordered) - 1; $index > 0; $index--) {
            $random = $this->randomInt($seed, $domain, $counter++);
            $swapIndex = $random % ($index + 1);
            [$ordered[$index], $ordered[$swapIndex]] = [$ordered[$swapIndex], $ordered[$index]];
        }

        return $ordered;
    }

    /**
     * @template T of array<string, mixed>
     * @param  list<T>  $items
     * @return list<T>
     */
    private function rotate(array $items, string $seed, string $domain): array
    {
        $count = count($items);
        if ($count < 2) {
            return $items;
        }
        $rotation = ($this->randomInt($seed, $domain, 0) % ($count - 1)) + 1;

        return array_values([...array_slice($items, $rotation), ...array_slice($items, 0, $rotation)]);
    }

    private function randomInt(string $seed, string $domain, int $counter): int
    {
        $bytes = hash_hmac('sha256', $domain."\0".pack('N', $counter), $seed, true);
        $unpacked = unpack('Nvalue', substr($bytes, 0, 4));

        return is_array($unpacked) ? (int) $unpacked['value'] : 0;
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return list<string>
     */
    private function ids(array $questions): array
    {
        return array_values(array_map(static fn (array $question): string => (string) $question['id'], $questions));
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<string>
     */
    private function optionIds(array $options): array
    {
        return array_values(array_map(static fn (array $option): string => (string) ($option['id'] ?? ''), $options));
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     */
    private function sameSet(array $left, array $right): bool
    {
        sort($left);
        sort($right);

        return $left === $right;
    }

    private function invalidBlueprint(string $detail): ApiProblemException
    {
        return new ApiProblemException(409, 'ASSESSMENT_BLUEPRINT_INVALID', 'Assessment blueprint is invalid', $detail);
    }
}
