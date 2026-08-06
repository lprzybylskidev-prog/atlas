<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Optional\TimeTracking\Application\Contracts\OtherWorkCategoryStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\OtherWorkSessionStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\ActiveOtherWorkSession;
use App\Modules\Optional\TimeTracking\Application\DTOs\OtherWorkCategory;
use App\Modules\Optional\TimeTracking\Application\Enums\OtherWorkApprovalStatus;
use App\Modules\Optional\TimeTracking\Application\Enums\OtherWorkClosureReason;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final readonly class DatabaseOtherWorkSessionStore implements OtherWorkSessionStore
{
    public function __construct(
        private ConnectionInterface $database,
        private OtherWorkCategoryStore $categories,
    ) {}

    public function startForActiveWorkSession(
        int $userId,
        ?string $categoryKey,
        string $description,
        DateTimeImmutable $startedAt,
    ): ActiveOtherWorkSession {
        if (trim($description) === '') {
            throw new InvalidArgumentException('Other work description cannot be empty.');
        }

        return $this->database->transaction(function () use ($userId, $categoryKey, $description, $startedAt): ActiveOtherWorkSession {
            $workSession = $this->database->table(TimeTrackingDatabaseTable::WORK_SESSIONS)
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first(['id', 'team_id']);

            if (! is_object($workSession)) {
                throw new RuntimeException('Cannot start Other work without an active TimeTracking work session.');
            }

            $existing = $this->database->table(TimeTrackingDatabaseTable::OTHER_WORK)
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first(['id', 'public_id', 'team_id', 'work_session_id', 'category_key', 'started_at']);

            if (is_object($existing)) {
                return $this->activeOtherWorkFromRow($existing, $userId);
            }

            $teamId = $this->intValue($workSession->team_id ?? null);
            $category = $this->categoryForTeam($teamId, $categoryKey);

            if ($category?->requiresComment === true && trim($description) === '') {
                throw new InvalidArgumentException('Other work category requires a comment.');
            }

            $approved = $category?->autoApprovalEnabled === true;
            $now = now();
            $publicId = (string) Str::ulid();
            $workSessionId = $this->intValue($workSession->id ?? null);
            $id = $this->database->table(TimeTrackingDatabaseTable::OTHER_WORK)->insertGetId([
                'public_id' => $publicId,
                'work_session_id' => $workSessionId,
                'user_id' => $userId,
                'team_id' => $teamId,
                'category_key' => $categoryKey,
                'description' => $description,
                'end_note' => null,
                'approval_status' => $approved ? OtherWorkApprovalStatus::Approved->value : OtherWorkApprovalStatus::Pending->value,
                'started_at' => $startedAt,
                'ended_at' => null,
                'exact_seconds' => null,
                'closure_reason' => null,
                'requires_manager_review' => ! $approved,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return new ActiveOtherWorkSession(
                id: (int) $id,
                publicId: $publicId,
                userId: $userId,
                teamId: $teamId,
                workSessionId: $workSessionId,
                categoryKey: $categoryKey,
                startedAt: $startedAt,
            );
        });
    }

    public function closeActiveForUser(int $userId, OtherWorkClosureReason $reason, DateTimeImmutable $endedAt, ?string $endNote): void
    {
        $this->database->transaction(function () use ($userId, $reason, $endedAt, $endNote): void {
            $active = $this->database->table(TimeTrackingDatabaseTable::OTHER_WORK)
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first(['id', 'started_at', 'approval_status']);

            if (! is_object($active)) {
                return;
            }

            $this->closeOtherWork(
                otherWorkId: $this->intValue($active->id ?? null),
                startedAt: new DateTimeImmutable($this->stringValue($active->started_at ?? null)),
                endedAt: $endedAt,
                reason: $reason,
                endNote: $endNote,
                requiresManagerReview: $reason !== OtherWorkClosureReason::Normal
                    || $this->stringValue($active->approval_status ?? null) !== OtherWorkApprovalStatus::Approved->value,
                approvalStatus: $reason === OtherWorkClosureReason::Normal ? null : OtherWorkApprovalStatus::UnderReview,
            );
        });
    }

    public function moveActiveToUnderReview(int $userId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Other work review reason cannot be empty.');
        }

        $this->database->table(TimeTrackingDatabaseTable::OTHER_WORK)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->where('approval_status', OtherWorkApprovalStatus::Approved->value)
            ->update([
                'approval_status' => OtherWorkApprovalStatus::UnderReview->value,
                'requires_manager_review' => true,
                'end_note' => $reason,
                'updated_at' => now(),
            ]);
    }

    public function decidePending(int $otherWorkId, OtherWorkApprovalStatus $status): bool
    {
        if (! in_array($status, [OtherWorkApprovalStatus::Approved, OtherWorkApprovalStatus::Rejected], true)) {
            throw new InvalidArgumentException('Other work decision status must be final.');
        }

        if ($otherWorkId < 1) {
            return false;
        }

        return $this->database->table(TimeTrackingDatabaseTable::OTHER_WORK)
            ->where('id', $otherWorkId)
            ->whereNotNull('ended_at')
            ->where('requires_manager_review', true)
            ->whereIn('approval_status', [
                OtherWorkApprovalStatus::Pending->value,
                OtherWorkApprovalStatus::UnderReview->value,
            ])
            ->update([
                'approval_status' => $status->value,
                'requires_manager_review' => false,
                'updated_at' => now(),
            ]) === 1;
    }

    private function categoryForTeam(int $teamId, ?string $categoryKey): ?OtherWorkCategory
    {
        if ($categoryKey === null || $categoryKey === '') {
            return null;
        }

        foreach ($this->categories->activeForTeam($teamId) as $category) {
            if ($category->categoryKey === $categoryKey) {
                return $category;
            }
        }

        throw new InvalidArgumentException('Other work category is not active for the selected team.');
    }

    private function closeOtherWork(
        int $otherWorkId,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $endedAt,
        OtherWorkClosureReason $reason,
        ?string $endNote,
        bool $requiresManagerReview,
        ?OtherWorkApprovalStatus $approvalStatus,
    ): void {
        if ($otherWorkId < 1) {
            return;
        }

        $seconds = max(0, $endedAt->getTimestamp() - $startedAt->getTimestamp());

        $this->database->table(TimeTrackingDatabaseTable::OTHER_WORK)
            ->where('id', $otherWorkId)
            ->whereNull('ended_at')
            ->update(array_filter([
                'ended_at' => $endedAt,
                'exact_seconds' => $seconds,
                'closure_reason' => $reason->value,
                'end_note' => $endNote,
                'approval_status' => $approvalStatus?->value,
                'requires_manager_review' => $requiresManagerReview,
                'updated_at' => now(),
            ], static fn (mixed $value): bool => $value !== null));
    }

    private function activeOtherWorkFromRow(object $row, int $userId): ActiveOtherWorkSession
    {
        return new ActiveOtherWorkSession(
            id: $this->intValue($row->id ?? null),
            publicId: $this->stringValue($row->public_id ?? null),
            userId: $userId,
            teamId: $this->intValue($row->team_id ?? null),
            workSessionId: $this->intValue($row->work_session_id ?? null),
            categoryKey: $this->nullableString($row->category_key ?? null),
            startedAt: new DateTimeImmutable($this->stringValue($row->started_at ?? null)),
        );
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = (string) $value;

        return $string === '' ? null : $string;
    }
}
