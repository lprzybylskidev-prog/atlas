<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class IntegrationsDatabaseTable
{
    public const CONNECTIONS = DatabaseSchema::OPTIONAL_INTEGRATIONS.'.integration_connections';

    public const CREDENTIALS = DatabaseSchema::OPTIONAL_INTEGRATIONS.'.integration_credentials';

    public const EXTERNAL_ID_MAPPINGS = DatabaseSchema::OPTIONAL_INTEGRATIONS.'.external_id_mappings';

    public const SYNC_RUNS = DatabaseSchema::OPTIONAL_INTEGRATIONS.'.synchronization_runs';

    public const IDEMPOTENCY_KEYS = DatabaseSchema::OPTIONAL_INTEGRATIONS.'.idempotency_keys';

    public const CIRCUIT_BREAKERS = DatabaseSchema::OPTIONAL_INTEGRATIONS.'.circuit_breakers';
}
