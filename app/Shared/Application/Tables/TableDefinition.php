<?php

declare(strict_types=1);

namespace App\Shared\Application\Tables;

final readonly class TableDefinition
{
    /**
     * @param  list<TableColumn>  $columns
     */
    public function __construct(
        public string $key,
        public array $columns,
        public string $defaultSort,
        public string $defaultDirection = 'asc',
    ) {}

    /**
     * @return list<string>
     */
    public function columnKeys(): array
    {
        return array_map(static fn (TableColumn $column): string => $column->key, $this->columns);
    }

    /**
     * @return list<string>
     */
    public function sortableKeys(): array
    {
        return array_values(array_map(
            static fn (TableColumn $column): string => $column->key,
            array_filter($this->columns, static fn (TableColumn $column): bool => $column->sortable),
        ));
    }

    /**
     * @return list<string>
     */
    public function searchableKeys(): array
    {
        return array_values(array_map(
            static fn (TableColumn $column): string => $column->key,
            array_filter($this->columns, static fn (TableColumn $column): bool => $column->searchable),
        ));
    }

    /**
     * @return list<string>
     */
    public function defaultVisibleColumns(): array
    {
        return array_values(array_map(
            static fn (TableColumn $column): string => $column->key,
            array_filter($this->columns, static fn (TableColumn $column): bool => $column->defaultVisible),
        ));
    }
}
