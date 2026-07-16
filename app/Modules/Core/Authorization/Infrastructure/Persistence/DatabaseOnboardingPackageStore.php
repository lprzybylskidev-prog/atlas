<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Infrastructure\Persistence;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Packages\OnboardingPackageDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseOnboardingPackageStore implements OnboardingPackageStore
{
    public function allActive(): array
    {
        $packages = [];

        foreach (DB::table('authorization_onboarding_packages')
            ->where('is_active', true)
            ->orderBy('label')
            ->get()
            ->all() as $row) {
            $packages[] = $this->definitionFromRow($row);
        }

        return $packages;
    }

    public function upsert(
        string $name,
        string $label,
        array $initialRoleNames,
        array $directPermissionNames,
        array $templatePermissionNames,
    ): void {
        $values = [
            'label' => $label,
            'initial_role_names' => json_encode($initialRoleNames, JSON_THROW_ON_ERROR),
            'direct_permission_names' => json_encode($directPermissionNames, JSON_THROW_ON_ERROR),
            'template_permission_names' => json_encode($templatePermissionNames, JSON_THROW_ON_ERROR),
            'is_active' => true,
            'updated_at' => now(),
        ];

        if (DB::table('authorization_onboarding_packages')->where('name', $name)->exists()) {
            DB::table('authorization_onboarding_packages')->where('name', $name)->update($values);

            return;
        }

        DB::table('authorization_onboarding_packages')->insert($values + [
            'public_id' => (string) Str::ulid(),
            'name' => $name,
            'created_at' => now(),
        ]);
    }

    public function deactivate(string $name): void
    {
        DB::table('authorization_onboarding_packages')
            ->where('name', $name)
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
