<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class MigrationTimestampArchitectureTest extends TestCase
{
    /** @var list<string> */
    private const VENDOR_OWNED_MIGRATIONS = [
        '2026_07_15_121135_create_telescope_entries_table.php',
    ];

    public function test_atlas_migrations_do_not_define_timezone_naive_timestamp_columns(): void
    {
        foreach ($this->migrationFiles() as $file) {
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            self::assertFalse(
                $this->containsTimezoneNaiveTimestamp($contents),
                sprintf('%s defines a timezone-naive timestamp. Use timestampTz(), dateTimeTz(), timestampsTz(), or softDeletesTz().', $file->getPathname()),
            );
        }
    }

    #[DataProvider('timezoneNaiveDefinitions')]
    public function test_guard_rejects_every_timezone_naive_schema_definition(string $definition): void
    {
        self::assertTrue($this->containsTimezoneNaiveTimestamp($definition));
    }

    /** @return iterable<string, array{string}> */
    public static function timezoneNaiveDefinitions(): iterable
    {
        yield 'single timestamp' => ["\$table->timestamp('occurred_at');"];
        yield 'single date-time' => ["\$table->dateTime('occurred_at');"];
        yield 'model timestamps' => ['$table->timestamps();'];
        yield 'nullable model timestamps' => ['$table->nullableTimestamps();'];
        yield 'soft delete timestamp' => ['$table->softDeletes();'];
        yield 'raw PostgreSQL timestamp shorthand' => ['occurred_at timestamp not null'];
        yield 'raw PostgreSQL timestamp' => ['occurred_at timestamp without time zone not null'];
    }

    #[DataProvider('timezoneAwareDefinitions')]
    public function test_guard_accepts_timezone_aware_and_non_timestamp_temporal_definitions(string $definition): void
    {
        self::assertFalse($this->containsTimezoneNaiveTimestamp($definition));
    }

    /** @return iterable<string, array{string}> */
    public static function timezoneAwareDefinitions(): iterable
    {
        yield 'single timestamp with timezone' => ["\$table->timestampTz('occurred_at');"];
        yield 'single date-time with timezone' => ["\$table->dateTimeTz('occurred_at');"];
        yield 'model timestamps with timezone' => ['$table->timestampsTz();'];
        yield 'soft delete timestamp with timezone' => ['$table->softDeletesTz();'];
        yield 'calendar date' => ["\$table->date('starts_on');"];
        yield 'framework epoch' => ["\$table->unsignedInteger('available_at');"];
    }

    private function containsTimezoneNaiveTimestamp(string $contents): bool
    {
        return preg_match('/->\s*(?:timestamp|dateTime|timestamps|nullableTimestamps|softDeletes)\s*\(/', $contents) === 1
            || preg_match('/\b[a-z_][a-z0-9_]*\s+timestamp\b(?!\s+with\s+time\s+zone)/i', $contents) === 1;
    }

    /** @return iterable<SplFileInfo> */
    private function migrationFiles(): iterable
    {
        $root = dirname(__DIR__, 3).'/database/migrations';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $candidate) {
            if (
                $candidate instanceof SplFileInfo
                && $candidate->isFile()
                && $candidate->getExtension() === 'php'
                && ! in_array($candidate->getFilename(), self::VENDOR_OWNED_MIGRATIONS, true)
            ) {
                yield $candidate;
            }
        }
    }
}
