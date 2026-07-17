<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Http\Controllers;

use App\Modules\Core\Notifications\Application\Public\Contracts\RealtimeFeed;
use App\Modules\Core\Notifications\Application\Public\DTOs\RealtimeEventSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class RealtimeEventsController
{
    public function __construct(
        private RealtimeFeed $events,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId)) {
            return response()->json(['events' => []]);
        }

        return response()->json([
            'events' => array_map(
                static fn (RealtimeEventSummary $event): array => [
                    'publicId' => $event->publicId,
                    'topic' => $event->topic,
                    'eventType' => $event->eventType,
                    'teamPublicId' => $event->teamPublicId,
                    'payload' => $event->payload,
                    'createdAt' => $event->createdAt,
                ],
                $this->events->visibleEvents(
                    userPublicId: $userPublicId,
                    teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
                    afterPublicId: $request->query('after') === null ? null : (string) $request->query('after'),
                    limit: max(1, min(100, $request->integer('limit', 50))),
                ),
            ),
        ]);
    }
}
