<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Infrastructure\Persistence;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Packages\OnboardingPackageDefinition;
use App\Modules\Core\Authorization\Infrastructure\Persistence\TableNames\AuthorizationDatabaseTable;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseOnboardingPackageStore implements OnboardingPackageStore
{
    public function __construct(
        private readonly TeamLookup $teams,
    ) {}

    public function allActive(?string $teamPublicId = null): array
    {
        $packages = [];
        $teamId = $teamPublicId === null ? null : $this->teams->internalIdForPublicId($teamPublicId);

        if ($teamPublicId !== null && $teamId === null) {
            return [];
        }

        foreach ($this->withTeamSummaries(DB::table(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)
            ->where('authorization_onboarding_packages.is_active', true)
            ->when($teamId !== null, static function ($query) use ($teamId): void {
                $query->where('authorization_onboarding_packages.team_id', $teamId);
            })
            ->orderBy('authorization_onboarding_packages.label')
            ->get([
                'authorization_onboarding_packages.public_id',
                'authorization_onboarding_packages.team_id',
                'authorization_onboarding_packages.name',
                'authorization_onboarding_packages.label',
                'authorization_onboarding_packages.initial_role_names',
                'authorization_onboarding_packages.direct_permission_names',
                'authorization_onboarding_packages.template_permission_names',
            ])
            ->all()) as $row) {
            $packages[] = $this->definitionFromRow($row);
        }

        usort($packages, fn (OnboardingPackageDefinition $first, OnboardingPackageDefinition $second): int => [$first->teamName, $first->label] <=> [$second->teamName, $second->label]);

        return $packages;
    }

    public function findByPublicId(string $publicId): ?OnboardingPackageDefinition
    {
        $rows = $this->withTeamSummaries(DB::table(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)
            ->where('authorization_onboarding_packages.public_id', $publicId)
            ->get([
                'authorization_onboarding_packages.public_id',
                'authorization_onboarding_packages.team_id',
                'authorization_onboarding_packages.name',
                'authorization_onboarding_packages.label',
                'authorization_onboarding_packages.initial_role_names',
                'authorization_onboarding_packages.direct_permission_names',
                'authorization_onboarding_packages.template_permission_names',
            ])
            ->all());
        $row = $rows[0] ?? null;

        return is_object($row) ? $this->definitionFromRow($row) : null;
    }

    public function findActiveForTeam(string $name, string $teamPublicId): ?OnboardingPackageDefinition
    {
        $teamId = $this->teams->internalIdForPublicId($teamPublicId);

        if ($teamId === null) {
            return null;
        }

        $rows = $this->withTeamSummaries(DB::table(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)
            ->where('authorization_onboarding_packages.name', $name)
            ->where('authorization_onboarding_packages.is_active', true)
            ->where('authorization_onboarding_packages.team_id', $teamId)
            ->get([
                'authorization_onboarding_packages.public_id',
                'authorization_onboarding_packages.team_id',
                'authorization_onboarding_packages.name',
                'authorization_onboarding_packages.label',
                'authorization_onboarding_packages.initial_role_names',
                'authorization_onboarding_packages.direct_permission_names',
                'authorization_onboarding_packages.template_permission_names',
            ])
            ->all());
        $row = $rows[0] ?? null;

        return is_object($row) ? $this->definitionFromRow($row) : null;
    }

    public function upsert(
        string $teamPublicId,
        string $name,
        string $label,
        array $initialRoleNames,
        array $directPermissionNames,
        array $templatePermissionNames,
    ): void {
        $teamId = $this->teams->internalIdForPublicId($teamPublicId);

        if ($teamId === null) {
            return;
        }

        $values = [
            'label' => $label,
            'initial_role_names' => json_encode($initialRoleNames, JSON_THROW_ON_ERROR),
            'direct_permission_names' => json_encode($directPermissionNames, JSON_THROW_ON_ERROR),
            'template_permission_names' => json_encode($templatePermissionNames, JSON_THROW_ON_ERROR),
            'is_active' => true,
            'updated_at' => now(),
        ];

        if (DB::table(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)->where('team_id', $teamId)->where('name', $name)->exists()) {
            DB::table(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)
                ->where('team_id', $teamId)
                ->where('name', $name)
                ->update($values);

            return;
        }

        DB::table(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)->insert($values + [
            'public_id' => (string) Str::ulid(),
            'team_id' => $teamId,
            'name' => $name,
            'created_at' => now(),
        ]);
    }

    public function deactivate(string $publicId): void
    {
        DB::table(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)
            ->where('public_id', $publicId)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    private function definitionFromRow(object $row): OnboardingPackageDefinition
    {
        $values = get_object_vars($row);

        return new OnboardingPackageDefinition(
            publicId: $this->stringValue($values, 'public_id'),
            teamPublicId: $this->stringValue($values, 'team_public_id'),
            teamName: $this->displayName($values, 'team_display_name', 'team_name'),
            name: $this->stringValue($values, 'name'),
            label: $this->stringValue($values, 'label'),
            initialRoleNames: $this->stringList($values, 'initial_role_names'),
            directPermissionNames: $this->stringList($values, 'direct_permission_names'),
            templatePermissionNames: $this->stringList($values, 'template_permission_names'),
        );
    }

    /**
     * @param  array<int, stdClass>  $rows
     * @return list<stdClass>
     */
    private function withTeamSummaries(array $rows): array
    {
        $rows = array_values($rows);
        $teamIds = [];

        foreach ($rows as $row) {
            $values = get_object_vars($row);
            $teamId = $values['team_id'] ?? null;

            if (is_numeric($teamId)) {
                $teamIds[] = (int) $teamId;
            }
        }

        $summaries = $this->teams->summariesForInternalIds($teamIds);

        foreach ($rows as $row) {
            $values = get_object_vars($row);
            $teamId = $values['team_id'] ?? null;
            $summary = is_numeric($teamId) ? ($summaries[(int) $teamId] ?? null) : null;

            $row->team_public_id = $summary === null ? '' : $summary->publicId;
            $row->team_name = $summary === null ? '' : $summary->name;
            $row->team_display_name = $summary === null ? '' : $summary->name;
        }

        return $rows;
    }

    /**
     * @param  array<mixed>  $values
     */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<mixed>  $values
     */
    private function displayName(array $values, string $displayKey, string $fallbackKey): string
    {
        $displayName = $this->stringValue($values, $displayKey);

        return $displayName !== '' ? $displayName : $this->stringValue($values, $fallbackKey);
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values, string $key): array
    {
        $value = $values[$key] ?? '[]';
        $decoded = json_decode(is_string($value) ? $value : '[]', true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_string'));
    }
}
