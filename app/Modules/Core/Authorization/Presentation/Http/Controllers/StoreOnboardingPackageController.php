<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class StoreOnboardingPackageController
{
    public function __construct(
        private OnboardingPackageStore $packages,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_.-]+$/'],
            'label' => ['required', 'string', 'max:255'],
            'initial_roles' => ['nullable', 'array'],
            'initial_roles.*' => ['string'],
            'direct_permissions' => ['nullable', 'array'],
            'direct_permissions.*' => ['string'],
        ]);
        $validated = is_array($validated) ? $validated : [];
        $directPermissions = $this->stringList($validated, 'direct_permissions');

        $this->packages->upsert(
            name: $this->stringValue($validated, 'name'),
            label: $this->stringValue($validated, 'label'),
            initialRoleNames: $this->stringList($validated, 'initial_roles'),
            directPermissionNames: $directPermissions,
            templatePermissionNames: $directPermissions,
        );

        return redirect()
            ->route('admin.authorization.packages.index')
            ->with('success', 'Onboarding package was saved.');
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
