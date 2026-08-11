<?php

declare(strict_types=1);

namespace App\Shared\Application\Imports\DTOs;

final readonly class ImportExecutionRunSummary
{
    public function __construct(
        public int $processRunId,
        public string $publicId,
        public string $importKey,
        public string $sourceType,
        public ?string $idempotencyKey,
        public string $idempotencyState,
        public ?string $fileOriginalName,
    ) {}
}
