<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Infrastructure\Persistence;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Packages\OnboardingPackageDefinition;
use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseOnboardingPackageStore implements OnboardingPackageStore
{
    public function allActive(?string $teamPublicId = null): array
    {
        $packages = [];

        foreach (DB::table(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)
            ->join(TeamsDatabaseTable::TEAMS, 'authorization_onboarding_packages.team_id', '=', 'teams.id')
            ->where('authorization_onboarding_packages.is_active', true)
            ->when($teamPublicId !== null, static function ($query) use ($teamPublicId): void {
                $query->where('teams.public_id', $teamPublicId);
            })
            ->orderBy('teams.name')
            ->orderBy('authorization_onboarding_packages.label')
            ->get([
                'authorization_onboarding_packages.public_id',
                'authorization_onboarding_packages.name',
                'authorization_onboarding_packages.label',
                'authorization_onboarding_packages.initial_role_names',
                'authorization_onboarding_packages.direct_permission_names',
                'authorization_onboarding_packages.template_permission_names',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'teams.display_name as team_display_name',
            ])
            ->all() as $row) {
            $packages[] = $this->definitionFromRow($row);
        }

        return $packages;
    }

    public function findByPublicId(string $publicId): ?OnboardingPackageDefinition
    {
        $row = DB::table(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)
            ->join(TeamsDatabaseTable::TEAMS, 'authorization_onboarding_packages.team_id', '=', 'teams.id')
            ->where('authorization_onboarding_packages.public_id', $publicId)
            ->first([
                'authorization_onboarding_packages.public_id',
                'authorization_onboarding_packages.name',
                'authorization_onboarding_packages.label',
                'authorization_onboarding_packages.initial_role_names',
                'authorization_onboarding_packages.direct_permission_names',
                'authorization_onboarding_packages.template_permission_names',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'teams.display_name as team_display_name',
            ]);

        return is_object($row) ? $this->definitionFromRow($row) : null;
    }

    public function findActiveForTeam(string $name, string $teamPublicId): ?OnboardingPackageDefinition
    {
        $row = DB::table(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)
            ->join(TeamsDatabaseTable::TEAMS, 'authorization_onboarding_packages.team_id', '=', 'teams.id')
            ->where('authorization_onboarding_packages.name', $name)
            ->where('authorization_onboarding_packages.is_active', true)
            ->where('teams.public_id', $teamPublicId)
            ->first([
                'authorization_onboarding_packages.public_id',
                'authorization_onboarding_packages.name',
                'authorization_onboarding_packages.label',
                'authorization_onboarding_packages.initial_role_names',
                'authorization_onboarding_packages.direct_permission_names',
                'authorization_onboarding_packages.template_permission_names',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'teams.display_name as team_display_name',
            ]);

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
        $teamId = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        if (! is_int($teamId)) {
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
