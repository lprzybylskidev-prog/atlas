<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleKey;
use App\Shared\Application\Modules\ModuleRegistry;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class AdminModulesDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(
        private ModuleRegistry $registry,
        private ModuleActivationService $activation,
    ) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::MODULES;
    }

    public function tableName(): string
    {
        return 'Module activation overview';
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
        return 'admin-modules-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'moduleKey' => 'Module',
            'category' => 'Category',
            'technicallyAvailable' => 'Available',
            'globallyEnabled' => 'Global',
            'teamEnabled' => 'Active team',
            'effectiveEnabled' => 'Effective',
            'teamStateSource' => 'Team source',
            'supportsGlobalActivation' => 'Global support',
            'supportsTeamActivation' => 'Team support',
            'requiredDependencies' => 'Required dependencies',
            'optionalDependencies' => 'Optional dependencies',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $teamId = is_string($request->activeTeamPublicId) ? $this->teamId($request->activeTeamPublicId) : null;
        $rows = array_map(fn (ModuleDefinition $module): array => $this->moduleRow($module, $teamId), $this->registry->all());

        foreach ($this->sorted($this->filtered($this->filteredByControls($rows, $request), $request), $request) as $row) {
            yield $row;
        }
    }

    /**
     * @return array<string, scalar|\Stringable|null>
     */
    private function moduleRow(ModuleDefinition $module, ?int $teamId): array
    {
        $effective = $this->activation->effectiveState($module->key()->value, $teamId);

        return [
            'moduleKey' => $module->key()->value,
            'category' => $module->category()->value,
            'technicallyAvailable' => $effective->technicallyAvailable,
            'globallyEnabled' => $effective->globallyEnabled,
            'teamEnabled' => $effective->teamEnabled,
            'effectiveEnabled' => $effective->effectiveEnabled,
            'teamStateSource' => $effective->source,
            'supportsGlobalActivation' => $module->supportsGlobalActivation(),
            'supportsTeamActivation' => $module->supportsTeamActivation(),
            'requiredDependencies' => implode(', ', array_map(static fn (ModuleKey $key): string => $key->value, $module->requiredDependencies())),
            'optionalDependencies' => implode(', ', array_map(static fn (ModuleKey $key): string => $key->value, $module->optionalDependencies())),
        ];
    }

    /**
     * @param  list<array<string, scalar|\Stringable|null>>  $rows
     * @return list<array<string, scalar|\Stringable|null>>
     */
    private function filteredByControls(array $rows, ReportExportGenerationRequest $request): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($request): bool {
            if (self::filterValue($request, 'category') !== 'all' && $row['category'] !== self::filterValue($request, 'category')) {
                return false;
            }

            if (self::filterValue($request, 'source') !== 'all' && $row['teamStateSource'] !== self::filterValue($request, 'source')) {
                return false;
            }

            if (self::filterValue($request, 'availability') !== 'all' && $row['technicallyAvailable'] !== (self::filterValue($request, 'availability') === 'yes')) {
                return false;
            }

            return self::filterValue($request, 'effective') === 'all' || $row['effectiveEnabled'] === (self::filterValue($request, 'effective') === 'yes');
        }));
    }

    private function teamId(string $teamPublicId): ?int
    {
        $teamId = DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        return is_numeric($teamId) ? (int) $teamId : null;
    }
}
