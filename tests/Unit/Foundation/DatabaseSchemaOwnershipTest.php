<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Modules\Core\Audit\Application\Public\Persistence\AuditDatabaseTable;
use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Exports\Application\Public\Persistence\ExportsDatabaseTable;
use App\Modules\Core\Files\Application\Public\Persistence\FilesDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Notifications\Application\Public\Persistence\NotificationsDatabaseTable;
use App\Modules\Core\Privacy\Application\Public\Persistence\PrivacyDatabaseTable;
use App\Modules\Core\Settings\Application\Public\Persistence\SettingsDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\FeatureFlags\Application\Public\Persistence\FeatureFlagsDatabaseTable;
use App\Modules\Optional\Imports\Application\Public\Persistence\ImportsDatabaseTable;
use App\Modules\Optional\Integrations\Application\Public\Persistence\IntegrationsDatabaseTable;
use App\Modules\Optional\ManagedProcesses\Application\Public\Persistence\ManagedProcessesDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
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
}
