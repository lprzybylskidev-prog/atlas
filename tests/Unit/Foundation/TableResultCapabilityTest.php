<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableColumn;
use App\Shared\Application\Tables\TableDefinition;
use App\Shared\Application\Tables\TableResult;
use App\Shared\Application\Tables\TableState;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TableResultCapabilityTest extends TestCase
{
    public function test_saved_views_are_disabled_until_the_table_explicitly_enables_them(): void
    {
        $state = new TableState(1, 10, 'name', 'asc', '', ['name'], ['name'], null);
        $result = new TableResult([], 0, $state);

        self::assertFalse($result->tableMeta('application.table')['capabilities']['savedViews']);
        self::assertSame([], $result->tableMeta('application.table')['savedViews']);

        $enabled = $result->withSavedViews([['publicId' => 'view-1', 'name' => 'My view']]);

        self::assertTrue($enabled->tableMeta('admin.table')['capabilities']['savedViews']);
        self::assertSame('view-1', $enabled->tableMeta('admin.table')['savedViews'][0]['publicId']);
    }

    public function test_array_result_exposes_the_complete_searched_dataset_independently_of_pagination_and_sorting(): void
    {
        $definition = new TableDefinition(
            key: 'application.summary-contract',
            columns: [
                new TableColumn('name', true, true),
                new TableColumn('status', true, true),
            ],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );
        $state = new TableState(2, 1, 'name', 'desc', 'matching', ['name', 'status'], ['name', 'status'], null);

        $result = (new ArrayTableProcessor)->process([
            ['name' => 'First matching record', 'status' => 'open'],
            ['name' => 'Second matching record', 'status' => 'closed'],
            ['name' => 'Unrelated record', 'status' => 'open'],
        ], $definition, $state);

        self::assertSame(2, $result->total);
        self::assertCount(1, $result->rows);
        self::assertSame('First matching record', $result->rows[0]['name']);
        self::assertSame(['First matching record', 'Second matching record'], array_column($result->filteredRows, 'name'));
        self::assertSame($result->filteredRows, $result->withSavedViews([])->filteredRows);
    }

    public function test_array_backed_summary_surfaces_use_the_complete_searched_result(): void
    {
        $summaryControllers = [];
        $violations = [];
        $root = dirname(__DIR__, 3);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);

            if (! str_contains($contents, 'ArrayTableProcessor') || ! str_contains($contents, "'summary' =>")) {
                continue;
            }

            $path = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $summaryControllers[] = $path;

            if (! str_contains($contents, '->filteredRows')) {
                $violations[] = $path;
            }
        }

        sort($summaryControllers);
        sort($violations);

        self::assertGreaterThanOrEqual(15, count($summaryControllers), 'The summary-scope guard must cover the real array-backed report inventory.');
        self::assertSame([], $violations, 'Array-backed summary surfaces must aggregate TableResult::filteredRows.');
    }
}
