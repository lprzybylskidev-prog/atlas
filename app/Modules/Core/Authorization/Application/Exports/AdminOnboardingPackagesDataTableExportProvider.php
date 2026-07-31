<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Exports;

use App\Modules\Core\Authorization\Application\Packages\OnboardingPackageCatalog;
use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class AdminOnboardingPackagesDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private OnboardingPackageCatalog $packages) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::PACKAGES;
    }

    public function tableName(): string
    {
        return 'Authorization presets';
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
        return 'admin-packages-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'id' => 'Internal ID',
            'teamName' => 'Team',
            'teamPublicId' => 'Team public ID',
            'label' => 'Label',
            'name' => 'Name',
            'initialRoles' => 'Initial roles',
            'directPermissions' => 'Direct permissions',
            'templatePermissions' => 'Template permissions',
            'isActive' => 'Active',
            'createdAt' => 'Created at',
            'updatedAt' => 'Updated at',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $databasePackages = DB::table(DatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)
            ->get(['id', 'public_id', 'is_active', 'created_at', 'updated_at'])
            ->keyBy('public_id');
        $rows = array_map(static function ($package) use ($databasePackages): array {
            $databasePackage = $databasePackages->get($package->publicId);
            $values = is_object($databasePackage) ? get_object_vars($databasePackage) : [];
            $id = $values['id'] ?? null;
            $isActive = $values['is_active'] ?? true;
            $createdAt = $values['created_at'] ?? '';
            $updatedAt = $values['updated_at'] ?? '';

            return [
                'id' => is_numeric($id) ? (int) $id : null,
                'publicId' => $package->publicId,
                'teamPublicId' => $package->teamPublicId,
                'teamName' => $package->teamName,
                'name' => $package->name,
                'label' => $package->label,
                'initialRoles' => self::listValue($package->initialRoleNames),
                'directPermissions' => self::listValue($package->directPermissionNames),
                'templatePermissions' => self::listValue($package->templatePermissionNames),
                'isActive' => (bool) $isActive,
                'createdAt' => is_string($createdAt) ? $createdAt : '',
                'updatedAt' => is_string($updatedAt) ? $updatedAt : '',
            ];
        }, $this->packages->all());

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

            $team = self::filterValue($request, 'team');

            if ($team !== 'all' && $row['teamPublicId'] !== $team) {
                return false;
            }

            if (self::filterValue($request, 'roles') === 'with' && self::listCount($row['initialRoles'] ?? '') <= 0) {
                return false;
            }

            if (self::filterValue($request, 'roles') === 'without' && self::listCount($row['initialRoles'] ?? '') > 0) {
                return false;
            }

            if (self::filterValue($request, 'directPermissions') === 'with' && self::listCount($row['directPermissions'] ?? '') <= 0) {
                return false;
            }

            if (self::filterValue($request, 'directPermissions') === 'without' && self::listCount($row['directPermissions'] ?? '') > 0) {
                return false;
            }

            if (self::filterValue($request, 'templatePermissions') === 'with' && self::listCount($row['templatePermissions'] ?? '') <= 0) {
                return false;
            }

            if (self::filterValue($request, 'templatePermissions') === 'without' && self::listCount($row['templatePermissions'] ?? '') > 0) {
                return false;
            }

            return true;
        }));
    }

    private static function listCount(mixed $value): int
    {
        if (! is_string($value) || trim($value) === '') {
            return 0;
        }

        return count(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));
    }
}
