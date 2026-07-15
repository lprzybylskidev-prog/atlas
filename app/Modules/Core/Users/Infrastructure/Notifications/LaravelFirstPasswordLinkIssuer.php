<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Infrastructure\Notifications;

use App\Modules\Core\Users\Application\Contracts\FirstPasswordLinkIssuer;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use RuntimeException;

final class LaravelFirstPasswordLinkIssuer implements FirstPasswordLinkIssuer
{
    public function issue(string $email): void
    {
        $status = Password::broker()->sendResetLink(
            ['email' => $email],
            static function (CanResetPassword $user, string $token): void {
                Notification::route('mail', $user->getEmailForPasswordReset())
                    ->notify(new FirstPasswordSetupNotification($token, $user->getEmailForPasswordReset()));
            },
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw new RuntimeException(sprintf('First password link could not be issued. Status: %s.', $status));
        }
    }
}
