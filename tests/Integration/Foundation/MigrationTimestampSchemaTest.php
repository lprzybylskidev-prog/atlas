<?php

declare(strict_types=1);

namespace Tests\Integration\Foundation;

use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use UnexpectedValueException;

final class MigrationTimestampSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_atlas_owned_schemas_contain_no_timezone_naive_timestamp_columns(): void
    {
        $columns = DB::table('information_schema.columns')
            ->whereIn('table_schema', DatabaseSchema::all())
            ->where('data_type', 'timestamp without time zone')
            ->orderBy('table_schema')
            ->orderBy('table_name')
            ->orderBy('ordinal_position')
            ->get(['table_schema', 'table_name', 'column_name'])
            ->map(fn (object $column): string => sprintf(
                '%s.%s.%s',
                $this->scalarString($column->table_schema),
                $this->scalarString($column->table_name),
                $this->scalarString($column->column_name),
            ))
            ->all();

        self::assertSame([], $columns, 'Atlas-owned schemas contain timezone-naive timestamps: '.implode(', ', $columns));
    }

    private function scalarString(mixed $value): string
    {
        if (! is_scalar($value)) {
            throw new UnexpectedValueException('PostgreSQL information schema returned a non-scalar identifier.');
        }

        return (string) $value;
    }
}
