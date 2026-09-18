<?php

namespace App\Services;

use RuntimeException;

final class QuestionBankPromptSeed
{
    /**
     * @return array{
     *   id: string,
     *   version: string,
     *   compatible_schema: string,
     *   status: string,
     *   purpose: string,
     *   runtime_dependency: string,
     *   source: string,
     *   prompt: string,
     *   sample_output: string,
     *   history: array<int, array{version: string, status: string, source: string}>
     * }
     */
    public function entry(): array
    {
        return [
            'id' => 'MODRIK_QUESTION_BANK_MASTER_V1',
            'version' => '1.0.0',
            'compatible_schema' => 'modrik-question-bank-v1',
            'status' => 'active',
            'purpose' => 'Manual/offline preparation of source-grounded Question Bank packs for governed upload and review.',
            'runtime_dependency' => 'none',
            'source' => 'docs/learning/MODRIK_QUESTION_BANK_MASTER_V1.md',
            'prompt' => $this->resourceText('content-prompts/MODRIK_QUESTION_BANK_MASTER_V1.txt'),
            'sample_output' => $this->resourceText('content-prompts/MODRIK_QUESTION_BANK_MASTER_V1.sample.json'),
            'history' => [[
                'version' => '1.0.0',
                'status' => 'active',
                'source' => 'owner-authorized planning baseline #352',
            ]],
        ];
    }

    private function resourceText(string $relativePath): string
    {
        $contents = file_get_contents(resource_path($relativePath));
        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Prompt Library seed resource is unavailable.');
        }

        return $contents;
    }
}
