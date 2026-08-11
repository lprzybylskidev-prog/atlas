<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Infrastructure\Persistence;

use App\Modules\Core\Audit\Infrastructure\Persistence\TableNames\AuditDatabaseTable;
use App\Shared\Application\Tables\TableDefinition;
use App\Shared\Application\Tables\TableResult;
use App\Shared\Application\Tables\TableState;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class AuditEventReadModel
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  callable(Builder): mixed  $scope
     * @param  callable(object): array<string, mixed>  $map
     */
    public function page(TableDefinition $definition, TableState $state, callable $scope, callable $map): TableResult
    {
        $query = $this->db->table(AuditDatabaseTable::AUDIT_EVENTS);
        $scope($query);
        $this->applySearch($query, $definition, $state->search);

        $total = $query->count();
        $sortColumn = $this->column($state->sort);
        $direction = $state->direction === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortColumn, $direction);
        if ($sortColumn !== 'id') {
            $query->orderBy('id', $direction);
        }

        $rows = array_values($query
            ->offset(($state->page - 1) * $state->perPage)
            ->limit($state->perPage)
            ->get()
            ->map($map)
            ->values()
            ->all());

        return new TableResult($rows, $total, $state);
    }

    /** @return array{visible: int, security: int, rejected: int, failed: int, impersonated: int, withReason: int} */
    public function summary(TableDefinition $definition, TableState $state, callable $scope): array
    {
        $query = $this->db->table(AuditDatabaseTable::AUDIT_EVENTS);
        $scope($query);
        $this->applySearch($query, $definition, $state->search);

        $row = $query->selectRaw(<<<'SQL'
count(*) as visible,
count(*) filter (where is_security = true) as security,
count(*) filter (where result = 'rejected') as rejected,
count(*) filter (where result = 'failed') as failed,
count(*) filter (where impersonation_session_id is not null and impersonation_session_id <> '') as impersonated,
count(*) filter (where reason is not null and reason <> '') as with_reason
SQL)->first();

        return [
            'visible' => $this->intValue($row->visible ?? null),
            'security' => $this->intValue($row->security ?? null),
            'rejected' => $this->intValue($row->rejected ?? null),
            'failed' => $this->intValue($row->failed ?? null),
            'impersonated' => $this->intValue($row->impersonated ?? null),
            'withReason' => $this->intValue($row->with_reason ?? null),
        ];
    }

    private function applySearch(Builder $query, TableDefinition $definition, string $search): void
    {
        if ($search === '') {
            return;
        }

        $columns = array_values(array_filter(array_map(
            fn (string $key): ?string => $this->searchableColumn($key),
            $definition->searchableKeys(),
        )));

        $query->where(function (Builder $nested) use ($columns, $search): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $nested->{$method}($column, 'ilike', '%'.$search.'%');
            }
        });
    }

    private function column(string $key): string
    {
        return $this->searchableColumn($key) ?? 'occurred_at';
    }

    private function searchableColumn(string $key): ?string
    {
        return match ($key) {
            'id' => 'id',
            'publicId' => 'public_id',
            'occurredAt' => 'occurred_at',
            'module' => 'module',
            'action' => 'action',
            'result' => 'result',
            'source' => 'source',
            'actorPublicId' => 'actor_public_id',
            'actualActorPublicId' => 'actual_actor_public_id',
            'impersonatedUserPublicId' => 'impersonated_user_public_id',
            'impersonationSessionId' => 'impersonation_session_id',
            'targetType' => 'target_type',
            'targetPublicId' => 'target_public_id',
            'aggregateType' => 'aggregate_type',
            'aggregatePublicId' => 'aggregate_public_id',
            'teamPublicId' => 'team_public_id',
            'correlationId' => 'correlation_id',
            'reason' => 'reason',
            default => null,
        };
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
