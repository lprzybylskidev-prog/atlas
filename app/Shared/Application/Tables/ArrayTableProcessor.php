<?php

declare(strict_types=1);

namespace App\Shared\Application\Tables;

final class ArrayTableProcessor
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function process(array $rows, TableDefinition $definition, TableState $state): TableResult
    {
        $filtered = $this->filter($rows, $definition, $state->search);
        $sorted = $this->sort($filtered, $state->sort, $state->direction);
        $total = count($sorted);
        $offset = ($state->page - 1) * $state->perPage;

        return new TableResult(
            rows: array_slice($sorted, $offset, $state->perPage),
            total: $total,
            state: $state,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filter(array $rows, TableDefinition $definition, string $search): array
    {
        if ($search === '') {
            return $rows;
        }

        $needle = mb_strtolower($search);
        $keys = $definition->searchableKeys();

        return array_values(array_filter($rows, static function (array $row) use ($keys, $needle): bool {
            foreach ($keys as $key) {
                $value = $row[$key] ?? null;
                $haystack = is_array($value)
                    ? implode(' ', array_map(static fn (mixed $item): string => self::stringValue($item), $value))
                    : self::stringValue($value);

                if (str_contains(mb_strtolower($haystack), $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sort(array $rows, string $key, string $direction): array
    {
        usort($rows, static function (array $first, array $second) use ($key, $direction): int {
            $left = $first[$key] ?? null;
            $right = $second[$key] ?? null;
            $result = self::comparableValue($left) <=> self::comparableValue($right);

            return $direction === 'desc' ? -$result : $result;
        });

        return $rows;
    }

    private static function comparableValue(mixed $value): int|string
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_array($value)) {
            return implode(' ', array_map(static fn (mixed $item): string => self::stringValue($item), $value));
        }

        return mb_strtolower(self::stringValue($value));
    }

    private static function stringValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return '';
    }
}
