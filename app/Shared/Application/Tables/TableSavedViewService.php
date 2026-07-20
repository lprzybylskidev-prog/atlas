<?php

declare(strict_types=1);

namespace App\Shared\Application\Tables;

use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class TableSavedViewService
{
    public function __construct(
        private SecurityAuditRecorder $audit,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listFor(string $tableKey, int $userId, ?int $teamId): array
    {
        $defaultViewId = DB::table(DatabaseTable::TABLE_SAVED_VIEW_DEFAULTS)
            ->where('user_id', $userId)
            ->where('table_key', $tableKey)
            ->value('table_saved_view_id');

        $query = DB::table(DatabaseTable::TABLE_SAVED_VIEWS)
            ->where('table_key', $tableKey)
            ->where(static function (Builder $query) use ($userId, $teamId): void {
                $query->where(static function (Builder $private) use ($userId): void {
                    $private->where('type', 'private')->where('owner_user_id', $userId);
                })
                    ->orWhere(static function (Builder $shared) use ($teamId): void {
                        $shared->where('type', 'team')->where('team_id', $teamId);
                    })
                    ->orWhere('type', 'system');
            })
            ->orderByRaw("case type when 'system' then 0 when 'team' then 1 else 2 end")
            ->orderBy('name');

        return array_values($query->get()->map(static function (object $view) use ($defaultViewId): array {
            $values = get_object_vars($view);
            $state = json_decode(self::stringValue($values['state'] ?? '{}'), true);

            return [
                'publicId' => self::stringValue($values['public_id'] ?? ''),
                'name' => self::stringValue($values['name'] ?? ''),
                'type' => self::stringValue($values['type'] ?? 'private'),
                'state' => is_array($state) ? $state : [],
                'isDefault' => self::intValue($values['id'] ?? null) === self::intValue($defaultViewId),
            ];
        })->all());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function create(string $tableKey, string $name, string $type, array $state, int $userId, ?int $teamId, ?string $actorPublicId): string
    {
        $definition = AdminTableDefinitions::get($tableKey);
        $type = $type === 'team' ? 'team' : 'private';

        if ($type === 'team' && $teamId === null) {
            throw new HttpException(422, 'A team view requires an active team.');
        }

        $publicId = (string) Str::ulid();

        DB::table(DatabaseTable::TABLE_SAVED_VIEWS)->insert([
            'public_id' => $publicId,
            'table_key' => $tableKey,
            'name' => mb_substr(trim($name), 0, 80),
            'type' => $type,
            'owner_user_id' => $userId,
            'team_id' => $type === 'team' ? $teamId : null,
            'state' => json_encode($this->sanitizeState($state, $definition), JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordAudit('table_saved_view.created', $type, $tableKey, $publicId, $actorPublicId);

        return $publicId;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function update(string $publicId, string $name, array $state, int $userId, ?int $teamId, ?string $actorPublicId): void
    {
        $view = $this->editableView($publicId, $userId, $teamId);
        $values = get_object_vars($view);

        if (($values['type'] ?? '') === 'system') {
            throw new HttpException(403, 'System views cannot be changed.');
        }

        $tableKey = self::stringValue($values['table_key'] ?? '');
        $type = self::stringValue($values['type'] ?? '');
        $definition = AdminTableDefinitions::get($tableKey);

        DB::table(DatabaseTable::TABLE_SAVED_VIEWS)
            ->where('id', self::intValue($values['id'] ?? null))
            ->update([
                'name' => mb_substr(trim($name), 0, 80),
                'state' => json_encode($this->sanitizeState($state, $definition), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        $this->recordAudit('table_saved_view.updated', $type, $tableKey, $publicId, $actorPublicId);
    }

    public function delete(string $publicId, int $userId, ?int $teamId, ?string $actorPublicId): void
    {
        $view = $this->editableView($publicId, $userId, $teamId);
        $values = get_object_vars($view);

        if (($values['type'] ?? '') === 'system') {
            throw new HttpException(403, 'System views cannot be deleted.');
        }

        DB::table(DatabaseTable::TABLE_SAVED_VIEWS)->where('id', self::intValue($values['id'] ?? null))->delete();
        $this->recordAudit(
            'table_saved_view.deleted',
            self::stringValue($values['type'] ?? ''),
            self::stringValue($values['table_key'] ?? ''),
            $publicId,
            $actorPublicId,
        );
    }

    public function copy(string $publicId, string $name, string $type, int $userId, ?int $teamId, ?string $actorPublicId): string
    {
        $view = $this->visibleView($publicId, $userId, $teamId);
        $values = get_object_vars($view);
        $state = json_decode(self::stringValue($values['state'] ?? '{}'), true);

        return $this->create(
            tableKey: self::stringValue($values['table_key'] ?? ''),
            name: $name,
            type: $type,
            state: self::arrayValue($state),
            userId: $userId,
            teamId: $teamId,
            actorPublicId: $actorPublicId,
        );
    }

    public function setDefault(string $publicId, int $userId, ?int $teamId): void
    {
        $view = $this->visibleView($publicId, $userId, $teamId);
        $values = get_object_vars($view);

        DB::table(DatabaseTable::TABLE_SAVED_VIEW_DEFAULTS)->updateOrInsert(
            [
                'user_id' => $userId,
                'table_key' => self::stringValue($values['table_key'] ?? ''),
            ],
            [
                'team_id' => $teamId,
                'table_saved_view_id' => self::intValue($values['id'] ?? null),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function sanitizeState(array $state, TableDefinition $definition): array
    {
        $sort = is_string($state['sort'] ?? null) && in_array($state['sort'], $definition->sortableKeys(), true)
            ? $state['sort']
            : $definition->defaultSort;
        $direction = ($state['direction'] ?? null) === 'desc' ? 'desc' : 'asc';
        $search = is_string($state['search'] ?? null) ? mb_substr(trim($state['search']), 0, 120) : '';
        $columns = array_key_exists('columns', $state)
            ? TableState::safeColumnList(implode(',', array_filter((array) $state['columns'], 'is_string')), $definition->columnKeys())
            : $definition->defaultVisibleColumns();
        $columnOrder = TableState::safeColumnList(implode(',', array_filter((array) ($state['columnOrder'] ?? []), 'is_string')), $definition->columnKeys());

        return [
            'sort' => $sort,
            'direction' => $direction,
            'search' => preg_replace('/[[:cntrl:]]/', '', $search) ?? '',
            'columns' => $columns,
            'columnOrder' => $columnOrder === [] ? $definition->columnKeys() : $columnOrder,
            'filters' => $this->safeFilters($state['filters'] ?? []),
            'grouping' => TableState::safeColumnList(implode(',', array_filter((array) ($state['grouping'] ?? []), 'is_string')), $definition->columnKeys()),
            'timeRange' => $this->safeTimeRange($state['timeRange'] ?? null, $definition),
        ];
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    private function safeFilters(mixed $filters): array
    {
        if (! is_array($filters)) {
            return [];
        }

        $safe = [];

        foreach ($filters as $key => $value) {
            if (is_string($key) && (is_string($value) || is_int($value) || is_bool($value) || $value === null)) {
                $safe[mb_substr($key, 0, 80)] = is_string($value) ? mb_substr($value, 0, 120) : $value;
            }
        }

        return $safe;
    }

    /**
     * @return array<string, string|null>|null
     */
    private function safeTimeRange(mixed $timeRange, TableDefinition $definition): ?array
    {
        if (! is_array($timeRange)) {
            return null;
        }

        $range = self::arrayValue($timeRange);
        $key = self::stringValue($range['key'] ?? '');

        if (! in_array($key, $definition->columnKeys(), true)) {
            return null;
        }

        return [
            'key' => $key,
            'mode' => self::stringValue($range['mode'] ?? '') === 'dynamic' ? 'dynamic' : 'fixed',
            'from' => self::stringValue($range['from'] ?? null) ?: null,
            'to' => self::stringValue($range['to'] ?? null) ?: null,
            'preset' => self::stringValue($range['preset'] ?? null) ?: null,
        ];
    }

    private function editableView(string $publicId, int $userId, ?int $teamId): object
    {
        $view = $this->visibleView($publicId, $userId, $teamId);
        $values = get_object_vars($view);
        $type = self::stringValue($values['type'] ?? '');

        if ($type === 'private' && self::intValue($values['owner_user_id'] ?? null) !== $userId) {
            throw new HttpException(403, 'This private view belongs to another user.');
        }

        if ($type === 'team' && self::intValue($values['team_id'] ?? null) !== $teamId) {
            throw new HttpException(403, 'This team view belongs to another team.');
        }

        return $view;
    }

    private function visibleView(string $publicId, int $userId, ?int $teamId): object
    {
        $view = DB::table(DatabaseTable::TABLE_SAVED_VIEWS)
            ->where('public_id', $publicId)
            ->where(static function (Builder $query) use ($userId, $teamId): void {
                $query->where(static function (Builder $private) use ($userId): void {
                    $private->where('type', 'private')->where('owner_user_id', $userId);
                })
                    ->orWhere(static function (Builder $shared) use ($teamId): void {
                        $shared->where('type', 'team')->where('team_id', $teamId);
                    })
                    ->orWhere('type', 'system');
            })
            ->first();

        if (! is_object($view)) {
            throw new HttpException(404, 'Saved table view was not found.');
        }

        return $view;
    }

    private function recordAudit(string $action, string $type, string $tableKey, string $viewPublicId, ?string $actorPublicId): void
    {
        if (! in_array($type, ['team', 'system'], true)) {
            return;
        }

        $this->audit->record(new SecurityAuditEvent(
            module: 'shared',
            action: $action,
            result: 'success',
            source: 'admin-ui',
            actorPublicId: $actorPublicId,
            targetPublicId: null,
            reason: null,
            category: SecurityAuditCategory::Authorization,
            metadata: [
                'table_key' => $tableKey,
                'view_public_id' => $viewPublicId,
                'view_type' => $type,
            ],
        ));
    }

    private static function stringValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return '';
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private static function arrayValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
