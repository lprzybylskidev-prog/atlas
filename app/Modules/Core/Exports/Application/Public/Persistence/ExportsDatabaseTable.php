<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class ExportsDatabaseTable
{
    public const REPORT_EXPORT_REQUESTS = DatabaseSchema::CORE_EXPORTS.'.export_requests';

    public const REPORT_EXPORT_ARTIFACTS = DatabaseSchema::CORE_EXPORTS.'.export_artifacts';

    public const REPORT_RENDER_CREDENTIALS = DatabaseSchema::CORE_EXPORTS.'.render_credentials';
}
