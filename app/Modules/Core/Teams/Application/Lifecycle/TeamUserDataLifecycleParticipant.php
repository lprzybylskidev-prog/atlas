<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Lifecycle;

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant;
use App\Shared\Application\DataLifecycle\DataLifecycleImpact;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecyclePreview;
use App\Shared\Application\DataLifecycle\DataLifecycleResult;
use App\Shared\Application\DataLifecycle\DataLifecycleStepResult;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class TeamUserDataLifecycleParticipant implements DataLifecycleParticipant
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function preview(DataLifecycleSubject $subject, DataLifecycleOperation $operation): DataLifecyclePreview
    {
        $userId = $this->userId($subject);

        if ($userId === null) {
            return new DataLifecyclePreview([]);
        }

        return new DataLifecyclePreview([
            new DataLifecycleImpact(
                'teams.user_assignments',
                $this->assignments($userId)->count(),
                true,
                $this->records($this->assignments($userId), ['id', 'team_id', 'user_id', 'is_head_manager', 'valid_from', 'valid_to', 'created_at']),
            ),
            new DataLifecycleImpact(
                'teams.manager_relationships',
                $this->relationships($userId)->count(),
                true,
                $this->records($this->relationships($userId), ['id', 'team_id', 'manager_user_id', 'report_user_id', 'valid_from', 'valid_to', 'created_at']),
            ),
        ]);
    }

    public function execute(DataLifecycleSubject $subject, DataLifecycleOperation $operation, string $correlationId): DataLifecycleResult
    {
        $userId = $this->userId($subject);

        if ($userId === null) {
            return new DataLifecycleResult([]);
        }

        $now = now();

        return new DataLifecycleResult([
            new DataLifecycleStepResult(
                'teams.user_assignments_ended',
                $this->assignments($userId)
                    ->where(static function (Builder $query) use ($now): void {
                        $query->whereNull('valid_to')->orWhere('valid_to', '>', $now);
                    })
                    ->update([
                        'is_head_manager' => false,
                        'valid_to' => $now,
                        'updated_at' => $now,
                    ]),
                true,
            ),
            new DataLifecycleStepResult(
                'teams.manager_relationships_ended',
                $this->relationships($userId)
                    ->where(static function (Builder $query) use ($now): void {
                        $query->whereNull('valid_to')->orWhere('valid_to', '>', $now);
                    })
                    ->update([
                        'valid_to' => $now,
                        'ended_by_user_id' => null,
                        'end_reason' => 'Privacy lifecycle operation redacted this relationship.',
                        'updated_at' => $now,
                    ]),
                true,
            ),
            new DataLifecycleStepResult(
                'teams.manager_relationship_actor_references_redacted',
                $this->db->table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
                    ->where('created_by_user_id', $userId)
                    ->orWhere('ended_by_user_id', $userId)
                    ->update([
                        'created_by_user_id' => null,
                        'ended_by_user_id' => null,
                        'updated_at' => $now,
                    ]),
                true,
            ),
        ]);
    }

    private function userId(DataLifecycleSubject $subject): ?int
    {
        if ($subject->type !== 'user') {
            return null;
        }

        $id = $this->db->table(IdentityDatabaseTable::USERS)
            ->where('public_id', $subject->identifier)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function assignments(int $userId): Builder
    {
        return $this->db->table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->where('user_id', $userId);
    }

    private function relationships(int $userId): Builder
    {
        return $this->db->table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where(static function (Builder $query) use ($userId): void {
                $query
                    ->where('manager_user_id', $userId)
                    ->orWhere('report_user_id', $userId);
            });
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function records(Builder $query, array $columns): array
    {
        $records = [];

        foreach ($query->get($columns) as $record) {
            $records[] = $this->recordToArray($record);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function recordToArray(object $record): array
    {
        $row = [];

        foreach ((array) $record as $key => $value) {
            if (is_string($key)) {
                $row[$key] = $value;
            }
        }

        return $row;
    }
}
