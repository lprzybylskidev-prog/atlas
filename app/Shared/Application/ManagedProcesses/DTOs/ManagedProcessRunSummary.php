<?php

declare(strict_types=1);

namespace App\Shared\Application\ManagedProcesses\DTOs;

final readonly class ManagedProcessRunSummary
{
    public function __construct(
        public string $publicId,
        public string $status,
        public ?string $currentStage,
        public int $progressCurrent,
        public ?int $progressTotal,
        public ?string $progressLabel,
        public ?string $createdAt,
        public ?string $startedAt,
        public ?string $finishedAt,
    ) {}
}
