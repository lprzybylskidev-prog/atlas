<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Application\Tables\AdminTableDefinitions;

final readonly class AdminTeamsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::TEAMS;
    }

    public function tableName(): string
    {
        return 'Admin teams';
    }

    public function owningModuleKey(): string
    {
        return 'teams';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-teams-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'id' => 'Internal ID',
            'name' => 'Name',
            'isActive' => 'Active',
            'createdAt' => 'Created at',
            'updatedAt' => 'Updated at',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = array_values(Team::query()
            ->get(['id', 'public_id', 'name', 'is_active', 'created_at', 'updated_at'])
            ->map(static fn (Team $team): array => [
                'id' => $team->id,
                'publicId' => (string) $team->public_id,
                'name' => $team->name,
                'isActive' => $team->is_active,
                'createdAt' => $team->created_at?->toISOString() ?? '',
                'updatedAt' => $team->updated_at?->toISOString() ?? '',
            ])
            ->all());

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
            if (self::filterValue($request, 'status') === 'active') {
                return $row['isActive'] === true;
            }

            if (self::filterValue($request, 'status') === 'inactive') {
                return $row['isActive'] !== true;
            }

            return true;
        }));
    }
}
