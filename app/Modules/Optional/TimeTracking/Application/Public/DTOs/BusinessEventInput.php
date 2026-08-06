<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Public\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class BusinessEventInput
{
    /**
     * @param  array<string, bool|float|int|string|null>  $attributes
     * @param  array<string, float|int>  $metricInputs
     */
    public function __construct(
        public string $sourceEventId,
        public string $ownerModuleKey,
        public string $eventKey,
        public int $schemaVersion,
        public DateTimeImmutable $occurredAt,
        public ?string $userPublicId,
        public ?string $teamPublicId,
        public ?string $workSessionPublicId,
        public AnalysisContextSnapshot $contextSnapshot,
        public array $attributes = [],
        public array $metricInputs = [],
    ) {
        if (trim($sourceEventId) === '' || trim($ownerModuleKey) === '' || trim($eventKey) === '') {
            throw new InvalidArgumentException('Business event source, owner module, and event keys must be non-empty strings.');
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Business event schema version must be positive.');
        }

        foreach (array_keys($attributes) as $key) {
            if (trim($key) === '') {
                throw new InvalidArgumentException('Business event attribute keys must be non-empty strings.');
            }
        }

        foreach ($metricInputs as $key => $value) {
            if (trim($key) === '') {
                throw new InvalidArgumentException('Business event metric input keys must be non-empty strings.');
            }

            if (! is_finite((float) $value)) {
                throw new InvalidArgumentException('Business event metric inputs must be finite numbers.');
            }
        }
    }
}
