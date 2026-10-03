<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Contracts\ReportExportGenerator;
use App\Modules\Core\Exports\Application\DTOs\GeneratedReportArtifact;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\Enums\ReportExportFormat;
use Illuminate\Support\Str;

final readonly class JsonReportExportGenerator implements ReportExportGenerator
{
    public function __construct(private TabularReportExportData $table) {}

    public function supports(ReportExportFormat $format): bool
    {
        return $format === ReportExportFormat::Json;
    }

    public function generate(ReportExportGenerationRequest $request): GeneratedReportArtifact
    {
        $columns = $this->table->columns($request);
        $rows = [];
        foreach ($this->table->rows($request, $columns) as $values) {
            $row = [];
            foreach ($columns as $index => $column) {
                $row[$column->key] = $values[$index] ?? '';
            }
            $rows[] = $row;
        }

        return new GeneratedReportArtifact(
            filename: sprintf('%s-%s.json', Str::slug($request->reportName), now('UTC')->format('Ymd-His')),
            contentType: 'application/json; charset=UTF-8',
            contents: json_encode(['rows' => $rows, 'total_rows' => count($rows)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }
}
