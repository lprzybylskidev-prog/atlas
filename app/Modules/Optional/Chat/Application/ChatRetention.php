<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Modules\Core\Files\Application\Public\Contracts\FileLookup;
use App\Modules\Optional\Chat\Application\Audit\ChatAuditEvents;
use App\Modules\Optional\Chat\Application\Contracts\ChatRetentionStore;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchProjectionWriter;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use DateTimeImmutable;
use Illuminate\Support\Facades\Config;

final readonly class ChatRetention
{
    public function __construct(
        private ChatRetentionStore $store,
        private FileLifecycle $files,
        private FileLookup $fileLookup,
        private SearchProjectionWriter $search,
        private ChatTransaction $transaction,
        private AuditRecorder $audit,
    ) {}

    public function configuredDays(): ?int
    {
        $days = $this->store->hasRetentionSetting() ? $this->store->retentionDays() : Config::get('chat.retention_days');

        return is_int($days) && $days > 0 ? $days : null;
    }

    public function configure(?int $days, string $actorPublicId, string $teamPublicId): void
    {
        if ($days !== null && ($days < 1 || $days > 3650)) {
            throw new \InvalidArgumentException('Chat retention must be between 1 and 3650 days.');
        }
        $before = $this->configuredDays();
        $this->transaction->run(function () use ($days, $before, $actorPublicId, $teamPublicId): void {
            $this->store->setRetentionDays($days);
            $this->audit->record(new AuditEvent(
                module: 'chat', action: ChatAuditEvents::RETENTION_CONFIGURED, result: 'succeeded', source: 'admin',
                actorPublicId: $actorPublicId, teamPublicId: $teamPublicId,
                targetType: 'chat_retention_policy', targetPublicId: 'global',
                aggregateType: 'chat_retention_policy', aggregatePublicId: 'global',
                before: ['days' => $before], after: ['days' => $days],
            ));
        });
    }

    /** @return array{removed:int,attachmentsRemoved:int,timelineEntriesRemoved:int,failed:int,disabled:bool} */
    public function cleanup(DateTimeImmutable $now = new DateTimeImmutable): array
    {
        $days = $this->configuredDays();
        if ($days === null) {
            return ['removed' => 0, 'attachmentsRemoved' => 0, 'timelineEntriesRemoved' => 0, 'failed' => 0, 'disabled' => true];
        }
        $cutoff = $now->modify(sprintf('-%d days', $days));
        $limit = max(1, min(1000, Config::integer('chat.retention_batch_size', 100)));
        $result = ['removed' => 0, 'attachmentsRemoved' => 0, 'timelineEntriesRemoved' => 0, 'failed' => 0, 'disabled' => false];
        foreach ($this->store->expiredMessages($cutoff, $limit) as $message) {
            $fileFailure = false;
            foreach ($message->attachments as $attachment) {
                $file = $this->fileLookup->status($attachment['filePublicId']);
                if ($file !== null && ! $file->deleted && ! $this->files->delete($attachment['filePublicId'], reason: 'Chat message retention')->completed) {
                    $fileFailure = true;
                    break;
                }
            }
            if ($fileFailure) {
                $result['failed']++;

                continue;
            }
            $documentIds = ['message-'.$message->publicId, 'link-'.$message->publicId];
            foreach ($message->attachments as $attachment) {
                $documentIds[] = 'attachment-'.$attachment['publicId'];
                $result['attachmentsRemoved']++;
            }
            $this->search->delete(ChatSearch::INDEX_KEY, $documentIds);
            $this->transaction->run(fn () => $this->store->deleteMessage($message->id));
            $result['removed']++;
        }
        $result['timelineEntriesRemoved'] = $this->store->deleteTimelineEntries($cutoff, $limit);

        return $result;
    }

    /** @return array{retentionDays:?int,messages:int,attachments:int,voiceMessages:int,oldestMessageAt:?string} */
    public function summary(DateTimeImmutable $now = new DateTimeImmutable): array
    {
        $days = $this->configuredDays();

        return ['retentionDays' => $days] + $this->store->summary($days === null ? null : $now->modify(sprintf('-%d days', $days)));
    }

    public function auditRunRequested(string $actorPublicId, string $teamPublicId, string $processPublicId): void
    {
        $this->audit->record(new AuditEvent(
            module: 'chat', action: ChatAuditEvents::RETENTION_RUN_REQUESTED, result: 'succeeded', source: 'admin',
            actorPublicId: $actorPublicId, teamPublicId: $teamPublicId,
            targetType: 'chat_retention_policy', targetPublicId: 'global', aggregateType: 'chat_retention_policy', aggregatePublicId: 'global',
            metadata: ['process_public_id' => $processPublicId],
        ));
    }
}
