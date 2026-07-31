<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\DataLifecycle;

use App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant;
use App\Shared\Application\DataLifecycle\DataLifecycleImpact;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecyclePreview;
use App\Shared\Application\DataLifecycle\DataLifecycleResult;
use App\Shared\Application\DataLifecycle\DataLifecycleStepResult;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class SharedDerivedDataLifecycleParticipant implements DataLifecycleParticipant
{
    public function __construct(private ConnectionInterface $db) {}

    public function preview(DataLifecycleSubject $subject, DataLifecycleOperation $operation): DataLifecyclePreview
    {
        $impacts = array_values(array_filter([
            $this->impact('shared.cache', $this->matchingCache($subject)->count(), $this->records($this->matchingCache($subject), [
                'key',
                'expiration',
            ])),
            $this->impact('shared.cache_locks', $this->matchingCacheLocks($subject)->count(), $this->records($this->matchingCacheLocks($subject), [
                'key',
                'owner',
                'expiration',
            ])),
            $this->impact('shared.queued_jobs', $this->matchingQueuedJobs($subject)->count(), $this->records($this->matchingQueuedJobs($subject), [
                'id',
                'queue',
                'attempts',
                'reserved_at',
                'available_at',
                'created_at',
            ])),
        ]));

        return new DataLifecyclePreview($impacts);
    }

    public function execute(DataLifecycleSubject $subject, DataLifecycleOperation $operation, string $correlationId): DataLifecycleResult
    {
        return new DataLifecycleResult([
            new DataLifecycleStepResult('shared.cache_removed', $this->matchingCache($subject)->delete(), true),
            new DataLifecycleStepResult('shared.cache_locks_removed', $this->matchingCacheLocks($subject)->delete(), true),
            new DataLifecycleStepResult('shared.queued_jobs_removed', $this->matchingQueuedJobs($subject)->delete(), true),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $details
     */
    private function impact(string $dataSet, int $records, array $details): ?DataLifecycleImpact
    {
        return $records > 0 ? new DataLifecycleImpact($dataSet, $records, true, $details) : null;
    }

    private function matchingCache(DataLifecycleSubject $subject): Builder
    {
        return $this->db->table(DatabaseTable::CACHE)
            ->where(function (Builder $query) use ($subject): void {
                $this->orTextContains($query, 'key', $subject->identifier);
                $this->orTextContains($query, 'value', $subject->identifier);
            });
    }

    private function matchingCacheLocks(DataLifecycleSubject $subject): Builder
    {
        return $this->db->table(DatabaseTable::CACHE_LOCKS)
            ->where(function (Builder $query) use ($subject): void {
                $this->orTextContains($query, 'key', $subject->identifier);
                $this->orTextContains($query, 'owner', $subject->identifier);
            });
    }

    private function matchingQueuedJobs(DataLifecycleSubject $subject): Builder
    {
        return $this->db->table(DatabaseTable::JOBS)
            ->where(function (Builder $query) use ($subject): void {
                $this->orTextContains($query, 'payload', $subject->identifier);
            });
    }

    private function orTextContains(Builder $query, string $column, string $value): void
    {
        match ($column) {
            'key' => $query->orWhereRaw('key LIKE ?', [$this->like($value)]),
            'owner' => $query->orWhereRaw('owner LIKE ?', [$this->like($value)]),
            'payload' => $query->orWhereRaw('payload LIKE ?', [$this->like($value)]),
            'value' => $query->orWhereRaw('value LIKE ?', [$this->like($value)]),
            default => null,
        };
    }

    private function like(string $value): string
    {
        return '%'.$value.'%';
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
