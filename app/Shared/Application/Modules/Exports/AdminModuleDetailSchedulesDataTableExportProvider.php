<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class AdminModuleDetailSchedulesDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::MODULE_DETAIL_SCHEDULES;
    }

    public function tableName(): string
    {
        return 'Module activation schedules';
    }

    public function owningModuleKey(): string
    {
        return 'authorization';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-module-detail-schedules-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'moduleKey' => 'Module',
            'scope' => 'Scope',
            'teamName' => 'Team',
            'teamPublicId' => 'Team public ID',
            'targetEnabled' => 'Target',
            'status' => 'Status',
            'reason' => 'Reason',
            'effectiveAt' => 'Effective at',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $module = self::filterValue($request, 'module');

        if ($module === '') {
            return;
        }

        $rows = DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->leftJoin(TeamsDatabaseTable::TEAMS, 'module_activation_schedules.team_id', '=', 'teams.id')
            ->where('module_activation_schedules.module_key', $module)
            ->orderByDesc('module_activation_schedules.effective_at')
            ->limit(200)
            ->get([
                'module_activation_schedules.public_id',
                'module_activation_schedules.scope',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'module_activation_schedules.target_enabled',
                'module_activation_schedules.status',
                'module_activation_schedules.reason',
                'module_activation_schedules.effective_at',
            ])
            ->map(static fn (object $row): array => [
                'publicId' => self::stringValue($row->public_id ?? ''),
                'moduleKey' => $module,
                'scope' => self::stringValue($row->scope ?? ''),
                'teamName' => self::stringValue($row->team_name ?? ''),
                'teamPublicId' => self::stringValue($row->team_public_id ?? ''),
                'targetEnabled' => (bool) ($row->target_enabled ?? false),
                'status' => self::stringValue($row->status ?? ''),
                'reason' => self::stringValue($row->reason ?? ''),
                'effectiveAt' => self::stringValue($row->effective_at ?? ''),
            ])
            ->values()
            ->all();

        foreach ($this->sorted($this->filtered(array_values($rows), $request), $request) as $row) {
            yield $row;
        }
    }
}
