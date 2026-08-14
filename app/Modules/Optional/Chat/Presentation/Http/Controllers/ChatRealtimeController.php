<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Optional\Chat\Application\RealtimeManager;
use App\Modules\Optional\Chat\Domain\Realtime\ManualStatus;
use App\Modules\Optional\Chat\Presentation\Support\ChatRealtimePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ChatRealtimeController
{
    public function __construct(private RealtimeManager $realtime) {}

    public function reconcile(Request $request, string $conversation): JsonResponse
    {
        $values = $request->validate(['after_message_public_id' => ['nullable', 'string', 'size:26']]);

        if (! is_array($values)) {
            abort(422);
        }

        [$userPublicId, $teamPublicId] = $this->context($request);
        $result = $this->realtime->reconcile(
            $userPublicId,
            $teamPublicId,
            $conversation,
            is_string($values['after_message_public_id'] ?? null) ? $values['after_message_public_id'] : null,
        );

        return response()->json([
            'messages' => array_map([ChatRealtimePayload::class, 'message'], $result['messages']),
            'state' => ChatRealtimePayload::state($result['state']),
            'participantCursors' => array_map([ChatRealtimePayload::class, 'cursor'], $result['participantCursors']),
            'presence' => array_map([ChatRealtimePayload::class, 'presence'], $result['presence']),
            'totalUnread' => $result['totalUnread'],
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        return response()->json(ChatRealtimePayload::presence($this->realtime->heartbeat($userPublicId, $teamPublicId)));
    }

    public function status(Request $request): JsonResponse
    {
        $values = $request->validate([
            'status' => ['required', 'string', 'in:available,busy,do_not_disturb,out_of_office'],
            'custom_text' => ['nullable', 'string', 'max:120'],
            'custom_emoji' => ['nullable', 'string', 'max:16'],
        ]);

        if (! is_array($values)) {
            abort(422);
        }

        [$userPublicId, $teamPublicId] = $this->context($request);
        $statusValue = $values['status'] ?? null;

        if (! is_string($statusValue)) {
            abort(422);
        }

        $status = ManualStatus::from($statusValue);

        return response()->json(ChatRealtimePayload::presence($this->realtime->updateStatus(
            $userPublicId,
            $teamPublicId,
            $status,
            is_string($values['custom_text'] ?? null) ? $values['custom_text'] : null,
            is_string($values['custom_emoji'] ?? null) ? $values['custom_emoji'] : null,
        )));
    }

    public function delivered(Request $request, string $conversation, string $message): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);
        $this->realtime->markDelivered($userPublicId, $teamPublicId, $conversation, $message);

        return response()->json(['ok' => true]);
    }

    public function read(Request $request, string $conversation, string $message): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);
        $this->realtime->markRead($userPublicId, $teamPublicId, $conversation, $message);

        return response()->json(['ok' => true]);
    }

    public function unread(Request $request, string $conversation, string $message): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);
        $this->realtime->markUnread($userPublicId, $teamPublicId, $conversation, $message);

        return response()->json(['ok' => true]);
    }

    public function unreadTotal(Request $request): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        return response()->json(['totalUnread' => $this->realtime->totalUnread($userPublicId, $teamPublicId)]);
    }

    /** @return array{string, string} */
    private function context(Request $request): array
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId) || ! is_string($teamPublicId)) {
            abort(403);
        }

        return [$userPublicId, $teamPublicId];
    }
}
