<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Support;

use App\Shared\Application\Exports\Enums\ReportExportFormat;

final class AdminDataTableExportMeta
{
    /**
     * @return array{endpoint: string, formats: list<string>, detailedAudit?: bool}
     */
    public static function defaults(?string $endpoint = null, bool $detailedAudit = false): array
    {
        $meta = [
            'endpoint' => $endpoint ?? route('admin.exports.data-table'),
            'formats' => [
                ReportExportFormat::Csv->value,
                ReportExportFormat::Xlsx->value,
                ReportExportFormat::Pdf->value,
                ReportExportFormat::BrowserPrint->value,
            ],
        ];

        if ($detailedAudit) {
            $meta['detailedAudit'] = true;
        }

        return $meta;
    }
}
