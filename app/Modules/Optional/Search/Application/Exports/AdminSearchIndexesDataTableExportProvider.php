<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor;
use App\Shared\Application\Tables\AdminTableDefinitions;

final readonly class AdminSearchIndexesDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private SearchIndexRegistry $indexes) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::SEARCH_INDEXES;
    }

    public function tableName(): string
    {
        return 'Search indexes';
    }

    public function owningModuleKey(): string
    {
        return 'search';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-search-indexes-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'key' => 'Index key',
            'moduleKey' => 'Module',
            'stableAlias' => 'Stable alias',
            'searchableFields' => 'Searchable',
            'filterableFields' => 'Filterable',
            'sortableFields' => 'Sortable',
            'containsSensitiveData' => 'Sensitive',
            'supportsDeletion' => 'Deletion',
            'supportsAnonymization' => 'Anonymization',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = array_map(fn (SearchIndexDescriptor $descriptor): array => [
            'key' => $descriptor->key,
            'moduleKey' => $descriptor->moduleKey,
            'stableAlias' => $descriptor->stableAlias,
            'searchableFields' => self::listValue($descriptor->searchableFields),
            'filterableFields' => self::listValue($descriptor->filterableFields),
            'sortableFields' => self::listValue($descriptor->sortableFields),
            'containsSensitiveData' => $descriptor->containsSensitiveData,
            'supportsDeletion' => $descriptor->supportsDeletion,
            'supportsAnonymization' => $descriptor->supportsAnonymization,
        ], $this->indexes->all());

        foreach ($this->sorted($this->filtered($this->filteredByControls($rows, $request), $request), $request) as $row) {
            yield $row;
        }
    }

    /**
     * @param  list<array<string, scalar|\Stringable|null>>  $rows
     * @return list<array<string, scalar|\Stringable|null>>
     */
    private function filteredByControls(array $rows, ReportExportGenerationRequest $request): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($request): bool {
            $module = self::filterValue($request, 'module');
            $sensitivity = self::filterValue($request, 'sensitivity');
            $deletion = self::filterValue($request, 'deletion');
            $anonymization = self::filterValue($request, 'anonymization');

            if ($module !== 'all' && $row['moduleKey'] !== $module) {
                return false;
            }

            if ($sensitivity === 'sensitive' && $row['containsSensitiveData'] !== true) {
                return false;
            }

            if ($sensitivity === 'non_sensitive' && $row['containsSensitiveData'] !== false) {
                return false;
            }

            if ($deletion === 'supported' && $row['supportsDeletion'] !== true) {
                return false;
            }

            if ($deletion === 'unsupported' && $row['supportsDeletion'] !== false) {
                return false;
            }

            if ($anonymization === 'supported' && $row['supportsAnonymization'] !== true) {
                return false;
            }

            if ($anonymization === 'unsupported' && $row['supportsAnonymization'] !== false) {
                return false;
            }

            return true;
        }));
    }
}
