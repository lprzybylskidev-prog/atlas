<?php

declare(strict_types=1);

namespace Tests\Integration\Foundation;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

final class PostgreSqlSchemaInvariantTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fresh_schema_has_canonical_ownership_constraints_indexes_and_triggers(): void
    {
        $searchPath = DB::selectOne('show search_path');

        self::assertIsObject($searchPath);
        self::assertSame('public', get_object_vars($searchPath)['search_path'] ?? null);

        foreach (DatabaseSchema::all() as $schema) {
            self::assertTrue($this->schemaExists($schema), sprintf('Missing PostgreSQL schema [%s].', $schema));
        }

        foreach ($this->atlasTables() as $table) {
            self::assertTrue(Schema::hasTable($table), sprintf('Missing canonical Atlas table [%s].', $table));
        }

        self::assertTrue(Schema::hasColumns(IdentityDatabaseTable::USERS, [
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_confirmed_at',
            'account_sensitivity',
            'password_changed_at',
            'avatar_color',
            'avatar_image_file_public_id',
        ]));
        self::assertTrue(Schema::hasColumns(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES, ['user_id', 'team_id', 'email', 'primary']));
        self::assertTrue(Schema::hasColumns(FilesDatabaseTable::FILE_OBJECTS, ['retention_source_file_object_id', 'acknowledged_by_user_id', 'acknowledged_at']));
        self::assertTrue(Schema::hasColumns(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS, ['structural_role']));
        self::assertTrue($this->checkConstraintContains(
            'core_teams',
            'team_user_assignments',
            'team_user_assignments_structural_role_check',
            "'employee'::character varying, 'manager'::character varying, 'head_manager'::character varying",
        ));

        self::assertForeignKey(IdentityDatabaseTable::SESSIONS, 'user_id', IdentityDatabaseTable::USERS, 'n');
        self::assertForeignKey(AuthorizationDatabaseTable::ROLES, 'team_id', TeamsDatabaseTable::TEAMS, 'r');
        self::assertForeignKey(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS, 'team_id', TeamsDatabaseTable::TEAMS, 'r');
        self::assertForeignKey(AuthorizationDatabaseTable::MODEL_HAS_ROLES, 'team_id', TeamsDatabaseTable::TEAMS, 'r');
        self::assertForeignKey(FilesDatabaseTable::FILE_OBJECTS, 'retention_source_file_object_id', FilesDatabaseTable::FILE_OBJECTS, 'r');
        self::assertForeignKey(PrivacyDatabaseTable::OPERATION_PREVIEWS, 'operation_request_id', PrivacyDatabaseTable::OPERATION_REQUESTS, 'r');

        foreach ([
            'team_user_assignments_active_unique',
            'team_manager_relationships_active_unique',
            'notification_email_addresses_primary_unique',
            'report_export_artifacts_one_available_per_request',
            'time_tracking_work_sessions_active_user_unique',
            'time_tracking_module_segments_active_session_unique',
            'time_tracking_breaks_active_user_unique',
            'time_tracking_other_work_active_user_unique',
        ] as $index) {
            self::assertTrue($this->isPartialUniqueIndex($index), sprintf('Missing partial unique index [%s].', $index));
        }

        self::assertTrue($this->triggerExists('core_audit', 'audit_events', 'audit_events_append_only'));
        self::assertTrue($this->triggerExists('core_audit', 'audit_security_events', 'audit_security_events_append_only'));
        self::assertTrue($this->triggerExists('shared', 'module_activation_history', 'module_activation_history_append_only'));
        self::assertTrue($this->functionExists('core_audit', 'prevent_audit_mutation'));

        $unauthorizedPublicTables = DB::table('pg_catalog.pg_tables')
            ->where('schemaname', 'public')
            ->whereNotIn('tablename', ['migrations', 'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring', 'pulse_values', 'pulse_entries', 'pulse_aggregates'])
            ->pluck('tablename')
            ->all();

        self::assertSame([], $unauthorizedPublicTables, 'Atlas-owned tables must not be created in public.');
    }

    /** @return list<string> */
    private function atlasTables(): array
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
            foreach ((new ReflectionClass($class))->getConstants() as $table) {
                if (is_string($table) && str_contains($table, '.')) {
                    $tables[] = $table;
                }
            }
        }

        sort($tables);

        return array_values(array_unique($tables));
    }

    private function schemaExists(string $schema): bool
    {
        return DB::table('information_schema.schemata')->where('schema_name', $schema)->exists();
    }

    private function isPartialUniqueIndex(string $name): bool
    {
        $index = DB::table('pg_catalog.pg_indexes')->where('indexname', $name)->value('indexdef');

        return is_string($index) && str_contains($index, 'UNIQUE INDEX') && str_contains($index, ' WHERE ');
    }

    private function triggerExists(string $schema, string $table, string $trigger): bool
    {
        return DB::table('information_schema.triggers')
            ->where('trigger_schema', $schema)
            ->where('event_object_table', $table)
            ->where('trigger_name', $trigger)
            ->exists();
    }

    private function checkConstraintContains(string $schema, string $table, string $constraint, string $expected): bool
    {
        $definition = DB::table('pg_catalog.pg_constraint as c')
            ->join('pg_catalog.pg_class as t', 't.oid', '=', 'c.conrelid')
            ->join('pg_catalog.pg_namespace as n', 'n.oid', '=', 't.relnamespace')
            ->where('n.nspname', $schema)
            ->where('t.relname', $table)
            ->where('c.conname', $constraint)
            ->selectRaw('pg_get_constraintdef(c.oid) as definition')
            ->value('definition');

        return is_string($definition) && str_contains($definition, $expected);
    }

    private function functionExists(string $schema, string $function): bool
    {
        return DB::table('pg_catalog.pg_proc as procedure')
            ->join('pg_catalog.pg_namespace as namespace', 'namespace.oid', '=', 'procedure.pronamespace')
            ->where('namespace.nspname', $schema)
            ->where('procedure.proname', $function)
            ->exists();
    }

    private static function assertForeignKey(string $table, string $column, string $foreignTable, string $deleteAction): void
    {
        [$schema, $tableName] = explode('.', $table, 2);
        [$foreignSchema, $foreignTableName] = explode('.', $foreignTable, 2);

        $record = DB::selectOne(<<<'SQL'
select foreign_namespace.nspname as foreign_schema,
       foreign_table.relname as foreign_table,
       foreign_attribute.attname as foreign_column,
       fk.confdeltype as delete_action
from pg_catalog.pg_constraint fk
join pg_catalog.pg_class local_table on local_table.oid = fk.conrelid
join pg_catalog.pg_namespace local_namespace on local_namespace.oid = local_table.relnamespace
join pg_catalog.pg_class foreign_table on foreign_table.oid = fk.confrelid
join pg_catalog.pg_namespace foreign_namespace on foreign_namespace.oid = foreign_table.relnamespace
join pg_catalog.pg_attribute local_attribute on local_attribute.attrelid = local_table.oid and local_attribute.attnum = fk.conkey[1]
join pg_catalog.pg_attribute foreign_attribute on foreign_attribute.attrelid = foreign_table.oid and foreign_attribute.attnum = fk.confkey[1]
where fk.contype = 'f'
  and local_namespace.nspname = ?
  and local_table.relname = ?
  and local_attribute.attname = ?
SQL, [$schema, $tableName, $column]);

        self::assertNotNull($record, sprintf('Missing FK for [%s.%s].', $table, $column));
        self::assertIsObject($record);

        $values = get_object_vars($record);

        self::assertSame($foreignSchema, $values['foreign_schema'] ?? null);
        self::assertSame($foreignTableName, $values['foreign_table'] ?? null);
        self::assertSame('id', $values['foreign_column'] ?? null);
        self::assertSame($deleteAction, $values['delete_action'] ?? null);
    }
}
