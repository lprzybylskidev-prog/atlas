<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationEmailPreferenceManager;
use App\Shared\Presentation\Support\FlashMessage;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class StoreNotificationEmailAddressController
{
    public function __construct(
        private NotificationEmailPreferenceManager $emails,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $user = $request->user();
        $userId = $this->intValue(data_get($user, 'id'));
        $primaryEmail = $this->stringValue(data_get($user, 'email'));

        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        $this->emails->addAddressForUser(
            $userId,
            $primaryEmail,
            data_get($user, 'email_verified_at') instanceof DateTimeInterface ? data_get($user, 'email_verified_at') : null,
            $request->string('email')->toString(),
            is_string($teamPublicId) ? $teamPublicId : null,
        );

        return back()->with('flash.messages', [
            FlashMessage::success('flash.user_profile.notification_email_added'),
        ]);
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
