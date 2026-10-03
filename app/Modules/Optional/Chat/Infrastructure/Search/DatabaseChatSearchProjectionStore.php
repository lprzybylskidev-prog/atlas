<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Search;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\ChatSearch;
use App\Modules\Optional\Chat\Application\Contracts\ChatSearchProjectionStore;
use App\Modules\Optional\Chat\Application\DTOs\ChatSearchCandidate;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationType;
use App\Modules\Optional\Chat\Domain\Meetings\TranscriptionStatus;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use stdClass;

final readonly class DatabaseChatSearchProjectionStore implements ChatSearchProjectionStore
{
    public function __construct(
        private ConnectionInterface $database,
        private UserLookup $users,
    ) {}

    public function documents(): iterable
    {
        foreach ($this->users->allActiveDisplaySummaries() as $user) {
            yield $this->document('user-'.$user->publicId, [
                'result_type' => 'user',
                'user_public_id' => $user->publicId,
                'title' => $user->name,
                'body' => $user->email,
                'author_name' => $user->name,
                'global_scope' => true,
                'occurred_at' => '1970-01-01T00:00:00+00:00',
            ]);
        }

        foreach ($this->conversationRows() as $conversation) {
            yield $this->conversationDocument($conversation);
        }

        foreach ($this->messageRows() as $message) {
            yield $this->messageDocument($message);
            foreach ($this->attachmentRows($this->integer($message->id)) as $attachment) {
                yield $this->attachmentDocument($message, $attachment);
            }
            if ($this->links($this->string($message->body)) !== []) {
                yield $this->linkDocument($message);
            }
        }

        foreach ($this->transcriptRows() as $transcript) {
            yield $this->transcriptDocument($transcript);
        }
    }

    public function expectedDocumentCount(): int
    {
        return iterator_count((function (): \Generator {
            yield from $this->documents();
        })());
    }

    public function documentsForSource(string $type, string $publicId): array
    {
        if ($type === 'conversation') {
            $row = $this->database->table(ChatDatabaseTable::CONVERSATIONS)->where('public_id', $publicId)->whereNull('closed_at')->first();

            return $row instanceof stdClass ? [$this->conversationDocument($row)] : [];
        }
        if ($type === 'message') {
            $message = $this->database->table(ChatDatabaseTable::MESSAGES.' as messages')
                ->join(ChatDatabaseTable::CONVERSATIONS.' as conversations', 'conversations.id', '=', 'messages.conversation_id')
                ->where('messages.public_id', $publicId)->first(['messages.*', 'conversations.public_id as conversation_public_id',
                    'conversations.type', 'conversations.name', 'conversations.team_public_id']);
            if (! $message instanceof stdClass) {
                return [];
            }
            $documents = [$this->messageDocument($message)];
            foreach ($this->attachmentRows($this->integer($message->id)) as $attachment) {
                $documents[] = $this->attachmentDocument($message, $attachment);
            }
            if ($this->links($this->string($message->body)) !== []) {
                $documents[] = $this->linkDocument($message);
            }

            return $documents;
        }
        if ($type === 'transcript') {
            $row = $this->transcriptQuery()->where('transcriptions.public_id', $publicId)->first();

            return $row instanceof stdClass ? [$this->transcriptDocument($row)] : [];
        }

        return [];
    }

    public function deletedDocumentIdsForSource(string $type, string $publicId): array
    {
        if ($type === 'transcript' && $this->documentsForSource($type, $publicId) === []) {
            return ['transcript-'.$publicId];
        }
        if ($type === 'message') {
            $body = $this->database->table(ChatDatabaseTable::MESSAGES)->where('public_id', $publicId)->value('body');
            if (! is_string($body) || $this->links($body) === []) {
                return ['link-'.$publicId];
            }
        }

        return [];
    }

    public function resolve(string $documentId, int $viewerUserId): ?ChatSearchCandidate
    {
        [$type, $publicId] = array_pad(explode('-', $documentId, 2), 2, null);

        return match ($type) {
            'user' => $this->resolveUser($publicId),
            'conversation' => $this->resolveConversation($publicId, $viewerUserId),
            'message' => $this->resolveMessage($publicId, $viewerUserId),
            'attachment' => $this->resolveAttachment($publicId, $viewerUserId),
            'link' => $this->resolveLink($publicId, $viewerUserId),
            'transcript' => $this->resolveTranscript($publicId, $viewerUserId),
            default => null,
        };
    }

    private function resolveUser(?string $publicId): ?ChatSearchCandidate
    {
        if ($publicId === null) {
            return null;
        }
        foreach ($this->users->allActiveDisplaySummaries() as $user) {
            if ($user->publicId === $publicId) {
                return new ChatSearchCandidate('user', $publicId, $user->name, $user->email);
            }
        }

        return null;
    }

    private function resolveConversation(?string $publicId, int $viewerUserId): ?ChatSearchCandidate
    {
        $row = $publicId === null ? null : $this->database->table(ChatDatabaseTable::CONVERSATIONS)
            ->where('public_id', $publicId)->whereNull('closed_at')->first();
        if (! $row instanceof stdClass) {
            return null;
        }
        if ($this->meetingConversationBlocked($this->integer($row->id), $viewerUserId)) {
            return null;
        }

        return new ChatSearchCandidate(
            'conversation',
            $publicId,
            $this->conversationTitle($row, $viewerUserId),
            '',
            $publicId,
        );
    }

    private function resolveMessage(?string $publicId, int $viewerUserId): ?ChatSearchCandidate
    {
        $row = $this->visibleMessageRow($publicId, $viewerUserId);
        if (! $row instanceof stdClass) {
            return null;
        }
        $authorId = $this->integer($row->author_user_id);
        $author = $this->users->displaySummariesForInternalIds([$authorId])[$authorId] ?? null;

        return new ChatSearchCandidate(
            'message',
            $this->string($row->public_id),
            $this->conversationTitle($row, $viewerUserId),
            $this->string($row->body),
            $this->string($row->conversation_public_id),
            $this->string($row->public_id),
            occurredAt: $this->string($row->created_at),
            authorName: $author?->name,
        );
    }

    private function resolveAttachment(?string $publicId, int $viewerUserId): ?ChatSearchCandidate
    {
        $row = $publicId === null ? null : $this->database->table(ChatDatabaseTable::MESSAGE_ATTACHMENTS.' as attachments')
            ->join(ChatDatabaseTable::MESSAGES.' as messages', 'messages.id', '=', 'attachments.message_id')
            ->join(ChatDatabaseTable::CONVERSATIONS.' as conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('attachments.public_id', $publicId)->whereNull('attachments.discarded_at')
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from(ChatDatabaseTable::MESSAGE_DELETIONS.' as deletions')
                ->whereColumn('deletions.message_id', 'messages.id')->where('deletions.user_id', $viewerUserId))
            ->first(['attachments.*', 'messages.public_id as message_public_id', 'messages.created_at as message_created_at', 'messages.conversation_id',
                'conversations.public_id as conversation_public_id', 'conversations.type', 'conversations.name', 'conversations.team_public_id']);
        if (! $row instanceof stdClass) {
            return null;
        }
        if ($this->meetingConversationBlocked($this->integer($row->conversation_id), $viewerUserId)) {
            return null;
        }

        return new ChatSearchCandidate('file', $publicId, $this->conversationTitle($row, $viewerUserId), $this->string($row->original_name),
            $this->string($row->conversation_public_id), $this->string($row->message_public_id), occurredAt: $this->string($row->message_created_at));
    }

    private function resolveLink(?string $messagePublicId, int $viewerUserId): ?ChatSearchCandidate
    {
        $row = $this->visibleMessageRow($messagePublicId, $viewerUserId);
        if (! $row instanceof stdClass) {
            return null;
        }
        $links = $this->links($this->string($row->body));
        if ($links !== []) {
            return new ChatSearchCandidate('link', $messagePublicId ?? '', $this->conversationTitle($row, $viewerUserId), implode("\n", $links),
                $this->string($row->conversation_public_id), $this->string($row->public_id), occurredAt: $this->string($row->created_at));
        }

        return null;
    }

    private function resolveTranscript(?string $publicId, int $viewerUserId): ?ChatSearchCandidate
    {
        $row = $publicId === null ? null : $this->transcriptQuery()->where('transcriptions.public_id', $publicId)->first();
        if (! $row instanceof stdClass) {
            return null;
        }
        $transcriptionId = $this->integer($row->id);
        $participant = $this->transcriptParticipant($transcriptionId, $this->integer($row->occurrence_id), $viewerUserId);
        $shared = $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPT_SHARES)
            ->where('transcription_id', $transcriptionId)->where('recipient_user_id', $viewerUserId)->whereNull('revoked_at')->exists();
        if (! $participant && ! $shared) {
            return null;
        }

        return new ChatSearchCandidate(
            'transcript',
            $publicId,
            $participant ? $this->string($row->meeting_title) : '',
            $this->string($row->current_text),
            $participant ? $this->string($row->conversation_public_id) : null,
            transcriptionPublicId: $publicId,
            occurredAt: $this->string($row->updated_at),
            transcriptParticipant: $participant,
        );
    }

    private function visibleMessageRow(?string $publicId, int $viewerUserId): ?stdClass
    {
        if ($publicId === null) {
            return null;
        }

        $row = $this->database->table(ChatDatabaseTable::MESSAGES.' as messages')
            ->join(ChatDatabaseTable::CONVERSATIONS.' as conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('messages.public_id', $publicId)
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from(ChatDatabaseTable::MESSAGE_DELETIONS.' as deletions')
                ->whereColumn('deletions.message_id', 'messages.id')->where('deletions.user_id', $viewerUserId))
            ->first(['messages.*', 'conversations.public_id as conversation_public_id', 'conversations.type', 'conversations.name', 'conversations.team_public_id']);

        if (! $row instanceof stdClass || $this->meetingConversationBlocked($this->integer($row->conversation_id), $viewerUserId)) {
            return null;
        }

        return $row;
    }

    private function conversationTitle(stdClass $row, int $viewerUserId): string
    {
        if ($this->string($row->type) !== ConversationType::Direct->value) {
            return $this->nonEmptyString($row->name ?? null)
                ?? $this->string($row->conversation_public_id ?? $row->public_id ?? null);
        }
        $conversationId = isset($row->conversation_id) ? $this->integer($row->conversation_id) : $this->integer($row->id);
        $otherId = $this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)
            ->where('conversation_id', $conversationId)->where('user_id', '<>', $viewerUserId)->whereNull('ended_at')->value('user_id');
        $otherUserId = is_numeric($otherId) ? $this->integer($otherId) : null;
        $person = $otherUserId !== null ? ($this->users->displaySummariesForInternalIds([$otherUserId])[$otherUserId] ?? null) : null;

        return $person->name ?? '';
    }

    /** @return list<stdClass> */
    private function conversationRows(): array
    {
        return array_values($this->database->table(ChatDatabaseTable::CONVERSATIONS)->whereNull('closed_at')->orderBy('id')->get()->all());
    }

    /** @return list<stdClass> */
    private function messageRows(): array
    {
        return array_values($this->database->table(ChatDatabaseTable::MESSAGES.' as messages')
            ->join(ChatDatabaseTable::CONVERSATIONS.' as conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->orderBy('messages.id')->get(['messages.*', 'conversations.public_id as conversation_public_id',
                'conversations.type', 'conversations.name', 'conversations.team_public_id'])->all());
    }

    /** @return list<stdClass> */
    private function attachmentRows(int $messageId): array
    {
        return array_values($this->database->table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)
            ->where('message_id', $messageId)->whereNull('discarded_at')->orderBy('id')->get()->all());
    }

    /** @return list<stdClass> */
    private function transcriptRows(): array
    {
        return array_values($this->transcriptQuery()->orderBy('transcriptions.id')->get()->all());
    }

    private function transcriptQuery(): Builder
    {
        return $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS.' as transcriptions')
            ->join(ChatDatabaseTable::MEETING_RECORDINGS.' as recordings', 'recordings.id', '=', 'transcriptions.recording_id')
            ->join(ChatDatabaseTable::MEETING_OCCURRENCES.' as occurrences', 'occurrences.id', '=', 'recordings.occurrence_id')
            ->join(ChatDatabaseTable::MEETINGS.' as meetings', 'meetings.id', '=', 'occurrences.meeting_id')
            ->join(ChatDatabaseTable::CONVERSATIONS.' as conversations', 'conversations.id', '=', 'meetings.conversation_id')
            ->where('transcriptions.status', TranscriptionStatus::Completed->value)
            ->where('recordings.status', 'ready')->whereNull('recordings.retention_removed_at')
            ->whereNotNull('transcriptions.current_text')
            ->select(['transcriptions.*', 'recordings.occurrence_id', 'meetings.title as meeting_title', 'conversations.public_id as conversation_public_id']);
    }

    private function conversationDocument(stdClass $row): SearchDocument
    {
        $memberIds = array_values($this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)
            ->where('conversation_id', $this->integer($row->id))->whereNull('ended_at')->pluck('user_id')
            ->map(fn (mixed $id): int => $this->integer($id))->all());
        $names = array_map(static fn ($person): string => $person->name, array_values($this->users->displaySummariesForInternalIds($memberIds)));
        $global = $this->string($row->type) !== ConversationType::Team->value;

        $publicId = $this->string($row->public_id);

        return $this->document('conversation-'.$publicId, [
            'result_type' => 'conversation', 'title' => $this->scalarString($row->name ?? null), 'body' => implode(' ', $names),
            'conversation_public_id' => $publicId, 'global_scope' => $global,
            'occurred_at' => $this->instant($row->updated_at),
        ], $global ? [] : [$this->string($row->team_public_id)]);
    }

    private function messageDocument(stdClass $row): SearchDocument
    {
        $authorId = $this->integer($row->author_user_id);
        $author = $this->users->displaySummariesForInternalIds([$authorId])[$authorId] ?? null;
        $global = $this->string($row->type) !== ConversationType::Team->value;
        $publicId = $this->string($row->public_id);

        return $this->document('message-'.$publicId, [
            'result_type' => 'message', 'title' => $this->scalarString($row->name ?? null), 'body' => $this->string($row->body),
            'author_name' => $author->name ?? '', 'author_public_id' => $author->publicId ?? '',
            'conversation_public_id' => $this->string($row->conversation_public_id), 'global_scope' => $global,
            'occurred_at' => $this->instant($row->created_at),
        ], $global ? [] : [$this->string($row->team_public_id)]);
    }

    private function attachmentDocument(stdClass $message, stdClass $attachment): SearchDocument
    {
        $global = $this->string($message->type) !== ConversationType::Team->value;
        $authorId = $this->integer($message->author_user_id);
        $author = $this->users->displaySummariesForInternalIds([$authorId])[$authorId] ?? null;

        return $this->document('attachment-'.$this->string($attachment->public_id), [
            'result_type' => 'file', 'title' => $this->scalarString($message->name ?? null), 'body' => $this->string($attachment->original_name),
            'author_name' => $author->name ?? '', 'author_public_id' => $author->publicId ?? '',
            'conversation_public_id' => $this->string($message->conversation_public_id), 'global_scope' => $global,
            'occurred_at' => $this->instant($message->created_at),
        ], $global ? [] : [$this->string($message->team_public_id)]);
    }

    private function linkDocument(stdClass $message): SearchDocument
    {
        $global = $this->string($message->type) !== ConversationType::Team->value;
        $authorId = $this->integer($message->author_user_id);
        $author = $this->users->displaySummariesForInternalIds([$authorId])[$authorId] ?? null;
        $publicId = $this->string($message->public_id);

        return $this->document('link-'.$publicId, [
            'result_type' => 'link', 'title' => $this->scalarString($message->name ?? null), 'body' => implode("\n", $this->links($this->string($message->body))),
            'author_name' => $author->name ?? '', 'author_public_id' => $author->publicId ?? '',
            'conversation_public_id' => $this->string($message->conversation_public_id), 'global_scope' => $global,
            'occurred_at' => $this->instant($message->created_at),
        ], $global ? [] : [$this->string($message->team_public_id)]);
    }

    private function transcriptDocument(stdClass $row): SearchDocument
    {
        return $this->document('transcript-'.$this->string($row->public_id), [
            'result_type' => 'transcript', 'title' => $this->string($row->meeting_title), 'body' => $this->string($row->current_text),
            'conversation_public_id' => $this->string($row->conversation_public_id), 'global_scope' => true,
            'occurred_at' => $this->instant($row->updated_at),
        ], [], [ChatPermissionCatalog::TRANSCRIPTION_SHOW]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $teamIds
     * @param  list<string>|null  $permissions
     */
    private function document(string $id, array $fields, array $teamIds = [], ?array $permissions = null): SearchDocument
    {
        return new SearchDocument($id, ChatSearch::INDEX_KEY, 'chat', $fields, $teamIds, $permissions ?? [ChatPermissionCatalog::SEARCH_INDEX]);
    }

    private function transcriptParticipant(int $transcriptionId, int $occurrenceId, int $viewerUserId): bool
    {
        return $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS.' as transcriptions')
            ->join(ChatDatabaseTable::MEETING_RECORDINGS.' as recordings', 'recordings.id', '=', 'transcriptions.recording_id')
            ->join(ChatDatabaseTable::MEETING_OCCURRENCES.' as occurrences', 'occurrences.id', '=', 'recordings.occurrence_id')
            ->join(ChatDatabaseTable::MEETING_INVITATIONS.' as invitations', 'invitations.meeting_id', '=', 'occurrences.meeting_id')
            ->where('transcriptions.id', $transcriptionId)->where('invitations.user_id', $viewerUserId)->whereNull('invitations.removed_at')
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS.' as denied')
                ->where('denied.occurrence_id', $occurrenceId)->where('denied.user_id', $viewerUserId)->whereNotNull('denied.banned_at'))
            ->exists();
    }

    private function meetingConversationBlocked(int $conversationId, int $viewerUserId): bool
    {
        return $this->database->table(ChatDatabaseTable::MEETINGS.' as meetings')
            ->join(ChatDatabaseTable::MEETING_OCCURRENCES.' as occurrences', 'occurrences.meeting_id', '=', 'meetings.id')
            ->join(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS.' as participants', 'participants.occurrence_id', '=', 'occurrences.id')
            ->where('meetings.conversation_id', $conversationId)
            ->where('occurrences.rtc_status', 'active')
            ->where('participants.user_id', $viewerUserId)
            ->whereNotNull('participants.banned_at')
            ->exists();
    }

    /** @return list<string> */
    private function links(string $body): array
    {
        preg_match_all('~https?://[^\s<>]+~iu', $body, $matches);
        $links = array_map(static fn (string $url): string => rtrim($url, '.,;:!?)]}'), $matches[0]);

        return array_values(array_unique(array_filter($links)));
    }

    private function integer(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new \UnexpectedValueException('Chat Search projection expected an integer persistence value.');
        }

        return (int) $value;
    }

    private function string(mixed $value): string
    {
        if (! is_scalar($value) || (string) $value === '') {
            throw new \UnexpectedValueException('Chat Search projection expected a non-empty string persistence value.');
        }

        return (string) $value;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function instant(mixed $value): string
    {
        return (new \DateTimeImmutable($this->string($value)))->format(DATE_ATOM);
    }
}
