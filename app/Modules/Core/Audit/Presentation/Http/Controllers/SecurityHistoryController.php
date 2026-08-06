<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Persistence\AuditDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SecurityHistoryController
{
    public function __construct(
        private ArrayTableProcessor $tables,
        private TableRequestContext $context,
        private TableSavedViewService $views,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::SECURITY_HISTORY);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $filters = $this->filters($request);
        $query = DB::table(AuditDatabaseTable::AUDIT_EVENTS)
            ->where('is_security', true);

        if ($filters['user'] !== '' && $filters['user'] !== 'all') {
            $query->where(static function (Builder $query) use ($filters): void {
                $query
                    ->where('actor_public_id', $filters['user'])
                    ->orWhere('actual_actor_public_id', $filters['user'])
                    ->orWhere('impersonated_user_public_id', $filters['user'])
                    ->orWhere('target_public_id', $filters['user']);
            });
        }

        $this->whereExact($query, 'action', $filters['action']);
        $this->whereExact($query, 'source', $filters['source']);

        if (in_array($filters['result'], ['succeeded', 'rejected', 'failed'], true)) {
            $query->where('result', $filters['result']);
        }

        if ($this->isDate($filters['dateFrom'])) {
            $query->whereDate('occurred_at', '>=', $filters['dateFrom']);
        }

        if ($this->isDate($filters['dateTo'])) {
            $query->whereDate('occurred_at', '<=', $filters['dateTo']);
        }

        $records = array_values($query
            ->orderByDesc('occurred_at')
            ->limit(500)
            ->get()
            ->all());
        $users = $this->usersForEvents($records);

        $events = array_map(fn (object $record): array => [
            'publicId' => self::stringValue($record, 'public_id'),
            'occurredAt' => self::stringValue($record, 'occurred_at'),
            'user' => $this->eventUser($record, $users),
            'module' => self::stringValue($record, 'module'),
            'action' => self::stringValue($record, 'action'),
            'result' => self::stringValue($record, 'result'),
            'source' => self::stringValue($record, 'source'),
            'actorPublicId' => self::stringValue($record, 'actor_public_id'),
            'actualActorPublicId' => self::stringValue($record, 'actual_actor_public_id'),
            'impersonatedUserPublicId' => self::stringValue($record, 'impersonated_user_public_id'),
            'impersonationSessionId' => self::stringValue($record, 'impersonation_session_id'),
            'targetType' => self::stringValue($record, 'target_type'),
            'targetPublicId' => self::stringValue($record, 'target_public_id'),
            'teamPublicId' => self::stringValue($record, 'team_public_id'),
            'reason' => self::stringValue($record, 'reason'),
        ], $records);
        $rows = array_map(static fn (array $event): array => [
            'publicId' => $event['publicId'],
            'userName' => $event['user']['name'] !== '' ? $event['user']['name'] : $event['user']['publicId'],
            'userEmail' => $event['user']['email'] !== '' ? $event['user']['email'] : $event['user']['publicId'],
            'userContext' => $event['user']['context'],
            'occurredAt' => $event['occurredAt'],
            'action' => $event['action'],
            'result' => $event['result'],
            'source' => $event['source'],
            'teamPublicId' => $event['teamPublicId'],
            'impersonationSessionId' => $event['impersonationSessionId'],
            'reason' => $event['reason'],
        ], $events);
        $result = $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $this->viewFilters($filters);

        return Inertia::render('Admin/Audit/SecurityHistory', [
            'events' => $result->rows,
            'summary' => $this->summary($rows),
            'table' => $table,
            'filters' => $filters,
            'filterOptions' => [
                'users' => $this->userOptions(),
                'actions' => $this->distinctOptions('action'),
                'sources' => $this->distinctOptions('source'),
            ],
        ]);
    }

    /**
     * @param  list<object>  $records
     * @return array<string, array{name: string, email: string}>
     */
    private function usersForEvents(array $records): array
    {
        $publicIds = [];

        foreach ($records as $record) {
            foreach (['impersonated_user_public_id', 'target_public_id', 'actor_public_id', 'actual_actor_public_id'] as $property) {
                $publicId = self::stringValue($record, $property);

                if ($publicId !== '') {
                    $publicIds[$publicId] = true;
                }
            }
        }

        if ($publicIds === []) {
            return [];
        }

        $users = [];

        foreach (DB::table(IdentityDatabaseTable::USERS)
            ->whereIn('public_id', array_keys($publicIds))
            ->get(['public_id', 'name', 'email'])
            ->all() as $user) {
            $publicId = self::stringValue($user, 'public_id');

            if ($publicId === '') {
                continue;
            }

            $users[$publicId] = [
                'name' => self::stringValue($user, 'name'),
                'email' => self::stringValue($user, 'email'),
            ];
        }

        return $users;
    }

    /**
     * @param  array<string, array{name: string, email: string}>  $users
     * @return array{publicId: string, name: string, email: string, context: string}
     */
    private function eventUser(object $record, array $users): array
    {
        foreach ([
            'impersonated_user_public_id' => 'Impersonated user',
            'target_public_id' => 'Target user',
            'actor_public_id' => 'Actor',
            'actual_actor_public_id' => 'Actual actor',
        ] as $property => $context) {
            $publicId = self::stringValue($record, $property);

            if ($publicId === '') {
                continue;
            }

            $user = $users[$publicId] ?? null;

            return [
                'publicId' => $publicId,
                'name' => $user === null ? $publicId : $user['name'],
                'email' => $user === null ? '' : $user['email'],
                'context' => $context,
            ];
        }

        return [
            'publicId' => '',
            'name' => '',
            'email' => '',
            'context' => '',
        ];
    }

    private function filterString(Request $request, string $key): string
    {
        $value = preg_replace('/[[:cntrl:]]/', '', (string) $request->query($key, '')) ?? '';

        return mb_substr(trim($value), 0, 120);
    }

    /**
     * @return array{user: string, action: string, result: string, source: string, dateFrom: string, dateTo: string}
     */
    private function filters(Request $request): array
    {
        return [
            'user' => $this->filterString($request, 'user'),
            'action' => $this->filterString($request, 'action'),
            'result' => $this->filterString($request, 'result'),
            'source' => $this->filterString($request, 'source'),
            'dateFrom' => $this->filterString($request, 'date_from'),
            'dateTo' => $this->filterString($request, 'date_to'),
        ];
    }

    /**
     * @param  array{user: string, action: string, result: string, source: string, dateFrom: string, dateTo: string}  $filters
     * @return array<string, string>
     */
    private function viewFilters(array $filters): array
    {
        return array_filter([
            'user' => $filters['user'],
            'action' => $filters['action'],
            'result' => $filters['result'],
            'source' => $filters['source'],
            'date_from' => $filters['dateFrom'],
            'date_to' => $filters['dateTo'],
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{visible: int, rejected: int, failed: int, impersonated: int, withReason: int}
     */
    private function summary(array $rows): array
    {
        return [
            'visible' => count($rows),
            'rejected' => count(array_filter($rows, static fn (array $row): bool => ($row['result'] ?? '') === 'rejected')),
            'failed' => count(array_filter($rows, static fn (array $row): bool => ($row['result'] ?? '') === 'failed')),
            'impersonated' => count(array_filter($rows, static fn (array $row): bool => ($row['impersonationSessionId'] ?? '') !== '')),
            'withReason' => count(array_filter($rows, static fn (array $row): bool => trim(self::stringFromValue($row['reason'] ?? '')) !== '')),
        ];
    }

    private function whereExact(Builder $query, string $column, string $value): void
    {
        if ($value === '' || $value === 'all') {
            return;
        }

        $query->where($column, $value);
    }

    private function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function userOptions(): array
    {
        $options = [];

        foreach (DB::table(IdentityDatabaseTable::USERS)
            ->orderBy('name')
            ->orderBy('email')
            ->get(['public_id', 'name', 'email'])
            ->all() as $user) {
            $publicId = self::stringValue($user, 'public_id');

            if ($publicId === '') {
                continue;
            }

            $name = self::stringValue($user, 'name');
            $email = self::stringValue($user, 'email');
            $label = trim($name) !== '' ? $name : $publicId;

            if (trim($email) !== '') {
                $label = sprintf('%s <%s>', $label, $email);
            }

            $options[] = [
                'value' => $publicId,
                'label' => sprintf('%s - %s', $label, $publicId),
            ];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function distinctOptions(string $column): array
    {
        $options = [];

        foreach (DB::table(AuditDatabaseTable::AUDIT_EVENTS)
            ->where('is_security', true)
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

    private static function stringValue(object $record, string $property): string
    {
        $value = $record->{$property} ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private static function stringFromValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
