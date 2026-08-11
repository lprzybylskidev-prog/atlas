<?php

declare(strict_types=1);

namespace App\Shared\Application\Imports\DTOs;

final readonly class ImportExecutionDetail
{
    /**
     * @param  array<string, mixed>  $mappingSnapshot
     * @param  array<string, mixed>  $sourceMetadata
     * @param  array<string, mixed>  $statistics
     * @param  list<ImportRowErrorSummary>  $errors
     */
    public function __construct(
        public string $publicId,
        public string $importKey,
        public string $sourceType,
        public ?string $apiReference,
        public ?string $externalReference,
        public array $mappingSnapshot,
        public array $sourceMetadata,
        public array $statistics,
        public ?string $idempotencyKey,
        public string $idempotencyState,
        public array $errors,
    ) {}
}
