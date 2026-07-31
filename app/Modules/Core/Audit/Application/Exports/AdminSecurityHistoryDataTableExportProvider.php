<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class AdminSecurityHistoryDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::SECURITY_HISTORY;
    }

    public function tableName(): string
    {
        return 'Security history';
    }

    public function owningModuleKey(): string
    {
        return 'audit';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-security-history-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'userName' => 'User',
            'userEmail' => 'User email',
            'userContext' => 'User context',
            'occurredAt' => 'Occurred at',
            'action' => 'Action',
            'result' => 'Result',
            'source' => 'Source',
            'teamPublicId' => 'Team',
            'impersonationSessionId' => 'Impersonation session',
            'reason' => 'Reason',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $userPublicId = self::filterValue($request, 'user');
        $query = DB::table(DatabaseTable::AUDIT_EVENTS)->where('is_security', true);

        if ($userPublicId !== '' && $userPublicId !== 'all') {
            $query->where(static function (Builder $query) use ($userPublicId): void {
                $query
                    ->where('actor_public_id', $userPublicId)
                    ->orWhere('actual_actor_public_id', $userPublicId)
                    ->orWhere('impersonated_user_public_id', $userPublicId)
                    ->orWhere('target_public_id', $userPublicId);
            });
        }

        $this->whereExact($query, 'action', self::filterValue($request, 'action'));
        $this->whereExact($query, 'source', self::filterValue($request, 'source'));

        if (in_array(self::filterValue($request, 'result'), ['succeeded', 'rejected', 'failed'], true)) {
            $query->where('result', self::filterValue($request, 'result'));
        }

        if ($this->isDate(self::filterValue($request, 'date_from'))) {
            $query->whereDate('occurred_at', '>=', self::filterValue($request, 'date_from'));
        }

        if ($this->isDate(self::filterValue($request, 'date_to'))) {
            $query->whereDate('occurred_at', '<=', self::filterValue($request, 'date_to'));
        }

        $records = array_values($query
            ->orderByDesc('occurred_at')
            ->limit(500)
            ->get()
            ->all());
        $users = $this->usersForEvents($records);
        $rows = array_map(fn (object $record): array => $this->row($record, $users), $records);

        foreach ($this->sorted($this->filtered($rows, $request), $request) as $row) {
            yield $row;
        }
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
                $publicId = self::stringValue($record->{$property} ?? '');

                if ($publicId !== '') {
                    $publicIds[$publicId] = true;
                }
            }
        }

        if ($publicIds === []) {
            return [];
        }

        $users = [];

        foreach (DB::table(DatabaseTable::USERS)
            ->whereIn('public_id', array_keys($publicIds))
            ->get(['public_id', 'name', 'email'])
            ->all() as $user) {
            $publicId = self::stringValue($user->public_id ?? '');

            if ($publicId === '') {
                continue;
            }

            $users[$publicId] = [
                'name' => self::stringValue($user->name ?? ''),
                'email' => self::stringValue($user->email ?? ''),
            ];
        }

        return $users;
    }

    /**
     * @param  array<string, array{name: string, email: string}>  $users
     * @return array<string, scalar|\Stringable|null>
     */
    private function row(object $record, array $users): array
    {
        $user = $this->eventUser($record, $users);

        return [
            'publicId' => self::stringValue($record->public_id ?? ''),
            'userName' => $user['name'] !== '' ? $user['name'] : $user['publicId'],
            'userEmail' => $user['email'] !== '' ? $user['email'] : $user['publicId'],
            'userContext' => $user['context'],
            'occurredAt' => self::stringValue($record->occurred_at ?? ''),
            'action' => self::stringValue($record->action ?? ''),
            'result' => self::stringValue($record->result ?? ''),
            'source' => self::stringValue($record->source ?? ''),
            'teamPublicId' => self::stringValue($record->team_public_id ?? ''),
            'impersonationSessionId' => self::stringValue($record->impersonation_session_id ?? ''),
            'reason' => self::stringValue($record->reason ?? ''),
        ];
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
            $publicId = self::stringValue($record->{$property} ?? '');

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
}
