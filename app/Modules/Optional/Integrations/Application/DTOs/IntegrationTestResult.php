<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\DTOs;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class IntegrationTestResult
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public string $integrationKey,
        public bool $successful,
        public string $message,
        public CarbonImmutable $testedAt,
        public ?int $durationMs = null,
        public array $metadata = [],
    ) {
        if (trim($integrationKey) === '' || trim($message) === '') {
            throw new InvalidArgumentException('Integration test result fields must be non-empty strings.');
        }
    }
}
