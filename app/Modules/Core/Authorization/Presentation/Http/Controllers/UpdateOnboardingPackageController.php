<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Packages\OnboardingPackageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class UpdateOnboardingPackageController
{
    public function __construct(
        private OnboardingPackageCatalog $catalog,
        private OnboardingPackageStore $packages,
    ) {}

    public function __invoke(Request $request, string $package): RedirectResponse
    {
        $definition = $this->catalog->getByPublicId($package);

        if ($definition === null) {
            abort(404);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'initial_roles' => ['nullable', 'array'],
            'initial_roles.*' => ['string'],
            'direct_permissions' => ['nullable', 'array'],
            'direct_permissions.*' => ['string'],
        ]);
        $validated = is_array($validated) ? $validated : [];
        $directPermissions = $this->stringList($validated, 'direct_permissions');

        $this->packages->upsert(
            teamPublicId: $definition->teamPublicId,
            name: $definition->name,
            label: $this->stringValue($validated, 'label'),
            initialRoleNames: $this->stringList($validated, 'initial_roles'),
            directPermissionNames: $directPermissions,
            templatePermissionNames: $directPermissions,
        );

        return redirect()
            ->route('admin.authorization.packages.edit', ['package' => $package])
            ->with('success', 'Preset was updated.');
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
        $value = $values[$key] ?? [];

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
