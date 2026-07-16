<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Users\Application\ActivateUserAccount;
use App\Modules\Core\Users\Application\Commands\ActivateUserAccountCommand;
use App\Modules\Core\Users\Application\Commands\DeactivateUserAccountCommand;
use App\Modules\Core\Users\Application\Commands\ResetUserMfaCommand;
use App\Modules\Core\Users\Application\Commands\UnlockUserAccountCommand;
use App\Modules\Core\Users\Application\Contracts\FirstPasswordLinkIssuer;
use App\Modules\Core\Users\Application\DeactivateUserAccount;
use App\Modules\Core\Users\Application\ResetUserMfa;
use App\Modules\Core\Users\Application\UnlockUserAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class UserAccountActionController
{
    public function __construct(
        private ActivateUserAccount $activate,
        private DeactivateUserAccount $deactivate,
        private UnlockUserAccount $unlock,
        private ResetUserMfa $resetMfa,
        private UserCredentialAccountDirectory $accounts,
        private FirstPasswordLinkIssuer $firstPasswordLinks,
    ) {}

    public function activate(string $user): RedirectResponse
    {
        $this->activate->handle(new ActivateUserAccountCommand($user));

        return redirect()->route('admin.users.index')->with('success', 'User was activated.');
    }

    public function deactivate(string $user): RedirectResponse
    {
        $this->deactivate->handle(new DeactivateUserAccountCommand($user));

        return redirect()->route('admin.users.index')->with('success', 'User was deactivated.');
    }

    public function verifyEmail(string $user): RedirectResponse
    {
        $this->accounts->verifyEmail($user);

        return redirect()->route('admin.users.index')->with('success', 'User email was verified.');
    }

    public function requireEmailVerification(string $user): RedirectResponse
    {
        $this->accounts->requireEmailVerification($user);

        return redirect()->route('admin.users.index')->with('success', 'User email verification was required again.');
    }

    public function resendFirstPassword(Request $request, string $user): RedirectResponse
    {
        foreach ($this->accounts->allAdminRows() as $account) {
            if ($account->publicId === $user) {
                $this->firstPasswordLinks->issue($account->email);
                break;
            }
        }

        return redirect()->route('admin.users.index')->with('success', 'First-password link was sent.');
    }

    public function unlock(Request $request, string $user): RedirectResponse
    {
        $actorPublicId = data_get($request->user(), 'public_id');

        $this->unlock->handle(new UnlockUserAccountCommand(
            targetPublicId: $user,
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : '',
            reason: 'Admin user action',
        ));

        return redirect()->route('admin.users.index')->with('success', 'User login was unlocked.');
    }

    public function resetMfa(Request $request, string $user): RedirectResponse
    {
        $actorPublicId = data_get($request->user(), 'public_id');

        $this->resetMfa->handle(new ResetUserMfaCommand(
            targetPublicId: $user,
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : '',
            reason: 'Admin user action',
        ));

        return redirect()->route('admin.users.index')->with('success', 'User MFA was reset.');
    }
}
