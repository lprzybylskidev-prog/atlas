<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Public\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class AnalysisContextSnapshot
{
    /**
     * @param  list<string>  $roleKeys
     */
    public function __construct(
        public ?string $teamPublicId,
        public ?string $teamName,
        public array $roleKeys,
        public ?string $processPublicId,
        public ?string $processKey,
        public string $moduleKey,
        public DateTimeImmutable $capturedAt,
    ) {
        if (trim($moduleKey) === '') {
            throw new InvalidArgumentException('Analysis context snapshot module key must be a non-empty string.');
        }

        foreach ($roleKeys as $roleKey) {
            if (trim($roleKey) === '') {
                throw new InvalidArgumentException('Analysis context snapshot role keys must be non-empty strings.');
            }
        }
    }
}
