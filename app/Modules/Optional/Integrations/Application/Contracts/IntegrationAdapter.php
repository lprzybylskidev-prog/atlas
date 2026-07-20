<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Contracts;

use App\Modules\Optional\Integrations\Application\DTOs\IntegrationDefinition;
use App\Modules\Optional\Integrations\Application\DTOs\IntegrationTestResult;

interface IntegrationAdapter
{
    public function definition(): IntegrationDefinition;

    public function testConnection(string $correlationId): IntegrationTestResult;
}
