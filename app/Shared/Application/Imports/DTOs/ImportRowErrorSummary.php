<?php

declare(strict_types=1);

namespace App\Shared\Application\Imports\DTOs;

final readonly class ImportRowErrorSummary
{
    /**
     * @param  array<string, mixed>  $safeContext
     */
    public function __construct(
        public string $publicId,
        public string $runPublicId,
        public string $importPublicId,
        public ?int $rowNumber,
        public ?string $fieldName,
        public string $severity,
        public string $errorCode,
        public string $message,
        public array $safeContext = [],
    ) {}
}
