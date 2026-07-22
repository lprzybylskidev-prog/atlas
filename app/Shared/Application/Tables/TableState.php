<?php

declare(strict_types=1);

namespace App\Shared\Application\Tables;

use Illuminate\Http\Request;

final readonly class TableState
{
    /**
     * @param  list<string>  $columns
     * @param  list<string>  $columnOrder
     */
    public function __construct(
        public int $page,
        public int $perPage,
        public string $sort,
        public string $direction,
        public string $search,
        public array $columns,
        public array $columnOrder,
        public ?string $view,
    ) {}

    public static function fromRequest(Request $request, TableDefinition $definition): self
    {
        return self::fromPayload($request->query->all(), $definition);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload, TableDefinition $definition): self
    {
        $page = max(1, min(10000, self::intValue($payload['page'] ?? 1)));
        $perPage = self::allowedPerPage(self::intValue($payload['per_page'] ?? 10));
        $direction = strtolower(self::stringValue($payload['direction'] ?? $definition->defaultDirection));
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $sort = self::stringValue($payload['sort'] ?? $definition->defaultSort);

        if (! in_array($sort, $definition->sortableKeys(), true)) {
            $sort = $definition->defaultSort;
        }

        $search = preg_replace('/[[:cntrl:]]/', '', self::stringValue($payload['search'] ?? '')) ?? '';
        $search = mb_substr(trim($search), 0, 120);
        $hasColumns = array_key_exists('columns', $payload);
        $columns = self::safeColumnList(self::stringValue($payload['columns'] ?? ''), $definition->columnKeys());
        $columnOrder = self::safeColumnList(self::stringValue($payload['column_order'] ?? ''), $definition->columnKeys());
        $view = $payload['view'] ?? null;

        return new self(
            page: $page,
            perPage: $perPage,
            sort: $sort,
            direction: $direction,
            search: $search,
            columns: $hasColumns ? $columns : $definition->defaultVisibleColumns(),
            columnOrder: $columnOrder === [] ? $definition->columnKeys() : $columnOrder,
            view: is_string($view) && $view !== '' ? mb_substr($view, 0, 64) : null,
        );
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function stringValue(mixed $value): string
    {
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    public static function safeColumnList(string $value, array $allowed): array
    {
        $columns = array_filter(array_map('trim', explode(',', $value)), static fn (string $column): bool => $column !== '');
        $safe = [];

        foreach ($columns as $column) {
            if (in_array($column, $allowed, true) && ! in_array($column, $safe, true)) {
                $safe[] = $column;
            }
        }

        return $safe;
    }

    public static function allowedPerPage(int $perPage): int
    {
        return in_array($perPage, [10, 25, 50, 100, 250], true) ? $perPage : 10;
    }

    /**
     * @return array{page: int, perPage: int, sort: string, direction: string, search: string, columns: list<string>, columnOrder: list<string>, filters: array<string, int|string|bool|null>, grouping: list<string>, timeRange: array<string, string|null>|null, view: string|null}
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'perPage' => $this->perPage,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'search' => $this->search,
            'columns' => $this->columns,
            'columnOrder' => $this->columnOrder,
            'filters' => [],
            'grouping' => [],
            'timeRange' => null,
            'view' => $this->view,
        ];
    }
}
