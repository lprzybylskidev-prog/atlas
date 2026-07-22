<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class AdminModuleDetailHistoryDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::MODULE_DETAIL_HISTORY;
    }

    public function tableName(): string
    {
        return 'Module activation history';
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
        return 'admin-module-detail-history-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'moduleKey' => 'Module',
            'scope' => 'Scope',
            'teamName' => 'Team',
            'teamPublicId' => 'Team public ID',
            'previousEnabled' => 'Previous',
            'newEnabled' => 'New',
            'source' => 'Source',
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

        $rows = DB::table(DatabaseTable::MODULE_ACTIVATION_HISTORY)
            ->leftJoin(DatabaseTable::TEAMS, 'module_activation_history.team_id', '=', 'teams.id')
            ->where('module_activation_history.module_key', $module)
            ->orderByDesc('module_activation_history.effective_at')
            ->limit(200)
            ->get([
                'module_activation_history.scope',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'module_activation_history.previous_enabled',
                'module_activation_history.new_enabled',
                'module_activation_history.source',
                'module_activation_history.reason',
                'module_activation_history.effective_at',
            ])
            ->map(static fn (object $row): array => [
                'moduleKey' => $module,
                'scope' => self::stringValue($row->scope ?? ''),
                'teamName' => self::stringValue($row->team_name ?? ''),
                'teamPublicId' => self::stringValue($row->team_public_id ?? ''),
                'previousEnabled' => $row->previous_enabled === null ? null : (bool) $row->previous_enabled,
                'newEnabled' => (bool) ($row->new_enabled ?? false),
                'source' => self::stringValue($row->source ?? ''),
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
