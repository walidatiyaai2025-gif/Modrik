<?php

namespace App\Services\Updates;

final readonly class UpdateExecutionResult
{
    public const SUCCESS = 'SUCCESS';

    public const FAILED = 'FAILED';

    public const ROLLED_BACK = 'ROLLED_BACK';

    public const PARTIAL_REQUIRES_OPERATOR = 'PARTIAL_REQUIRES_OPERATOR';

    public const REQUIRES_HOST_ACTION = 'REQUIRES_HOST_ACTION';

    /** @param array<string, bool|string|null> $details */
    public function __construct(
        public string $status,
        public string $releaseId,
        public ?string $previousRelease,
        public array $details = [],
    ) {}

    /** @return array{status:string,release_id:string,previous_release:?string,details:array<string,bool|string|null>} */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'release_id' => $this->releaseId,
            'previous_release' => $this->previousRelease,
            'details' => $this->details,
        ];
    }
}
