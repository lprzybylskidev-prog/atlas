<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Contracts;

use App\Modules\Optional\Integrations\Application\DTOs\IntegrationDefinition;

interface IntegrationRegistry
{
    /**
     * @return list<IntegrationDefinition>
     */
    public function all(): array;

    public function get(string $integrationKey): ?IntegrationAdapter;
}
