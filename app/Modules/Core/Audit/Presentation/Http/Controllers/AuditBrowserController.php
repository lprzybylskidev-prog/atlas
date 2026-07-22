<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Presentation\Http\Controllers;

use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AuditBrowserController
{
    public function __construct(
        private ArrayTableProcessor $tables,
        private TableRequestContext $context,
        private TableSavedViewService $views,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::AUDIT);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $filters = $this->filters($request);

        $query = DB::table(DatabaseTable::AUDIT_EVENTS)->orderByDesc('occurred_at');
        $this->applyFilters($query, $filters);

        $rows = array_values($query->limit(5000)->get()
            ->map(fn (object $record): array => $this->row($record))
            ->all());

        $result = $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor(AdminTableDefinitions::AUDIT, $userId, $teamId));
        $table = $result->tableMeta(AdminTableDefinitions::AUDIT, AdminDataTableExportMeta::defaults());
        $tableState = $table['state'] ?? [];
        $table['state'] = is_array($tableState)
            ? [...$tableState, 'filters' => $this->viewFilters($filters)]
            : ['filters' => $this->viewFilters($filters)];

        return Inertia::render('Admin/Audit/Index', [
            'events' => $result->rows,
            'table' => $table,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    public function impersonationSession(string $session): Response
    {
        $events = array_values(DB::table(DatabaseTable::AUDIT_EVENTS)
            ->where('impersonation_session_id', $session)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (object $record): array => $this->row($record))
            ->all());

        abort_if($events === [], 404);

        $start = $events[0];
        $end = $events[array_key_last($events)] ?? $start;

        return Inertia::render('Admin/Audit/ImpersonationSession', [
            'session' => [
                'id' => $session,
                'startedAt' => $start['occurredAt'] ?? '',
                'endedAt' => ($end['action'] ?? '') === 'impersonation.end' ? ($end['occurredAt'] ?? '') : null,
                'actualActorPublicId' => $start['actualActorPublicId'] ?: ($start['actorPublicId'] ?? ''),
                'impersonatedUserPublicId' => $start['impersonatedUserPublicId'] ?: ($start['targetPublicId'] ?? ''),
                'teamPublicId' => $start['teamPublicId'] ?? '',
                'reason' => $start['reason'] ?? '',
                'operationCount' => count($events),
                'rejectedCount' => count(array_filter($events, static fn (array $event): bool => ($event['result'] ?? '') === 'rejected')),
            ],
            'events' => $events,
            'exports' => AdminDataTableExportMeta::defaults(),
        ]);
    }

    /**
     * @return array{actor: string, actualActor: string, impersonatedUser: string, impersonationSession: string, target: string, targetType: string, action: string, team: string, module: string, source: string, correlation: string, result: string, security: string, dateFrom: string, dateTo: string}
     */
    private function filters(Request $request): array
    {
        return [
            'actor' => $this->filterString($request, 'actor'),
            'actualActor' => $this->filterString($request, 'actual_actor'),
            'impersonatedUser' => $this->filterString($request, 'impersonated_user'),
            'impersonationSession' => $this->filterString($request, 'impersonation_session'),
            'target' => $this->filterString($request, 'target'),
            'targetType' => $this->filterString($request, 'target_type'),
            'action' => $this->filterString($request, 'action'),
            'team' => $this->filterString($request, 'team'),
            'module' => $this->filterString($request, 'module'),
            'source' => $this->filterString($request, 'source'),
            'correlation' => $this->filterString($request, 'correlation'),
            'result' => $this->filterString($request, 'result'),
            'security' => $this->filterString($request, 'security'),
            'dateFrom' => $this->filterString($request, 'date_from'),
            'dateTo' => $this->filterString($request, 'date_to'),
        ];
    }

    private function filterString(Request $request, string $key): string
    {
        $value = preg_replace('/[[:cntrl:]]/', '', (string) $request->query($key, '')) ?? '';

        return mb_substr(trim($value), 0, 120);
    }

    /**
     * @param  array{actor: string, actualActor: string, impersonatedUser: string, impersonationSession: string, target: string, targetType: string, action: string, team: string, module: string, source: string, correlation: string, result: string, security: string, dateFrom: string, dateTo: string}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $this->whereLike($query, 'actor_public_id', $filters['actor']);
        $this->whereLike($query, 'actual_actor_public_id', $filters['actualActor']);
        $this->whereLike($query, 'impersonated_user_public_id', $filters['impersonatedUser']);
        $this->whereLike($query, 'impersonation_session_id', $filters['impersonationSession']);
        $this->whereLike($query, 'target_public_id', $filters['target']);
        $this->whereLike($query, 'correlation_id', $filters['correlation']);
        $this->whereExact($query, 'target_type', $filters['targetType']);
        $this->whereExact($query, 'action', $filters['action']);
        $this->whereExact($query, 'team_public_id', $filters['team']);
        $this->whereExact($query, 'module', $filters['module']);
        $this->whereExact($query, 'source', $filters['source']);

        if (in_array($filters['result'], ['succeeded', 'rejected', 'failed'], true)) {
            $query->where('result', $filters['result']);
        }

        if ($filters['security'] === 'yes') {
            $query->where('is_security', true);
        } elseif ($filters['security'] === 'no') {
            $query->where('is_security', false);
        }

        if ($this->isDate($filters['dateFrom'])) {
            $query->whereDate('occurred_at', '>=', $filters['dateFrom']);
        }

        if ($this->isDate($filters['dateTo'])) {
            $query->whereDate('occurred_at', '<=', $filters['dateTo']);
        }
    }

    private function whereExact(Builder $query, string $column, string $value): void
    {
        if ($value === '') {
            return;
        }

        $query->where($column, $value);
    }

    private function whereLike(Builder $query, string $column, string $value): void
    {
        if ($value === '') {
            return;
        }

        $query->where($column, 'ilike', '%'.$value.'%');
    }

    private function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    /**
     * @param  array{actor: string, actualActor: string, impersonatedUser: string, impersonationSession: string, target: string, targetType: string, action: string, team: string, module: string, source: string, correlation: string, result: string, security: string, dateFrom: string, dateTo: string}  $filters
     * @return array<string, string>
     */
    private function viewFilters(array $filters): array
    {
        return array_filter([
            'actor' => $filters['actor'],
            'actual_actor' => $filters['actualActor'],
            'impersonated_user' => $filters['impersonatedUser'],
            'impersonation_session' => $filters['impersonationSession'],
            'target' => $filters['target'],
            'target_type' => $filters['targetType'],
            'action' => $filters['action'],
            'team' => $filters['team'],
            'module' => $filters['module'],
            'source' => $filters['source'],
            'correlation' => $filters['correlation'],
            'result' => $filters['result'],
            'security' => $filters['security'],
            'date_from' => $filters['dateFrom'],
            'date_to' => $filters['dateTo'],
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * @return array{modules: list<array{value: string, label: string}>, actions: list<array{value: string, label: string}>, sources: list<array{value: string, label: string}>, targetTypes: list<array{value: string, label: string}>, teams: list<array{value: string, label: string}>}
     */
    private function filterOptions(): array
    {
        return [
            'modules' => $this->distinctOptions('module'),
            'actions' => $this->distinctOptions('action'),
            'sources' => $this->distinctOptions('source'),
            'targetTypes' => $this->distinctOptions('target_type'),
            'teams' => $this->teamOptions(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function distinctOptions(string $column): array
    {
        $options = [];

        foreach (DB::table(DatabaseTable::AUDIT_EVENTS)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all() as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $text = (string) $value;
            $options[] = [
                'value' => $text,
                'label' => $text,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function teamOptions(): array
    {
        $options = [];

        foreach (DB::table(DatabaseTable::AUDIT_EVENTS)
            ->leftJoin(DatabaseTable::TEAMS, 'audit_events.team_public_id', '=', 'teams.public_id')
            ->whereNotNull('audit_events.team_public_id')
            ->where('audit_events.team_public_id', '<>', '')
            ->select('audit_events.team_public_id', 'teams.name')
            ->distinct()
            ->orderBy('teams.name')
            ->orderBy('audit_events.team_public_id')
            ->get() as $row) {
            $values = get_object_vars($row);
            $publicId = $this->stringValue($values['team_public_id'] ?? '');

            if ($publicId === '') {
                continue;
            }

            $name = $this->stringValue($values['name'] ?? '');
            $options[] = [
                'value' => $publicId,
                'label' => $name === '' ? $publicId : sprintf('%s (%s)', $name, $publicId),
            ];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(object $record): array
    {
        $values = get_object_vars($record);

        return [
            'id' => $this->intValue($values['id'] ?? null),
            'publicId' => $this->stringValue($values['public_id'] ?? ''),
            'occurredAt' => $this->stringValue($values['occurred_at'] ?? ''),
            'module' => $this->stringValue($values['module'] ?? ''),
            'action' => $this->stringValue($values['action'] ?? ''),
            'result' => $this->stringValue($values['result'] ?? ''),
            'source' => $this->stringValue($values['source'] ?? ''),
            'actorPublicId' => $this->stringValue($values['actor_public_id'] ?? ''),
            'actualActorPublicId' => $this->stringValue($values['actual_actor_public_id'] ?? ''),
            'impersonatedUserPublicId' => $this->stringValue($values['impersonated_user_public_id'] ?? ''),
            'impersonationSessionId' => $this->stringValue($values['impersonation_session_id'] ?? ''),
            'targetType' => $this->stringValue($values['target_type'] ?? ''),
            'targetPublicId' => $this->stringValue($values['target_public_id'] ?? ''),
            'aggregateType' => $this->stringValue($values['aggregate_type'] ?? ''),
            'aggregatePublicId' => $this->stringValue($values['aggregate_public_id'] ?? ''),
            'teamPublicId' => $this->stringValue($values['team_public_id'] ?? ''),
            'correlationId' => $this->stringValue($values['correlation_id'] ?? ''),
            'reason' => $this->stringValue($values['reason'] ?? ''),
            'security' => (bool) ($values['is_security'] ?? false),
            'metadata' => $this->metadataSummary($values['metadata'] ?? null),
        ];
    }

    private function metadataSummary(mixed $metadata): string
    {
        if (! is_string($metadata) || $metadata === '' || $metadata === '[]' || $metadata === '{}') {
            return '';
        }

        $decoded = json_decode($metadata, true);

        if (! is_array($decoded)) {
            return '';
        }

        return implode(', ', array_slice(array_keys($decoded), 0, 8));
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) || $value === null ? (string) $value : '';
    }

    private function intValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
