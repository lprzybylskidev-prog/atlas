<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\DTOs;

use InvalidArgumentException;

final readonly class IntegrationExecutionResult
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public bool $successful,
        public int $attempts,
        public string $correlationId,
        public ?string $idempotencyKey = null,
        public ?string $errorMessage = null,
        public array $metadata = [],
    ) {
        if ($attempts < 1 || trim($correlationId) === '') {
            throw new InvalidArgumentException('Integration execution result requires attempts and correlation ID.');
        }
    }
}
