<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Inertia;

use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationInbox;
use App\Modules\Core\Notifications\Application\Public\DTOs\NotificationSummary;
use App\Modules\Core\Notifications\Presentation\Support\NotificationTextLocalizer;
use App\Shared\Presentation\Inertia\Contracts\InertiaSharedDataContributor;
use Illuminate\Http\Request;

final readonly class NotificationsInertiaData implements InertiaSharedDataContributor
{
    public function __construct(
        private NotificationInbox $notifications,
    ) {}

    public function key(): string
    {
        return 'core.notifications';
    }

    public function data(Request $request): array
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId)) {
            return [
                'notifications' => [
                    'unreadCount' => 0,
                    'latest' => [],
                ],
            ];
        }

        $team = is_string($teamPublicId) ? $teamPublicId : null;
        $localizer = new NotificationTextLocalizer;

        return [
            'notifications' => [
                'unreadCount' => $this->notifications->unreadCount($userPublicId, $team),
                'latest' => array_map(
                    static fn (NotificationSummary $notification): array => [
                        'publicId' => $notification->publicId,
                        'type' => $notification->type,
                        'severity' => $notification->severity,
                        'title' => $localizer->title($notification),
                        'body' => $localizer->body($notification),
                        'deepLinkUrl' => $notification->deepLinkUrl,
                        'teamPublicId' => $notification->teamPublicId,
                        'read' => $notification->read,
                        'createdAt' => $notification->createdAt,
                        'readAt' => $notification->readAt,
                    ],
                    $this->notifications->latestForUser($userPublicId, $team, 10),
                ),
            ],
        ];
    }
}
