<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Public\Contracts\OnboardingPackageDirectory;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationManager;
use App\Modules\Core\Identity\Application\Public\Contracts\ImpersonationEligibilityChecker;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Identity\Application\Public\DTOs\UserCredentialAccountOption;
use App\Modules\Core\Settings\Application\Public\Contracts\SecuritySessionSettings;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamSessionLimitSettings;
use App\Modules\Optional\TimeTracking\Application\Public\Contracts\UserBreakPolicySettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final readonly class EditUserAccountController
{
    public function __construct(
        private UserCredentialAccountDirectory $accounts,
        private UserTeamMembershipManager $memberships,
        private UserTeamSessionLimitSettings $sessionLimits,
        private UserTeamAuthorizationManager $authorization,
        private OnboardingPackageDirectory $packages,
        private ImpersonationEligibilityChecker $impersonation,
        private SecuritySessionSettings $sessionSettings,
        private UserBreakPolicySettings $breakPolicies,
    ) {}

    public function __invoke(Request $request, string $user): Response
    {
        $account = $this->accounts->findAdminRow($user);

        if ($account === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $actorPublicId = $this->actorPublicId($request);
        $eligibility = $actorPublicId !== null ? $this->impersonation->eligibility($request, $actorPublicId, $account->publicId) : null;

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'publicId' => $account->publicId,
                'name' => $account->name,
                'email' => $account->email,
                'isActive' => $account->isActive,
                'emailVerified' => $account->emailVerified,
                'firstPasswordSet' => $account->firstPasswordSet,
                'loginLocked' => $account->loginLocked,
                'mfaEnabled' => $account->mfaEnabled,
                'accountSensitivity' => $account->accountSensitivity,
                'canImpersonate' => $eligibility !== null && $eligibility->canStart,
                'impersonationRequiresSensitiveOverride' => $eligibility !== null && $eligibility->requiresSensitiveOverride,
            ],
            'teamMemberships' => array_map(function ($membership) use ($account): array {
                $assignments = $this->authorization->assignmentsForUserTeam($account->publicId, $membership->teamPublicId);
                $sessionLimits = $this->sessionLimits->resolvedForUserTeam($account->publicId, $membership->teamPublicId);
                $breakLimits = $this->breakPolicies->resolvedForUserTeam($account->publicId, $membership->teamPublicId);
                $hasUserTeamSessionOverride = $sessionLimits['source'] === 'user_team';
                $hasUserTeamBreakOverride = $breakLimits['source'] === 'user_team';

                return [
                    'teamPublicId' => $membership->teamPublicId,
                    'teamName' => $membership->teamName,
                    'teamActive' => $membership->teamActive,
                    'validFrom' => $membership->validFrom,
                    'validTo' => $membership->validTo,
                    'roleNames' => $assignments->roleNames,
                    'directPermissionNames' => $assignments->directPermissionNames,
                    'inactivityTimeoutMinutes' => $hasUserTeamSessionOverride ? $sessionLimits['inactivityTimeoutMinutes'] : null,
                    'sessionMaxLifetimeMinutes' => $hasUserTeamSessionOverride ? $sessionLimits['sessionMaxLifetimeMinutes'] : null,
                    'breakDailyLimitMinutes' => $hasUserTeamBreakOverride ? $breakLimits['dailyLimitMinutes'] : null,
                    'breakMaximumSingleMinutes' => $hasUserTeamBreakOverride ? $breakLimits['maximumSingleBreakMinutes'] : null,
                ];
            }, $this->memberships->activeMembershipsForUser($account->publicId)),
            'assignableTeams' => array_map(static fn ($team): array => [
                'value' => $team->publicId,
                'label' => $team->name,
            ], $this->memberships->assignableTeamsForUser($account->publicId)),
            'teamPolicyDefaults' => $this->teamPolicyDefaults(array_values(array_unique(array_merge(
                array_map(static fn ($membership): string => $membership->teamPublicId, $this->memberships->activeMembershipsForUser($account->publicId)),
                array_map(static fn ($team): string => $team->publicId, $this->memberships->assignableTeamsForUser($account->publicId)),
            )))),
            'packages' => array_map(static fn ($package): array => [
                'publicId' => $package->publicId,
                'teamPublicId' => $package->teamPublicId,
                'teamName' => $package->teamName,
                'name' => $package->name,
                'label' => $package->label,
                'initialRoles' => $package->initialRoleNames,
                'directPermissions' => $package->directPermissionNames,
                'templatePermissions' => $package->templatePermissionNames,
            ], $this->packages->all()),
            'copySources' => array_map(function (UserCredentialAccountOption $user): array {
                $assignmentsByTeam = [];

                foreach ($this->memberships->activeMembershipsForUser($user->publicId) as $membership) {
                    $assignment = $this->authorization->assignmentsForUserTeam($user->publicId, $membership->teamPublicId);
                    $assignmentsByTeam[$membership->teamPublicId] = [
                        'roles' => $assignment->roleNames,
                        'directPermissions' => $assignment->directPermissionNames,
                    ];
                }

                return [
                    'publicId' => $user->publicId,
                    'name' => $user->name,
                    'email' => $user->email,
                    'assignmentsByTeam' => $assignmentsByTeam,
                ];
            }, array_values(array_filter(
                $this->accounts->allOptions(),
                static fn (UserCredentialAccountOption $user): bool => $user->publicId !== $account->publicId,
            ))),
            'roleOptions' => $this->authorization->roleOptions(),
            'permissionOptions' => $this->authorization->permissionOptions(),
            'rolePermissionMap' => $this->authorization->rolePermissionMap(),
            'sessionDefaults' => [
                'inactivityTimeoutMinutes' => $this->sessionSettings->inactivityTimeoutMinutes(),
                'sessionMaxLifetimeMinutes' => $this->globalSessionMaxLifetimeMinutes(),
            ],
        ]);
    }

    /**
     * @param  list<string>  $teamPublicIds
     * @return array<string, array{inactivityTimeoutMinutes: int, sessionMaxLifetimeMinutes: int, breakDailyLimitMinutes: int, breakMaximumSingleMinutes: int}>
     */
    private function teamPolicyDefaults(array $teamPublicIds): array
    {
        $defaults = [];

        foreach ($teamPublicIds as $teamPublicId) {
            $sessionLimits = $this->sessionLimits->resolvedForTeam($teamPublicId);
            $breakLimits = $this->breakPolicies->resolvedForTeam($teamPublicId);
            $defaults[$teamPublicId] = [
                'inactivityTimeoutMinutes' => $sessionLimits['inactivityTimeoutMinutes'],
                'sessionMaxLifetimeMinutes' => $sessionLimits['sessionMaxLifetimeMinutes'],
                'breakDailyLimitMinutes' => $breakLimits['dailyLimitMinutes'],
                'breakMaximumSingleMinutes' => $breakLimits['maximumSingleBreakMinutes'],
            ];
        }

        return $defaults;
    }

    private function globalSessionMaxLifetimeMinutes(): int
    {
        $configured = config('atlas.security.sessions.max_lifetime_minutes', 720);

        return max(1, is_numeric($configured) ? (int) $configured : 720);
    }

    private function actorPublicId(Request $request): ?string
    {
        $actor = $request->user();
        $publicId = $actor instanceof Model ? $actor->getAttribute('public_id') : null;

        return is_string($publicId) ? $publicId : null;
    }
}
