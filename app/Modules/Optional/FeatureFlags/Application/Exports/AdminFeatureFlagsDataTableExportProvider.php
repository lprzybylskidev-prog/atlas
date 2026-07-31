<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagRegistry;
use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagStore;
use App\Shared\Application\Tables\AdminTableDefinitions;

final readonly class AdminFeatureFlagsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(
        private FeatureFlagRegistry $registry,
        private FeatureFlagStore $store,
    ) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::FEATURE_FLAGS;
    }

    public function tableName(): string
    {
        return 'Feature flags';
    }

    public function owningModuleKey(): string
    {
        return 'feature_flags';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-feature-flags-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'name' => 'Flag',
            'key' => 'Key',
            'ownerModule' => 'Owner module',
            'type' => 'Type',
            'defaultEnabled' => 'Default',
            'globalEnabled' => 'Global',
            'teamEnabled' => 'Team',
            'effectiveEnabled' => 'Effective',
            'source' => 'Source',
            'lifecycle' => 'Lifecycle',
            'description' => 'Description',
            'teamScoped' => 'Team scoped',
            'selectedTeamPublicId' => 'Selected team public ID',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $teamPublicId = self::filterValue($request, 'team');
        $teamPublicId = $teamPublicId === 'all' || $teamPublicId === '' ? null : $teamPublicId;
        $rows = array_map(fn ($definition): array => $this->flagRow($definition->key->value, $teamPublicId), $this->registry->all());

        foreach ($this->sorted($this->filtered($this->filteredByControls($rows, $request), $request), $request) as $row) {
            yield $row;
        }
    }

    /**
     * @return array<string, scalar|\Stringable|null>
     */
    private function flagRow(string $key, ?string $teamPublicId): array
    {
        $state = $this->store->state($key, $teamPublicId);

        return [
            'key' => $state->definition->key->value,
            'name' => $state->definition->name,
            'description' => $state->definition->description,
            'type' => $state->definition->type->value,
            'ownerModule' => $state->definition->ownerModule,
            'lifecycle' => $state->definition->lifecycle,
            'teamScoped' => $state->definition->teamScoped,
            'defaultEnabled' => $state->definition->defaultEnabled,
            'globalEnabled' => $state->globalValue === null ? null : (bool) ($state->globalValue['enabled'] ?? false),
            'teamEnabled' => $state->teamValue === null ? null : (bool) ($state->teamValue['enabled'] ?? false),
            'effectiveEnabled' => $state->enabled(),
            'source' => $state->source,
            'selectedTeamPublicId' => $state->teamPublicId,
        ];
    }

    /**
     * @param  list<array<string, scalar|\Stringable|null>>  $rows
     * @return list<array<string, scalar|\Stringable|null>>
     */
    private function filteredByControls(array $rows, ReportExportGenerationRequest $request): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($request): bool {
            $status = self::filterValue($request, 'status');
            $source = self::filterValue($request, 'source');
            $owner = self::filterValue($request, 'owner');
            $lifecycle = self::filterValue($request, 'lifecycle');

            if ($status === 'enabled' && $row['effectiveEnabled'] !== true) {
                return false;
            }

            if ($status === 'disabled' && $row['effectiveEnabled'] !== false) {
                return false;
            }

            if ($source !== 'all' && $row['source'] !== $source) {
                return false;
            }

            if ($owner !== 'all' && $row['ownerModule'] !== $owner) {
                return false;
            }

            if ($lifecycle !== 'all' && $row['lifecycle'] !== $lifecycle) {
                return false;
            }

            return true;
        }));
    }
}
