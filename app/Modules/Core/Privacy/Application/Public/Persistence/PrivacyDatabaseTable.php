<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class PrivacyDatabaseTable
{
    public const OPERATION_REQUESTS = DatabaseSchema::CORE_PRIVACY.'.operation_requests';

    public const OPERATION_PREVIEWS = DatabaseSchema::CORE_PRIVACY.'.operation_previews';

    public const LEGAL_HOLDS = DatabaseSchema::CORE_PRIVACY.'.legal_holds';
}
