<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Support;

use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;

final class AdminDataTableExportMeta
{
    /**
     * @return array{endpoint: string, formats: list<string>}
     */
    public static function defaults(?string $endpoint = null): array
    {
        return [
            'endpoint' => $endpoint ?? route('admin.exports.data-table'),
            'formats' => [
                ReportExportFormat::Csv->value,
                ReportExportFormat::Xlsx->value,
                ReportExportFormat::Pdf->value,
                ReportExportFormat::BrowserPrint->value,
            ],
        ];
    }
}
