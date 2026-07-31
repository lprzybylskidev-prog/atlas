<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\DTOs;

final readonly class PrivacyExecutionResult
{
    /**
     * @param  list<array{step: string, affectedRecords: int, idempotent: bool}>  $steps
     * @param  list<array{code: string, message: string}>  $blockers
     */
    public function __construct(
        public string $publicId,
        public string $status,
        public array $steps,
        public array $blockers,
        public int $affectedRecords,
        public bool $completed,
    ) {}
}
