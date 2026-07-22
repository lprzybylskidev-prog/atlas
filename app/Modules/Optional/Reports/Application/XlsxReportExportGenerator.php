<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application;

use App\Modules\Optional\Reports\Application\Contracts\ReportExportGenerator;
use App\Modules\Optional\Reports\Application\DTOs\GeneratedReportArtifact;
use App\Modules\Optional\Reports\Application\DTOs\ReportExportGenerationRequest;
use App\Modules\Optional\Reports\Application\Enums\ReportExportFormat;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final readonly class XlsxReportExportGenerator implements ReportExportGenerator
{
    public function __construct(private TabularReportExportData $table) {}

    public function supports(ReportExportFormat $format): bool
    {
        return $format === ReportExportFormat::Xlsx;
    }

    public function generate(ReportExportGenerationRequest $request): GeneratedReportArtifact
    {
        $columns = $this->table->columns($request);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        foreach ($columns as $offset => $column) {
            $coordinate = Coordinate::stringFromColumnIndex($offset + 1).'1';
            $sheet->setCellValueExplicit($coordinate, $column->label, DataType::TYPE_STRING);
        }

        $rowNumber = 2;
        $dataRowCount = 0;

        foreach ($this->table->rows($request, $columns) as $row) {
            foreach ($row as $offset => $cell) {
                $coordinate = Coordinate::stringFromColumnIndex($offset + 1).$rowNumber;
                $sheet->setCellValueExplicit($coordinate, $cell, DataType::TYPE_STRING);
            }

            $rowNumber++;
            $dataRowCount++;
        }

        $rowNumber++;

        foreach ($this->table->totals($dataRowCount) as $total) {
            $sheet->setCellValueExplicit('A'.$rowNumber, $total->label, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$rowNumber, $total->value, DataType::TYPE_STRING);
            $rowNumber++;
        }

        $this->format($spreadsheet, count($columns), max(1, $rowNumber - 1));

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        ob_start();

        try {
            $writer->save('php://output');
            $contents = ob_get_clean();
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        if (! is_string($contents)) {
            throw new RuntimeException('XLSX export stream could not be read.');
        }

        return new GeneratedReportArtifact(
            filename: sprintf('%s-%s.xlsx', Str::slug($request->reportName), now('UTC')->format('Ymd-His')),
            contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            contents: $contents,
        );
    }

    private function format(Spreadsheet $spreadsheet, int $columnCount, int $rowCount): void
    {
        if ($columnCount < 1) {
            return;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter(sprintf('A1:%s%d', $lastColumn, $rowCount));
        $sheet->getStyle(sprintf('A1:%s1', $lastColumn))->getFont()->setBold(true);
        $sheet->getStyle(sprintf('A1:%s1', $lastColumn))->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFE5E7EB');

        for ($column = 1; $column <= $columnCount; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
    }
}
