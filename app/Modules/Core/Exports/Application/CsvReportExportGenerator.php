<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Contracts\ReportExportGenerator;
use App\Modules\Core\Exports\Application\DTOs\GeneratedReportArtifact;
use App\Modules\Core\Exports\Application\DTOs\ReportExportColumn;
use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class CsvReportExportGenerator implements ReportExportGenerator
{
    public function __construct(private TabularReportExportData $table) {}

    public function supports(ReportExportFormat $format): bool
    {
        return $format === ReportExportFormat::Csv;
    }

    public function generate(ReportExportGenerationRequest $request): GeneratedReportArtifact
    {
        $columns = $this->table->columns($request);
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('CSV export stream could not be opened.');
        }

        fputcsv($stream, array_map(static fn (ReportExportColumn $column): string => $column->label, $columns));

        $rowCount = 0;

        foreach ($this->table->rows($request, $columns) as $row) {
            fputcsv($stream, $row);
            $rowCount++;
        }

        fputcsv($stream, []);

        foreach ($this->table->totals($rowCount) as $total) {
            fputcsv($stream, [$total->label, $total->value]);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($contents)) {
            throw new RuntimeException('CSV export stream could not be read.');
        }

        return new GeneratedReportArtifact(
            filename: sprintf('%s-%s.csv', Str::slug($request->reportName), now('UTC')->format('Ymd-His')),
            contentType: 'text/csv; charset=UTF-8',
            contents: $contents,
        );
    }
}
