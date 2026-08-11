<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Exports;

use App\Modules\Optional\ManagedProcesses\Infrastructure\Persistence\TableNames\ManagedProcessesDatabaseTable;
use App\Shared\Application\Exports\AbstractAdminDataTableExportProvider;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\ExportPermissions;
use App\Shared\Application\Tables\RegisteredTables;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Illuminate\Support\Facades\DB;

final readonly class AdminManagedProcessSchedulesDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private TeamLookup $teams) {}

    public function tableKey(): string
    {
        return RegisteredTables::MANAGED_PROCESS_SCHEDULES;
    }

    public function tableName(): string
    {
        return 'Managed process schedules';
    }

    public function owningModuleKey(): string
    {
        return 'managed_processes';
    }

    public function requestPermission(): string
    {
        return ExportPermissions::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-managed-process-schedules-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'processKey' => 'Process',
            'moduleKey' => 'Module',
            'team' => 'Team',
            'cronExpression' => 'Cron',
            'enabled' => 'Enabled',
            'nextDueAt' => 'Next due',
            'createdAt' => 'Created',
            'overlapPolicy' => 'Overlap',
            'reason' => 'Reason',
            'scope' => 'Scope',
            'timezone' => 'Timezone',
            'intervalKey' => 'Interval',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $schedules = DB::table(ManagedProcessesDatabaseTable::SCHEDULES)
            ->orderByDesc('process_schedules.created_at')
            ->get(['process_schedules.*']);
        $teamSummaries = $this->teams->summariesForInternalIds(array_values($schedules
            ->pluck('team_id')
            ->filter(static fn (mixed $teamId): bool => is_numeric($teamId))
            ->map(static fn (mixed $teamId): int => (int) $teamId)
            ->unique()
            ->all()));

        $rows = array_values($schedules
            ->map(static function (object $schedule) use ($teamSummaries): array {
                $teamId = is_numeric($schedule->team_id ?? null) ? (int) $schedule->team_id : null;
                $team = $teamId === null ? null : ($teamSummaries[$teamId] ?? null);

                return [
                    'publicId' => self::stringValue($schedule->public_id ?? null),
                    'processKey' => self::stringValue($schedule->process_key ?? null),
                    'moduleKey' => self::stringValue($schedule->module_key ?? null),
                    'scope' => self::stringValue($schedule->scope ?? null),
                    'team' => self::stringValue($team?->name),
                    'timezone' => self::stringValue($schedule->timezone ?? null),
                    'cronExpression' => self::stringValue($schedule->cron_expression ?? null),
                    'intervalKey' => self::stringValue($schedule->interval_key ?? null),
                    'enabled' => (bool) ($schedule->enabled ?? false),
                    'nextDueAt' => self::stringValue($schedule->next_due_at ?? null),
                    'overlapPolicy' => self::stringValue($schedule->overlap_policy ?? null),
                    'reason' => self::stringValue($schedule->reason ?? null),
                    'createdAt' => self::stringValue($schedule->created_at ?? null),
                ];
            })
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
            foreach (['processKey' => 'process', 'moduleKey' => 'module'] as $column => $filter) {
                $value = self::filterValue($request, $filter);

                if ($value !== '' && $value !== 'all' && $row[$column] !== $value) {
                    return false;
                }
            }

            $state = self::filterValue($request, 'state');

            if ($state === 'enabled' && $row['enabled'] !== true) {
                return false;
            }

            if ($state === 'disabled' && $row['enabled'] === true) {
                return false;
            }

            return self::dateRangeMatches(self::stringValue($row['nextDueAt'] ?? ''), self::filterValue($request, 'from'), self::filterValue($request, 'to'));
        }));
    }
}
