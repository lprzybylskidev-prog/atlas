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

        foreach ($this->sorted($this->filtered($rows, $request), $request) as $row) {
            yield $row;
        }
    }
}
