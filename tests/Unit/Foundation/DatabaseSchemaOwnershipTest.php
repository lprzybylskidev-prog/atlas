<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Modules\Core\Audit\Infrastructure\Persistence\TableNames\AuditDatabaseTable;
use App\Modules\Core\Authorization\Infrastructure\Persistence\TableNames\AuthorizationDatabaseTable;
use App\Modules\Core\Exports\Infrastructure\Persistence\TableNames\ExportsDatabaseTable;
use App\Modules\Core\Files\Infrastructure\Persistence\TableNames\FilesDatabaseTable;
use App\Modules\Core\Identity\Infrastructure\Persistence\TableNames\IdentityDatabaseTable;
use App\Modules\Core\Notifications\Infrastructure\Persistence\TableNames\NotificationsDatabaseTable;
use App\Modules\Core\Privacy\Infrastructure\Persistence\TableNames\PrivacyDatabaseTable;
use App\Modules\Core\Settings\Infrastructure\Persistence\TableNames\SettingsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Modules\Optional\FeatureFlags\Infrastructure\Persistence\TableNames\FeatureFlagsDatabaseTable;
use App\Modules\Optional\Imports\Infrastructure\Persistence\TableNames\ImportsDatabaseTable;
use App\Modules\Optional\Integrations\Infrastructure\Persistence\TableNames\IntegrationsDatabaseTable;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Persistence\TableNames\ManagedProcessesDatabaseTable;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\TableNames\TimeTrackingDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use App\Shared\Infrastructure\Database\DatabaseTable;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

final class DatabaseSchemaOwnershipTest extends TestCase
{
    public function test_shared_database_table_registry_contains_only_shared_infrastructure_tables(): void
    {
        $reflection = new ReflectionClass(DatabaseTable::class);

        foreach ($reflection->getConstants() as $name => $table) {
            if ($name === 'unqualified') {
                continue;
            }

            self::assertIsString($table);
            self::assertStringStartsWith(
                DatabaseSchema::SHARED.'.',
                $table,
                sprintf('Shared DatabaseTable must not expose module-owned table constant [%s].', $name),
            );
        }
    }

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

    #[Test]
    public function pre_production_migrations_are_canonical_and_do_not_depend_on_a_broad_search_path(): void
    {
        $violations = [];

        foreach ($this->migrationFiles() as $file) {
            $contents = file_get_contents($file);

            if (! is_string($contents)) {
                continue;
            }

            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            $up = explode('public function down(): void', $contents, 2)[0];
            $forbidden = [
                'Schema::table(' => 'alters an already-created table',
                '->after(' => 'uses the unsupported PostgreSQL column-position API',
                'Schema::hasTable(' => 'contains local historical table detection',
                'Schema::hasColumn(' => 'contains local historical column detection',
                'optional_reports' => 'references the removed pre-production Reports schema',
            ];

            foreach ($forbidden as $needle => $reason) {
                if (str_contains($contents, $needle)) {
                    $violations[] = sprintf('%s %s.', $relative, $reason);
                }
            }

            if (str_contains($up, 'Schema::dropIfExists(') || preg_match('/drop\s+schema/i', $up) === 1) {
                $violations[] = $relative.' destructively repairs local state before creating its canonical schema.';
            }

            if (! str_contains(basename($file), 'create_')) {
                $violations[] = $relative.' is not a canonical create migration after the pre-production squash.';
            }

            if (preg_match('/execute\s+function\s+[a-zA-Z_][a-zA-Z0-9_]*\s*\(/i', $contents) === 1) {
                $violations[] = $relative.' invokes a PostgreSQL function without an explicit schema.';
            }
        }

        self::assertSame('public', config('database.connections.pgsql.search_path'));
        self::assertStringContainsString('DB_SEARCH_PATH=public', (string) file_get_contents(base_path('.env.example')));
        self::assertSame([], $violations);
    }

    /**
     * @return list<string>
     */
    private function atlasTableNames(): array
    {
        $classes = [
            AuditDatabaseTable::class,
            AuthorizationDatabaseTable::class,
            ExportsDatabaseTable::class,
            FilesDatabaseTable::class,
            IdentityDatabaseTable::class,
            NotificationsDatabaseTable::class,
            PrivacyDatabaseTable::class,
            SettingsDatabaseTable::class,
            TeamsDatabaseTable::class,
            FeatureFlagsDatabaseTable::class,
            ImportsDatabaseTable::class,
            IntegrationsDatabaseTable::class,
            ManagedProcessesDatabaseTable::class,
            TimeTrackingDatabaseTable::class,
            DatabaseTable::class,
        ];
        $tables = [];

        foreach ($classes as $class) {
            foreach ((new ReflectionClass($class))->getConstants() as $value) {
                if (! is_string($value) || ! str_contains($value, '.')) {
                    continue;
                }

                $tables[] = DatabaseTable::unqualified($value);
            }
        }

        sort($tables);

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

    /**
     * @return list<string>
     */
    private function migrationFiles(): array
    {
        $files = glob(database_path('migrations/*.php'));

        self::assertIsArray($files);
        sort($files);

        return $files;
    }
}
