<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Http\Controllers;

use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class BulkMarkNotificationReadController
{
    public function __construct(
        private NotificationInbox $notifications,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notifications' => ['required', 'array', 'min:1', 'max:5000'],
            'notifications.*' => ['required', 'string'],
        ]);
        $userPublicId = data_get($request->user(), 'public_id');
        $notificationIds = is_array($validated) && is_array($validated['notifications'] ?? null) ? $validated['notifications'] : [];

        if (is_string($userPublicId)) {
            foreach ($notificationIds as $notificationPublicId) {
                if (is_string($notificationPublicId)) {
                    $this->notifications->markRead($userPublicId, $notificationPublicId);
                }
            }
        }

        return back()->with('success', 'Notifications were marked as read.');
    }
}
