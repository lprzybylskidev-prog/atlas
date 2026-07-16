<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final readonly class EditUserAccountController
{
    public function __construct(
        private UserCredentialAccountDirectory $accounts,
    ) {}

    public function __invoke(string $user): Response
    {
        $account = $this->accounts->findAdminRow($user);

        if ($account === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

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
            ],
        ]);
    }
}
