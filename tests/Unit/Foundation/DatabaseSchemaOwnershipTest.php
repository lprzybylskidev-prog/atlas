<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Shared\Infrastructure\Database\DatabaseTable;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

final class DatabaseSchemaOwnershipTest extends TestCase
{
    #[Test]
    public function atlas_owned_tables_are_not_referenced_without_schema_qualification(): void
    {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $contents = file_get_contents($file);

            if (! is_string($contents)) {
                continue;
            }

            foreach ($this->atlasTableNames() as $table) {
                $quoted = preg_quote($table, '/');
                $patterns = [
                    "/Schema::(?:create|table|dropIfExists)\\(\\s*'{$quoted}'/",
                    "/->constrained\\(\\s*'{$quoted}'/",
                    "/DB::table\\(\\s*'{$quoted}'/",
                    "/->(?:join|leftJoin|rightJoin)\\(\\s*'{$quoted}'/",
                    "/assertDatabase(?:Has|Missing|Count)\\(\\s*'{$quoted}'/",
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $contents) === 1) {
                        $violations[] = $file.' references '.$table.' without its PostgreSQL schema.';
                    }
                }
            }
        }

        self::assertSame([], $violations);
    }

    /**
     * @return list<string>
     */
    private function atlasTableNames(): array
    {
        $constants = (new ReflectionClass(DatabaseTable::class))->getConstants();
        $tables = [];

        foreach ($constants as $value) {
            if (! is_string($value) || ! str_contains($value, '.')) {
                continue;
            }

            $tables[] = DatabaseTable::unqualified($value);
        }

        return array_values(array_unique($tables));
    }

    /**
     * @return list<string>
     */
    private function phpFiles(): array
    {
        $directories = [
            base_path('app'),
            base_path('config'),
            base_path('database'),
            base_path('tests'),
        ];
        $files = [];

        foreach ($directories as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

            foreach ($iterator as $file) {
                if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = $file->getPathname();

                if ($path === __FILE__) {
                    continue;
                }

                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }
}
