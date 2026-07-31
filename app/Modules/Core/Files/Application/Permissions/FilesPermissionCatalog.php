<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class FilesPermissionCatalog implements ModulePermissionContribution
{
    public const ADMIN_FILES_INDEX = 'admin.files.index';

    public const ADMIN_FILES_RESCAN = 'admin.files.rescan';

    public const ADMIN_FILES_ACKNOWLEDGE = 'admin.files.acknowledge';

    public const FILES_DOWNLOAD = 'files.download';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::ADMIN_FILES_INDEX, 'View private file metadata, scan states, queues, and infected files.'),
            new ModulePermissionDefinition(self::ADMIN_FILES_RESCAN, 'Request a safe malware rescan for blocked or clean files.'),
            new ModulePermissionDefinition(self::ADMIN_FILES_ACKNOWLEDGE, 'Mark problematic file scan states as handled after operational review.'),
            new ModulePermissionDefinition(self::FILES_DOWNLOAD, 'Download authorized clean private files.'),
        ];
    }
}
