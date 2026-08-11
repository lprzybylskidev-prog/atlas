<?php

declare(strict_types=1);

namespace App\Shared\Application\Exports;

final class ExportPermissions
{
    public const REQUEST = 'exports.request';

    public const DOWNLOAD = 'exports.download';

    public const PRINT = 'exports.print';

    public const AUDIT_EXPORT = 'exports.audit-export';

    public const DATA_TABLE = 'exports.data-table';

    public const ADMIN_INDEX = 'admin.exports.index';

    public const ADMIN_DATA_TABLE = 'admin.exports.data-table';
}
