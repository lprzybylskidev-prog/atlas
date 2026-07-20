<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\DTOs;

use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessLogSeverity;

final readonly class ProcessLogEntry
{
    /**
     * @param  array<string, scalar|null>|null  $safeContext
     */
    public function __construct(
        public ProcessLogSeverity $severity,
        public string $eventType,
        public string $message,
        public ?string $stage = null,
        public ?array $safeContext = null,
        public ?int $rowNumber = null,
        public ?string $entityPublicId = null,
        public ?string $externalReference = null,
        public ?string $sourceReference = null,
        public ?string $errorCode = null,
        public ?string $exceptionClass = null,
        public ?bool $retryable = null,
    ) {}
}
