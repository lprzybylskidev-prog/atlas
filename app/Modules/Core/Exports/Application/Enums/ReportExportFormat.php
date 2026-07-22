<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Enums;

enum ReportExportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';
    case BrowserPrint = 'browser_print';
}
