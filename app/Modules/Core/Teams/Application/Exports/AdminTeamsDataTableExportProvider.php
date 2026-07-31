<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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
            'displayName' => 'Display name',
            'name' => 'Technical name',
            'isActive' => 'Active',
            'membersCount' => 'Members',
            'createdAt' => 'Created at',
            'updatedAt' => 'Updated at',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = array_values(Team::query()
            ->from(DatabaseTable::TEAMS.' as teams')
            ->select([
                'teams.id',
                'teams.public_id',
                'teams.name',
                'teams.display_name',
                'teams.is_active',
                'teams.created_at',
                'teams.updated_at',
            ])
            ->selectSub(
                DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
                    ->selectRaw('count(*)')
                    ->whereColumn('team_user_assignments.team_id', 'teams.id')
                    ->where(static function (Builder $query): void {
                        $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
                    })
                    ->where(static function (Builder $query): void {
                        $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
                    }),
                'members_count',
            )
            ->get()
            ->map(static fn (Team $team): array => [
                'id' => $team->id,
                'publicId' => (string) $team->public_id,
                'name' => $team->name,
                'displayName' => is_string($team->display_name) && $team->display_name !== '' ? $team->display_name : $team->name,
                'isActive' => $team->is_active,
                'membersCount' => self::intValue($team->getAttribute('members_count')),
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
            if (self::filterValue($request, 'status') === 'active' && $row['isActive'] !== true) {
                return false;
            }

            if (self::filterValue($request, 'status') === 'inactive' && $row['isActive'] === true) {
                return false;
            }

            if (self::filterValue($request, 'members') === 'with') {
                return self::intValue($row['membersCount'] ?? 0) > 0;
            }

            if (self::filterValue($request, 'members') === 'without') {
                return self::intValue($row['membersCount'] ?? 0) <= 0;
            }

            return true;
        }));
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
