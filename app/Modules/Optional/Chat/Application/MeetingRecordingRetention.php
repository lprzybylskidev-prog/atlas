<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Modules\Optional\Chat\Application\Audit\ChatAuditEvents;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingStore;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use DateTimeImmutable;
use Illuminate\Support\Facades\Config;

final readonly class MeetingRecordingRetention
{
    public function __construct(
        private MeetingRecordingStore $recordings,
        private FileLifecycle $files,
        private ChatTransaction $transaction,
        private AuditRecorder $audit,
    ) {}

    public function configuredDays(): ?int
    {
        $days = $this->recordings->hasRecordingRetentionSetting()
            ? $this->recordings->recordingRetentionDays()
            : Config::get('chat.recording_retention_days');

        return is_int($days) && $days > 0 ? $days : null;
    }

    public function configure(?int $days, string $actorPublicId, string $teamPublicId): void
    {
        if ($days !== null && ($days < 1 || $days > 3650)) {
            throw new \InvalidArgumentException('Meeting recording retention must be between 1 and 3650 days.');
        }
        $before = $this->configuredDays();
        $this->transaction->run(function () use ($days, $before, $actorPublicId, $teamPublicId): void {
            $this->recordings->setRecordingRetentionDays($days);
            $this->audit->record(new AuditEvent(
                module: 'chat',
                action: ChatAuditEvents::RECORDING_RETENTION_CONFIGURED,
                result: 'succeeded',
                source: 'admin',
                actorPublicId: $actorPublicId,
                teamPublicId: $teamPublicId,
                targetType: 'recording_retention_policy',
                targetPublicId: 'global',
                aggregateType: 'recording_retention_policy',
                aggregatePublicId: 'global',
                before: ['days' => $before],
                after: ['days' => $days],
            ));
        });
    }

    /** @return array{removed:int,failed:int,disabled:bool} */
    public function cleanup(DateTimeImmutable $now = new DateTimeImmutable): array
    {
        $days = $this->configuredDays();
        if ($days === null) {
            return ['removed' => 0, 'failed' => 0, 'disabled' => true];
        }
        $limit = max(1, min(1000, Config::integer('chat.recording_retention_batch_size', 100)));
        $result = ['removed' => 0, 'failed' => 0, 'disabled' => false];
        foreach ($this->recordings->expiredReady($now->modify(sprintf('-%d days', $days)), $limit) as $recording) {
            if ($recording->filePublicId === null || ! $this->files->delete($recording->filePublicId, reason: 'Meeting recording retention')->completed) {
                $result['failed']++;

                continue;
            }
            $this->transaction->run(function () use ($recording): void {
                $this->recordings->markRemovedByRetention($recording->id);
                $this->audit->record(new AuditEvent(
                    module: 'chat', action: ChatAuditEvents::RECORDING_REMOVED_BY_RETENTION, result: 'succeeded', source: 'scheduler',
                    targetType: 'meeting_recording', targetPublicId: $recording->publicId,
                    aggregateType: 'meeting_recording', aggregatePublicId: $recording->publicId,
                    before: ['status' => $recording->status->value], after: ['status' => 'removed'],
                ));
            });
            $result['removed']++;
        }

        return $result;
    }

    /** @return array{retentionDays:?int,eligible:int,oldestEndedAt:?string} */
    public function summary(DateTimeImmutable $now = new DateTimeImmutable): array
    {
        $days = $this->configuredDays();
        $summary = $this->recordings->retentionSummary($days === null ? null : $now->modify(sprintf('-%d days', $days)));

        return ['retentionDays' => $days, 'eligible' => $summary['ready'], 'oldestEndedAt' => $summary['oldestEndedAt']];
    }
}
