<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Privacy\Infrastructure\Persistence\TableNames\PrivacyDatabaseTable;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\RegisteredTables;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

final readonly class PrivacyOperationHistoryController
{
    public function __construct(
        private ArrayTableProcessor $tables,
        private TableRequestContext $context,
        private TableSavedViewService $views,
        private UserLookup $users,
        private TeamLookup $teams,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = RegisteredTables::get(RegisteredTables::PRIVACY_OPERATIONS);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $rows = $this->rows();
        $filters = $this->filters($request, $rows);
        $filteredRows = $this->filteredRows($rows, $filters);
        $result = $this->tables->process($filteredRows, $definition, $state)
            ->withSavedViews($this->views->listFor(RegisteredTables::PRIVACY_OPERATIONS, $userId, $teamId));
        $table = $result->tableMeta(RegisteredTables::PRIVACY_OPERATIONS);
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/PrivacyRetention/Operations', [
            'operations' => $result->rows,
            'summary' => [
                'total' => count($result->filteredRows),
                'visible' => $result->total,
                'blocked' => count(array_filter($result->filteredRows, static fn (array $row): bool => ($row['status'] ?? null) === 'blocked')),
                'previewed' => count(array_filter($result->filteredRows, static fn (array $row): bool => ($row['status'] ?? null) === 'previewed')),
                'hardDelete' => count(array_filter($result->filteredRows, static fn (array $row): bool => ($row['operation'] ?? null) === 'hard_delete')),
                'anonymization' => count(array_filter($result->filteredRows, static fn (array $row): bool => ($row['operation'] ?? null) === 'anonymization')),
            ],
            'filterOptions' => [
                'operations' => $this->uniqueValues($rows, 'operation'),
                'statuses' => $this->uniqueValues($rows, 'status'),
                'subjectTypes' => $this->uniqueValues($rows, 'subjectType'),
                'teams' => $this->uniqueValues($rows, 'teamPublicId'),
            ],
            'table' => $table,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        $records = DB::table(PrivacyDatabaseTable::OPERATION_REQUESTS.' as requests')
            ->leftJoin(PrivacyDatabaseTable::OPERATION_PREVIEWS.' as previews', 'previews.operation_request_id', '=', 'requests.id')
            ->orderByDesc('requests.created_at')
            ->get([
                'requests.public_id',
                'requests.operation',
                'requests.subject_type',
                'requests.subject_identifier',
                'requests.status',
                'requests.dry_run',
                'requests.reason',
                'requests.confirmation_phrase',
                'requests.requested_by_user_id',
                'requests.team_id',
                'requests.previewed_at',
                'requests.created_at',
                'previews.blockers',
                'previews.participant_count',
                'previews.estimated_records',
                'previews.can_execute',
            ])
            ->all();

        return array_map(fn (object $row): array => $this->row($row), $this->enrichRecords($records));
    }

    /**
     * @param  array<int, stdClass>  $records
     * @return list<stdClass>
     */
    private function enrichRecords(array $records): array
    {
        $records = array_values($records);
        $userIds = [];
        $teamIds = [];

        foreach ($records as $record) {
            $userId = $this->nullableIntValue($record->requested_by_user_id ?? null);
            $teamId = $this->nullableIntValue($record->team_id ?? null);

            if ($userId !== null) {
                $userIds[] = $userId;
            }

            if ($teamId !== null) {
                $teamIds[] = $teamId;
            }
        }

        $users = $this->users->displaySummariesForInternalIds(array_values(array_unique($userIds)));
        $teams = $this->teams->summariesForInternalIds(array_values(array_unique($teamIds)));

        foreach ($records as $record) {
            $userId = $this->nullableIntValue($record->requested_by_user_id ?? null);
            $teamId = $this->nullableIntValue($record->team_id ?? null);
            $user = $userId === null ? null : ($users[$userId] ?? null);
            $team = $teamId === null ? null : ($teams[$teamId] ?? null);

            $record->actor_public_id = $user?->publicId;
            $record->team_public_id = $team?->publicId;
            $record->team_name = $team?->name;
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(object $row): array
    {
        return [
            'publicId' => $this->stringValue($row->public_id ?? ''),
            'operation' => $this->stringValue($row->operation ?? ''),
            'status' => $this->stringValue($row->status ?? ''),
            'subjectType' => $this->stringValue($row->subject_type ?? ''),
            'subjectIdentifier' => $this->stringValue($row->subject_identifier ?? ''),
            'dryRun' => (bool) ($row->dry_run ?? true),
            'canExecute' => (bool) ($row->can_execute ?? false),
            'estimatedRecords' => $this->intValue($row->estimated_records ?? null),
            'participantCount' => $this->intValue($row->participant_count ?? null),
            'blockerCount' => count($this->jsonList($row->blockers ?? '[]')),
            'teamPublicId' => $this->stringValue($row->team_public_id ?? ''),
            'teamName' => $this->stringValue($row->team_name ?? ''),
            'actorPublicId' => $this->stringValue($row->actor_public_id ?? ''),
            'reason' => $this->stringValue($row->reason ?? ''),
            'confirmationPhrase' => $this->stringValue($row->confirmation_phrase ?? ''),
            'previewedAt' => $this->stringValue($row->previewed_at ?? ''),
            'createdAt' => $this->stringValue($row->created_at ?? ''),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{operation: string, status: string, subject_type: string, team: string, executable: string}
     */
    private function filters(Request $request, array $rows): array
    {
        return [
            'operation' => $this->oneOf($request->query('operation'), $this->allOr($this->uniqueValues($rows, 'operation'))),
            'status' => $this->oneOf($request->query('status'), $this->allOr($this->uniqueValues($rows, 'status'))),
            'subject_type' => $this->oneOf($request->query('subject_type'), $this->allOr($this->uniqueValues($rows, 'subjectType'))),
            'team' => $this->oneOf($request->query('team'), $this->allOr($this->uniqueValues($rows, 'teamPublicId'))),
            'executable' => $this->oneOf($request->query('executable'), ['all', 'yes', 'no']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{operation: string, status: string, subject_type: string, team: string, executable: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['operation'] !== 'all' && ($row['operation'] ?? null) !== $filters['operation']) {
                return false;
            }

            if ($filters['status'] !== 'all' && ($row['status'] ?? null) !== $filters['status']) {
                return false;
            }

            if ($filters['subject_type'] !== 'all' && ($row['subjectType'] ?? null) !== $filters['subject_type']) {
                return false;
            }

            if ($filters['team'] !== 'all' && ($row['teamPublicId'] ?? null) !== $filters['team']) {
                return false;
            }

            if ($filters['executable'] === 'yes' && ($row['canExecute'] ?? false) !== true) {
                return false;
            }

            if ($filters['executable'] === 'no' && ($row['canExecute'] ?? false) !== false) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : 'all';
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function allOr(array $values): array
    {
        return ['all', ...$values];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function uniqueValues(array $rows, string $key): array
    {
        $values = [];

        foreach ($rows as $row) {
            $value = $row[$key] ?? null;

            if (is_string($value) && $value !== '' && ! in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        sort($values);

        return $values;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableIntValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function stringValue(mixed $value): string
    {
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonList(mixed $value): array
    {
        if (is_array($value)) {
            return $this->arrayList($value);
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return $this->arrayList($decoded);
    }

    /**
     * @param  array<mixed>  $values
     * @return list<array<string, mixed>>
     */
    private function arrayList(array $values): array
    {
        $rows = [];

        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }

            $row = [];

            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    $row[$key] = $item;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
