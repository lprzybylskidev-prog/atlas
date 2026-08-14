<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Optional\Chat\Application\Contracts\ChatRealtimePublisher;
use App\Modules\Optional\Chat\Application\MessageManager;
use App\Modules\Optional\Chat\Presentation\Support\ChatRealtimePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ChatMessageController
{
    public function __construct(
        private MessageManager $messages,
        private ChatRealtimePublisher $realtime,
    ) {}

    public function store(Request $request, string $conversation): JsonResponse
    {
        $values = $request->validate([
            'body' => ['present', 'nullable', 'string', 'max:20000'],
            'client_message_key' => ['required', 'string', 'max:120'],
            'reply_to_message_public_id' => ['nullable', 'string', 'size:26'],
            'mentioned_user_public_ids' => ['array'],
            'mentioned_user_public_ids.*' => ['string', 'size:26'],
            'attachment_public_ids' => ['array', 'max:20'],
            'attachment_public_ids.*' => ['string', 'size:26'],
        ]);
        $body = is_array($values) && is_string($values['body'] ?? null) ? $values['body'] : '';
        $clientMessageKey = is_array($values) ? ($values['client_message_key'] ?? null) : null;
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId) || ! is_string($teamPublicId) || ! is_string($clientMessageKey)) {
            abort(403);
        }

        $message = $this->messages->send(
            actorPublicId: $userPublicId,
            activeTeamPublicId: $teamPublicId,
            conversationPublicId: $conversation,
            body: $body,
            clientMessageKey: $clientMessageKey,
            replyToMessagePublicId: is_string($values['reply_to_message_public_id'] ?? null) ? $values['reply_to_message_public_id'] : null,
            mentionedUserPublicIds: $this->strings($values['mentioned_user_public_ids'] ?? null),
            attachmentPublicIds: $this->strings($values['attachment_public_ids'] ?? null),
        );

        $payload = ChatRealtimePayload::message($message);
        $this->realtime->conversation($conversation, 'chat.message.created', [
            'conversationPublicId' => $conversation,
            'message' => $payload,
        ]);

        return response()->json($payload, 201);
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                abort(422);
            }

            $strings[] = $item;
        }

        return $strings;
    }
}
