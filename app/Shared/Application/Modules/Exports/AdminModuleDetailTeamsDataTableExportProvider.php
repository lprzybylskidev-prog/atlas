<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Exports;

use App\Shared\Application\Exports\AbstractAdminDataTableExportProvider;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\ExportPermissions;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Tables\RegisteredTables;
use App\Shared\Application\Teams\Contracts\TeamLookup;

final readonly class AdminModuleDetailTeamsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(
        private ModuleActivationService $activation,
        private TeamLookup $teams,
    ) {}

    public function tableKey(): string
    {
        return RegisteredTables::MODULE_DETAIL_TEAMS;
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
        return ExportPermissions::REQUEST;
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

        foreach ($this->teams->allSummaries() as $team) {
            $effective = $this->activation->effectiveState($module, $team->internalId);
            $rows[] = [
                'publicId' => $team->publicId,
                'moduleKey' => $module,
                'name' => $team->name,
                'isActive' => $team->active,
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
}
