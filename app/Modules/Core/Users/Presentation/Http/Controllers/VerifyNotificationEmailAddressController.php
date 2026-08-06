<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationEmailPreferenceManager;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class VerifyNotificationEmailAddressController
{
    public function __construct(
        private NotificationEmailPreferenceManager $emails,
    ) {}

    public function __invoke(Request $request, string $email, string $token): RedirectResponse
    {
        $user = $request->user();

        $verified = $this->emails->verifyForUser($this->intValue(data_get($user, 'id')), $email, $token);

        return redirect()->route('users.profile')->with('flash.messages', [
            $verified
                ? FlashMessage::success('flash.user_profile.notification_email_verified')
                : FlashMessage::error('flash.user_profile.notification_email_verification_failed'),
        ]);
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }
}
