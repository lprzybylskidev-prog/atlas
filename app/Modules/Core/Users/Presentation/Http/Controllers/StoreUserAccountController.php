<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Public\Contracts\OnboardingPackageDirectory;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserOnboardingPackageApplier;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationManager;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
use App\Modules\Core\Users\Application\Commands\CreateUserAccountCommand;
use App\Modules\Core\Users\Application\CreateUserAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class StoreUserAccountController
{
    public function __construct(
        private CreateUserAccount $users,
        private UserTeamMembershipManager $memberships,
        private UserTeamAuthorizationManager $authorization,
        private UserOnboardingPackageApplier $onboardingPackages,
        private OnboardingPackageDirectory $packageDirectory,
        private UserCredentialAccountDirectory $accounts,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'team_assignments' => ['required', 'array', 'min:1'],
            'team_assignments.*.team_public_id' => ['required', 'string'],
            'team_assignments.*.source' => ['required', 'string', 'in:manual,package,copy'],
            'team_assignments.*.onboarding_package' => ['nullable', 'required_if:team_assignments.*.source,package', 'string'],
            'team_assignments.*.copy_authorization_from_user' => ['nullable', 'required_if:team_assignments.*.source,copy', 'string'],
            'team_assignments.*.role_names' => ['array'],
            'team_assignments.*.role_names.*' => ['string'],
            'team_assignments.*.direct_permission_names' => ['array'],
            'team_assignments.*.direct_permission_names.*' => ['string'],
        ]);
        $validated = is_array($validated) ? $validated : [];

        $actorPublicId = data_get($request->user(), 'public_id');
        $name = $this->stringValue($validated, 'name');
        $email = $this->stringValue($validated, 'email');
        $teamAssignments = $this->teamAssignments($validated);

        $this->validateTeamAssignments($teamAssignments);

        $account = $this->users->handle(new CreateUserAccountCommand(
            name: $name,
            email: $email,
            onboardingPackageName: null,
            teamPublicId: null,
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            copyAuthorizationFromUserPublicId: null,
        ));

        if (is_string($actorPublicId)) {
            foreach ($teamAssignments as $assignment) {
                $this->memberships->addAccess($actorPublicId, $account->publicId, $assignment['team_public_id']);

                if ($assignment['source'] === 'package') {
                    $this->onboardingPackages->applyDuringUserCreation(
                        packageName: $assignment['onboarding_package'],
                        userPublicId: $account->publicId,
                        teamPublicId: $assignment['team_public_id'],
                        actorPublicId: $actorPublicId,
                    );

                    continue;
                }

                $roleNames = $assignment['role_names'];
                $directPermissionNames = $assignment['direct_permission_names'];

                if ($assignment['source'] === 'copy') {
                    $source = $this->authorization->assignmentsForUserTeam(
                        $assignment['copy_authorization_from_user'],
                        $assignment['team_public_id'],
                    );
                    $roleNames = $source->roleNames;
                    $directPermissionNames = $source->directPermissionNames;
                }

                $this->authorization->replaceAssignmentsForUserTeam(
                    actorPublicId: $actorPublicId,
                    userPublicId: $account->publicId,
                    teamPublicId: $assignment['team_public_id'],
                    roleNames: $roleNames,
                    directPermissionNames: $directPermissionNames,
                    reason: 'Initial user team assignment.',
                );
            }
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account was created and the first-password link was sent.');
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
     * @return list<array{team_public_id: string, source: string, onboarding_package: string, copy_authorization_from_user: string, role_names: list<string>, direct_permission_names: list<string>}>
     */
    private function teamAssignments(array $values): array
    {
        $assignments = $values['team_assignments'] ?? [];

        if (! is_array($assignments)) {
            return [];
        }

        $result = [];

        foreach ($assignments as $assignment) {
            if (! is_array($assignment)) {
                continue;
            }

            $teamPublicId = $assignment['team_public_id'] ?? '';

            if (! is_string($teamPublicId) || $teamPublicId === '') {
                continue;
            }

            $result[] = [
                'team_public_id' => $teamPublicId,
                'source' => $this->stringValue($assignment, 'source') ?: 'manual',
                'onboarding_package' => $this->stringValue($assignment, 'onboarding_package'),
                'copy_authorization_from_user' => $this->stringValue($assignment, 'copy_authorization_from_user'),
                'role_names' => $this->stringList($assignment['role_names'] ?? []),
                'direct_permission_names' => $this->stringList($assignment['direct_permission_names'] ?? []),
            ];
        }

        return $result;
    }

    /**
     * @param  list<array{team_public_id: string, source: string, onboarding_package: string, copy_authorization_from_user: string, role_names: list<string>, direct_permission_names: list<string>}>  $assignments
     */
    private function validateTeamAssignments(array $assignments): void
    {
        foreach ($assignments as $assignment) {
            if (! $this->memberships->teamExists($assignment['team_public_id'])) {
                throw ValidationException::withMessages([
                    'team_assignments' => __('validation.exists', ['attribute' => 'team']),
                ]);
            }

            if ($assignment['source'] === 'package' && ! $this->packageExistsForTeam($assignment['onboarding_package'], $assignment['team_public_id'])) {
                throw ValidationException::withMessages([
                    'team_assignments' => __('validation.custom.team_assignments.preset_team'),
                ]);
            }

            if ($assignment['source'] !== 'copy') {
                continue;
            }

            if (! $this->accounts->publicIdExists($assignment['copy_authorization_from_user'])) {
                throw ValidationException::withMessages([
                    'team_assignments' => __('validation.exists', ['attribute' => 'source user']),
                ]);
            }

            if (! $this->memberships->hasActiveMembership(
                $assignment['copy_authorization_from_user'],
                $assignment['team_public_id'],
            )) {
                throw ValidationException::withMessages([
                    'team_assignments' => __('validation.custom.team_assignments.copy_source_team'),
                ]);
            }
        }
    }

    private function packageExistsForTeam(string $packageName, string $teamPublicId): bool
    {
        foreach ($this->packageDirectory->all() as $package) {
            if ($package->name === $packageName && $package->teamPublicId === $teamPublicId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, 'is_string'));
    }
}
