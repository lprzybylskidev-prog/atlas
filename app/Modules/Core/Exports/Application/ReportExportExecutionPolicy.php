<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use Illuminate\Support\Facades\Config;

final class ReportExportExecutionPolicy
{
    public function canRunSynchronously(ReportExportRequestSnapshot $snapshot): bool
    {
        if (! $snapshot->synchronousAllowed || $snapshot->estimatedRowCount === null || $snapshot->estimatedRowCount < 0) {
            return false;
        }

        if ($snapshot->format === ReportExportFormat::BrowserPrint) {
            return true;
        }

        if ($snapshot->format === ReportExportFormat::Pdf && ! Config::boolean('atlas.exports.synchronous_pdf_enabled', false)) {
            return false;
        }

        $maxRows = $snapshot->format === ReportExportFormat::Pdf
            ? Config::integer('atlas.exports.synchronous_pdf_max_rows', 0)
            : Config::integer('atlas.exports.synchronous_export_max_rows', 1000);
        $maxCells = $snapshot->format === ReportExportFormat::Pdf
            ? Config::integer('atlas.exports.synchronous_pdf_max_cells', 0)
            : Config::integer('atlas.exports.synchronous_export_max_cells', 10000);

        if ($maxRows < 1 || $maxCells < 1) {
            return false;
        }

        $visibleColumnCount = max(1, count($snapshot->visibleColumns));

        return $snapshot->estimatedRowCount <= $maxRows
            && ($snapshot->estimatedRowCount * $visibleColumnCount) <= $maxCells;
    }
}
