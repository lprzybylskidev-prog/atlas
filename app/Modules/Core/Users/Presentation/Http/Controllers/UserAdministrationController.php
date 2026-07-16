<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Identity\Application\Public\DTOs\AdminUserCredentialAccount;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserAdministrationController
{
    public function __construct(
        private UserCredentialAccountDirectory $accounts,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => array_map(static fn (AdminUserCredentialAccount $user): array => [
                'id' => $user->id,
                'publicId' => $user->publicId,
                'name' => $user->name,
                'email' => $user->email,
                'isActive' => $user->isActive,
                'emailVerified' => $user->emailVerified,
                'firstPasswordSet' => $user->firstPasswordSet,
                'loginLocked' => $user->loginLocked,
                'mfaEnabled' => $user->mfaEnabled,
                'emailVerifiedAt' => $user->emailVerifiedAt,
                'twoFactorConfirmedAt' => $user->twoFactorConfirmedAt,
                'firstPasswordSetAt' => $user->firstPasswordSetAt,
                'deactivatedAt' => $user->deactivatedAt,
                'failedLoginAttempts' => $user->failedLoginAttempts,
                'loginLockCount' => $user->loginLockCount,
                'loginLockedUntil' => $user->loginLockedUntil,
                'createdAt' => $user->createdAt,
                'updatedAt' => $user->updatedAt,
            ], $this->accounts->allAdminRows()),
        ]);
    }
}
