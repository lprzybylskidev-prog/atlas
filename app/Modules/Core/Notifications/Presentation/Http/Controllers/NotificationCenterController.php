<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Http\Controllers;

use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationInbox;
use App\Modules\Core\Notifications\Application\Public\DTOs\NotificationSummary;
use App\Modules\Core\Notifications\Presentation\Support\NotificationTextLocalizer;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationCenterController
{
    public function __construct(
        private NotificationInbox $notifications,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::NOTIFICATIONS);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $localizer = new NotificationTextLocalizer;

        $rows = is_string($userPublicId)
            ? array_map(
                static fn (NotificationSummary $notification): array => [
                    'publicId' => $notification->publicId,
                    'type' => $notification->type,
                    'severity' => $notification->severity,
                    'title' => $localizer->title($notification),
                    'body' => $localizer->body($notification) ?? '',
                    'teamPublicId' => $notification->teamPublicId ?? '',
                    'read' => $notification->read,
                    'createdAt' => $notification->createdAt,
                    'readAt' => $notification->readAt ?? '',
                    'deepLinkUrl' => $notification->deepLinkUrl ?? '',
                ],
                $this->notifications->allForUser($userPublicId, is_string($teamPublicId) ? $teamPublicId : null),
            )
            : [];

        $result = $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));

        return Inertia::render('Notifications/Index', [
            'notificationRows' => $result->rows,
            'table' => $result->tableMeta($definition->key),
        ]);
    }
}
