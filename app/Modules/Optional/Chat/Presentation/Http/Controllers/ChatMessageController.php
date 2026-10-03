<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Optional\Chat\Application\Contracts\ChatRealtimePublisher;
use App\Modules\Optional\Chat\Application\MessageManager;
use App\Modules\Optional\Chat\Presentation\Support\ChatRealtimePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final readonly class ChatMessageController
{
    public function __construct(
        private MessageManager $messages,
        private ChatRealtimePublisher $realtime,
    ) {}

    public function store(Request $request, string $conversation): JsonResponse
    {
        $request->validate([
            'body' => ['present', 'nullable', 'string', 'max:20000'],
            'client_message_key' => ['required', 'string', 'max:120'],
            'reply_to_message_public_id' => ['nullable', 'string', 'size:26'],
            'mentioned_user_public_ids' => ['array'],
            'mentioned_user_public_ids.*' => ['string', 'size:26'],
            'attachment_public_ids' => ['array', 'max:20'],
            'attachment_public_ids.*' => ['string', 'size:26'],
        ]);
        $body = $request->string('body')->toString();
        $clientMessageKey = $request->input('client_message_key');
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
            replyToMessagePublicId: $request->filled('reply_to_message_public_id') ? $request->string('reply_to_message_public_id')->toString() : null,
            mentionedUserPublicIds: $this->strings($request->input('mentioned_user_public_ids', [])),
            attachmentPublicIds: $this->strings($request->input('attachment_public_ids', [])),
        );

        $payload = ChatRealtimePayload::message($message);
        $this->realtime->conversation($conversation, 'chat.message.created', [
            'conversationPublicId' => $conversation,
            'message' => $payload,
        ]);

        return response()->json($payload, 201);
    }

    public function update(Request $request, string $conversation, string $message): JsonResponse
    {
        $values = $request->validate([
            'action' => ['required', 'string', 'in:edit,delete_for_me,react,remove_reaction,pin,unpin,bookmark,remove_bookmark,forward'],
            'body' => ['nullable', 'string', 'max:20000'],
            'expected_version' => ['nullable', 'integer', 'min:1'],
            'emoji' => ['nullable', 'string', 'max:32'],
            'destination_conversation_public_id' => ['nullable', 'string', 'size:26'],
            'client_message_key' => ['nullable', 'string', 'max:120'],
        ]);
        [$userPublicId, $teamPublicId] = $this->context($request);
        $action = $request->string('action')->toString();
        $expectedVersion = $request->input('expected_version');
        $result = match ($action) {
            'edit' => $this->messages->edit(
                $userPublicId,
                $teamPublicId,
                $conversation,
                $message,
                is_numeric($expectedVersion) ? (int) $expectedVersion : throw new InvalidArgumentException('Expected message version is required.'),
                $request->string('body')->toString(),
            ),
            'delete_for_me' => $this->messages->deleteForMe($userPublicId, $teamPublicId, $conversation, $message),
            'react' => $this->messages->react($userPublicId, $teamPublicId, $conversation, $message, $this->requiredString($request, 'emoji')),
            'remove_reaction' => $this->messages->removeReaction($userPublicId, $teamPublicId, $conversation, $message, $this->requiredString($request, 'emoji')),
            'pin' => $this->messages->pin($userPublicId, $teamPublicId, $conversation, $message),
            'unpin' => $this->messages->unpin($userPublicId, $teamPublicId, $conversation, $message),
            'bookmark' => $this->messages->bookmark($userPublicId, $teamPublicId, $conversation, $message),
            'remove_bookmark' => $this->messages->removeBookmark($userPublicId, $teamPublicId, $conversation, $message),
            'forward' => $this->messages->forward(
                $userPublicId,
                $teamPublicId,
                $conversation,
                $message,
                $this->requiredString($request, 'destination_conversation_public_id'),
                $this->requiredString($request, 'client_message_key'),
            ),
            default => throw new InvalidArgumentException('Unsupported Chat message action.'),
        };

        return response()->json(ChatRealtimePayload::message($result));
    }

    public function history(Request $request, string $conversation, string $message): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        return response()->json(['revisions' => array_map(static fn ($revision): array => [
            'version' => $revision->version,
            'body' => $revision->body,
            'renderedHtml' => $revision->renderedHtml,
            'createdAt' => $revision->createdAt->format(DATE_ATOM),
        ], $this->messages->editHistory($userPublicId, $teamPublicId, $conversation, $message))]);
    }

    public function draft(Request $request, string $conversation): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        if ($request->isMethod('put')) {
            $request->validate([
                'body' => ['present', 'nullable', 'string', 'max:20000'],
                'reply_to_message_public_id' => ['nullable', 'string', 'size:26'],
            ]);
            $draft = $this->messages->saveDraft(
                $userPublicId,
                $teamPublicId,
                $conversation,
                $request->string('body')->toString(),
                $request->filled('reply_to_message_public_id') ? $request->string('reply_to_message_public_id')->toString() : null,
            );
        } else {
            $draft = $this->messages->draft($userPublicId, $teamPublicId, $conversation);
        }

        return response()->json(['draft' => $draft === null ? null : [
            'body' => $draft->body,
            'replyToMessagePublicId' => $draft->replyToMessagePublicId,
            'version' => $draft->version,
            'updatedAt' => $draft->updatedAt->format(DATE_ATOM),
        ]]);
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

    private function requiredString(Request $request, string $key): string
    {
        $value = $request->input($key);

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('Chat message field [%s] is required.', $key));
        }

        return $value;
    }
}
