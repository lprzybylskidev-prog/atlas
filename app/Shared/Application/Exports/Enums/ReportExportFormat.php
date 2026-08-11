<?php

declare(strict_types=1);

namespace App\Shared\Application\Exports\Enums;

enum ReportExportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';
    case BrowserPrint = 'browser_print';
}
