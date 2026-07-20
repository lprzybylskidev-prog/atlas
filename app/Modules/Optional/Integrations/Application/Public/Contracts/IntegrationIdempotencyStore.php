<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Public\Contracts;

interface IntegrationIdempotencyStore
{
    public function begin(string $integrationKey, string $operation, string $idempotencyKey, string $requestHash, ?int $teamId = null): bool;

    /**
     * @param  array<string, scalar|null>  $responseSummary
     */
    public function complete(string $integrationKey, string $operation, string $idempotencyKey, bool $successful, array $responseSummary = []): void;
}
