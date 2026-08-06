<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Tables\AdminTableDefinitions;
use Illuminate\Support\Facades\DB;

final readonly class AdminModuleDetailTeamsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private ModuleActivationService $activation) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::MODULE_DETAIL_TEAMS;
    }

    public function tableName(): string
    {
        return 'Module detail teams';
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
        return 'admin-module-detail-teams-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Team public ID',
            'moduleKey' => 'Module',
            'name' => 'Team',
            'isActive' => 'Team active',
            'teamEnabled' => 'Team override',
            'effectiveEnabled' => 'Active',
            'source' => 'Configuration source',
            'version' => 'Version',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $module = self::filterValue($request, 'module');

        if ($module === '') {
            return;
        }

        $rows = [];

        foreach (DB::table(TeamsDatabaseTable::TEAMS)->orderBy('display_name')->orderBy('name')->get(['id', 'public_id', 'name', 'display_name', 'is_active']) as $team) {
            $teamId = is_numeric($team->id ?? null) ? (int) $team->id : null;

            if ($teamId === null) {
                continue;
            }

            $effective = $this->activation->effectiveState($module, $teamId);
            $rows[] = [
                'publicId' => self::stringValue($team->public_id ?? ''),
                'moduleKey' => $module,
                'name' => self::teamDisplayName($team),
                'isActive' => (bool) ($team->is_active ?? false),
                'teamEnabled' => $effective->teamEnabled,
                'effectiveEnabled' => $effective->effectiveEnabled,
                'source' => $effective->source,
                'version' => $effective->teamVersion,
            ];
        }

        foreach ($this->sorted($this->filtered($rows, $request), $request) as $row) {
            yield $row;
        }
    }

    private static function teamDisplayName(object $team): string
    {
        $displayName = self::stringValue($team->display_name ?? '');

        return $displayName === '' ? self::stringValue($team->name ?? '') : $displayName;
    }
}
