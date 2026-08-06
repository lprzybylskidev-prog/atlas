<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class ImportsDatabaseTable
{
    public const EXECUTIONS = DatabaseSchema::OPTIONAL_IMPORTS.'.import_executions';

    public const ROW_ERRORS = DatabaseSchema::OPTIONAL_IMPORTS.'.import_row_errors';

    public const IDEMPOTENCY_KEYS = DatabaseSchema::OPTIONAL_IMPORTS.'.import_idempotency_keys';
}
