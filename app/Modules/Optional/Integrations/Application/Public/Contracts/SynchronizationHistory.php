<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Public\Contracts;

interface SynchronizationHistory
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function start(string $integrationKey, string $operation, string $correlationId, ?int $teamId = null, array $metadata = []): int;

    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function finish(int $runId, string $status, ?string $message = null, array $metadata = []): void;
}
