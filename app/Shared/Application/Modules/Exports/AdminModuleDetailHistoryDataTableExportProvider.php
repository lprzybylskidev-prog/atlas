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

final readonly class AdminModuleDetailHistoryDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private TeamLookup $teams) {}

    public function tableKey(): string
    {
        return RegisteredTables::MODULE_DETAIL_HISTORY;
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
        return ExportPermissions::REQUEST;
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

        $sourceRows = DB::table(DatabaseTable::MODULE_ACTIVATION_HISTORY)
            ->where('module_activation_history.module_key', $module)
            ->orderByDesc('module_activation_history.effective_at')
            ->limit(200)
            ->get([
                'module_activation_history.team_id',
                'module_activation_history.scope',
                'module_activation_history.previous_enabled',
                'module_activation_history.new_enabled',
                'module_activation_history.source',
                'module_activation_history.reason',
                'module_activation_history.effective_at',
            ])
            ->all();
        $teams = $this->teamsByInternalId($sourceRows);
        $rows = array_map(static fn (object $row): array => [
            'moduleKey' => $module,
            'scope' => self::stringValue($row->scope ?? ''),
            'teamName' => self::teamName($teams, $row) ?? '',
            'teamPublicId' => self::teamPublicId($teams, $row) ?? '',
            'previousEnabled' => $row->previous_enabled === null ? null : (bool) $row->previous_enabled,
            'newEnabled' => (bool) ($row->new_enabled ?? false),
            'source' => self::stringValue($row->source ?? ''),
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
