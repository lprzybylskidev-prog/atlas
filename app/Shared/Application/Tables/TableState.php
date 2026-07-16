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
        $page = max(1, min(10000, $request->integer('page', 1)));
        $perPage = self::allowedPerPage($request->integer('per_page', 10));
        $direction = strtolower((string) $request->query('direction', $definition->defaultDirection));
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $sort = (string) $request->query('sort', $definition->defaultSort);

        if (! in_array($sort, $definition->sortableKeys(), true)) {
            $sort = $definition->defaultSort;
        }

        $search = preg_replace('/[[:cntrl:]]/', '', (string) $request->query('search', '')) ?? '';
        $search = mb_substr(trim($search), 0, 120);
        $hasColumns = $request->query->has('columns');
        $columns = self::safeColumnList((string) $request->query('columns', ''), $definition->columnKeys());
        $columnOrder = self::safeColumnList((string) $request->query('column_order', ''), $definition->columnKeys());
        $view = $request->query('view');

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
