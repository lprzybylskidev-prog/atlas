<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;
use App\Modules\Core\Users\Application\ActivateUserAccount;
use App\Modules\Core\Users\Application\Commands\ActivateUserAccountCommand;
use App\Modules\Core\Users\Application\Commands\DeactivateUserAccountCommand;
use App\Modules\Core\Users\Application\Commands\ResetUserMfaCommand;
use App\Modules\Core\Users\Application\Commands\UnlockUserAccountCommand;
use App\Modules\Core\Users\Application\Contracts\FirstPasswordLinkIssuer;
use App\Modules\Core\Users\Application\DeactivateUserAccount;
use App\Modules\Core\Users\Application\ResetUserMfa;
use App\Modules\Core\Users\Application\UnlockUserAccount;
use App\Shared\Presentation\Support\FlashMessage;
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
        private UserSessionRegistry $sessions,
        private SecurityAuditRecorder $audit,
    ) {}

    public function activate(string $user): RedirectResponse
    {
        $this->activate->handle(new ActivateUserAccountCommand($user));

        return redirect()->route('admin.users.index')->with('flash.messages', [
            FlashMessage::success('flash.users.activated'),
        ]);
    }

    public function deactivate(string $user): RedirectResponse
    {
        $this->deactivate->handle(new DeactivateUserAccountCommand($user));

        return redirect()->route('admin.users.index')->with('flash.messages', [
            FlashMessage::success('flash.users.deactivated'),
        ]);
    }

    public function verifyEmail(string $user): RedirectResponse
    {
        $this->accounts->verifyEmail($user);

        return redirect()->route('admin.users.index')->with('flash.messages', [
            FlashMessage::success('flash.users.email_verified'),
        ]);
    }

    public function requireEmailVerification(string $user): RedirectResponse
    {
        $this->accounts->requireEmailVerification($user);

        return redirect()->route('admin.users.index')->with('flash.messages', [
            FlashMessage::success('flash.users.email_verification_required'),
        ]);
    }

    public function resendFirstPassword(Request $request, string $user): RedirectResponse
    {
        foreach ($this->accounts->allAdminRows() as $account) {
            if ($account->publicId === $user) {
                $this->firstPasswordLinks->issue($account->email);
                break;
            }
        }

        return redirect()->route('admin.users.index')->with('flash.messages', [
            FlashMessage::success('flash.users.first_password_sent'),
        ]);
    }

    public function unlock(Request $request, string $user): RedirectResponse
    {
        $actorPublicId = data_get($request->user(), 'public_id');

        $this->unlock->handle(new UnlockUserAccountCommand(
            targetPublicId: $user,
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : '',
            reason: 'Admin user action',
        ));

        return redirect()->route('admin.users.index')->with('flash.messages', [
            FlashMessage::success('flash.users.login_unlocked'),
        ]);
    }

    public function resetMfa(Request $request, string $user): RedirectResponse
    {
        $actorPublicId = data_get($request->user(), 'public_id');

        $this->resetMfa->handle(new ResetUserMfaCommand(
            targetPublicId: $user,
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : '',
            reason: 'Admin user action',
        ));

        return redirect()->route('admin.users.index')->with('flash.messages', [
            FlashMessage::success('flash.users.mfa_reset'),
        ]);
    }

    public function invalidateSessions(Request $request, string $user): RedirectResponse
    {
        $this->sessions->invalidateUser($user);
        $actorPublicId = data_get($request->user(), 'public_id');

        $this->audit->record(new SecurityAuditEvent(
            module: 'identity',
            action: 'user.sessions_invalidated',
            result: 'succeeded',
            source: 'ui',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            targetPublicId: $user,
            reason: 'Admin user action',
            category: SecurityAuditCategory::Session,
        ));

        return redirect()->route('admin.users.index')->with('flash.messages', [
            FlashMessage::success('flash.users.sessions_invalidated'),
        ]);
    }
}
