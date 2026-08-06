<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class FilesDatabaseTable
{
    public const FILE_OBJECTS = DatabaseSchema::CORE_FILES.'.file_objects';

    public const FILE_SCAN_EVIDENCE = DatabaseSchema::CORE_FILES.'.file_scan_evidence';
}
