<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Exports;

use App\Shared\Application\Exports\AbstractAdminDataTableExportProvider;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\ExportPermissions;
use App\Shared\Application\Tables\RegisteredTables;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\DTOs\TeamLookupSummary;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class AdminModuleDetailSchedulesDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private TeamLookup $teams) {}

    public function tableKey(): string
    {
        return RegisteredTables::MODULE_DETAIL_SCHEDULES;
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
        return ExportPermissions::REQUEST;
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

        $sourceRows = DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->where('module_activation_schedules.module_key', $module)
            ->orderByDesc('module_activation_schedules.effective_at')
            ->limit(200)
            ->get([
                'module_activation_schedules.public_id',
                'module_activation_schedules.scope',
                'module_activation_schedules.team_id',
                'module_activation_schedules.target_enabled',
                'module_activation_schedules.status',
                'module_activation_schedules.reason',
                'module_activation_schedules.effective_at',
            ])
            ->all();
        $teams = $this->teamsByInternalId($sourceRows);
        $rows = array_map(static fn (object $row): array => [
            'publicId' => self::stringValue($row->public_id ?? ''),
            'moduleKey' => $module,
            'scope' => self::stringValue($row->scope ?? ''),
            'teamName' => self::teamName($teams, $row) ?? '',
            'teamPublicId' => self::teamPublicId($teams, $row) ?? '',
            'targetEnabled' => (bool) ($row->target_enabled ?? false),
            'status' => self::stringValue($row->status ?? ''),
            'reason' => self::stringValue($row->reason ?? ''),
            'effectiveAt' => self::stringValue($row->effective_at ?? ''),
        ], $sourceRows);

        foreach ($this->sorted($this->filtered(array_values($rows), $request), $request) as $row) {
            yield $row;
        }
    }

    /**
     * @param  array<int, stdClass>  $rows
     * @return array<int, TeamLookupSummary>
     */
    private function teamsByInternalId(array $rows): array
    {
        $teamIds = [];

        foreach ($rows as $row) {
            $teamId = $row->team_id ?? null;

            if (is_numeric($teamId)) {
                $teamIds[] = (int) $teamId;
            }
        }

        return $this->teams->summariesForInternalIds(array_values(array_unique($teamIds)));
    }

    /**
     * @param  array<int, TeamLookupSummary>  $teams
     */
    private static function teamPublicId(array $teams, object $row): ?string
    {
        $teamId = $row->team_id ?? null;

        return is_numeric($teamId) ? ($teams[(int) $teamId]->publicId ?? null) : null;
    }

    /**
     * @param  array<int, TeamLookupSummary>  $teams
     */
    private static function teamName(array $teams, object $row): ?string
    {
        $teamId = $row->team_id ?? null;

        return is_numeric($teamId) ? ($teams[(int) $teamId]->name ?? null) : null;
    }
}
