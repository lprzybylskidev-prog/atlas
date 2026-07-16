<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application;

use App\Modules\Core\Authorization\Application\Public\Contracts\UserAuthorizationAssignmentCopier;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserOnboardingPackageApplier;
use App\Modules\Core\Users\Application\Commands\CreateUserAccountCommand;
use App\Modules\Core\Users\Application\Contracts\FirstPasswordLinkIssuer;
use App\Modules\Core\Users\Application\Contracts\UserAccountRepository;
use App\Modules\Core\Users\Application\DTOs\CreatedUserAccount;
use App\Modules\Core\Users\Application\Exceptions\InvalidUserAccountData;
use Illuminate\Support\Str;

final readonly class CreateUserAccount
{
    public function __construct(
        private UserAccountRepository $accounts,
        private FirstPasswordLinkIssuer $firstPasswordLinks,
        private UserOnboardingPackageApplier $onboardingPackages,
        private UserAuthorizationAssignmentCopier $assignmentCopier,
    ) {}

    public function handle(CreateUserAccountCommand $command): CreatedUserAccount
    {
        $normalized = new CreateUserAccountCommand(
            name: trim($command->name),
            email: Str::lower(trim($command->email)),
            onboardingPackageName: $command->onboardingPackageName === null ? null : trim($command->onboardingPackageName),
            teamPublicId: $command->teamPublicId === null ? null : trim($command->teamPublicId),
            actorPublicId: $command->actorPublicId === null ? null : trim($command->actorPublicId),
            copyAuthorizationFromUserPublicId: $command->copyAuthorizationFromUserPublicId === null ? null : trim($command->copyAuthorizationFromUserPublicId),
        );

        if ($normalized->name === '') {
            throw InvalidUserAccountData::missingName();
        }

        if (filter_var($normalized->email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidUserAccountData::invalidEmail();
        }

        if ($this->accounts->existsByEmail($normalized->email)) {
            throw InvalidUserAccountData::duplicateEmail($normalized->email);
        }

        $onboardingPackageName = $normalized->onboardingPackageName ?? '';
        $copyAuthorizationFromUserPublicId = $normalized->copyAuthorizationFromUserPublicId ?? '';
        $authorizationTeamPublicId = '';
        $hasOnboardingPackage = $onboardingPackageName !== '';
        $hasCopySource = $copyAuthorizationFromUserPublicId !== '';

        if ($hasOnboardingPackage && $hasCopySource) {
            throw InvalidUserAccountData::conflictingAuthorizationSources();
        }

        if ($hasOnboardingPackage || $hasCopySource) {
            $authorizationTeamPublicId = $normalized->teamPublicId ?? '';

            if ($authorizationTeamPublicId === '') {
                throw InvalidUserAccountData::missingTeamForAuthorizationAssignment();
            }
        }

        $account = $this->accounts->createAwaitingFirstPassword(
            command: $normalized,
            internalPassword: Str::password(length: 64),
        );

        $this->firstPasswordLinks->issue($account->email);

        if ($hasOnboardingPackage || $hasCopySource) {
            if ($hasOnboardingPackage) {
                $this->onboardingPackages->applyDuringUserCreation(
                    packageName: $onboardingPackageName,
                    userPublicId: $account->publicId,
                    teamPublicId: $authorizationTeamPublicId,
                    actorPublicId: $normalized->actorPublicId,
                );
            }

            if ($hasCopySource) {
                $this->assignmentCopier->copyForUserCreation(
                    sourceUserPublicId: $copyAuthorizationFromUserPublicId,
                    targetUserPublicId: $account->publicId,
                    teamPublicId: $authorizationTeamPublicId,
                );
            }
        }

        return new CreatedUserAccount(
            publicId: $account->publicId,
            name: $account->name,
            email: $account->email,
            firstPasswordLinkIssued: true,
        );
    }
}
