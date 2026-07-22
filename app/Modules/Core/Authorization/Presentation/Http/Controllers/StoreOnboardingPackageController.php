<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class StoreOnboardingPackageController
{
    public function __construct(
        private OnboardingPackageStore $packages,
        private UserTeamMembershipManager $memberships,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_public_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_.-]+$/'],
            'label' => ['required', 'string', 'max:255'],
            'initial_roles' => ['nullable', 'array'],
            'initial_roles.*' => ['string'],
            'direct_permissions' => ['nullable', 'array'],
            'direct_permissions.*' => ['string'],
        ]);
        $validated = is_array($validated) ? $validated : [];
        $directPermissions = $this->stringList($validated, 'direct_permissions');
        $teamPublicId = $this->stringValue($validated, 'team_public_id');

        if (! $this->memberships->teamExists($teamPublicId)) {
            throw ValidationException::withMessages([
                'team_public_id' => __('validation.exists', ['attribute' => 'team']),
            ]);
        }

        $this->packages->upsert(
            teamPublicId: $teamPublicId,
            name: $this->stringValue($validated, 'name'),
            label: $this->stringValue($validated, 'label'),
            initialRoleNames: $this->stringList($validated, 'initial_roles'),
            directPermissionNames: $directPermissions,
            templatePermissionNames: $directPermissions,
        );

        return redirect()
            ->route('admin.authorization.packages.index')
            ->with('flash.messages', [
                FlashMessage::success('flash.authorization.package_saved'),
            ]);
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
