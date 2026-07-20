<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationManager;
use App\Modules\Core\Identity\Application\Public\Contracts\ImpersonationEligibilityChecker;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
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
        private UserTeamAuthorizationManager $authorization,
        private ImpersonationEligibilityChecker $impersonation,
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

                return [
                    'teamPublicId' => $membership->teamPublicId,
                    'teamName' => $membership->teamName,
                    'teamActive' => $membership->teamActive,
                    'validFrom' => $membership->validFrom,
                    'validTo' => $membership->validTo,
                    'roleNames' => $assignments->roleNames,
                    'directPermissionNames' => $assignments->directPermissionNames,
                ];
            }, $this->memberships->activeMembershipsForUser($account->publicId)),
            'assignableTeams' => array_map(static fn ($team): array => [
                'value' => $team->publicId,
                'label' => $team->name,
            ], $this->memberships->assignableTeamsForUser($account->publicId)),
            'roleOptions' => $this->authorization->roleOptions(),
            'permissionOptions' => $this->authorization->permissionOptions(),
        ]);
    }

    private function actorPublicId(Request $request): ?string
    {
        $actor = $request->user();
        $publicId = $actor instanceof Model ? $actor->getAttribute('public_id') : null;

        return is_string($publicId) ? $publicId : null;
    }
}
