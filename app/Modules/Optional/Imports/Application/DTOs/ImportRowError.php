<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Application\DTOs;

final readonly class ImportRowError
{
    /**
     * @param  array<string, scalar|null>  $safeContext
     */
    public function __construct(
        public ?int $rowNumber,
        public ?string $fieldName,
        public string $severity,
        public string $errorCode,
        public string $message,
        public array $safeContext = [],
    ) {}
}
