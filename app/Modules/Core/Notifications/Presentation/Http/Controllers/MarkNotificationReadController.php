<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Http\Controllers;

use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationInbox;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class MarkNotificationReadController
{
    public function __construct(
        private NotificationInbox $notifications,
    ) {}

    public function __invoke(Request $request, string $notification): RedirectResponse
    {
        $userPublicId = data_get($request->user(), 'public_id');

        if (is_string($userPublicId)) {
            $this->notifications->markRead($userPublicId, $notification);
        }

        return back()->with('flash.messages', [
            FlashMessage::success('flash.notifications.marked_read'),
        ]);
    }
}
