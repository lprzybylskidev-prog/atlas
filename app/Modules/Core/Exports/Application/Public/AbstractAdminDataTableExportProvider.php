<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public;

use App\Modules\Core\Exports\Application\Contracts\AdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\DTOs\ReportExportColumn;
use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use App\Modules\Core\Exports\Application\Public\DTOs\AdminDataTableExportContext;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\TableColumn;
use App\Shared\Application\Tables\TableDefinition;
use Stringable;

abstract readonly class AbstractAdminDataTableExportProvider implements AdminDataTableExportProvider
{
    public function reportKey(): string
    {
        return $this->tableKey();
    }

    public function tableDefinition(): TableDefinition
    {
        return AdminTableDefinitions::get($this->tableKey());
    }

    public function allowedExportColumns(AdminDataTableExportContext $context): array
    {
        return $this->tableDefinition()->columnKeys();
    }

    public function supportedFormats(AdminDataTableExportContext $context): array
    {
        return [
            ReportExportFormat::Csv,
            ReportExportFormat::Xlsx,
            ReportExportFormat::Pdf,
            ReportExportFormat::BrowserPrint,
        ];
    }

    public function columns(ReportExportGenerationRequest $request): array
    {
        $labels = $this->columnLabels();

        return array_map(
            static fn (TableColumn $column): ReportExportColumn => new ReportExportColumn($column->key, $labels[$column->key] ?? $column->key),
            $this->tableDefinition()->columns,
        );
    }

    /**
     * @return array<string, string>
     */
    abstract protected function columnLabels(): array;

    /**
     * @param  list<array<string, scalar|Stringable|null>>  $rows
     * @return list<array<string, scalar|Stringable|null>>
     */
    protected function filtered(array $rows, ReportExportGenerationRequest $request): array
    {
        $search = mb_strtolower(trim(self::stringValue($request->filters['search'] ?? '')));

        if ($search === '') {
            return $rows;
        }

        $searchable = $this->tableDefinition()->searchableKeys();

        return array_values(array_filter($rows, static function (array $row) use ($search, $searchable): bool {
            foreach ($searchable as $column) {
                if (str_contains(mb_strtolower(self::stringValue($row[$column] ?? '')), $search)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  list<array<string, scalar|Stringable|null>>  $rows
     * @return list<array<string, scalar|Stringable|null>>
     */
    protected function sorted(array $rows, ReportExportGenerationRequest $request): array
    {
        $sort = $request->sorting[0]['id'] ?? $this->tableDefinition()->defaultSort;
        $desc = (bool) ($request->sorting[0]['desc'] ?? false);

        usort($rows, static function (array $first, array $second) use ($sort, $desc): int {
            $result = self::stringValue($first[$sort] ?? '') <=> self::stringValue($second[$sort] ?? '');

            return $desc ? -$result : $result;
        });

        return $rows;
    }

    protected static function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return '';
    }

    protected static function filterValue(ReportExportGenerationRequest $request, string $key): string
    {
        return self::stringValue($request->filters[$key] ?? 'all');
    }

    /**
     * @param  list<string>  $values
     */
    protected static function listValue(array $values): string
    {
        return implode(', ', $values);
    }

    protected static function dateRangeMatches(string $value, string $from, string $to): bool
    {
        if ($value === '') {
            return $from === '' && $to === '';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return true;
        }

        if ($from !== '' && $from !== 'all') {
            $fromTimestamp = strtotime($from.' 00:00:00');

            if ($fromTimestamp !== false && $timestamp < $fromTimestamp) {
                return false;
            }
        }

        if ($to !== '' && $to !== 'all') {
            $toTimestamp = strtotime($to.' 23:59:59');

            if ($toTimestamp !== false && $timestamp > $toTimestamp) {
                return false;
            }
        }

        return true;
    }
}
