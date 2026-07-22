<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application;

use App\Modules\Optional\Reports\Application\DTOs\ReportExportColumn;
use App\Modules\Optional\Reports\Application\DTOs\ReportExportGenerationRequest;
use App\Modules\Optional\Reports\Application\DTOs\ReportExportTotal;
use DateTimeInterface;
use RuntimeException;
use Stringable;

final readonly class TabularReportExportData
{
    public function __construct(private ReportExportDataProviderRegistry $providers) {}

    /**
     * @return list<ReportExportColumn>
     */
    public function columns(ReportExportGenerationRequest $request): array
    {
        $availableColumns = $this->providers->get($request->reportKey)->columns($request);
        $availableByKey = [];

        foreach ($availableColumns as $column) {
            $availableByKey[$column->key] = $column;
        }

        $orderedKeys = array_values(array_unique([
            ...$request->columnOrder,
            ...$request->visibleColumns,
        ]));
        $selected = [];

        foreach ($orderedKeys as $key) {
            if (in_array($key, $request->visibleColumns, true) && in_array($key, $request->allowedColumns, true) && isset($availableByKey[$key])) {
                $selected[] = $availableByKey[$key];
            }
        }

        return $selected;
    }

    /**
     * @param  list<ReportExportColumn>  $columns
     * @return iterable<list<string>>
     */
    public function rows(ReportExportGenerationRequest $request, array $columns): iterable
    {
        foreach ($this->providers->get($request->reportKey)->rows($request) as $row) {
            yield array_map(
                fn (ReportExportColumn $column): string => $this->cell($row[$column->key] ?? null),
                $columns,
            );
        }
    }

    public function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            $cell = (string) $value;

            return preg_match('/^[=+\-@\t\r]/', $cell) === 1 ? "'".$cell : $cell;
        }

        throw new RuntimeException('Report export rows must contain scalar, stringable, date, or null values.');
    }

    /**
     * @return list<ReportExportTotal>
     */
    public function totals(int $rowCount): array
    {
        return [new ReportExportTotal('Total rows', (string) max(0, $rowCount))];
    }
}
