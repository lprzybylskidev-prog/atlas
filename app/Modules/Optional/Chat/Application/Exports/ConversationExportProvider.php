<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Exports;

use App\Modules\Core\Files\Application\Public\Enums\FileScanState;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\ConversationManager;
use App\Modules\Optional\Chat\Application\MessageManager;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Shared\Application\Exports\Contracts\ReportExportDataProvider;
use App\Shared\Application\Exports\DTOs\ReportExportColumn;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use RuntimeException;

final readonly class ConversationExportProvider implements ReportExportDataProvider
{
    public const KEY = 'chat.conversation';

    public function __construct(
        private ConversationManager $conversations,
        private MessageManager $messages,
        private UserLookup $users,
        private ChatModuleAccess $access,
    ) {}

    public function reportKey(): string
    {
        return self::KEY;
    }

    public function columns(ReportExportGenerationRequest $request): array
    {
        return [
            new ReportExportColumn('message_public_id', trans('exports.chat.message_id', [], $request->locale)),
            new ReportExportColumn('author', trans('exports.chat.author', [], $request->locale)),
            new ReportExportColumn('body', trans('exports.chat.body', [], $request->locale)),
            new ReportExportColumn('created_at', trans('exports.chat.created_at', [], $request->locale)),
            new ReportExportColumn('edited', trans('exports.chat.edited', [], $request->locale)),
            new ReportExportColumn('edit_history', trans('exports.chat.edit_history', [], $request->locale)),
            new ReportExportColumn('forwarded', trans('exports.chat.forwarded', [], $request->locale)),
            new ReportExportColumn('attachments', trans('exports.chat.attachments', [], $request->locale)),
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $conversation = $request->filters['conversation_public_id'] ?? null;
        if ($request->activeTeamPublicId !== null) {
            $this->access->ensureAllowed(
                $request->requestingUserPublicId,
                $request->activeTeamPublicId,
                ChatPermissionCatalog::EXPORT_STORE,
            );
        }
        if (! is_string($conversation) || $request->activeTeamPublicId === null
            || ! $this->conversations->canAccess($request->requestingUserPublicId, $request->activeTeamPublicId, $conversation)) {
            throw new RuntimeException('Conversation export is no longer authorized.');
        }
        foreach ($this->messages->messageBatches($request->requestingUserPublicId, $request->activeTeamPublicId, $conversation) as $messages) {
            $authors = $this->users->displaySummariesForPublicIds(array_values(array_unique(array_map(static fn ($message): string => $message->authorPublicId, $messages))));
            foreach ($messages as $message) {
                if ($message->deletedForViewer) {
                    continue;
                }
                $history = $this->messages->editHistory($request->requestingUserPublicId, $request->activeTeamPublicId, $conversation, $message->publicId);
                yield [
                    'message_public_id' => $message->publicId,
                    'author' => isset($authors[$message->authorPublicId])
                        ? $authors[$message->authorPublicId]->name.($authors[$message->authorPublicId]->active ? '' : ' ('.trans('glossary.status.inactive', [], $request->locale).')')
                        : $message->authorPublicId,
                    'body' => $message->body,
                    'created_at' => $message->createdAt,
                    'edited' => $message->edited,
                    'edit_history' => $message->edited ? json_encode(array_map(static fn ($revision): array => [
                        'version' => $revision->version,
                        'body' => $revision->body,
                        'created_at' => $revision->createdAt->format(DATE_ATOM),
                    ], $history), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : '',
                    'forwarded' => $message->forwarded,
                    'attachments' => implode(', ', array_map(
                        static fn ($attachment): string => $attachment->originalName,
                        array_filter($message->attachments, static fn ($attachment): bool => ! $attachment->discarded && $attachment->scanState === FileScanState::Clean),
                    )),
                ];
            }
        }
    }
}
